<?php

namespace App\Domain\Projection;

/**
 * The filtered views over the full internal projection. Every view is a pure
 * subset of the full projection — never an independent computation
 * (Prime Directive 7).
 */
enum ProjectionView: string
{
    /** schema.org + a minimal structure. The public, publishable payload. */
    case Public = 'public';

    /** Everything. The internal, richest representation. */
    case Internal = 'internal';

    /** RAG segments + provenance. */
    case Rag = 'rag';

    /** The asset / link / heading inventory only. */
    case Inventory = 'inventory';
}
