<?php

namespace Tests\Unit\Http;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class SecurityHeadersFramingTest extends TestCase
{
    private function frameHeader(string $path): ?string
    {
        $res = (new SecurityHeaders())->handle(Request::create($path), fn () => new Response('ok'));

        return $res->headers->get('X-Frame-Options');
    }

    public function test_grid_editor_site_preview_is_frameable_same_origin(): void
    {
        $this->assertSame('SAMEORIGIN', $this->frameHeader('/sites/monikcreations/portfolio?_grid=x&_toolbar=0'));
        $this->assertSame('SAMEORIGIN', $this->frameHeader('/api/v1/sites/s/pages/p/preview'));
    }

    public function test_everything_else_stays_deny(): void
    {
        $this->assertSame('DENY', $this->frameHeader('/admin/sites/monikcreations/grids'));
        $this->assertSame('DENY', $this->frameHeader('/login'));
    }
}
