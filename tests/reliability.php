<?php
/** Documented response variations through the production bridge, with no real provider. */
if(!isset($checks,$prep_id,$prep_input)||'cli'!==PHP_SAPI){exit(1);}
$reliability_cases=array(
 'completed'=>'', 'reordered'=>'', 'split_blocks'=>'', 'split_messages'=>'', 'reasoning_first'=>'', 'private_quotes'=>'', 'public_grounded_quotes'=>'', 'numbered_heading'=>'',
 'decimal'=>'', 'decimal_unsupported'=>'unsupported_number', 'incomplete_article'=>'', 'incomplete_not_empty'=>'structure',
 'invalid_json'=>'json', 'extra_field'=>'structure', 'markup'=>'field_text', 'long_intro'=>'field_text', 'missing_notes'=>'structure', 'missing_verification'=>'claim_coverage', 'unknown_source'=>'source_reference', 'unsupported_number'=>'unsupported_number', 'invented_quote'=>'quotation', 'unclosed_quote'=>'quotation', 'copying'=>'copying',
 'incomplete_limit'=>'output_limit', 'incomplete_filter'=>'incomplete', 'refusal'=>'refusal', 'tool_output'=>'response_envelope', 'unknown_block'=>'response_envelope', 'two_documents'=>'json', 'empty_text'=>'response_envelope', 'bad_usage'=>'response_usage', 'provider_failed'=>'provider_status', 'timeout'=>'transport', 'connection'=>'transport', 'rate_limit'=>'rate_limit', 'authentication'=>'authentication', 'service'=>'provider_unavailable', 'bad_request'=>'provider_rejected', 'secret'=>'secret',
);
foreach($reliability_cases as$scenario=>$expected){
 $wpdb->update($writing,array('state'=>'failed'),array('commission_id'=>$prep_id));$lock=Editorial_Scout_Runner::lock();
 scout_assert('writing_prepared'===$writer::prepare($prep_id,$scenario==='decimal'?array_merge($prep_input,array('notes'=>array($prep_input['notes'][0].' Measured spacing is 3.5 metres.'))):$prep_input,$lock),'Reliability fixture prepares without provider: '.$scenario);$rj=$writer::job($prep_id);$u=Editorial_Scout_OpenAI::usage();$u['last_at']=0;$u['daily']=0;update_option(Editorial_Scout_OpenAI::USAGE,$u,false);$r_calls=0;$actual_before=$u['actual']??0;
 $response_mock=static function($pre,$args,$url)use($scenario,&$r_calls){
  ++$r_calls;scout_assert(Editorial_Scout_OpenAI::ENDPOINT===$url&&0===$args['redirection']&&true===$args['sslverify'],'Fixed endpoint, no redirects and TLS verification retained');$request=json_decode($args['body'],true);scout_assert(false===$request['store']&&true===$request['text']['format']['strict']&&4096===$request['max_output_tokens']&&!isset($request['tools']),'Strict structured output, privacy and fixed output allowance retained');
  $payload=json_decode($request['input'],true);$r=scout_writing_response($payload);$env=json_decode($r['body'],true);$env['usage']=array('input_tokens'=>400,'output_tokens'=>500,'total_tokens'=>900);$env['output'][0]['role']='assistant';$env['output'][0]['status']='completed';$value=json_decode($env['output'][0]['content'][0]['text'],true);
  switch($scenario){
   case 'reordered':$value=array_reverse($value,true);$value['sections'][0]=array_reverse($value['sections'][0],true);$value['notes'][0]=array_reverse($value['notes'][0],true);break;
   case 'private_quotes':$value['notes'][1]['text']='The term “garden look” needs verification, not assumed certainty.';$value['tags']=array('A "garden look"');break;
   case 'public_grounded_quotes':$value['introduction']='The supplied notes call it a “shared community garden”. Independent checks remain necessary.';break;
   case 'numbered_heading':$value['sections'][0]['heading']='1. Consider the growing space';break;
   case 'decimal':$value['introduction']='The recorded spacing is 3.5 metres; wider context needs checking.';break;
   case 'decimal_unsupported':$value['introduction']='The recorded spacing is 3.75 metres.';break;
   case 'incomplete_article':$value=scout_writing_fixture($payload,true);break;
   case 'incomplete_not_empty':$value['status']='research_incomplete';break;
   case 'extra_field':$value['unexpected']='no';break;
   case 'markup':$value['headline']='<script>private canary</script>';break;
   case 'long_intro':$value['introduction']=str_repeat('a',1201);break;
   case 'missing_notes':$value['notes']=array();break;
   case 'missing_verification':$value['notes']=array($value['notes'][0]);break;
   case 'unknown_source':$value['notes'][0]['sources']=array(999999);break;
   case 'unsupported_number':$value['sections'][0]['heading']='1. A factual claim about 987654 gardens';break;
   case 'invented_quote':$value['introduction']='The notes claim “an invented quotation never supplied”.';break;
   case 'unclosed_quote':$value['introduction']='An unmatched “quotation is not safe.';break;
   case 'copying':$value['sections'][0]['text']=$payload['sources'][0]['material'];break;
   case 'secret':$value['headline']=EDITORIAL_SCOUT_OPENAI_API_KEY;break;
  }
  $text=wp_json_encode($value);$env['output'][0]['content'][0]['text']=$text;
  switch($scenario){
   case 'split_blocks':$half=(int)(strlen($text)/2);$env['output'][0]['content']=array(array('type'=>'output_text','text'=>substr($text,0,$half)),array('type'=>'output_text','text'=>substr($text,$half)));break;
   case 'split_messages':$half=(int)(strlen($text)/2);$second=$env['output'][0];$env['output'][0]['content'][0]['text']=substr($text,0,$half);$second['content'][0]['text']=substr($text,$half);$env['output'][]=$second;break;
   case 'reasoning_first':array_unshift($env['output'],array('type'=>'reasoning','id'=>'fixture_metadata','summary'=>array(array('type'=>'summary_text','text'=>'PRIVATE_REASONING_CANARY'))));break;
   case 'invalid_json':$env['output'][0]['content'][0]['text']='{"incomplete';break;
   case 'two_documents':$env['output'][0]['content'][0]['text']=$text.$text;break;
   case 'empty_text':$env['output'][0]['content'][0]['text']=' ';break;
   case 'incomplete_limit':$env['status']='incomplete';$env['incomplete_details']=array('reason'=>'max_output_tokens');break;
   case 'incomplete_filter':$env['status']='incomplete';$env['incomplete_details']=array('reason'=>'content_filter');break;
   case 'refusal':$env['output'][0]['content'][]=array('type'=>'refusal','refusal'=>'PRIVATE_REFUSAL_CANARY');break;
   case 'tool_output':array_unshift($env['output'],array('type'=>'function_call','name'=>'forbidden','arguments'=>'PRIVATE_TOOL_CANARY'));break;
   case 'unknown_block':$env['output'][0]['content'][]=array('type'=>'future_unknown','text'=>'PRIVATE_BLOCK_CANARY');break;
   case 'bad_usage':$env['usage']['output_tokens']=200;break;
   case 'provider_failed':$env['status']='failed';$env['error']=array('message'=>'PRIVATE_PROVIDER_CANARY');break;
   case 'timeout':return new WP_Error('http_request_failed','PRIVATE_TIMEOUT_CANARY');
   case 'connection':return new WP_Error('http_request_failed','PRIVATE_CONNECTION_CANARY');
   case 'rate_limit':$r['response']['code']=429;break;
   case 'authentication':$r['response']['code']=401;break;
   case 'service':$r['response']['code']=503;break;
   case 'bad_request':$r['response']['code']=400;break;
  }
  $r['body']=wp_json_encode($env);return $r;
 };
 add_filter('pre_http_request',$response_mock,99,3);$out=$writer::write($prep_id,$rj['context']['token'],true,$lock);remove_filter('pre_http_request',$response_mock,99);$job_after=$writer::job($prep_id);
 scout_assert(1===$r_calls&&($expected?(is_wp_error($out)&&$expected===$job_after['context']['error_stage']&&!isset($job_after['context']['result'])):('incomplete_article'===$scenario?'writing_inadequate':'writing_ready')===$out),'Production parser/validator handles documented scenario: '.$scenario);
 $u_after=Editorial_Scout_OpenAI::usage();$request_failure=in_array($scenario,array('timeout','connection','rate_limit','authentication','service','bad_request'),true);scout_assert($u_after['daily']>0&&$u_after['actual']===$actual_before+($request_failure||'bad_usage'===$scenario?0:900)&&(!$request_failure||'failed'===$u_after['status']),'Reservations and reported actual usage survive failures without mislabeling transport: '.$scenario);
 scout_assert(null===$job_after['post_id']&&scout_error($writer::write($prep_id,$rj['context']['token'],true,$lock),'writing_stale'),'No native post and no repeated request from consumed consent: '.$scenario);
 $summary=$screen::diagnostic_summary($prep_id);scout_assert(!str_contains($summary,'CANARY')&&!str_contains($summary,$rj['context']['token'])&&!str_contains($summary,EDITORIAL_SCOUT_OPENAI_API_KEY)&&!str_contains($summary,$prep_input['notes'][0])&&($expected?str_contains($summary,$expected):''===$summary),'Shareable diagnostic includes safe labels only: '.$scenario);
 if($expected){scout_assert($screen::message(is_wp_error($out)?$out->get_error_code():'',$expected)!==Editorial_Scout_Dashboard::explanation('ai_output'),'Writing failure never uses website-learning explanation');}
 Editorial_Scout_Runner::unlock($lock);
}
$before_diag=$writer::job($prep_id);$summary=$screen::diagnostic_summary($prep_id);ob_start();$screen::render($prep_id);$diagnostic_html=ob_get_clean();
scout_assert(str_contains($diagnostic_html,'Copy diagnostic summary')&&str_contains($diagnostic_html,'Technical details')&&$before_diag===$writer::job($prep_id),'In-admin diagnostics read the existing journal without mutation');
wp_set_current_user(get_user_by('login','scout-test-editor')->ID);scout_assert(''===$screen::diagnostic_summary($prep_id),'Non-administrator cannot obtain a writing diagnostic');wp_set_current_user(1);
$wpdb->update($writing,array('state'=>'failed'),array('commission_id'=>$prep_id));$lock=Editorial_Scout_Runner::lock();$writer::prepare($prep_id,$prep_input,$lock);$old_rules=$writer::job($prep_id);unset($old_rules['context']['rules']);$wpdb->update($writing,array('data'=>wp_json_encode($old_rules['context'])),array('commission_id'=>$prep_id));$no_calls=0;$guard=static function()use(&$no_calls){++$no_calls;return new WP_Error('not_allowed');};add_filter('pre_http_request',$guard,99);$stale=$writer::write($prep_id,$old_rules['context']['token'],true,$lock);remove_filter('pre_http_request',$guard,99);Editorial_Scout_Runner::unlock($lock);scout_assert(scout_error($stale,'writing_stale')&&0===$no_calls,'Pre-upgrade consent cannot approve materially changed rules');

ob_start();$screen::render($prep_id);$upgrade_html=ob_get_clean();scout_assert(str_contains($upgrade_html,'writing rules changed')&&!str_contains($upgrade_html,'name="consent"'),'Old preview UI requires fresh preparation rather than offering obsolete consent');
$protected=$writer::job($prep_id);$protected['context']['user']=999999;$protected['context']['error_stage']='PRIVATE_STAGE_CANARY';$wpdb->update($writing,array('state'=>'failed','data'=>wp_json_encode($protected['context'])),array('commission_id'=>$prep_id));scout_assert(''===$screen::diagnostic_summary($prep_id),'Another administrator cannot extract the original owner diagnostic');$protected['context']['user']=1;$wpdb->update($writing,array('data'=>wp_json_encode($protected['context'])),array('commission_id'=>$prep_id));scout_assert(str_contains($screen::diagnostic_summary($prep_id),'not_recorded')&&!str_contains($screen::diagnostic_summary($prep_id),'CANARY'),'Unknown historical stage is never echoed or invented');
