<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class CsrfTest extends TestCase
{
    public function test_post_without_csrf_token_is_rejected(): void
    {
        $this->markTestIncomplete();
    }

    public function test_post_with_valid_csrf_token_succeeds(): void
    {
        $this->markTestIncomplete();
    }
}
