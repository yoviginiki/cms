<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Site; use App\Models\Page; use Illuminate\Support\Facades\DB;
use App\Domain\Publishing\Services\{SitemapGenerator,RobotsGenerator,SeoService};
DB::unprepared("SET app.current_tenant_id='019dfba5-a96b-719d-954d-60a4a549f949'");
$site=Site::with('theme')->first();
echo "SITE: {$site->name} slug={$site->slug} custom_domain=".($site->custom_domain?:'(none)')."\n\n";
echo "===== SITEMAP (first 900 chars) =====\n";
try { echo substr(app(SitemapGenerator::class)->generate($site),0,900)."\n"; } catch(\Throwable $e){ echo "THROW: ".$e->getMessage()."\n"; }
echo "\n===== ROBOTS =====\n";
try { echo app(RobotsGenerator::class)->generate($site)."\n"; } catch(\Throwable $e){ echo "THROW: ".$e->getMessage()."\n"; }
echo "===== PAGE HEAD (first published page) =====\n";
$page=Page::where('site_id',$site->id)->where('status','published')->first();
if($page){ try { echo app(SeoService::class)->generatePageHead($page,$site)."\n"; } catch(\Throwable $e){ echo "THROW: ".$e->getMessage()."\n"; } }
else echo "(no published page)\n";
