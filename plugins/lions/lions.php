<?php

Class LionsPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['lions'] = array('%^/(lions)\b%i', array($this, '_lions'), CMD_LAST);
		return $cmds;
	}
	
	function _lions($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$rmsg = $params['lions'];
		if(trim($rmsg) == '') {
			$rmsg = '';
		}
		$rmsg = htmlspecialchars($rmsg);
		$rmsg = '<button onclick="play(\'/plugins/lions/lions.mp3\');">Play</button>' . $rmsg;
		$js = 'bareffect(function(){play("/plugins/lions/lions.mp3");});';

		Status::create()
			->data($rmsg)
			->cssclass('lions')
			->channel($channel)
			->js($js)
			->insert();

		return true;
	}
	
	function header($args)
	{
		echo <<< HEADER
<link href="/plugins/lions/lions.css" type="text/css" rel="stylesheet">
HEADER;
	}

}
?>