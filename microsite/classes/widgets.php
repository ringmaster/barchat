<?php

class Widgets {
	function get_widgets() {
		$user = Auth::user();
		
		$active_widgets = DB::get()->results("SELECT * FROM options WHERE user_id = :user_id AND grouping = 'widgets' ORDER BY name ASC", array('user_id' => $user->id));

		$widgets = array();
		foreach($active_widgets as $widgetdata) {
			$data = (object) unserialize($widgetdata->value);
			$data->id = $widgetdata->id;
			$contents = Plugin::call('widget_' . $data->name, "{$data->name}", $data);
			$title = isset($data->title) ? $data->title : $data->name;
			$widgets[$data->id] = '<div class="widget ' . $data->name . '" id="widget_' . $data->id . '">
<div class="widgettitle" ondblclick="$(this).parents(\'.widget\').children(\'.widgetbody\').slideToggle();return false;">' . $title . '
<a class="removewidget" href="#" onclick="removeWidget(' . $data->id . ');return false;">[-]</a>
<a class="collapsewidget" href="#" onclick="$(this).parents(\'.widget\').children(\'.widgetbody\').slideToggle();return false;">[v]</a>
</div><div class="widgetbody"><div class="widgetcontent">' . $contents . '</div></div></div>';
		}
		$widgets = implode('', array_filter($widgets));
		
		return $widgets;
	}
}
?>