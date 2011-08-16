<?php

Class RetreatPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['retreat'] = array('%^/retreat$%i', array($this, '_retreat'), CMD_LAST);
		return $cmds;
	}
	
	function _retreat($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$inchannels = DB::get()->col('SELECT name FROM channels WHERE user_id = :user_id', array('user_id' => $user->id));
		$join = 'office:' . $user->username;
		$joinalias = $this->_room_alias($join);

		foreach($inchannels as $partchan) {
			DB::get()->query('DELETE FROM channels WHERE name = :name AND user_id = :user_id;', array('name' => $partchan, 'user_id' => $user->id));
			if(preg_match('%^search:(?P<criteria>.+)$%i', $partchan, $searchmatches)) {
				DB::get()->query("DELETE FROM options WHERE name = :name AND grouping = 'searches' AND user_id = :user_id;", array('name' => $searchmatches['criteria'], 'user_id' => $user->id));
			}
			else if($partchan != $join) {
				Status::create()
					->data("{$user->username} has retreated to <a href=\"#\" onclick=\"joinRoom('" . addslashes($join) . "');return false;\">{$joinalias}</a> from {$partchan}")
					->type('part')
					->channel($partchan)
					->insert();
			}
		}
		
		DB::get()->query('INSERT INTO channels (name, user_id, last) VALUES (:name, :user_id, NOW());', array('name' => $join, 'user_id' => $user->id));

		if(!($herald = DB::get()->val("SELECT value FROM options WHERE user_id = :user_id AND name = :name AND grouping = :grouping", array('user_id' => $user->id, 'name' => 'Herald', 'grouping' => 'Identity')))) {
			$herald = '{$nickname} has joined {$room}';
		}
		$js = '';
		$cssclass = '';
		$packed = Plugin::call('herald', array('herald' => $herald, 'js' => $js, 'cssclass' => $cssclass));
		extract($packed);
		$herald = str_replace('{$nickname}', $user->nickname, $herald);
		$herald = str_replace('{$room}', $joinalias, $herald);
		$msg = htmlspecialchars($herald);
		Status::create()
			->data($msg)
			->type('join')
			->cssclass($cssclass)
			->channel($join)
			->js($js)
			->insert();

		Immediate::create()
			->laststatus()
			->js('setRoom("'.addslashes($join).'");');

		return true;
	} 

	function _room_alias($name) {
		$pjoin = DB::get()->val("SELECT value FROM options WHERE grouping = 'Rooms' AND name = 'alias' AND user_id = 0 AND room = :join", array('join' => $name));
		return $pjoin ? $pjoin : $name;
	}

}

?>
