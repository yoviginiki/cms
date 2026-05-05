<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class AssetTest extends TestCase
{
    public function test_can_upload_valid_image(): void
    {
        $this->markTestIncomplete();
    }

    public function test_rejects_php_file_disguised_as_image(): void
    {
        $this->markTestIncomplete();
    }

    public function test_rejects_oversized_file(): void
    {
        $this->markTestIncomplete();
    }

    public function test_rejects_disallowed_extension(): void
    {
        $this->markTestIncomplete();
    }

    public function test_mime_type_must_match_extension(): void
    {
        $this->markTestIncomplete();
    }

    public function test_svg_with_script_tag_rejected(): void
    {
        $this->markTestIncomplete();
    }

    public function test_deduplicates_by_checksum(): void
    {
        $this->markTestIncomplete();
    }
}
