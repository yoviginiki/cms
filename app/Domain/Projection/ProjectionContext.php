<?php

namespace App\Domain\Projection;

use App\Domain\Projection\Descriptors\BlockProjection;
use Closure;

/**
 * Everything the pure builder needs, passed in explicitly. The builder reads
 * nothing from the database, filesystem, network, cache, clock or randomness —
 * if it needs something, it comes through here.
 */
final class ProjectionContext
{
    /**
     * @param string       $pageId          Source page/post id.
     * @param string       $pageVersionId   Source page_versions id (provenance).
     * @param string       $url             Public URL of the page.
     * @param string       $title           Page title (root of every heading path).
     * @param string       $language        BCP-47 language tag.
     * @param string|null  $publishedAt     ISO-8601 or null.
     * @param string|null  $modifiedAt      ISO-8601 or null.
     * @param Closure(string):(?BlockProjection) $descriptorResolver
     *        Resolves a block type to its projection descriptor, or null when
     *        the block does not opt in.
     * @param null|Closure(string):bool $isFieldRendered
     *        Optional parity predicate: given a canonical field address, was it
     *        actually rendered on the page? Defaults to "everything rendered".
     */
    public function __construct(
        public readonly string $pageId,
        public readonly string $pageVersionId,
        public readonly string $url,
        public readonly string $title,
        public readonly string $language,
        public readonly ?string $publishedAt,
        public readonly ?string $modifiedAt,
        public readonly Closure $descriptorResolver,
        public readonly ?Closure $isFieldRendered = null,
    ) {
    }

    public function descriptorFor(string $type): ?BlockProjection
    {
        return ($this->descriptorResolver)($type);
    }

    public function fieldIsRendered(string $address): bool
    {
        if ($this->isFieldRendered === null) {
            return true;
        }

        return (bool) ($this->isFieldRendered)($address);
    }
}
