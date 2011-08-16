<?php
class SnowstormPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['snow'] = array('%^/(snow)$%i', array($this, '_snow'), CMD_LAST);
		return $cmds;
	}
	
	function _snow($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$status = DB::get()->val('SELECT max(status) FROM presence');
		$sid = $status . '__' . rand(1000, 9999);
		$output = $user->nickname . ' invokes the snow.';
		//$output .= '<div class="slash">/snow</div>';
		$js = <<< SNOWSCRIPT
bareffect(function(){
	$('html').addClass('snowing');
	snowStorm.start();
	snowStorm.resume();
	play('/plugins/snowstorm/snowstorm.mp3');
	window.setTimeout(function(){
		$('html').removeClass('snowing');
		snowStorm.stop();
	}, 60000);
});
SNOWSCRIPT;

		DB::get()->query("INSERT INTO presence (data, user_id, channel, cssclass, js) VALUES (:msg, :user_id, :channel, 'snow', :js)", array('msg' => $output, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		return true;
	}
	
	function header($args)
	{
		echo <<< HEADER
		<script src="/plugins/snowstorm/snowstorm.js" type="text/javascript"></script>
		<link href="/plugins/snowstorm/snowstorm.css" type="text/css" rel="stylesheet">
HEADER;
	}
	
}

?>