<?php

namespace App\Mail\Inbound;

final readonly class InboundAttachment
{
    public function __construct(
        public string $name,
        public string $mimeType,
        public string $content,
        public ?string $contentId = null,
        public bool $inline = false,
    ) {}
}
