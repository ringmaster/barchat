<?php

Class ChakakhanPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['chakakhan'] = array('%^/(chakakhan)\b%i', array($this, '_chakakhan'), CMD_LAST);
		return $cmds;
	}
	
	function _chakakhan($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$rmsg = $params['chakakhan'];
		if(trim($rmsg) == '') {
			$rmsg = '';
		}
		$rmsg = htmlspecialchars($rmsg);
		$rmsg = '<button onclick="play(\'/plugins/chakakhan/chakakhan.mp3\');">Play</button>' . $rmsg;
		$js = 'bareffect(function(){play("/plugins/chakakhan/chakakhan.mp3");});';

		DB::get()->query("INSERT INTO presence (data, user_id, channel, cssclass, js) VALUES (:msg, :user_id, :channel, 'chakakhan', :js)", array('msg' => $rmsg, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		return true;
	}
	
	function header($args)
	{
		echo <<< HEADER
<link href="/plugins/chakakhan/chakakhan.css" type="text/css" rel="stylesheet">
HEADER;
	}

}
?>