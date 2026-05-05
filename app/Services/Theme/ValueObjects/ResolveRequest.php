<?php

namespace App\Services\Theme\ValueObjects;

class ResolveRequest
{
    public function __construct(
        public string $tenantId,
        public ?string $siteId = null,
        public ?string $pageId = null,
        public ?string $blockId = null,
        public string $mode = 'light',
    ) {}
}
