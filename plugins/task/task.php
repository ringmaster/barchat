<?php

class TaskPlugin extends Plugin {
	
	function commands($cmds){
		$cmds[1]['task'] = array('%^/task\s+(?P<list>.+?)\s+(?P<task>.+)$%i', array($this, '_task'), CMD_LAST);
		$cmds[1]['taskdelete'] = array('%^/taskdelete\s+(?P<list>.+?)\s+(?P<key>\d+)$%i', array($this, '_taskdelete'), CMD_LAST);
		$cmds[1]['taskstate'] = array('%^/taskstate\s+(?P<list>.+?)\s+(?P<key>\d+)\s+(?P<state>\d)$%i', array($this, '_taskstate'), CMD_LAST);
		return $cmds;
	}
	
	function widget_tasks() {
		$id = 'tasklist';
		return <<< AJAXLOAD
<div id="{$id}"></div>
<script type="text/javascript">
try{
	$("#{$id}").load("/ajax/widgettasks");
}finally{}
if(typeof(zz{$id}) != 'undefined') {window.clearInterval(zz{$id});}
zz{$id} = window.setInterval(function(){
try{
	$("#{$id}").load("/ajax/widgettasks");
}finally{}
}, 300000);
</script>
AJAXLOAD;
		
	}
	
	function ajax_widgettasks($path) {
		
		$lists = DB::get()->col("SELECT name FROM options WHERE user_id = :user_id AND grouping = 'tasklists' ORDER BY name ASC;", array('user_id' => Auth::user_id()));
		
		foreach($lists as $list) {
		
			echo '<fieldset><legend>' . htmlspecialchars($list) . '</legend>';
			echo '<ul class="atasklist">';
			$tasks = $this->_get_tasks($list);
			$extra = '';
			foreach($tasks as $key => $task) {
				switch($task['state']) {
					case 0:
						$state = 'todo';
						$nextstate = '/taskstate ' . $list . ' ' . $key . ' 1';
						break;
					case 1:
						$state = 'done';
						$nextstate = '/taskstate ' . $list . ' ' . $key . ' 0';
						$extra = '<a href="#" class="delete" onclick="send(\'/taskdelete ' . $list . ' ' . $key . '\'); return false;">Delete</a>';
						break;
				}
				echo '
<li id="task_id_' . $key . '">
<span class="tasktext ' . $state . '">
<a href="#" onclick="send(\'' . $nextstate . '\'); return false;">' . htmlspecialchars($task['name']) . '</a>
</span>
<span class="hidden">
<!-- Not implemented yet
<a href="#" class="edit">Edit</a>
<a href="#" class="info">Info</a>
-->
' . $extra . '
</span></li>
				';			
			}
			echo '</ul>';
			echo '</fieldset>';
			
		}
		//echo '<li class="inputline"><input type="text"><button>Add</button></li>'; // do this by commandline only?
		echo <<< TASKLIST
<script type="text/javascript">
/*
$('.atasklist').sortable({
items: 'li:not(.inputline)',
connectWith: '.atasklist',
stop: function() {
	//$('#tasklist li.inputline:not(:last-child)').after($('#tasklist li').last());
}
});
*/
$('li:not(.inputline)').disableSelection();
</script>
TASKLIST;
		
	}
	
	function _task($params){
		$user = $params['user'];
		$channel = $params['channel'];
		$list = $params['list'];
		$task = $params['task'];

		$this->_add_task($list, $task);
		
		$output = '<b>New Task:</b> ' . htmlspecialchars($task);

		/* // To output to the channel:
		$js = "bareffect(function(){if(user_id == {$user->id}) {if($('.widget.tasks').length) {\$('#tasklist').load('/ajax/widgettasks');} else {send('/addwidget tasks');}}});";
		DB::get()->query("INSERT INTO presence (data, user_id, channel, cssclass, js) VALUES (:msg, :user_id, :channel, 'task', :js)", array('msg' => $output, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		*/

		$obj = new StdClass();
		$obj->laststatus = 0;
		$obj->js = "
if($('.widget.tasks').length) {\$('#tasklist').load('/ajax/widgettasks');} else {send('/addwidget tasks');}
addSystem({user_id:{$user->id}, data: '" . addslashes($output) . "', cssclass: 'ok', username: '{$user->username}', nickname: '{$user->nickname}', status: " . microtime(true) . ", js:''}, '#notices');
do_scroll();
";
		echo json_encode($obj);
		die();
		
		return true;
	}
	
	function _taskdelete($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$list = $params['list'];
		$key = $params['key'];
		
		$tasks = $this->_get_tasks($list);
		$taskname = $tasks[$key]['name'];
		$this->_delete_task($list, $key);
		
		$output = '<b>Deleted Task:</b> ' . htmlspecialchars($taskname);
		
		$obj = new StdClass();
		$obj->laststatus = 0;
		$obj->js = "
if($('.widget.tasks').length) {\$('#tasklist').load('/ajax/widgettasks');} else {send('/addwidget tasks');}
addSystem({user_id:{$user->id}, data: '" . addslashes($output) . "', cssclass: 'ok', username: '{$user->username}', nickname: '{$user->nickname}', status: " . microtime(true) . ", js:''}, '#notices');
do_scroll();
";
		echo json_encode($obj);
		die();
		
		return true;
	}
	
	function _taskstate($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$list = $params['list'];
		$key = $params['key'];
		$state = $params['state'];
		
		$tasks = $this->_get_tasks($list);
		$taskname = $tasks[$key]['name'];
		$this->_state_task($list, $key, $state);
		
		switch($state) {
			case 0:
				$output = '<b>Reactivated Task:</b> ' . htmlspecialchars($taskname);
				break;
			case 1:
				$output = '<b>Completed Task:</b> ' . htmlspecialchars($taskname);
				break;
		}
		
		$obj = new StdClass();
		$obj->laststatus = 0;
		$obj->js = "
if($('.widget.tasks').length) {\$('#tasklist').load('/ajax/widgettasks');} else {send('/addwidget tasks');}
addSystem({user_id:{$user->id}, data: '" . addslashes($output) . "', cssclass: 'ok', username: '{$user->username}', nickname: '{$user->nickname}', status: " . microtime(true) . ", js:''}, '#notices');
do_scroll();
";
		echo json_encode($obj);
		die();
			
		return true;
	}

	function autocomplete($auto, $cmd){
		$auto[] = "/task";
		if(strncasecmp($cmd, '/task', 5) == 0) {
			$auto[] = "/task {$nickname}\t project description";
			$auto[] = "/task\t project description";
		}
		return $auto;
	}

	function _get_tasks($list) {
		if($tasks = Option::get('tasklists', $list)) {
			$tasks = unserialize($tasks->value);
		}
		else {
			$tasks = array();
		}
		return $tasks;
	}
	
	function _add_task($list, $task, $state = 0) {
		$tasks = $this->_get_tasks($list);
		$tasks[] = array(
			'name' => $task,
			'state' => $state,
			'detail' => $detail,
			'created' => time(),
		);
		DB::get()->query("DELETE FROM options WHERE grouping = 'tasklists' AND name = :name AND user_id = :user_id", array('name' => $list, 'user_id' => Auth::user_id()));
		DB::get()->query("INSERT INTO options (grouping, name, user_id, value) VALUES ('tasklists', :name, :user_id, :value);", array('name' => $list, 'user_id' => Auth::user_id(), 'value' => serialize($tasks)));
	}
	
	function _delete_task($list, $taskid) {
		$tasks = $this->_get_tasks($list);
		unset($tasks[$taskid]);
		DB::get()->query("DELETE FROM options WHERE grouping = 'tasklists' AND name = :name AND user_id = :user_id", array('name' => $list, 'user_id' => Auth::user_id()));
		if(count($tasks) > 0 ) {
			DB::get()->query("INSERT INTO options (grouping, name, user_id, value) VALUES ('tasklists', :name, :user_id, :value);", array('name' => $list, 'user_id' => Auth::user_id(), 'value' => serialize($tasks)));
		}
	}
	
	function _state_task($list, $taskid, $state = 0) {
		$tasks = $this->_get_tasks($list);
		$tasks[$taskid]['state'] = $state;
		switch($state) {
			case 1:
				$tasks[$taskid]['completed'] = time();
				break;
		}
		DB::get()->query("DELETE FROM options WHERE grouping = 'tasklists' AND name = :name AND user_id = :user_id", array('name' => $list, 'user_id' => Auth::user_id()));
		DB::get()->query("INSERT INTO options (grouping, name, user_id, value) VALUES ('tasklists', :name, :user_id, :value);", array('name' => $list, 'user_id' => Auth::user_id(), 'value' => serialize($tasks)));
	}
		
}

?>