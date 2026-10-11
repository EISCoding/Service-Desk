<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores attachments under storage/app/private/attachments/YYYY/MM/<uuid>.
 */
class AttachmentStorage
{
    public const DISK = 'local';

    public function storeUpload(UploadedFile $file, Model $attachable): Attachment
    {
        $path = Storage::disk(self::DISK)->putFileAs($this->directory(), $file, $this->fileName($file->getClientOriginalExtension()));

        return $this->record($attachable, $path, $file->getClientOriginalName(), $file->getMimeType() ?: 'application/octet-stream', (int) $file->getSize());
    }

    /**
     * For attachments that arrive as raw content (mail import, Phase 4).
     */
    public function storeContents(string $contents, string $name, string $mimeType, Model $attachable, ?string $contentId = null): Attachment
    {
        $path = $this->directory().'/'.$this->fileName(pathinfo($name, PATHINFO_EXTENSION));
        Storage::disk(self::DISK)->put($path, $contents);

        return $this->record($attachable, $path, $name, $mimeType, strlen($contents), $contentId);
    }

    private function record(Model $attachable, string $path, string $name, string $mimeType, int $size, ?string $contentId = null): Attachment
    {
        $attachment = new Attachment([
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => Str::limit(basename(str_replace('\\', '/', $name)), 250, ''),
            'mime_type' => Str::limit($mimeType, 150, ''),
            'size' => $size,
            'content_id' => $contentId,
        ]);
        $attachment->attachable()->associate($attachable);
        $attachment->save();

        return $attachment;
    }

    private function directory(): string
    {
        return 'attachments/'.now()->format('Y/m');
    }

    private function fileName(string $extension): string
    {
        $extension = Str::lower(preg_replace('/[^A-Za-z0-9]/', '', $extension));

        return Str::uuid().($extension !== '' ? '.'.Str::limit($extension, 10, '') : '');
    }
}
