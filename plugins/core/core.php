<?php

Class Core extends Plugin
{
	
	function widget_names($initial, $data) {
		$presence = new PresenceController();
		return $presence->namebar();
	}
	
	function widget_rss($initial, $data) {
		$id = 'htmlwidgetcontent' . floor(microtime(true));
		return <<< AJAXLOAD
<div id="{$id}"></div>
<script type="text/javascript">
try{
	$("#{$id}").load("/ajax/widgetrss/{$data->id}");
}
finally{}
window.setInterval(function(){
try{
	$("#{$id}").load("/ajax/widgetrss/{$data->id}");
}
finally{}
}, 60000);
</script>
AJAXLOAD;
	}
	
	function ajax_toolpanel($path) {
		$user = Auth::user();
		DB::get()->query("DELETE FROM options WHERE user_id = :user_id AND grouping = 'Interface' AND name = 'toolpanel';", array('user_id' => $user->id));
		DB::get()->query("INSERT INTO options (user_id, name, grouping, value) VALUES (:user_id, 'toolpanel', 'Interface', :value);", array('user_id' => $user->id, 'value' => $_POST['value']));
	}
	
	function ajax_widgets($path) {
		echo Widgets::get_widgets();
	}
	
	function ajax_removewidget($path) {
		$id = $_POST['value'];
		$user = Auth::user();
		DB::get()->query("DELETE FROM options WHERE user_id = :user_id AND grouping = 'widgets' AND id = :id;", array('user_id' => $user->id, 'id' => $id));
	}
	
	function ajax_sortwidgets($path) {
		$names = $_POST['widget'];
		$user = Auth::user();
		$index = 0;
		foreach($names as $id) {
			$index++;
			DB::get()->query("UPDATE options SET name = :name WHERE id = :id;", array('name' => $index, 'id' => $id));
		}
	}
	
	function ajax_widgetrss($path) {
		$id = $path[0][0];
		$user = Auth::user();
		$widgetdata = DB::get()->row("SELECT * FROM options WHERE user_id = :user_id AND grouping = 'widgets' AND id = :id ORDER BY name ASC", array('user_id' => $user->id, 'id' => $id));

		$data = (object) unserialize($widgetdata->value);
		$xml = new SimpleXMLElement(file_get_contents($data->params));
		
		echo '<ul style="list-style: disc inside;">';
		$count = 0;
		foreach($xml->channel->item as $item) {
			echo '<li><a href="' . $item->link . '" target="_blank">' . $item->title . '</a></li>';
			if(++$count >= 5) break;
		}
		echo '</ul>';
		
	}

	function header($header) {
		$toolpanelwidth = DB::get()->val("SELECT value FROM options WHERE user_id = :user_id AND grouping = 'Interface' AND name = 'toolpanel';", array('user_id' => Auth::user_id()));
		$header .= <<< HEADER
<script type="text/javascript">
$(function(){
	getToolpanel($toolpanelwidth);
	$('#drawer').width($(window).width() - $toolpanelwidth - 30);
});
</script>
HEADER;
		echo $header;
	}
	
	function ajax_squelchroom($path) {
		$user = Auth::user();
		$room = $_POST['room'];
		$squelch = $_POST['squelch'];
		DB::get()->query("DELETE FROM options WHERE user_id = :user_id AND grouping = 'squelch' AND room = :room", array('user_id' => $user->id, 'room' => $room)); 
		DB::get()->query("INSERT INTO options (user_id, grouping, room, name, value) VALUES (:user_id, 'squelch', :room, 'squelch', :squelch)", array('user_id' => $user->id, 'room' => $room, 'squelch' => $squelch)); 
	}
	
	function autocomplete($auto, $cmd){
		$auto[] = "/addwidget \twidgetname";
		$auto[] = "/brains \tScreenshare-URL Phone-number";
		return $auto;
	}
	
}

?>
