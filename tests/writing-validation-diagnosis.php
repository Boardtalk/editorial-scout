<?php
/** Characterize 0.8.2 false rejections with synthetic responses, not live writing. */
if(!isset($checks,$prep_id,$prep_input)||'cli'!==PHP_SAPI){exit(1);}
$diagnostic_cases=array(
 'quoted_claim'=>array('stage'=>'claims','mutate'=>static function($r){$r['notes'][1]['text']='The term "garden look" is subjective and needs editorial review.';return $r;}),
 'long_introduction'=>array('stage'=>'field_text','mutate'=>static function($r){$r['introduction']=str_repeat('a',1201);return $r;}),
 'numbered_heading'=>array('stage'=>'unsupported_number','mutate'=>static function($r){$r['sections'][0]['heading']='1. Consider the planting style';return $r;}),
 'coverage_kind'=>array('stage'=>'claim_coverage','mutate'=>static function($r){$r['notes']=array($r['notes'][0],array('kind'=>'opinion','text'=>'Independent verification is missing and further research is needed.','sources'=>array()));return $r;}),
 'quoted_tag'=>array('stage'=>'tags','mutate'=>static function($r){$r['tags']=array('The "garden look"');return $r;}),
 'two_text_blocks'=>array('stage'=>'response_envelope'),
);
// Only this disposable test's own journal is changed to permit synthetic attempts.
$wpdb->update($writing,array('state'=>'failed'),array('commission_id'=>$prep_id));
foreach($diagnostic_cases as$case=>$spec){
 $lock=Editorial_Scout_Runner::lock();scout_assert('writing_prepared'===$writer::prepare($prep_id,$prep_input,$lock),'Diagnostic fixture prepares locally: '.$case);$dj=$writer::job($prep_id);$u=Editorial_Scout_OpenAI::usage();$u['last_at']=0;update_option(Editorial_Scout_OpenAI::USAGE,$u,false);$dcalls=0;
 $diagnostic_mock=static function($pre,$args,$url)use($spec,$case,&$dcalls){if(Editorial_Scout_OpenAI::ENDPOINT!==$url){return new WP_Error('unexpected_fixture_request');}++$dcalls;$payload=json_decode(json_decode($args['body'],true)['input'],true);$response=scout_writing_response($payload);$env=json_decode($response['body'],true);if('two_text_blocks'===$case){$text=$env['output'][0]['content'][0]['text'];$half=(int)(strlen($text)/2);$env['output'][0]['content']=array(array('type'=>'output_text','text'=>substr($text,0,$half)),array('type'=>'output_text','text'=>substr($text,$half)));}else{$value=$spec['mutate'](json_decode($env['output'][0]['content'][0]['text'],true));$env['output'][0]['content'][0]['text']=wp_json_encode($value);}$response['body']=wp_json_encode($env);return $response;};
 add_filter('pre_http_request',$diagnostic_mock,99,3);$dr=$writer::write($prep_id,$dj['context']['token'],true,$lock);remove_filter('pre_http_request',$diagnostic_mock,99);Editorial_Scout_Runner::unlock($lock);
 scout_assert(1===$dcalls&&scout_error($dr,'writing_output')&&$spec['stage']===$writer::job($prep_id)['context']['error_stage'],'Characterized safe stage, not a live diagnosis: '.$case);
 scout_assert(!isset($writer::job($prep_id)['context']['result'])&&null===$writer::job($prep_id)['post_id'],'Rejected synthetic output cannot create native content: '.$case);
}
$stage='claims';scout_assert(Editorial_Scout_Drafting_Screen::message('writing_output')===Editorial_Scout_Drafting_Screen::message('writing_output',$stage),'A persisted claims stage currently produces only the generic UI message');

$title_case=scout_writing_fixture($writer::job($prep_id)['context']['payload']);$title_case['headline']='A New Easier Way – Gardening With Nature Without Losing the ‘Garden’ Look';scout_assert(is_array($writer::validate($title_case,$writer::job($prep_id)['context']['payload'])),'The reported title punctuation itself passes current text validation');
