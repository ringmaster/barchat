<?php
class MeatwadPlugin extends Plugin 
{
	function commands($cmds) 
	{
		$cmds[1]['meatwad'] = array('%^/(meatwad)\b%i', array($this, '_meatwad'), CMD_LAST);
		return $cmds;
	}
	
	function _meatwad($params) 
	{
		$user = $params['user'];
		$channel = $params['channel'];
		$output = $user->nickname . ' calls the almighty meatwad!';
		$js = <<< MEATSCRIPT
bareffect(function(){
var t = $('<div class="meat"></div>');
$('#mainscroller').append(t);
$('.meat').css('display', 'block');
window.setTimeout(function(){
		$('.meat').css('display','none');
		$('.meat').remove();
	}, 10000);
});
MEATSCRIPT;

		DB::get()->query("INSERT INTO presence (data, user_id, channel, cssclass, js) VALUES (:msg, :user_id, :channel, 'meatwad', :js)", array('msg' => $output, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		return true;
	}
	function header($args)
	{
		echo <<< HEADER
		<link href="/plugins/meatwad/meat.css" type="text/css" rel="stylesheet">
HEADER;
	}
}
?>