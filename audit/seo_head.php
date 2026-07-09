<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Site; use App\Models\Page; use App\Models\Post; use Illuminate\Support\Facades\DB;
use App\Domain\Publishing\Services\SeoService;
DB::unprepared("SET app.current_tenant_id='019dfba5-a96b-719d-954d-60a4a549f949'");
$page=Page::whereNotNull('slug')->first();
$site=$page? Site::find($page->site_id) : Site::first();
echo "site={$site->name} slug={$site->slug} pages=".Page::where('site_id',$site->id)->count()." published=".Page::where('site_id',$site->id)->where('status','published')->count()."\n\n";
echo "===== PAGE HEAD (page: {$page->title} status={$page->status}) =====\n";
try{ echo app(SeoService::class)->generatePageHead($page,$site); }catch(\Throwable $e){echo "THROW: ".$e->getMessage();}
$post=Post::first();
if($post){ echo "\n\n===== POST HEAD (post: {$post->title}) =====\n"; try{ echo app(SeoService::class)->generatePageHead($post,$site); }catch(\Throwable $e){echo "THROW: ".$e->getMessage();} }
