<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{Site,Page,Block,Tenant,User}; use Illuminate\Support\Facades\DB;
use App\Domain\Publishing\Services\BuildPageService;
DB::beginTransaction();
DB::unprepared("SET app.current_tenant_id='019dfba5-a96b-719d-954d-60a4a549f949'");
$site = Site::first();
$page = new Page(['site_id'=>$site->id,'title'=>'Canvas Fixture','slug'=>'canvas-fixture-'.substr(md5(uniqid()),0,6),'status'=>'published','editor_mode'=>'canvas','seo_meta'=>['canvas'=>['page_type'=>'website','width'=>1200]]]);
$page->save();
$mk=function($attrs){ $b=new Block($attrs); $b->save(); return $b; };
$s1=$mk(['blockable_type'=>$page->getMorphClass(),'blockable_id'=>$page->id,'type'=>'section','level'=>'section','order'=>0,'data'=>['canvas'=>['height'=>560,'bleed'=>false,'background'=>'#f8fafc']]]);
$mk(['blockable_type'=>$page->getMorphClass(),'blockable_id'=>$page->id,'parent_block_id'=>$s1->id,'type'=>'heading','order'=>0,'data'=>['text'=>'Welcome to the Canvas','level'=>'h1'],'style'=>['layout'=>['x'=>90,'y'=>60,'width'=>620,'height'=>90,'rotation'=>-2,'zIndex'=>2]]]);
$mk(['blockable_type'=>$page->getMorphClass(),'blockable_id'=>$page->id,'parent_block_id'=>$s1->id,'type'=>'text','order'=>1,'data'=>['content'=>'<p>Freeform positioned paragraph placed lower and to the right.</p>'],'style'=>['layout'=>['x'=>300,'y'=>300,'width'=>520,'height'=>140,'zIndex'=>1]]]);
$mk(['blockable_type'=>$page->getMorphClass(),'blockable_id'=>$page->id,'parent_block_id'=>$s1->id,'type'=>'button','order'=>2,'data'=>['text'=>'Get started','url'=>'#'],'style'=>['layout'=>['x'=>90,'y'=>420,'width'=>200,'height'=>56]]]);
$s2=$mk(['blockable_type'=>$page->getMorphClass(),'blockable_id'=>$page->id,'type'=>'section','level'=>'section','order'=>1,'data'=>['canvas'=>['height'=>'auto','bleed'=>true,'background'=>'#0f172a']]]);
$mk(['blockable_type'=>$page->getMorphClass(),'blockable_id'=>$page->id,'parent_block_id'=>$s2->id,'type'=>'heading','order'=>0,'data'=>['text'=>'Full-bleed band','level'=>'h2'],'style'=>['layout'=>['x'=>60,'y'=>80,'width'=>500,'height'=>70,'zIndex'=>1]]]);
$html = app(BuildPageService::class)->build($page->fresh(), $site->theme, $site);
file_put_contents(__DIR__.'/fixture.html', $html);
echo "rendered ".strlen($html)." bytes -> canvas/fixture.html\n";
echo "cv-el count: ".substr_count($html,'class="cv-el"')."\n";
echo "has mobile stack rule: ".(str_contains($html,'@media(max-width:1200px)')?'yes':'no')."\n";
DB::rollBack(); // don't persist the fixture in dev
