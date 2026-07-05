<?php

namespace Tests\Unit\Services;

use App\Domain\Magazine\Services\MagazineReferenceExtractor;
use ReflectionMethod;
use Tests\TestCase;

class MagazineReferenceExtractorTest extends TestCase
{
    private function ids(string $value): array
    {
        $svc = app(MagazineReferenceExtractor::class);
        $m = new ReflectionMethod($svc, 'assetIdsFromString');
        $m->setAccessible(true);

        return $m->invoke($svc, $value);
    }

    public function test_extracts_asset_ids_from_both_url_shapes(): void
    {
        $a = '0197a001-aaaa-7bbb-8ccc-000000000001';
        $b = '0197a001-aaaa-7bbb-8ccc-000000000002';
        $html = '<img src="/api/v1/sites/0197a001-aaaa-7bbb-8ccc-0000000000ff/assets/' . $a . '/serve">'
            . ' <img src="https://sys.ensodo.eu/media/0197a001-aaaa-7bbb-8ccc-0000000000ff/' . $b . '?v=1">';
        $ids = $this->ids($html);
        $this->assertContains($a, $ids);
        $this->assertContains($b, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_ignores_external_urls_and_garbage(): void
    {
        $this->assertSame([], $this->ids('https://picsum.photos/seed/x/900/600'));
        $this->assertSame([], $this->ids('not a url at all'));
        $this->assertSame([], $this->ids(''));
    }
}
