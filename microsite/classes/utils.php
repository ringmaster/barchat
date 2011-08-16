<?php

class Utils
{
	function time_between($start, $end = NULL, $after=' ago')
	{
		//both times must be in seconds
		if($end == null) {
			$end = time();
		}
		$time = $end - $start;
		if($time <= 60){
			return $time . ' seconds'.$after;
		}
		if(60 < $time && $time <= 3600){
			return round($time/60,0).' minutes'.$after;
		}
		if(3600 < $time && $time <= 86400){
			return round($time/3600,0).' hours'.$after;
		}
		if(86400 < $time && $time <= 604800){
			return round($time/86400,0).' days'.$after;
		}
		if(604800 < $time && $time <= 2592000){
			return round($time/604800,0).' weeks'.$after;
		}
		if(2592000 < $time && $time <= 29030400){
			return round($time/2592000,0).' months'.$after;
		}
		if($time > 29030400){
			return 'More than a year'.$after;
		}
	}
	
	function cmdout($params) {
		$out = '';
		preg_match_all('/\(\?P<(.+?)>/', $params['cmd'][0], $matches);
		$msg = htmlspecialchars($params['msg']);
		foreach($matches[1] as $argname) {
			$msg = str_replace($params[$argname], '<em>' . $params[$argname] . '</em>', $msg);
		}
		$out .= $msg;
		if($params['omsg'] != $params['msg']) {
			$out = htmlspecialchars($params['omsg']) . ' &nbsp; <small style="color: #aaa">' . $out . '</small>';
		}
		$out = '<div class="slash">' . $out . '</div>';
		return $out;
	}
	
	function user_from_name(&$m){
		static $userlist = false;

		if(!$userlist) {
			$userlist = DB::get()->results("SELECT users.*, options.value as nickname FROM users LEFT JOIN options ON options.user_id = users.id AND name = 'Nickname' AND grouping = 'Identity' ORDER BY LENGTH(username) DESC");
		}
		$m = trim($m);
		foreach($userlist as $user) {
			if(strlen($user->username) > strlen($user->nickname)) {
				$us = array($user->username, $user->nickname);
			}
			else {
				$us = array($user->nickname, $user->username);
			}
			foreach($us as $u) {
				if(!$u) continue;
				if(stripos($m, $u) === 0) {
					$m = trim(substr($m, strlen($u)));
					return $user;
				}
			}
		}
		return false;
	}
}

?>