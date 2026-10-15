<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Reads the Laravel log files (storage/logs/*.log) for the admin area:
 * the newest entries of a file, parsed into level, time, message and details.
 */
final class LogReader
{
    /** Only the end of large files is read. */
    private const MAX_BYTES = 1_048_576;

    private const ERRORS = ['emergency', 'alert', 'critical', 'error'];

    /**
     * Log files, newest first.
     *
     * @return list<string>
     */
    public static function files(): array
    {
        $files = glob(storage_path('logs/*.log')) ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return array_map('basename', $files);
    }

    /**
     * Full path of a log file, or null for unknown names (no path traversal).
     */
    public static function path(string $file): ?string
    {
        return in_array($file, self::files(), true) ? storage_path('logs/'.$file) : null;
    }

    /**
     * @param  string  $level  errors | warnings | all
     * @return list<array{time: Carbon, level: string, message: string, details: string}>
     */
    public static function entries(string $file, string $level = 'all', string $search = '', int $limit = 200): array
    {
        $path = self::path($file);

        if (! $path) {
            return [];
        }

        $size = filesize($path);
        $handle = fopen($path, 'rb');
        fseek($handle, max(0, $size - self::MAX_BYTES));
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        preg_match_all(
            '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\] [\w-]+\.(\w+): (.*?)(?=^\[\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}|\z)/ms',
            $content,
            $matches,
            PREG_SET_ORDER,
        );

        $levels = match ($level) {
            'errors' => self::ERRORS,
            'warnings' => [...self::ERRORS, 'warning'],
            default => null,
        };

        $entries = [];
        foreach (array_reverse($matches) as [, $time, $entryLevel, $text]) {
            $entryLevel = strtolower($entryLevel);

            if (($levels && ! in_array($entryLevel, $levels, true)) || ($search !== '' && ! Str::contains($text, $search, ignoreCase: true))) {
                continue;
            }

            [$message, $details] = array_pad(explode("\n", rtrim($text), 2), 2, '');

            // "Message {"exception":...}": the JSON context belongs to the details.
            if (($position = strpos($message, ' {"')) !== false) {
                $details = trim(substr($message, $position)."\n".$details);
                $message = substr($message, 0, $position);
            }
            $entries[] = [
                'time' => Carbon::parse($time),
                'level' => $entryLevel,
                'message' => Str::limit($message, 500),
                'details' => Str::limit($details, 20_000),
            ];

            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }
}
