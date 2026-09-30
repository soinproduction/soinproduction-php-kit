<?php
define('ABSPATH', '/tmp/');
$hooks=[];
function add_action(...$args) {global $hooks; $hooks[$args[0]][]=$args[1];}
function add_filter(...$args) {add_action(...$args);}
function add_shortcode(...$args) {}
function wp_unslash($v) {return $v;}
function wpcf7_get_message($key) {return $key;}
function wpcf7_swv_create_rule($type,$args) {return $args;}
class WPCF7_FormTag {public $type; public $basetype; public $name; public function __construct($args) {foreach($args as $key=>$value) $this->$key=$value;}}
class WPCF7_ContactForm {static function get_current(){return new self;} function prop($key){return '[custom_select* Interested type "single" options "One|Two"]';} function scan_form_tags($args){return [];}}
class Result {public $errors=[]; function invalidate($tag,$message){$this->errors[$tag->name]=$message;}}
class Schema {public $rules=[]; function add_rule($rule){$this->rules[]=$rule;}}
require dirname(__DIR__) . '/plugins/sp-cf7/modules/sp-cf7-select-field/index.php';
foreach(['missing'=>[], 'empty'=>['Interested'=>''], 'selected'=>['Interested'=>'One']] as $case=>$post){$_POST=$post;$r=new Result;foreach($hooks['wpcf7_validate'] as $fn)$fn($r);if(($case==='selected') !== empty($r->errors))throw new Exception($case);if($case!=='selected'&&!isset($r->errors['Interested']))throw new Exception('Wrong case');echo "$case PASS\n";}
$s=new Schema;foreach($hooks['wpcf7_swv_create_schema'] as $fn)$fn($s,new WPCF7_ContactForm);if($s->rules[0]['field']!=='Interested')throw new Exception('Schema case');echo "schema PASS\n";
