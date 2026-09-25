<?php
error_reporting(E_ALL);
set_error_handler(function($n,$s){throw new Exception($s);});
define('ABSPATH','/tmp/');
class acf_field {public $name,$label,$category,$defaults;function __construct(){$this->initialize();} function add_field_filter(...$args){}}
function add_action($name,$fn){if($name==='acf/include_field_types')$fn();}
function acf_register_field_type($name){$GLOBALS['map']=new $name;}
function __($s,$d=''){return $s;}
function sanitize_key($s){return preg_replace('/[^a-z0-9_\-]/','',strtolower($s));}
function absint($v){return abs((int)$v);}
function sanitize_text_field($s){return strip_tags($s);}
function sanitize_textarea_field($s){return strip_tags($s);}
function esc_url_raw($s){return $s;}
function acf_get_valid_field($f){return $f;}
function acf_get_field_type($t){return new class {function load_field($f){return $f;}};}
function acf_get_metadata($p,$n){return $GLOBALS['db'][$p][$n]??null;}
function acf_update_value($v,$p,$f){$GLOBALS['db'][$p][$f['name']]=$v;}
function acf_get_value($p,$f){return acf_get_metadata($p,$f['name']);}
function acf_delete_value($p,$f){unset($GLOBALS['db'][$p][$f['name']]);}
function acf_validate_value($v,$f,$input){$GLOBALS['validated'][]=$input;}
require dirname(__DIR__) . '/acf/sp-interactive-map/index.php';
function check($ok,$label){if(!$ok)throw new Exception($label);echo "PASS $label\n";}
$m=$GLOBALS['map'];
$f=['key'=>'field_map','name'=>'constructor_0_map','sub_fields'=>[['key'=>'field_title','name'=>'title','type'=>'text']]];
$v=$m->update_value(['map_id'=>10,'zoom_enabled'=>1,'points'=>[['_id'=>'a','x'=>20,'y'=>30,'fields'=>['field_title'=>'First']],['_id'=>'b','x'=>40,'y'=>50,'fields'=>['field_title'=>'Second']]]],7,$f);
$GLOBALS['db'][7][$f['name']]=$v;
$loaded=$m->load_value($v,7,$f);
check($loaded['points'][0]['fields']['field_title']==='First','first point custom fields saved');
check($loaded['points'][1]['fields']['field_title']==='Second','second point custom fields saved');
check(!isset($v['points'][0]['select']) && !isset($v['points'][0]['name']),'no legacy fields saved');
$v2=$m->update_value(['map_id'=>10,'points'=>[$loaded['points'][1]]],7,$f);
check($m->load_value($v2,7,$f)['points'][0]['fields']['field_title']==='Second','surviving point retains data');
check(!isset($GLOBALS['db'][7]['constructor_0_map_point_a']),'removed point storage cleaned');
