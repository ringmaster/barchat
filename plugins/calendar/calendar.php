<?php

class CalendarPlugin extends Plugin {
		
	function commands($cmds){
		$cmds[1]['calendar'] = array('%^/calendar(?:\s+(?P<name>\w+)(?:\s+(?P<url>.+))?)?$%i', array($this, '_calendar'), CMD_LAST);
		return $cmds;
	}
	
	function _calendar($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$name = $params['name'];
		$url = $params['url'];
		
		if($name == '') {
			$cals = DB::get()->results("SELECT * FROM options WHERE user_id = :user_id AND grouping = 'calendar';", array('user_id' => $user->id));
			if(count($cals) == 0) {
				$msg = 'No calendars are currently enabled.';
			}
			else {
				$msg = 'The following calendars are available:<ul>';
				foreach($cals as $cal) {
					$msg .= '<li>' . htmlspecialchars($cal->name) . '</li>';
				}
				$msg .= '</ul>';
			}
			$obj = new StdClass();
			$obj->laststatus = 0;
			$obj->js = "reloadWidgets();addSystem({user_id:{$user->id}, data: '" . addslashes($msg) . "', cssclass: 'ok', username: '{$user->username}', nickname: '{$user->nickname}', status: " . microtime(true) . ", js:''}, '#notices');do_scroll();";
			echo json_encode($obj);
			die();
		}
		else if($url == '') {
			DB::get()->query("DELETE FROM options WHERE user_id = :user_id AND name = :name AND grouping = 'calendar';", array('user_id' => $user->id, 'name' => $name));
			$msg = 'Removed the "' . htmlspecialchars($name) . '" calendar.';
			$obj = new StdClass();
			$obj->laststatus = 0;
			$obj->js = "reloadWidgets();addSystem({user_id:{$user->id}, data: '" . addslashes($msg) . "', cssclass: 'ok', username: '{$user->username}', nickname: '{$user->nickname}', status: " . microtime(true) . ", js:''}, '#notices');do_scroll();";
			echo json_encode($obj);
			die();
		}
		else {
			DB::get()->query("INSERT INTO options (user_id, name, grouping, value) VALUES (:user_id, :name, 'calendar', :value);", array('user_id' => $user->id, 'name' => $name, 'value' => $url));
			$msg = 'Added the "' . htmlspecialchars($name) . '" calendar.';
			$obj = new StdClass();
			$obj->laststatus = 0;
			$obj->js = "reloadWidgets();addSystem({user_id:{$user->id}, data: '" . addslashes($msg) . "', cssclass: 'ok', username: '{$user->username}', nickname: '{$user->nickname}', status: " . microtime(true) . ", js:''}, '#notices');do_scroll();";
			echo json_encode($obj);
			die();
		}
	}
	
	function widget_calendar($initial, $data) {
		$id = 'calendarthing';
		return <<< AJAXLOAD
<div id="{$id}"></div>
<script type="text/javascript">
try{
	$("#{$id}").load("/ajax/widgetcal/{$data->id}");
}finally{}
if(typeof(zz{$id}) != 'undefined') {window.clearInterval(zz{$id});}
zz{$id} = window.setInterval(function(){
try{
	$("#{$id}").load("/ajax/widgetcal/{$data->id}");
}finally{}
}, 300000);
</script>
AJAXLOAD;
	}

	function autocomplete($auto, $cmd){
		$auto[] = "/calendar";
		return $auto;
	}
	
	
	function ajax_widgetcal($path) {
		$id = $path[0][0];
		$user = Auth::user();
		$widgetdata = DB::get()->row("SELECT * FROM options WHERE user_id = :user_id AND grouping = 'widgets' AND id = :id ORDER BY name ASC", array('user_id' => $user->id, 'id' => $id));

		$data = (object) unserialize($widgetdata->value);


		$calendars = DB::get()->results("SELECT * FROM options WHERE user_id = :user_id AND grouping = 'calendar'", array('user_id' => $user->id));

		$date = new DateTime('now', new DateTimeZone('GMT'));
		$date->setTimezone(new DateTimeZone('America/New_York'));
		
		$events= array();
		foreach($calendars as $cal) {
			$this->_get_events_url($events, $cal->value, $date);
		}
		$m = $date->format('n');
		$d = $date->format('j');
		$y = $date->format('Y');

		$output = '<table class="calendar" cellspacing="0" style="width:100%;">
<thead>
<tr><th><a href="#">&laquo;</a></th><th colspan="5">' . date('M Y') . '</th><th><a href="#">&raquo;</a></th></tr>
<tr><th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th> 
<th>Thu</th><th>Fri</th><th>Sat</th></tr>
</thead>
<tbody><tr>';
		
		$calstart = getdate(mktime(0, 0, 0, $m, 1, $y));
		$wday = $calstart['wday'];
		for($z = 1; $z <= $wday; $z++) {
			$output .= '<td class="day_empty">&nbsp;</td>';
		}
		$daysinmonth = getdate(mktime(0, 0, 0, $m + 1, 0, $y));
		$todaytime = mktime(0, 0, 0);
		for($z = 1; $z <= $daysinmonth['mday']; $z++) {
			$daytime = sprintf('%04d-%02d-%02d', $y, $m, $z);
			$day = getdate(strtotime($daytime));
			$classes = array();
			if($daytime == $date->format('Y-m-d')) {
				$classes[] = 'today';
			}
			if(isset($events[$daytime])) {
				$classes[] = 'date_has_event';
			}
			$output .= '<td class="' . implode(' ', $classes) . '"><span>' . $day['mday'] . '</span>';
			if(isset($events[$daytime])) {
				$output .= '<div class="events"><ul>';
				foreach($events[$daytime] as $event) {
					$output .= '<li>' . $event . '</li>';
				}
				$output .= '</ul></div>';
			}
			$output .= '</td>';
			if($day['wday'] == 6) {
				$output .= '</tr><tr>';
			}
		}
		for($z = $daysinmonth['wday'] + 1; $z <= 6; $z++) {
			$output .= '<td class="day_empty">&nbsp;</td>';
		}
		
		
		$output .=  '</tr></tbody></table>';

		echo $output;
	}
	
	function _get_events_url(&$events, $url, $date) {
		$v = new vcalendar();
		$v->setConfig( 'unique_id', 'barchat' );

		$v->setProperty( 'method', 'PUBLISH' );
		$v->setProperty( "x-wr-calname", "Calendar Sample" );
		$v->setProperty( "X-WR-CALDESC", "Calendar Description" );
		$v->setProperty( "X-WR-TIMEZONE", "America/New_York" );

		$v->setConfig( 'url', $url );
		try{
			$v->parse();
		}
		catch(exception $e) {}
		$v->sort();

		$m = $date->format('n');
		$d = $date->format('j');
		$y = $date->format('Y');
		
		$eventArray = $v->selectComponents($y,$m,1,$y,$m,31);
		foreach((array)$eventArray as $yearkey => $yeararray) {
			foreach((array)$yeararray as $monthkey => $montharray) {
				foreach((array)$montharray as $daykey => $dayarray) {
					foreach((array)$dayarray as $eventnumber => $event) {
						//echo "{$y}-{$m}-{$daykey} [{$eventnumber}]: ";
						$time = $event->dtstart['value'];
						$tz = $event->dtstart['params']['TZID'] == '' ? 'America/New_York' : $event->dtstart['params']['TZID'];
						if($time['tz'] == 'Z') {
							$tz = 'GMT';
						}
						if(isset($event->dtstart['params']['VALUE']) && $event->dtstart['params']['VALUE'] == 'DATE') {
							$allday = new DateTime("{$time['year']}-{$time['month']}-{$time['day']}", new DateTimeZone($tz));
							$allday->setTimezone(new DateTimeZone('America/New_York'));
							$d = sprintf('%04d-%02d-%02d', $y, $m, $daykey);
							if(!is_array($events[$d])) {
								$events[$d] = array();
							}
							$alldayint = intval($allday->format('U'));
							while(isset($events[$d][$alldayint])) {$alldayint++;}
							$events[$d][$alldayint] = '<span class="calendartime">All Day</span> ' . trim($event->summary['value']);
							//var_dump(date('r', $allday) . ' = ' . $allday);
							//var_dump($event->summary['value']);
						}
						else {
							if(isset($event->xprop['X-CURRENT-DTSTART'])) {
								$dt = new DateTime($event->xprop['X-CURRENT-DTSTART']['value'], new DateTimeZone($tz));
							}
							else {
								$dt = new DateTime("{$time['year']}-{$time['month']}-{$time['day']} {$time['hour']}:{$time['min']}:{$time['sec']}", new DateTimeZone($tz));
							}
							$dt->setTimezone(new DateTimeZone('America/New_York'));
							if(isset($event->xprop['X-CURRENT-DTEND'])) {
								$dte = new DateTime($event->xprop['X-CURRENT-DTEND']['value'], new DateTimeZone($tz));
							}
							else {
								$timee = $event->dtstart['value'];
								$dte = new DateTime("{$timee['year']}-{$timee['month']}-{$timee['day']} {$timee['hour']}:{$timee['min']}:{$timee['sec']}", new DateTimeZone($tz));
							}
							$dte->setTimezone(new DateTimeZone('America/New_York'));
							if(!is_array($events[$d])) {
								$events[$d] = array();
							}
							$d = sprintf('%04d-%02d-%02d', $y, $m, $daykey);
							$daytime = $dt->format('U');
							while(isset($events[$d][$daytime])) {$daytime++;}
							if($dt->format('g:ia') != $dte->format('g:ia')) {
								$events[$d][$daytime] = '<span class="calendartime">' . $dt->format('g:ia') . ' - ' . $dte->format('g:ia') . '</span> ' . trim($event->summary['value']);
							}
							else {
								$events[$d][$daytime] = '<span class="calendartime">' . $dt->format('g:ia') . '</span> ' . trim($event->summary['value']);
							}
							//var_dump($event->dtstart);
							//var_dump($event->summary['value']);
							//var_dump($dt->format('r'));
							//var_dump($event);
						}
					}
				}
			}
		}
	}
}

?>