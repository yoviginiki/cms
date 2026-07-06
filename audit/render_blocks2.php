<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Domain\Blocks\Services\BlockRegistry; use App\Models\Site;
use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\View;
DB::unprepared("SET app.current_tenant_id='019dfba5-a96b-719d-954d-60a4a549f949'");
$site=Site::first();
$types=array_column(app(BlockRegistry::class)->getAllTypes(),'type'); sort($types);
$fx=['title'=>'T','heading'=>'H','text'=>'x','content'=>'<p>x</p>','subtitle'=>'s','label'=>'l','caption'=>'c','url'=>'https://e.com','src'=>'https://e.com/x.jpg','alt'=>'a'];
$ctx=fn($d)=>['data'=>$d,'children'=>'','childrenArray'=>[],'site'=>$site,'blockStyle'=>[],'blockAnimation'=>[],'blockAdvanced'=>[],'blockResponsive'=>[]];
$throwE=[];$throwF=[];
foreach($types as $t){$v="blocks.$t"; if(!View::exists($v))continue;
 foreach(['empty'=>[],'fixture'=>$fx] as $m=>$d){
   try{View::make($v,$ctx($d))->render();}
   catch(\Throwable $e){ $msg="$t: ".substr($e->getMessage(),0,80); if($m=='empty'){$throwE[]=$msg;}else{$throwF[]=$msg;} }
 }}
echo "THROW on empty (".count($throwE)."):\n".implode("\n",$throwE)."\n\n";
echo "THROW on clean string fixture (".count($throwF)."):\n".implode("\n",$throwF)."\n";
