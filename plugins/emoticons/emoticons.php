<?php

class EmoticonsPlugin extends Plugin
{
	function listicons(){
		static $list = array();
		
		if(count($list) == 0) {
			$files = glob(dirname(__FILE__) . '/icons/*.*');
			foreach($files as $file) {
				$text = substr(basename($file), 0, -4);
				$text = substr($text, strpos($text, '_') + 1);
				if(preg_match('/^([0-9a-fz][0-9a-fz])+$/', $text)) {
					$itext = '';
					for($z=0;$z<strlen($text);$z+=2) {
						$sub = substr($text, $z, 2);
						if($sub == 'zz') {
							$list[$itext] = '/plugins/emoticons/icons/' . basename($file);
							$itext = '';
						}
						else {
							$itext .= htmlspecialchars(chr(hexdec($sub)));
						}
					}
					$list[$itext] = '/plugins/emoticons/icons/' . basename($file);
				}
			}
		}
		return $list;
	}
	
	
	function commands($cmds){
		$cmds[1]['thumbsup'] = array('/(?<![^\s])cl(?![^,\s])/i', array($this, '_thumbs'), CMD_FORWARD);
		
		$list = array_keys($this->listicons());
		
		$reg = array();
		foreach($list as $item) {
			$reg[] = preg_quote($item, '/');
		}
		$reg = implode('|', $reg);

		$cmds[1]['face'] = array('/(?<![^\s])(?P<emoticon>' . $reg . ')(?![^,\s])/', array($this, '_face'), CMD_FORWARD);
		return $cmds;
	}
	
	function _thumbs($msg, $params){
		$re = $params['cmd'][0];
		return preg_replace($re, '<img src="/plugins/emoticons/icons/thumb_up.png" alt="cl">', $msg);
	}
	
	function _face($msg, $params){
		$re = $params['cmd'][0];
		return preg_replace_callback($re, array($this, '_face_replace'), $msg);
	}
	
	function _face_replace($matches) {
		$list = $this->listicons();
		return '<img src="' . $list[$matches[0]] . '" alt="' . htmlspecialchars($matches[0]) . '" title="' . htmlspecialchars($matches[0]) . '" class="emoticon">';
	}
	
	function autocomplete($auto, $cmd){
		preg_match('%(\S+)$%i', $cmd, $submatch);
		
		$list = array_keys($this->listicons());
		foreach($list as $item) {
			if(strpos($item, $submatch[0]) === 0 && ($cmd[0] != ':' || $item[0] != ':')) {
				$auto[] = $cmd . substr($item, strlen($submatch[0]));
			}
		}
		return $auto;
	}
	
}
?>