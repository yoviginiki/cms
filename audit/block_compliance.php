<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Domain\Blocks\Services\BlockRegistry;
use App\Domain\References\Services\ReferenceExtractorRegistry;
use Illuminate\Support\Facades\View;

$reg = app(BlockRegistry::class);
$types = array_column($reg->getAllTypes(), 'type');
sort($types);
$extReg = app(ReferenceExtractorRegistry::class);

$reactBase = __DIR__.'/../resources/admin/src/components/blocks';
$indexTs = file_get_contents("$reactBase/index.ts");

$rows=[]; $counts=['no_blade'=>0,'no_react_dir'=>0,'no_editor'=>0,'no_preview'=>0,'no_def_ts'=>0,'not_imported'=>0,'no_extractor'=>0,'no_sanitize'=>0];
foreach($types as $t){
  $def = $reg->get($t);
  $blade = View::exists("blocks.$t");
  $rdir = is_dir("$reactBase/$t");
  $editor = file_exists("$reactBase/$t/Editor.tsx");
  $preview= file_exists("$reactBase/$t/Preview.tsx");
  $defts  = file_exists("$reactBase/$t/definition.ts");
  $imported = str_contains($indexTs, "'./$t'") || str_contains($indexTs, "\"./$t\"");
  $ext = $extReg->has($t);
  $san = $def ? ($def->sanitizationConfig() !== [] && ($def->sanitizationConfig()['HTML.Allowed'] ?? null) !== null) : false;
  $miss=[];
  if(!$blade){$miss[]='BLADE'; $counts['no_blade']++;}
  if(!$rdir){$miss[]='REACT_DIR'; $counts['no_react_dir']++;}
  else { if(!$editor){$miss[]='Editor'; $counts['no_editor']++;} if(!$preview){$miss[]='Preview'; $counts['no_preview']++;} if(!$defts){$miss[]='def.ts'; $counts['no_def_ts']++;} }
  if(!$imported){$miss[]='NOT_IMPORTED'; $counts['not_imported']++;}
  if(!$ext){$miss[]='NO_EXTRACTOR'; $counts['no_extractor']++;}
  if($miss) $rows[]=sprintf("%-22s %s", $t, implode(', ',$miss));
}
echo "REGISTERED PHP TYPES: ".count($types)."\n";
echo "React dirs: ".count(glob("$reactBase/*", GLOB_ONLYDIR))."  Blade views: ".count(glob(__DIR__.'/../resources/views/blocks/*.blade.php'))."\n\n";
echo "=== TYPES WITH MISSING ARTIFACTS ===\n".($rows? implode("\n",$rows) : "(none — all complete)")."\n\n";
echo "COUNTS: ".json_encode($counts)."\n\n";

// Orphans: react dirs / blade views with NO registered PHP type
$typeSet = array_flip($types);
$orphanReact=[]; foreach(glob("$reactBase/*",GLOB_ONLYDIR) as $d){ $n=basename($d); if(!isset($typeSet[$n])) $orphanReact[]=$n; }
$orphanBlade=[]; foreach(glob(__DIR__.'/../resources/views/blocks/*.blade.php') as $f){ $n=basename($f,'.blade.php'); if(!isset($typeSet[$n])) $orphanBlade[]=$n; }
echo "ORPHAN React dirs (no PHP type): ".(implode(', ',$orphanReact)?:'(none)')."\n";
echo "ORPHAN Blade views (no PHP type): ".(implode(', ',$orphanBlade)?:'(none)')."\n";
