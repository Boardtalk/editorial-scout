<?php
/** Fake semantic response for isolated CLI/browser tests, never an external request. */
function scout_semantic_fixture($payload) {
 $id=$payload['posts'][0]['id'];
 $result=array();
 foreach(array('name'=>'Garden and kitchen journal','topics'=>'vegetable growing, fruit trees, flowers, garden equipment, cooking, food preservation','audience'=>'','excluded'=>'','geography'=>'','tone'=>'Practical and explanatory','instructions'=>'Require human review','sections'=>'Gardening, cooking and machinery')as$field=>$text){
  $result[$field]=array('text'=>$text,'evidence'=>$text?array($id):array(),'uncertainty'=>'Small public sample; verify applicability.');
 }
 $result['categories']=array_slice(array_column($payload['categories'],'id'),0,1);
 return $result;
}
function scout_semantic_response($payload,$test=false){
 return array('response'=>array('code'=>200),'headers'=>array('content-type'=>'application/json'),'body'=>wp_json_encode(array('status'=>'completed','usage'=>array('total_tokens'=>700),'output'=>array(array('type'=>'message','content'=>array(array('type'=>'output_text','text'=>$test?'OK':wp_json_encode(scout_semantic_fixture($payload)))))))));
}
