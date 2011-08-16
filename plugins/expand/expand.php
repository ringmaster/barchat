<?php

class ExpandPlugin extends Plugin
{
	function commands($cmds){
		$cmds[1]['expand'] = array('%^/expand\s+(?P<from>.+?)\s*=&gt;\s*(?P<to>.*)$%i', array($this, '_expand'), CMD_LAST);
		$cmds[1]['collapse'] = array('%^/expand\s+(?P<from>.+)$%i', array($this, '_collapse'), CMD_LAST);
		return $cmds;
	}
	
	function _expand($params) {
		$from = $params['from'];
		$to = $params['to'];
		$user = $params['user'];
		
		DB::get()->query("DELETE FROM options WHERE grouping = 'Autocomplete' AND name = :from;", array('from' => $from));
		if($to != '') {
			DB::get()->query("INSERT INTO options (grouping, name, value) VALUES ('Autocomplete', :from, :to);", array('from' => $from, 'to' => $to));
			DB::get()->query(
			"INSERT INTO presence (data, user_id, type, cssclass, user_to, channel) VALUES (:msg, :user_id, 'system', 'ok', :user_to, '')", 
			array(
				'msg' => 'Set "' . htmlspecialchars($from) . '" to expand to "' . htmlspecialchars($to) . '".',
				'user_id' => 0,
				'user_to' => $user->id,
			)
			);
		}
		else {
			DB::get()->query(
			"INSERT INTO presence (data, user_id, type, cssclass, user_to, channel) VALUES (:msg, :user_id, 'system', 'ok', :user_to, '')", 
			array(
				'msg' => 'Unset expansion for "' . htmlspecialchars($from) . '".',
				'user_id' => 0,
				'user_to' => $user->id,
			)
			);
		}
		
		return true;
		
	}
	
	function autocomplete($auto, $cmd){
		$auto[] = "/expand \tshort => long";
		
		$expands = DB::get()->results("SELECT * FROM options WHERE grouping = 'Autocomplete';");
		foreach($expands as $expand) {
			$find = '%\b(' . preg_quote($expand->name, '%') . ')$%i';
			if(preg_match($find, $cmd)) {
				$auto[] = '*' . preg_replace($find, $expand->value, $cmd);
			}
		}
		
		return $auto;
	}
}

?>