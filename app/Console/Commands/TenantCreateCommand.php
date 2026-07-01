<?php

namespace App\Console\Commands;

use App\Domain\Sites\Services\SiteService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create
        {--tenant-name= : Tenant name (org/company)}
        {--tenant-slug= : Tenant slug}
        {--user-name= : Owner user name}
        {--user-email= : Owner user email}
        {--user-password= : Owner user password}
        {--site-name= : First site name}
        {--site-slug= : First site slug}
        {--site-domain= : Custom domain for the site}
        {--template=blank : Starter template (blank/blog/portfolio/business)}
        {--existing-tenant= : Use existing tenant ID instead of creating one}';

    protected $description = 'Create a new tenant with owner user and first site';

    public function handle(SiteService $siteService): int
    {
        // Collect inputs
        $tenantName = $this->option('tenant-name') ?: $this->ask('Tenant name (company/org)');
        $tenantSlug = $this->option('tenant-slug') ?: str($tenantName)->slug()->value();

        $existingTenantId = $this->option('existing-tenant');

        if ($existingTenantId) {
            $tenant = Tenant::find($existingTenantId);
            if (!$tenant) {
                $this->error("Tenant {$existingTenantId} not found.");
                return 1;
            }
            $this->info("Using existing tenant: {$tenant->name} ({$tenant->id})");
        } else {
            $tenant = Tenant::create([
                'name' => $tenantName,
                'slug' => $tenantSlug,
                'plan' => 'pro',
            ]);
            $this->info("Created tenant: {$tenant->name} ({$tenant->id})");
        }

        // Set RLS context
        DB::statement("SET app.current_tenant_id = '{$tenant->id}'");

        // Create owner user (skip if --existing-tenant and user already exists)
        $userEmail = $this->option('user-email') ?: $this->ask('Owner email', 'admin@' . $tenantSlug . '.com');
        $existingUser = User::where('tenant_id', $tenant->id)->where('email', $userEmail)->first();

        if ($existingUser) {
            $this->info("User {$userEmail} already exists, skipping.");
        } else {
            $userName = $this->option('user-name') ?: $this->ask('Owner name', $tenantName . ' Admin');
            $userPassword = $this->option('user-password') ?: $this->secret('Owner password');

            if (!$userPassword) {
                $this->error('Password is required.');
                return 1;
            }

            User::create([
                'tenant_id' => $tenant->id,
                'name' => $userName,
                'email' => $userEmail,
                'password' => Hash::make($userPassword),
                'role' => 'owner',
            ]);
            $this->info("Created owner user: {$userEmail}");
        }

        // Create site
        $siteName = $this->option('site-name') ?: $this->ask('Site name', $tenantName);
        $siteSlug = $this->option('site-slug') ?: str($siteName)->slug()->value();
        $siteDomain = $this->option('site-domain');

        $siteData = [
            'name' => $siteName,
            'slug' => $siteSlug,
        ];
        if ($siteDomain) {
            $siteData['custom_domain'] = $siteDomain;
        }

        $site = $siteService->createSite($siteData, $tenant);
        $this->info("Created site: {$site->name} (slug: {$site->slug}, id: {$site->id})");

        if ($siteDomain) {
            $this->info("Custom domain: {$siteDomain}");
        }

        // Apply template
        $template = $this->option('template');
        if ($template && $template !== 'blank') {
            $templateService = app(\App\Domain\Sites\Services\StarterTemplateService::class);
            $templateService->apply($site, $template);
            $this->info("Applied starter template: {$template}");
        }

        // Configure deploy for custom domain
        if ($siteDomain) {
            $settings = $site->settings ?? [];
            $settings['deploy_method'] = 'local';
            $site->update(['settings' => $settings]);
            $this->info("Deploy method set to local (custom domain path).");
        }

        $this->newLine();
        $this->info('Done! Login at: ' . config('app.url') . '/admin');
        $this->table(
            ['Field', 'Value'],
            [
                ['Tenant ID', $tenant->id],
                ['Site ID', $site->id],
                ['Site Slug', $site->slug],
                ['Custom Domain', $siteDomain ?: '—'],
                ['Template', $template],
            ]
        );

        return 0;
    }
}
