<?php

namespace Tests\Feature\Publishing;

use App\Domain\Publishing\Services\DeployTargetResolver;
use App\Models\Site;
use Tests\TestCase;

/**
 * Production php-fpm runs under open_basedir: stat()ing another domain's
 * docroot raises a warning that Laravel turns into an ErrorException. The
 * resolver's read path must degrade to "no owned target", never throw
 * (admin preview of slider pages calls it outside a build).
 */
class OpenBasedirResolverTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_open_basedir_errors_resolve_to_null(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id, 'custom_domain' => 'blocked.test']);
        config(['publishing.tenant_base' => '/var/lib/cms-open-basedir-probe']);

        ini_set('open_basedir', base_path() . PATH_SEPARATOR . sys_get_temp_dir());

        $this->assertNull(app(DeployTargetResolver::class)->tryLiveDocroot($site));
    }
}
