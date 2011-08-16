<?php

class AlarmPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['alarm'] = array('%^/(alarm)\s+(?P<event>.+)\s*@\s*(?P<time>[^@]+?)(?:\s+repeat\s+(?:every )?(?P<repeat>daily|weekday|day|hour))?$%i', array($this, '_alarm'), CMD_LAST);
		return $cmds;
	}
	
	function _alarm($params){
		$user = $params['user'];
		$channel = $params['channel'];
		$event = $params['event'];
		$time = $params['time'];
		$repeat = $params['repeat'];

		$adata = DB::get()->assoc("SELECT value FROM options WHERE user_id = :user_id AND name = 'alarmdata' AND grouping = 'Alarms'", array('user_id' => $user->id));
		if($adata == '') {
			$alarms = array();
		}
		else {		
			$alarms = unserialize($adata);
		}
		
		$time = strtotime($time);
		$output = '<div class="slash">' . htmlspecialchars($params['matches'][0]) . '</div>';
		if($time == 0) {
			$output .= 'Specified alarm time is <em>invalid</em>.';
		}
		else {
		
			$newalarm = array(
				'event' => $event,
				'time' => $time - intval((string)Option::get('Time', 'Zone Offset')) * 3600,
				'repeat' => $repeat,
			);
			$alarms[] = $newalarm;
			
			DB::get()->query("DELETE FROM options WHERE user_id = :user_id AND name = 'alarmdata' AND grouping = 'Alarms'", array('user_id' => $user->id));
			DB::get()->query("INSERT INTO options (grouping, name, user_id, value) VALUES('Alarms', 'alarmdata', :user_id, :value)", array('user_id' => $user->id, 'value' => serialize($alarms)));
					
			$output .= 'Alarm added for ' . date('M j, Y h:ia', $time) . '.';
		}
		
		DB::get()->query("INSERT INTO presence (data, user_id, type, cssclass, user_to, channel) VALUES (:msg, :user_id, 'system', 'ok', :user_to, '')", array('msg' => $output, 'user_id' => 0, 'user_to' => $user->id));
		return true;
	}
	
	function header()
	{
		echo <<< HEADER
		<link rel="stylesheet" type="text/css" href="/plugins/alarm/alarm.css">
HEADER;
	}
	
	function poll($laststatus, $statuscode, $user){
		static $alarms = null;
		
		if(!isset($alarms) || ($laststatus != $statuscode)) {
			$adata = DB::get()->val("SELECT value FROM options WHERE user_id = :user_id AND name = 'alarmdata' AND grouping = 'Alarms'", array('user_id' => $user->id));
			$alarms = unserialize($adata);
		}
		$changed = false;
		if(is_array($alarms)) {
			foreach($alarms as $key => $alarm) {
				if($alarm['time']<time()) {
					$output = 'ALARM: ' . $alarm['event'];
					$js = 'bareffect(function(){play("/plugins/alarm/alarm.mp3", true)});';
					unset($alarms[$key]);
					DB::get()->query("INSERT INTO presence (data, user_id, type, cssclass, user_to, channel, js) VALUES (:msg, :user_id, 'system', 'alarm', :user_to, '', :js)", array('msg' => $output, 'user_id' => 0, 'user_to' => $user->id, 'js' => $js));
					//DB::get()->query("INSERT INTO presence (data, user_id, channel, cssclass) VALUES (:msg, :user_id, :channel, 'alarm')", array('msg' => $output, 'user_id' => $user->id, 'channel' => $channel));
					$changed = true;
				}
			}
			if($changed) {
				DB::get()->query("DELETE FROM options WHERE user_id = :user_id AND name = 'alarmdata' AND grouping = 'Alarms'", array('user_id' => $user->id));
				DB::get()->query("INSERT INTO options (grouping, name, user_id, value) VALUES('Alarms', 'alarmdata', :user_id, :value)", array('user_id' => $user->id, 'value' => serialize($alarms)));
			}
		}
		
		return $laststatus;
	}

	
	
	function autocomplete($auto, $cmd){
		$auto[] = "/alarm \tevent name";
		if(preg_match('%/alarm\s+[^@]+$%i', $cmd)) {
			$auto[] = $cmd . " @\t time and/or date";
		}
		return $auto;
	}
	
}
?>