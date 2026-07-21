<?php

namespace App\Domain\Import\Data;

class ImportResult
{
    public function __construct(
        public int $categories = 0,
        public int $pages = 0,
        public int $posts = 0,
        public int $attachments = 0,
        public int $menus = 0,
        public array $warnings = [],
        public array $errors = [],
        public array $skipped = [],
    ) {
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function addSkipped(string $type, string $title): void
    {
        $this->skipped[] = ['type' => $type, 'title' => $title];
    }

    public function toArray(): array
    {
        return [
            'categories' => $this->categories,
            'pages' => $this->pages,
            'posts' => $this->posts,
            'attachments' => $this->attachments,
            'menus' => $this->menus,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'skipped' => $this->skipped,
        ];
    }
}
