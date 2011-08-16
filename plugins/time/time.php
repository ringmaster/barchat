<?php

class TimePlugin extends Plugin
{

	/**
	 * These are connector function.
	 *
	 * They connect this plugin to the datasource for projects and time tracking.
	 */
	
	function _get_projects() {
		static $projects = null;
		if(is_null($projects)) {
			$projects = $this->pdb()->assoc("SELECT pp.nid, pp.uri FROM project_projects pp, term_node tn, term_data td where tn.nid = pp.nid and tn.tid = td.tid and td.vid = 4 and td.name = 'Open';");
		}
		return $projects;
	}
	
	function _get_user() {
		static $uid = null;
		if(is_null($uid)) {
			$uid = Option::get('identity', 'time tracking uid');
		}
		return $uid;
	}

	function _add_time($timerec) {
		$projects = $this->_get_projects();
		
		$this->pdb()->query("
			INSERT INTO project_issue_time (`date`, created, changed, pid, iid, cid, uid, hours, bill_status, in_progress, subject, notes) 
			VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?, 0, ?, ?);
		", array(
			date('Y-m-d', strtotime($timerec['ondate'])), 
			time(), 
			time(), 
			array_search($timerec['pcode'], $projects),
			$this->_get_user(), 
			$timerec['time'], 
			$timerec['billable']?0:2,
			$timerec['task'], 
			$timerec['notes'] . (trim($timerec['notes']) != '' ? "\n" : '') . "From barchat",
		));
	}
	
	function _get_time() {
		$time = $this->pdb()->assoc('SELECT pid, sum(hours) FROM project_issue_time WHERE uid=:uid AND `date` = :ondate group by pid', 
			array(
				'uid' => $this->_get_user(),
				'ondate' => date('Y-m-d'),
			)
		);
		return $time;
	}
	
	
	/****************************************************************************/
	
	
	function commands($cmds){
		$cmds[1]['timecard'] = array('%^(?:/timecard|@\?)$%ix', array($this, '_timecard'), CMD_LAST);
		$cmds[1]['timecard_action'] = array('%^(?:/timecard|@\?)\s*(?P<action>.+)$%ix', array($this, '_timecard_action'), CMD_LAST);

		$cmds[1]['timetoggle'] = array('%^@(?P<pcode>[^@]\S+)?$%ix', array($this, '_timetoggle'), CMD_LAST);
		$cmds[1]['time'] = array('%^@\s*
(?:(?P<time>(\d*:\d{2})|(\d*(?:\.\d{1,2})?))\s+)?
(?P<pcode>[^@]\S+)
\s+(?P<task>.+?)
(?:\s+~(?P<approx>(\d*:\d{2})|(\d*(?:\.\d{1,2})?)))?
(?:\n(?P<notes>.+))?
$%six', array($this, '_time'), CMD_LAST);
		$cmds[1]['instanttime'] = array('/^@@\s*
(?:(?P<time>(\d*:\d{1,2})|(\d*(?:\.\d{1,2})?))\s+)
(?P<pcode>[^@]\S+)
\s+(?P<task>.+?)
(?:\s+~(?P<approx>(\d*:\d{2})|(\d*(?:\.\d{1,2})?)))?
(?:\n(?P<notes>.+))?
$/six', array($this, '_instant_time'), CMD_LAST);
		return $cmds;
	}
	
	
	function pdb() {
		static $db = false;
		if(!$db) {
			$db = new DB('mysql:host=localhost;dbname=projects_live', 'root', '', 'projects');
		}
		return $db;
	}
	
	function _get_timecard($date) {
		$user = Auth::user();
		$times = DB::get()->results("SELECT * FROM timecard WHERE user_id = :user_id AND ondate = :ondate ORDER BY start ASC, id ASC", array('user_id' => $user->id, 'ondate' => $date));
		
		foreach($times as $time) {
			$timecard .= date('H:i', strtotime($time->start)) . " {$time->purl} {$time->task}\n";
			if($time->notes != '') {
				$notes = $time->notes;
				$notes = preg_replace('%^\t+%ism', '', $notes);
				$notes = preg_replace('%^%ism', "\t", $notes);
				$timecard .= "{$notes}\n";
			}
		}
		
		$msg = '<textarea style="height: 200px;width: 95%;" onkeypress="$(\'#timecard_data\').addClass(\'changed\');$(\'#process_date,#timecard_process\').attr(\'disabled\',\'disabled\');">' . $timecard . '</textarea>';

		return $msg;
	}
	
	function _timecard($params) {
		$user = $params['user'];

		$drawervisible = DB::get()->val("SELECT id FROM drawers WHERE indexed = 'timecard' AND user_id = :user_id", array('user_id' => $user->id));
		if($drawervisible) {
			Immediate::create()->js('closedrawer('. $drawervisible . ');');
			return true;
		}

		$msg = '<div id="timecard_data">' . $this->_get_timecard(date('Y-m-d')) . '</div>';
		
		$dates = DB::get()->col("SELECT ondate FROM timecard WHERE user_id = :user_id group by ondate", array('user_id' => $user->id));
		if(count($dates) > 1) {
			$select = '<select id="process_date" name="process_date" onchange="$(\'#timecard_data\').load(\'/ajax/get_timecard\', {date:$(this).val()});">';
			$wasselected = false;
			foreach($dates as $date) {
				$selected = '';
				if($date == date('Y-m-d')) {
					$selected = ' selected="selected"';
					$wasselected = true;
				}
				$select .= '<option' . $selected . '>' . $date . '</option>';
			}
			if(!$wasselected) {
				$select .= '<option selected="selected">' . date('Y-m-d') . '</option>';
			}
			$select .= '</select>';
		}
		else {
			$select = '<input type="hidden" name="process_date" id="process_date" value="' . date('Y-m-d') . '">';
		}
		
		$msg .= '<div class="timecard_tools">
			<button id="timecard_save" onclick="$(\'#timecard_save,#timecard_process\').attr(\'disabled\',\'disabled\');$(\'#timecard_data\').load(\'/ajax/save_timecard\',{timecard:$(\'#timecard_data textarea\').val(), date:$(\'#process_date\').val()});return false;">Save changes</button>
			' . $select . '
			<button id="timecard_process" onclick="$(\'#timecard_save,#timecard_process\').attr(\'disabled\',\'disabled\');$(\'#timecard_data\').load(\'/ajax/process_timecard\',{timecard:$(\'#timecard_data textarea\').val(), date:$(\'#process_date\').val()});return false;">Process timecard</button>
			<button id="timecard_close" onclick="return closedrawer({$drawer_id});">Close timecard</button>
			</div>';
		
		DB::get()->query("DELETE FROM drawers WHERE indexed = 'timecard' AND user_id = :user_id", array('user_id' => $user->id));
		DB::get()->query("INSERT INTO drawers (user_id, message, indexed, cssclass) VALUES (:user_id, :msg, 'timecard', 'timecard');", array('user_id' => $user->id, 'msg' => $msg));

		Immediate::create()->js('refreshDrawers();');
		
		return true;
	}

	function _timecard_action($params) {
		$user = $params['user'];
		list($action,) = explode(' ', $params['action']);

		$drawervisible = DB::get()->val("SELECT id FROM drawers WHERE indexed = 'timecard' AND user_id = :user_id", array('user_id' => $user->id));
		if(!$drawervisible) {
			DB::get()->query("INSERT INTO drawers (user_id, message, indexed, cssclass) VALUES (:user_id, :msg, 'timecard', 'timecard');", array('user_id' => $user->id, 'msg' => '<div>Processing...</div>'));
		}
		
		switch($action) {
			case 'process':
				Immediate::create()->js('refreshDrawers(function(){$(\'#timecard_save,#timecard_process\').remove();$(\'#timecard_data\').load(\'/ajax/process_timecard\');return false;});');
				break;
			case 'save':
				Immediate::create()->js('refreshDrawers(function(){$(\'#timecard_save,#timecard_process\').remove();$(\'#timecard_data\').load(\'/ajax/save_timecard\',{timecard:$(\'#timecard_data textarea\').val()});return false;});');
				break;
			case 'alias':
				list($action,$from,$to) = explode(' ', $params['action']);
				if($from == '' || $to == '') {
					Immediate::error('The syntax of this command is: @?alias {project code alias} {project code}');
				}
				else {
					DB::get()->query("DELETE FROM options WHERE user_id = :user_id and grouping = 'time alias' and name = :name", array('user_id'=>$user->id, 'name'=> $from));
					DB::get()->query("INSERT INTO options (user_id, grouping, name, value) VALUES (:user_id, 'time alias', :name, :value)", array('user_id'=>$user->id, 'name'=> $from, 'value'=>$to));
					Immediate::ok('Set alias "' . htmlspecialchars($from) . '" to point to "' . htmlspecialchars($to) . '".');
				}
		}
		
		return true;
	}
	
	function _parse_times($body){
		preg_match_all('/^(?P<time>\d{1,2}:\d{2})
			(?:\s+
			  (?P<project>[\w\-]+)
			)
			(?:[[:blank:]]
			  (?P<task>[^\n]+)
			  (?:\n(?P<notes>(?:(?!\d{1,2}:\d{2}).+[\n\r]*)*))?
			)?/imx', $body, $matches, PREG_SET_ORDER);
		return $matches;
	}
	

	function _parse_hours($times){
		$projects = $this->_get_projects();
		
		for($z = 0; $z < count($times); $z++) {
			$begin_time = $times[$z]['time'];
			$end_time = $times[$z+1]['time'];
			$project = $times[$z]['project'];
			$task = $times[$z]['task'];
			$notes = trim($times[$z]['notes']);
			$hourstime = $this->_calc_time($begin_time, $end_time);
	
			$billable = !(preg_match('%-\$%', $task) || preg_match('%-\$%', $notes));
			
			$time = array(
				'time' => $hourstime,
				'pcode' => $project,
				'task' => $task,
				'notes' => $notes,
				'begin' => $begin_time,
				'end' => $end_time,
				'billable' => $billable,
				'real' => in_array($project, $projects),
			);
			if($z < count($times) - 1) {
				$time['time'] = $hourstime;
				$time['minutes'] = round(floatval($hourstime) * 60);
			}
			else {
				$time['time'] = 0;
				$time['minutes'] = 0;
			}
			$hours[] = $time;
		}
	
		return $hours;
	}	
		
	function _calc_time($begin, $end){
		list($b_hour, $b_minute) = explode(':', $begin);
		list($e_hour, $e_minute) = explode(':', $end);
		$b_hour = intval($b_hour);
		$e_hour = intval($e_hour);
		$b_minute = intval($b_minute);
		$e_minute = intval($e_minute);
	
		if($e_hour < $b_hour) {
			$e_hour += 12;
		}
		$b_minute += $b_hour * 60;
		$e_minute += $e_hour * 60;
	
		return round(100 * ($e_minute - $b_minute) / 60) / 100;
	}	
	
	function _timetoggle($params) {
		$user = $params['user'];
		$pcode = $this->_project_alias($params['pcode']);

		$drawervisible = DB::get()->val("SELECT id FROM drawers WHERE indexed = 'timecard' AND user_id = :user_id", array('user_id' => $user->id));
		if($drawervisible) {
			Immediate::error('You must <a href="#" onclick="send(\'@?\');return false;">close the timecard drawer</a> before toggling time tracking for the current task.');
			return true;
		}
		
		$lastproject = DB::get()->row("SELECT * FROM timecard WHERE user_id = :user_id AND ondate = curdate() AND start < NOW() ORDER BY start DESC, id DESC LIMIT 1", array('user_id' => $user->id));
		$projects = $this->pdb()->col('SELECT uri FROM project_projects');

		if(in_array($lastproject->purl, $projects) && $pcode == '') {
			DB::get()->query("INSERT INTO timecard (user_id, purl, task, ondate, start, finished, recorded) VALUES (:user_id, :purl, :task, now(), :start, 0, 0)",
				array(
					'user_id' => $user->id,
					'purl' => 'break',
					'task' => 'stopping work',
					'start' => date('H:i'),
				)
			);
			Immediate::ok('Pausing: <span class="project">' . $lastproject->purl . '</span> <span class="task">' . $lastproject->task . '</span>', 'ok time time_stop');
			$this->_set_status('', $user);
		}
		else {
			$recentprojects = DB::get()->results("SELECT * FROM timecard WHERE user_id = :user_id AND ondate = curdate() AND start < NOW() ORDER BY start DESC, id DESC", array('user_id' => $user->id));
			foreach($recentprojects as $recentproject) {
				if((in_array($recentproject->purl, $projects) && $pcode == '') || ($recentproject->purl == $pcode)) {
					if($pcode == '') {
						$notes = 'Restarting from ' . $endtask->task . ' at ' . date('H:i', strtotime($endtask->start));
					}
					else {
						$notes = 'Restarting from task at ' . date('H:i', strtotime($recentproject->start));
					}
					DB::get()->query("INSERT INTO timecard (user_id, purl, task, ondate, start, finished, recorded, notes) VALUES (:user_id, :purl, :task, now(), :start, 0, 0, :notes)",
						array(
							'user_id' => $user->id,
							'purl' => $recentproject->purl,
							'task' => $recentproject->task,
							'notes' => $notes,
							'start' => date('H:i'),
						)
					);
					Immediate::ok('Resuming: <span class="project">' . $recentproject->purl . '</span> <span class="task">' . $recentproject->task . '</span>', 'ok time time_play');
					$this->_set_status('<span class="project">' . htmlspecialchars($recentproject->purl) . '</span> <span class="task">' . htmlspecialchars($recentproject->task) . '</span>', $user);
					return true;
				}
				else {
					$endtask = $recentproject;
				}
			}

			DB::get()->query("INSERT INTO timecard (user_id, purl, task, ondate, start, finished, recorded, notes) VALUES (:user_id, :purl, :task, now(), :start, 0, 0, :notes)",
				array(
					'user_id' => $user->id,
					'purl' => $pcode,
					'task' => '--',
					'notes' => '',
					'start' => date('H:i'),
				)
			);
			Immediate::ok('Starting: <span class="project">' . $pcode . '</span>', 'ok time time_play');
		}
		
		return true;
	}
	
	function _set_status($status, $user) {
		DB::get()->query("DELETE FROM options WHERE user_id = :user_id AND name = 'Status' AND grouping = 'Identity'", array('user_id' => $user->id));
		if($status != '') {
			DB::get()->query("INSERT INTO options (user_id, name, grouping, value) VALUES (:user_id, 'Status', 'Identity', :status)", array('user_id' => $user->id, 'status' => $status));
		}

		return true;
	}
	
	function _time($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$task = $params['task'];
		$pcode =  $this->_project_alias($params['pcode']);
		$time = $params['time'];
		$approx = $params['approx'];
		$notes = $params['notes'];
		
		$drawervisible = DB::get()->val("SELECT id FROM drawers WHERE indexed = 'timecard' AND user_id = :user_id", array('user_id' => $user->id));
		if($drawervisible) {
			Immediate::error('You must <a href="#" onclick="send(\'@?\');return false;">close the timecard drawer</a> before entering new time entries.');
			return true;
		}
		
		$return = true;
		
		extract(Plugin::call('task_filter', array('task' => $task, 'notes' => $notes)));
		
		if(strpos($time, ':') === 0) {
			$hours = floatval(substr($time,1)) / 60;
			$time = date('H:i', time() - $hours * 3600);
		}
		elseif(strpos($time, ':') > 0) {
			// record actual time
			if(intval($time) < 13) { // If not using 24-hour time
				$lasttime = DB::get()->val("SELECT start FROM timecard WHERE user_id = :user_id AND ondate = curdate() ORDER BY start DESC, id DESC", array('user_id' => $user->id));
				if(intval($lasttime) > intval($time)) {
					$timeparts = split(':', $time);
					$time = (intval($timeparts[0])+12) . ':' . $timeparts[1];
				}
			}
		}
		elseif(trim($time) == '') {
			$time = date('H:i');
			$this->_set_status('<span class="project">' . htmlspecialchars($pcode) . '</span> <span class="task">' . htmlspecialchars($task) . '</span>', $user);
		}
		else {
			$hours = floatval($time);
			$time = date('H:i', time() - $hours * 3600);
		}

		if(strpos($approx, ':') === 0) {
			$est = floatval(substr($approx,1)) / 60;
		}
		elseif(strpos($approx, ':') > 0) {
			$est = (strtotime($approx) - strtotime($time)) / 3600;
		}
		elseif(trim($approx) == '') {
			$est = null;
		}
		else {
			$est = floatval($approx);
		}

		$msg = "<span class=\"time\">{$time}</span> <span class=\"project\">{$pcode}</span> <span class=\"task\">{$task}</span>";
		
		DB::get()->query("INSERT INTO timecard (user_id, purl, task, ondate, start, finished, recorded, estimated, notes) VALUES (:user_id, :purl, :task, now(), :start, 0, 0, :estimated, :notes)",
			array(
				'user_id' => $user->id,
				'purl' => $pcode,
				'task' => $task,
				'start' => $time,
				'estimated' => $est,
				'notes' => $notes,
			)
		);

		Immediate::ok($msg, 'ok time time_add');
				
		return $return;
	}

	function _instant_time($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$task = $params['task'];
		$pcode =  $this->_project_alias($params['pcode']);
		$time = $params['time'];
		$approx = $params['approx'];
		$notes = $params['notes'];
		
		$return = true;
		
		if(strpos($time, ':') === 0) {
			$hours = floatval(substr($time,1)) / 60;
		}
		elseif(strpos($time, ':') > 0) {
			list($hours, $minutes) = split(':', $time);
			$hours = intval($hours) + $minutes / 60;
		}
		else {
			$hours = floatval($time);
		}

		if(strpos($approx, ':') === 0) {
			$est = floatval(substr($approx,1)) / 60;
		}
		elseif(strpos($approx, ':') > 0) {
			$est = (strtotime($approx) - strtotime($time)) / 3600;
		}
		elseif(trim($approx) == '') {
			$est = null;
		}
		else {
			$est = floatval($approx);
		}
		
		extract(Plugin::call('task_filter', array('task' => $task, 'notes' => $notes)));

		$timerec = array(
			'time' => $hours,
			'ondate' => date('Y-m-d'),
			'pcode' => $pcode,
			'task' => $task,
			'notes' => $notes,
			'billable' => !(preg_match('%-\$%', $task) || preg_match('%-\$%', $notes)),
		);
		$projects = $this->_get_projects();
		if(in_array($timerec['pcode'], $projects)) {
			$this->_add_time($timerec);
			$msg = "<span class=\"time\">Added {$hours} hours</span> <span class=\"project\">{$pcode}</span> <span class=\"task\">{$task}</span>";
			Immediate::ok($msg, 'ok time time_instant');
		}
		else {
			Immediate::error('Specified project "'. $timerec['pcode'].'" does not exist.');
		}
				
		return $return;
	}
	
	function autocomplete($auto, $cmd){
		// Get the list of projects by most logged hours
		// select p.uri from project_projects p left join (select t.pid, sum(t.hours) as hours from project_issue_time t where t.uid = 13 and created > unix_timestamp(date_sub(now(), interval 21 month)) group by t.pid) t2 on t2.pid = p.nid order by t2.hours desc, p.uri asc;
		if(preg_match('/^@(?P<time>\s*(\d*:\d{2})|\s*(\d*(?:\.\d{1,2})?))/i', $cmd, $timematch)) {
			$projects = $this->_get_projects();
			$aliases = DB::get()->col("SELECT name FROM options where user_id = :user_id and grouping = 'time alias'", array('user_id'=>Auth::user_id()));
			$projects = array_merge($projects, $aliases);
			if(strpos($timematch['time'], ':') === 0) {
				$task = 'task for ' . substr($timematch['time'], 1) . ' minutes';
			}
			elseif(strpos($timematch['time'], ':') > 0) {
				$task = 'task started at ' . $timematch['time'];
			}
			else {
				$task = 'task for';
				if(floor($timematch['time']) > 0) { 
					$task .= ' ' . floor($timematch['time']) . ' hours';
				}
				if(round(60*($timematch['time'] - floor($timematch['time']))) > 0) { 
					$task .= ' ' . round(60*($timematch['time'] - floor($timematch['time']))) . ' minutes';
				}
			}
			foreach($projects as $project) {
				$auto[] = '@' . $timematch['time'] . ' ' . $project . " \t{$task}";
			}
		}
		if(preg_match('/^@@(?P<time>\s*(\d*:\d{1,2})|\s*(\d+(?:\.\d{1,2})?))/i', $cmd, $timematch)) {
			$projects = $this->_get_projects();
			$aliases = DB::get()->col("SELECT name FROM options where user_id = :user_id and grouping = 'time alias'", array('user_id'=>Auth::user_id()));
			$projects = array_merge($projects, $aliases);
			if(strpos($timematch['time'], ':') === 0) {
				$task = 'instant task for ' . substr($timematch['time'], 1) . ' minutes';
			}
			elseif(strpos($timematch['time'], ':') > 0) {
				list($hours, $minutes) = split(':', $timematch['time']);
				$task = "instant task for {$hours} hours {$minutes} minutes";
			}
			else {
				$task = 'instant task for';
				if(floor($timematch['time']) > 0) { 
					$task .= ' ' . floor($timematch['time']) . ' hours';
				}
				if(round(60*($timematch['time'] - floor($timematch['time']))) > 0) { 
					$task .= ' ' . round(60*($timematch['time'] - floor($timematch['time']))) . ' minutes';
				}
			}
			foreach($projects as $project) {
				$auto[] = '@@' . $timematch['time'] . ' ' . $project . " \t{$task}";
			}
		}
		else if($cmd[0] == '@') {
			$projects = $this->_get_projects();
			$aliases = DB::get()->col("SELECT name FROM options where user_id = :user_id and grouping = 'time alias'", array('user_id'=>Auth::user_id()));
			$projects = array_merge($projects, $aliases);
			foreach($projects as $project) {
				$auto[] = '@' . $project . " \ttask begins now";
			}
		}
		return $auto;
	}
	
	function _hsv_rgb ($H, $S, $V)  // HSV Values:Number 0-1 
	{                                 // RGB Results:Number 0-255 
		$RGB = array(); 

		if($S == 0) 
		{ 
			$R = $G = $B = $V * 255; 
		} 
		else 
		{ 
			$var_H = $H * 6; 
			$var_i = floor( $var_H ); 
			$var_1 = $V * ( 1 - $S ); 
			$var_2 = $V * ( 1 - $S * ( $var_H - $var_i ) ); 
			$var_3 = $V * ( 1 - $S * (1 - ( $var_H - $var_i ) ) ); 

			if       ($var_i == 0) { $var_R = $V     ; $var_G = $var_3  ; $var_B = $var_1 ; } 
			else if  ($var_i == 1) { $var_R = $var_2 ; $var_G = $V      ; $var_B = $var_1 ; } 
			else if  ($var_i == 2) { $var_R = $var_1 ; $var_G = $V      ; $var_B = $var_3 ; } 
			else if  ($var_i == 3) { $var_R = $var_1 ; $var_G = $var_2  ; $var_B = $V     ; } 
			else if  ($var_i == 4) { $var_R = $var_3 ; $var_G = $var_1  ; $var_B = $V     ; } 
			else                   { $var_R = $V     ; $var_G = $var_1  ; $var_B = $var_2 ; } 

			$R = $var_R * 255; 
			$G = $var_G * 255; 
			$B = $var_B * 255; 
		} 

		return sprintf('%02x%02x%02x', $R, $G, $B);
	} 
	
	function widget_timecard($initial, $data) {
		$id = 'htmlwidgetcontent' . floor(microtime(true));
		return <<< AJAXLOAD
<div id="{$id}" onclick="$('#{$id}').load('/ajax/timecard');"></div>
<script type="text/javascript">
try{
	$("#{$id}").load("/ajax/timecard");
}
finally{}
window.setInterval(function(){
try{
	$("#{$id}").load("/ajax/timecard");
}
finally{}
}, 60000);
</script>
AJAXLOAD;
	}


	function ajax_timecard() {
		$stats = DB::get()->row("SELECT min(start) as startday, max(start) as endday, count(start) as ttl FROM timecard WHERE user_id = :user_id AND ondate = curdate() ORDER BY start ASC, id ASC", array('user_id' => Auth::user()->id));
		$startday = intval($stats->startday);
		$endday = max(intval($stats->endday) + 1, date('H'));

		$projects = DB::get()->col("SELECT purl FROM timecard WHERE user_id = :user_id AND ondate = curdate() GROUP BY purl ORDER BY start ASC, id ASC", array('user_id' => Auth::user()->id));

		$projectcolor = array();
		$h = 0;
		$b = 0.5;
		foreach($projects as $project) {
			//$projectcolor[$project] = $this->_hsv_rgb($h, 0.2, 1);
			$h += 1/count($projects);
			$projectcolor[$project][false] = $this->_hsv_rgb(0.6, 0.8, $b);
			$projectcolor[$project][true] = $this->_hsv_rgb(0.3, 0.8, $b);
			$b += 0.5/count($projects);
		}

		echo '<table class="hourtable" style="width:100%;">';
		$showedtime = false;

		if($stats->ttl > 0 ) {
			$showedtime = true;
			for($z = $startday; $z < $endday; $z++) {
				$times = DB::get()->results("SELECT * FROM timecard WHERE user_id = :user_id AND ondate = curdate() AND start >= :start AND start < :end ORDER BY start ASC, id ASC", array('user_id' => Auth::user()->id, 'start' => "$z:00", 'end' => ($z+1) . ':00'));
				$early = DB::get()->row("SELECT * FROM timecard WHERE user_id = :user_id AND ondate = curdate() AND start < :start ORDER BY start DESC, id DESC LIMIT 1", array('user_id' => Auth::user()->id, 'start' => "$z:00"));
				$late = DB::get()->row("SELECT * FROM timecard WHERE user_id = :user_id AND ondate = curdate() AND start >= :end ORDER BY start ASC, id ASC LIMIT 1", array('user_id' => Auth::user()->id, 'end' => ($z+1) . ':00'));
	
				if($early) {			
					$early->start = "$z:00";
					array_unshift($times, $early);
				}
				if($late) {
					$late->start = ($z+1) . ':00';
				}
				else {
					$late = new stdClass();
					$late->purl = 'none';
					$late->task = 'none';
					$late->notes = '';
					$late->start = date('H:i');
				}
				$times[] = $late;
									
				$entries = array();
				for($i = 0; $i < count($times); $i++) {
					$time = $times[$i];
					$entries[] = array(
						'time' => date('H:i', strtotime($time->start)),
						'project' => $time->purl,
						'task' => $time->task,
						'notes' => $time->notes,
					);
				}
				$hours = $this->_parse_hours($entries);
	
				echo '<tr><td>' . date('ga', mktime($z)) . '</td><td style="width:100%;">';
				$owidth = 0;
				if(count($hours) > 0) {
					foreach($hours as $hour) {
						if($hour['time'] == 0) continue;
						$color = $hour['real'] ? $projectcolor[$hour['pcode']][$hour['billable']] : 'dddddd';
						$width = max(0, min(round($hour['time'] * 100), 100 - $owidth));
						echo '<div class="hour" style="background-color:#' . $color . ';width:' . $width . '%;" title="' . htmlentities($hour['pcode'] . ' (' . $hour['minutes'] . ' mins) : ' . $hour['task']) . '"></div>';
						$owidth += $width;
					}
				}
				else {
					echo '<div class="hour" style="background-color:#ff0000;width:100%;" title="No Time Data"></div>';
				}
				echo '</td></tr>';
			}
			echo '</table>';
		}
		
				
		$times = $this->_get_time();
		$projects = $this->_get_projects();
		if(count($times) > 0) {
			$showedtime = true;
			echo '<table class="hourtable" style="width:100%;">';
			echo '<tr><th>Project</th><td>Hours</td></tr>';
			foreach($times as $pid => $hours) {
				echo '<tr><th>' . $projects[$pid] . '</th><td>' . sprintf('%0.2f', $hours) . '</td></tr>';
			}
			echo '</table>';
		}
		
		if(!$showedtime) {
			echo '<div style="width:100%;padding:10px;text-align:center;font-size:1.7em;font-weight:bold;background:#f00;">No Time Entries Yet Today</div>';
		}
	}
	
	function ajax_process_timecard() {
		$user = Auth::user();
		
		if(!$this->_get_user()) {
			echo '<p>The current user does not have a time tracking user id set.</p>';
			exit();
		}
		
		$ondate = $_POST['date'];
		$projects = $this->_get_projects();
		$times = DB::get()->results("SELECT * FROM timecard WHERE user_id = :user_id AND ondate = :ondate ORDER BY start ASC, id ASC", array('user_id' => $user->id, 'ondate' => $ondate));

		$lasttime = end($times);
		if(in_array($lasttime->purl, $projects)) {
			echo "<p>Please add a task row like \"" . date('H:i', strtotime($lasttime->start)+60) . " quit end of day\" to conclude the day and set a duration for the last task.</p>";
		}
		else {
		
			DB::get()->query("DELETE FROM timecard WHERE user_id = :user_id AND ondate = :ondate", array('user_id' => $user->id, 'ondate' => $ondate));
	
			$processed = $skipped = 0;
			
			for($z = 0; $z < count($times) - 1; $z++) {
				$begin_time = $times[$z]->start;
				$end_time = $times[$z+1]->start;
		
				$timerec = array(
					'time' => $this->_calc_time($begin_time, $end_time),
					'ondate' => $times[$z]->ondate,
					'pcode' => $times[$z]->purl,
					'task' => $times[$z]->task,
					'notes' => $times[$z]->notes,
					'begin' => $begin_time,
					'end' => $end_time,
					'billable' => !(preg_match('%-\$%', $times[$z]->task) || preg_match('%-\$%', $times[$z]->notes)),
				);
				if(in_array($timerec['pcode'], $projects)) {
					$this->_add_time($timerec);
					$processed++;
				}
				else {
					$skipped++;
				}
			}
			
			echo "<p>{$processed} processed records. {$skipped} skipped records.</p>";
		}
		echo $this->_get_timecard($ondate);
		echo <<< PROCESS_SCRIPT
<script type="text/javascript">
$('#process_date,#timecard_process,#timecard_save').attr('disabled','');
$('#timecard_data').removeClass('changed');
</script>
PROCESS_SCRIPT;
	}
	
	function get_options($options) {
		$options[] = array('Identity', 'time tracking uid');
		return $options;
	}
	
	function ajax_save_timecard() {
		$user = Auth::user();
		
		$timecard = $_POST['timecard'];
		$date = $_POST['date'];
		
		$v1 = $this->_parse_times($timecard);
		$v2 = $this->_parse_hours($v1);
		
		DB::get()->query("DELETE FROM timecard WHERE user_id = :user_id AND ondate = :ondate", array('user_id' => $user->id, 'ondate' => $date));
		
		if(is_array($v2)) {
			foreach($v2 as $time) {
				DB::get()->query("INSERT INTO timecard (user_id, purl, task, ondate, start, finished, recorded, notes) VALUES (:user_id, :purl, :task, :ondate, :start, 0, 0, :notes)",
					array(
						'user_id' => $user->id,
						'purl' => $time['pcode'],
						'task' => $time['task'],
						'ondate' => $date,
						'start' => $time['begin'],
						'notes' => $time['notes'],
					)
				);
			}
		}
		
		echo "<p>" . count($v2) . " saved records.</p>";
		echo $this->_get_timecard($date);
		echo <<< SAVE_SCRIPT
<script type="text/javascript">
$('#process_date,#timecard_process,#timecard_save').attr('disabled','');
$('#timecard_data').removeClass('changed');
</script>
SAVE_SCRIPT;
	}

	function _project_alias($project) {
		$alias = Option::get('time alias', $project);
		if($alias) {
			return $alias;
		}
		else {
			return $project;
		}
	}
	
	function ajax_get_timecard() {
		$date = $_POST['date'];
		echo $this->_get_timecard($date);
		echo <<< LOAD_SCRIPT
<script type="text/javascript">
$('#process_date,#timecard_process,#timecard_save').attr('disabled','');
$('#timecard_data').removeClass('changed');
</script>
LOAD_SCRIPT;
	}
}

?>