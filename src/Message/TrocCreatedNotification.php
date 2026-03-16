<?php

namespace App\Message;

class TrocCreatedNotification
{
    public function __construct(
        private readonly int $annonceId,
        private readonly string $title,
        private readonly string $category,
    ) {
    }

    public function getAnnonceId(): int
    {
        return $this->annonceId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCategory(): string
    {
        return $this->category;
    }
}
