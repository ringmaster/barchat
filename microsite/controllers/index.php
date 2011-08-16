<?php

class IndexController
{
	function __construct($path)
	{
		$chanbar = '				<ul>
						<li id="settings" class="option"><a href="#" class="button">settings</a></li>
						<li id="files" class="option"><a href="#" class="button">files</a></li>
						<li id="people" class="option"><a href="#" class="button">people</a></li>
						</ul>
						';
		
		$user = Auth::user();
		$curchan = DB::get()->val('SELECT name from channels where user_id = :user_id AND active = 1', array('user_id' => $user->id));
		if($curchan == '') {
			$curchan = 'bar';
		}
		
		$widgets = Widgets::get_widgets();
		
		$components = array(
			'title' => 'Barchat Home',
			'path' => $path,
			'chanbar' => $chanbar,
			'user_id' => Auth::user_id(),
			'username' => $user->username,
			'nickname' => $user->nickname,
			'session_key' => $user->session_key,
			'cur_chan' => addslashes($curchan),
			'widgets' => $widgets,
		);
		$v = new View($components);
		
		Plugin::call('reload', $user);
		
		
		//check for user agent
		$useragent = $_SERVER['HTTP_USER_AGENT'];
		//
		if(preg_match('/ip(hone|od|ad)/i',$useragent)) {
			$v->render('template-ios');
		} else {
			$v->render('template');
		}
	}

}
?>