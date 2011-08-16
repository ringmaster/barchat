<?php

class BrainJar extends Plugin
{

	function commands($cmds){
		$cmds[1]['jar'] = array('%^/(jar)\s+(?P<name>.+)$%i', array($this, '_jar'), CMD_LAST);
		$cmds[1]['jar2'] = array('%^/(jar)\s*$%i', array($this, '_jar'), CMD_LAST);
		return $cmds;
	}
	
	function _jar($params) {
		$jar = $params['jar'];
		$criteria = $params['criteria'];
		$user = $params['user'];
		$channel = $params['channel'];

		$msg = Utils::cmdout($params);
		
		$msg .= "send this to the jar : ".$criteria;
			
		Status::create()
			->data($msg)
			->user_id($user->id)
			->cssclass('brainjar')
			->channel($channel)
			->insert();
		
		return true;

	}
}
?>