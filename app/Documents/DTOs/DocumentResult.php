<?php

namespace App\Documents\DTOs;

final class DocumentResult
{
    public function __construct(
        public readonly string $content,
        public readonly string $filename,
        public readonly string $mimeType = 'application/pdf',
        public readonly array $meta = []
    ) {}

    /**
     * Devolver contenido en Base64 para visualización en APIs JSON o Data URIs
     */
    public function toBase64(): string
    {
        return base64_encode($this->content);
    }

    /**
     * Devolver Data URI completa
     */
    public function toDataUri(): string
    {
        return 'data:' . $this->mimeType . ';base64,' . $this->toBase64();
    }
}
