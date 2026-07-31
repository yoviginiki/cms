<?php

namespace Tests\Unit\InlineEdit;

use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Policies\PagePolicy;
use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Framework\TestCase;

/**
 * DB-free proof of the Phase 4 role × action matrix and the tenant guard.
 * Tenant is resolved through an in-memory site relation, so no DB is touched.
 */
final class PageInlinePolicyTest extends TestCase
{
    private static \Illuminate\Foundation\Application $app;
    private PagePolicy $policy;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 3);
        require_once $root . '/vendor/autoload.php';
        self::$app = require $root . '/bootstrap/app.php';
        self::$app->make(Kernel::class)->bootstrap();
    }

    protected function setUp(): void
    {
        $this->policy = new PagePolicy();
    }

    private function page(string $tenantId): Page
    {
        $page = new Page();
        $page->setRelation('site', new Site(['tenant_id' => $tenantId]));
        return $page;
    }

    private function user(string $role, string $tenantId = 't1'): User
    {
        return new User(['role' => $role, 'tenant_id' => $tenantId]);
    }

    public static function matrix(): array
    {
        return [
            //          role       edit   publish
            'viewer' => ['viewer', false, false],
            'author' => ['author', false, false],
            'editor' => ['editor', true,  false],
            'admin'  => ['admin',  true,  true],
            'owner'  => ['owner',  true,  true],
        ];
    }

    /**
     * @dataProvider matrix
     */
    public function test_same_tenant_matrix(string $role, bool $edit, bool $publish): void
    {
        $page = $this->page('t1');
        $user = $this->user($role, 't1');

        $this->assertSame($edit, $this->policy->inlineEdit($user, $page), "$role inlineEdit");
        $this->assertSame($publish, $this->policy->inlinePublish($user, $page), "$role inlinePublish");
    }

    /**
     * @dataProvider matrix
     */
    public function test_cross_tenant_is_always_denied(string $role): void
    {
        $page = $this->page('t2'); // page's tenant differs from the user's
        $user = $this->user($role, 't1');

        $this->assertFalse($this->policy->inlineEdit($user, $page), "$role inlineEdit across tenant");
        $this->assertFalse($this->policy->inlinePublish($user, $page), "$role inlinePublish across tenant");
    }
}
