<?php

Class Sound extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['sound'] = array('%^/sound\s+(?P<file>\S+)(?:\s+(?P<style>\S+))?$%i', array($this, '_sound'), CMD_LAST);

		return $cmds;
	}
	
	function _sound($params) {
		$file = $params['file'];
		$style = $params['style'];
		$channel = $params['channel'];
		$user = Auth::user();
		$cssclass = array('sound');
		if($style != '') {
			$cssclass[] = $style;
		}
		
		if($filerow = DB::get()->row('SELECT * FROM files WHERE filename = :file', array('file' => $file))) {
			Status::create()
				->data(Utils::cmdout($params))
				->user_id($user->id)
				->channel($channel)
				->cssclass(implode(' ', $cssclass))
				->js('bareffect(function(){play(' . json_encode($filerow->url) . ');});')
				->insert();
		}
		else {
			Status::create()
				->data('Sorry, that file was not found in the file listing.')
				->user_id($user->id)
				->type('system')
				->cssclass('error')
				->user_to($user->id)
				->insert();
		}
		
		return true;
	}
	
	function autocomplete($auto, $cmd){
		$auto[] = "/sound \tfilename";
		return $auto;
	}
	
}

?>