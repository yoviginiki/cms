<?php

namespace App\Domain\Magazine\Enums;

enum FrameType: string
{
    case Text = 'text';
    case Image = 'image';
    case Shape = 'shape';
    case Line = 'line';
    case Quote = 'quote';
    case PageNumber = 'pageNumber';
    case ArticleReference = 'articleReference';
    case Decorative = 'decorative';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text Frame',
            self::Image => 'Image Frame',
            self::Shape => 'Shape',
            self::Line => 'Line',
            self::Quote => 'Pull Quote',
            self::PageNumber => 'Page Number',
            self::ArticleReference => 'Article Reference',
            self::Decorative => 'Decorative Element',
        };
    }
}
