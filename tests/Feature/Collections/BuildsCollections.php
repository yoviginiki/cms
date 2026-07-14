<?php

namespace Tests\Feature\Collections;

use App\Domain\Collections\Services\CollectionService;
use App\Models\ContentCollection;
use App\Models\Site;

/**
 * Shared fixtures: the Books/Authors pair (relation showcase) and a
 * Parts/Suppliers pair (pivot-fields showcase) used across the G1 suite.
 */
trait BuildsCollections
{
    private function createAuthorsCollection(Site $site): ContentCollection
    {
        return app(CollectionService::class)->create($site, [
            'name' => 'Authors',
            'tier' => 'static',
            'schema' => [
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'searchable' => true, 'show_in_list' => true],
                    ['key' => 'bio', 'label' => 'Biography', 'type' => 'rich_text'],
                ],
                'title_field' => 'name',
            ],
        ]);
    }

    private function createBooksCollection(Site $site, ContentCollection $authors): ContentCollection
    {
        return app(CollectionService::class)->create($site, [
            'name' => 'Books',
            'tier' => 'static',
            'schema' => [
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'searchable' => true, 'show_in_list' => true],
                    ['key' => 'isbn', 'label' => 'ISBN', 'type' => 'sku', 'unique' => true, 'searchable' => true, 'show_in_list' => true],
                    ['key' => 'price', 'label' => 'Price', 'type' => 'price', 'show_in_list' => true],
                    ['key' => 'genre', 'label' => 'Genre', 'type' => 'select', 'facetable' => true, 'options' => ['Sci-Fi', 'Fantasy', 'Mystery']],
                    ['key' => 'tags', 'label' => 'Tags', 'type' => 'multi_select', 'options' => ['classic', 'new', 'signed']],
                    ['key' => 'released', 'label' => 'Release date', 'type' => 'date'],
                    ['key' => 'in_stock', 'label' => 'In stock', 'type' => 'boolean', 'facetable' => true],
                    ['key' => 'publisher_url', 'label' => 'Publisher URL', 'type' => 'url'],
                    ['key' => 'contact_email', 'label' => 'Contact email', 'type' => 'email'],
                    ['key' => 'contact_phone', 'label' => 'Contact phone', 'type' => 'phone'],
                    ['key' => 'summary', 'label' => 'Summary', 'type' => 'rich_text', 'searchable' => true],
                    ['key' => 'cover', 'label' => 'Cover', 'type' => 'image'],
                    ['key' => 'author', 'label' => 'Author', 'type' => 'relation', 'facetable' => true, 'relation' => ['collection_id' => $authors->id, 'mode' => 'many']],
                ],
                'title_field' => 'title',
            ],
        ]);
    }

    /** @return array{0: ContentCollection, 1: ContentCollection} [suppliers, parts] */
    private function createPartsAndSuppliers(Site $site): array
    {
        $service = app(CollectionService::class);

        $suppliers = $service->create($site, [
            'name' => 'Suppliers',
            'schema' => [
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                    ['key' => 'lead_time', 'label' => 'Lead time (days)', 'type' => 'number'],
                ],
                'title_field' => 'name',
            ],
        ]);

        $parts = $service->create($site, [
            'name' => 'Parts',
            'schema' => [
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'searchable' => true],
                    ['key' => 'part_number', 'label' => 'Part number', 'type' => 'sku', 'required' => true, 'unique' => true, 'searchable' => true],
                    ['key' => 'suppliers', 'label' => 'Suppliers', 'type' => 'relation', 'relation' => [
                        'collection_id' => $suppliers->id,
                        'mode' => 'many',
                        'pivot_fields' => [
                            ['key' => 'supplier_part_number', 'label' => 'Supplier part number', 'type' => 'sku', 'required' => true],
                            ['key' => 'supplier_price', 'label' => 'Supplier price', 'type' => 'price'],
                        ],
                    ]],
                ],
                'title_field' => 'name',
            ],
        ]);

        return [$suppliers, $parts];
    }
}
