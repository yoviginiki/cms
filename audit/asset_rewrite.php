<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Domain\Publishing\Services\AssetPublisher;
use App\Models\Asset; use Illuminate\Support\Facades\DB;
DB::unprepared("SET app.current_tenant_id='019dfba5-a96b-719d-954d-60a4a549f949'");
// find a real asset with variants
$asset = Asset::whereNotNull('variants')->where('variants','!=','[]')->first();
if($asset){
  echo "ASSET {$asset->id} ext=".pathinfo($asset->storage_path,PATHINFO_EXTENSION)." checksum=".substr($asset->checksum??'',0,12)."\n";
  echo "variants: ".json_encode(array_keys($asset->variants))."\n\n";
  $sid = $asset->site_id; $aid=$asset->id;
  $sample = '<picture><source srcset="/api/v1/sites/'.$sid.'/assets/'.$aid.'/serve/webp_400 400w, /api/v1/sites/'.$sid.'/assets/'.$aid.'/serve/webp_800 800w" type="image/webp"><img src="/api/v1/sites/'.$sid.'/assets/'.$aid.'/serve/medium_800" srcset="/api/v1/sites/'.$sid.'/assets/'.$aid.'/serve/thumb_200 200w" data-base="/api/v1/sites/'.$sid.'/assets/'.$aid.'/serve"></picture>';
  echo "IN:\n$sample\n\nOUT:\n".AssetPublisher::rewriteHtml($sample)."\n";
} else echo "no asset with variants\n";
