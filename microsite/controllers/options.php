<?php

class OptionsController
{
	function index($path){
		$options = $this->_get_options();
		$v = new View($options);
		
		$v->render('options');
	}
	
	function _get_options(){
		// Core options:
		$optionlist = array(
			array('Amazon Web Services', 'AWS Secret Access Key'),
			array('Amazon Web Services', 'AWS Access Key ID'),
			array('Amazon Web Services', 'S3 Bucket Name'),
			array('Time', 'Zone Offset'),
		);
		
		// Plugin options
		$optionlist = Plugin::call('get_options', $optionlist);
		
		$options = array(
			'system' => array(),
			'user' => array(),
		);

		foreach($optionlist as $option) {
			$opobj = Option::get($option[0], $option[1]);
			if(!is_object($opobj)) {
				$opobj = new Option();
				$opobj->grouping = $option[0];
				$opobj->name = $option[1];
				$opobj->id = $option[0] . ':' . $option[1];
			}
			if($opobj->user_id == 0) {
				$options['system'][] = $opobj;
			}
			else {
				$options['user'][] = $opobj;
			}
		}
		return $options;
	}
	
	function save($path)
	{
		$options = DB::get()->results('SELECT * FROM options WHERE user_id = 0 OR user_id = :user_id', array('user_id' => Auth::user_id()));
		foreach($options as $option) {
			if($option->istoggle) {
				if(isset($_POST['option'][$option->id])) {
					DB::get()->query('UPDATE options SET value = 1 WHERE id = :id', array('id'=>$option->id));
				}
				else {
					DB::get()->query('UPDATE options SET value = 0 WHERE id = :id', array('id'=>$option->id));
				}
			}
			elseif($option->ispassword) {
				if(isset($_POST['option'][$option->id]) && !preg_match('%^\*+$%', $_POST['option'][$option->id])) {
					DB::get()->query('UPDATE options SET value = :value WHERE id = :id', array('id'=>$option->id, 'value'=>$_POST['option'][$option->id]));
				}
			}
			else{
				if(isset($_POST['option'][$option->id])) {
					DB::get()->query('UPDATE options SET value = :value WHERE id = :id', array('id'=>$option->id, 'value'=>$_POST['option'][$option->id]));
				}
			}
		}
		echo <<< JSOUT
<script type="text/javascript">window.parent.$('#options').slideToggle('fast');</script>
JSOUT;
	}
	
	function css($path)
	{
		$channel = array_shift($path);
		$file = DB::get()->val("SELECT value FROM options WHERE name = 'css' AND grouping = 'decor' AND room = :channel;", array('channel' => $channel));
		header('content-type: text/css');
		echo $file;
		die();
	}
}

?>