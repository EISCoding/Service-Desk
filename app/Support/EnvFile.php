<?php

namespace App\Support;

use RuntimeException;

/**
 * Writes values into the .env file (installer): existing keys are replaced,
 * new ones appended. Without a .env, .env.example is the starting point.
 */
final class EnvFile
{
    public static function path(): string
    {
        return app()->environmentFilePath();
    }

    public static function writable(): bool
    {
        $path = self::path();

        return is_file($path) ? is_writable($path) : is_writable(dirname($path));
    }

    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public static function write(array $values): void
    {
        $path = self::path();
        $example = base_path('.env.example');
        $content = is_file($path) ? (string) file_get_contents($path) : (is_file($example) ? (string) file_get_contents($example) : '');

        foreach ($values as $key => $value) {
            $line = $key.'='.self::encode($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $content = preg_match($pattern, $content)
                ? preg_replace_callback($pattern, fn () => $line, $content, 1)
                : rtrim($content, "\n")."\n".$line."\n";
        }

        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException("Die Datei {$path} kann nicht geschrieben werden.");
        }
    }

    /**
     * Value as dotenv expects it: plain when safe, otherwise quoted
     * (single quotes are literal, so "$" in passwords stays as it is).
     */
    private static function encode(string|int|bool|null $value): string
    {
        $value = match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };

        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+-]+$/', $value)) {
            return $value;
        }

        return ! str_contains($value, "'")
            ? "'".$value."'"
            : '"'.addcslashes($value, '"\\$').'"';
    }
}
