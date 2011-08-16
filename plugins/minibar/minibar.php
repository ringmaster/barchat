<?php

Class Minibar extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['minibar'] = array('%^/minibar\s+(?P<watch>.+)$%i', array($this, '_minibar'), CMD_LAST);

		return $cmds;
	}
	
	function _minibar($params) {
		$watch = $params['watch'];
		$user = Auth::user();

		$widgets = DB::get()->results("SELECT * FROM options WHERE grouping = 'widgets' AND user_id = :user_id", array('user_id' => $user->id));
		foreach($widgets as $widget) {
			$data = unserialize($widget->value);
			if($data['name'] == 'minibar') {
				$data['rooms'][] = $watch;
				$widget->value = serialize($data);
				$widget->update('options', 'id');
				Immediate::create()
					->js("reloadWidgets();addSystem({user_id:{$user->id}, data: 'Added \'" . addslashes($watch) . "\' to minibar.', cssclass: 'ok', username: '{$user->username}', nickname: '{$user->nickname}', status: " . microtime(true) . ", js:''}, '#notices');do_scroll();");
				return true;
			}
		}

		$lastwidgetid = DB::get()->val("SELECT MAX(id) FROM options");
		if(!$lastwidgetid) {
			$lastwidgetid = 0;
		}
		$lastwidgetid++;
		
		$data = array(
			'name' => 'minibar',
			'params' => '',
			'rooms' => array($watch),
		);
		
		DB::get()->query("INSERT INTO options (name, grouping, value, user_id) VALUES (:name, 'widgets', :value, :user_id);", array('name' => $lastwidgetid, 'value' => serialize($data), 'user_id' => $user->id));

		Immediate::create()
			->js("reloadWidgets();addSystem({user_id:{$user->id}, data: 'Added \'" . addslashes($watch) . "\' as minibar widget.', cssclass: 'ok', username: '{$user->username}', nickname: '{$user->nickname}', status: " . microtime(true) . ", js:''}, '#notices');do_scroll();");

		return true;
	}
	
	function widget_minibar($initial, $data) {
		$out = '';
		foreach($data->rooms as $room) {
			$wid = 'minibarRoom' . $room;
			$widsafe = preg_replace('%\W+%', '_', $wid);
			$out .= <<< AJAXLOAD
<small>In {$room}:</small><table id="{$widsafe}" class="watch notices"></table>
<script type="text/javascript">
var minibarfader = 0;
try{
	updateHandlers.minibarHandler{$widsafe} = function (update, updates, channels, target) {
		if(allmute) return;
		if(update.channel == '{$room}') {
			ttarget = "#{$widsafe}";
			window.clearTimeout(minibarfader);
			minibarfader = window.setTimeout(function(){
				$('.widget.minibar table').each(function(){\$('tr:not(:last)', this).fadeOut('slow', function(){\$(this).remove();})});
			}, 10000);
			return updateHandlers.primaryHandler(update, updates, channels, ttarget);
		}
	}
}
finally{}
</script>
AJAXLOAD;
		}
		return $out;
	}


	function autocomplete($auto, $cmd){
		$auto[] = "/minibar \troom";
		return $auto;
	}
	
}

?>