<?php

namespace App\Domain\Projection\Descriptors;

/**
 * The semantic type of a projected field. Drives how the builder treats the
 * value: RichText fields are HTML-parsed for inventory (links, inline assets)
 * and word counting; AssetRef fields resolve to the asset inventory; Url
 * fields become outbound links.
 */
enum FieldType: string
{
    case Text = 'text';
    case RichText = 'richtext';
    case AssetRef = 'asset_ref';
    case Url = 'url';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
}
