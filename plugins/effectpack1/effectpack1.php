<?php

Class EffectPack1Plugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['flyers'] = array('%^/flyers$%i', array($this, '_flyers'), CMD_LAST);
		$cmds[1]['redflag'] = array('%^/redflag$%i', array($this, '_redflag'), CMD_LAST);
		$cmds[1]['popcorn'] = array('%^/popcorn$%i', array($this, '_popcorn'), CMD_LAST);
		$cmds[1]['brains'] = array('%^/brains(?:\s+(?P<condition>.+))?$%i', array($this, '_brains'), CMD_LAST);
		$cmds[1]['asplode'] = array('%^/asplode$%i', array($this, '_asplode'), CMD_LAST);
		$cmds[1]['crickets'] = array('%^/crickets%i', array($this, '_crickets'), CMD_LAST);
		$cmds[1]['morning'] = array('%^\s*(?:morning|mornin\'?|aloha|greetings|howdy)[!?\.]*\s*?(?:\s+@(?P<target>\S+))?$%i', array($this, '_morning'), CMD_LAST);
		$cmds[2]['bi'] = array('/(?<!\w)(\*\/|\/\*)(?P<style>\S+(\s+\S+)*)(\*\/|\/\*)(?!\w)/i', array($this, '_bi'), CMD_FORWARD);
		$cmds[2]['b'] = array('/(?<!\w)\*(?P<style>\S+(\s+\S+)*)\*(?!\w)/i', array($this, '_b'), CMD_FORWARD);
		$cmds[2]['i'] = array('/(?<!\w)\/(?P<style>\S+(\s+\S+)*)\/(?!\w)/i', array($this, '_i'), CMD_FORWARD);
		$cmds[2]['tm'] = array('/(?<=\w)(?<!\.h)TM\b/i', array($this, '_tm'), CMD_FORWARD);
		$cmds[2]['r'] = array('/(\w)\(r\)(?!\w)/i', array($this, '_r'), CMD_FORWARD);
		$cmds[2]['tldr'] = array('/\btl;dr\b/i', array($this, '_tldr'), CMD_FORWARD);
		$cmds[2]['hexcolor'] = array('/(?<!\w)#(?P<color>[0-9a-f]{6})\b/i', array($this, '_hexcolor'), CMD_FORWARD);
		$cmds[2]['ninja'] = array('/^\/ninja$/i', array($this, '_ninja'), CMD_LAST);
		$cmds[2]['facepunch'] = array('/^\/facepunch\s+(?P<name>.+)$/i', array($this, '_facepunch'), CMD_LAST);
		$cmds[2]['deck'] = array('/\b(d+e+[ck]{2,}|chil+iwack+)\b/i', array($this, '_deck'), CMD_FORWARD);
		$cmds[2]['MC'] = array('/\bMC\b/', array($this, '_mc'), CMD_FORWARD);
		return $cmds;
	}

	function _deck($msg, $params) {
		$user = $params['user'];
		if($user->id == 3) {
			$msg = preg_replace('/\b(d+e+[ck]{2,}|chil+iwack+)\b/i', 'cool', $msg);
		}
		return $msg;
	}	

	function _mc($msg, $params) {
		$user = $params['user'];
		if($user->id == 3) {
			$msg = preg_replace('/\bMC\b/i', 'michael', $msg);
		}
		return $msg;
	}	

	function _bi($msg, $params) {
		if(strpos($msg, '//') !== false) return $msg;
		$out = preg_replace('/(?<![\w\/\*])(\*\/|\/\*)(?P<style>\S+?(\s+\S+?)*)(\*\/|\/\*)(?![\w\/\*])/i', '<b><i><span class="style">$1</span>$2<span class="style">$4</span></i></b>', $msg);
		return $out;
	}
	
	function _b($msg, $params) {
		$out = preg_replace('/(?<![\w\/\*])\*(?P<style>\S+?(\s+\S+?)*)\*(?![\w\/\*])/i', '<b><span class="style">*</span>$1<span class="style">*</span></b>', $msg);
		return $out;
	}

	function _i($msg, $params) {
		if(strpos($msg, '//') !== false) return $msg;
		$out = preg_replace('/(?<![\w\/\*])\/(?P<style>\S+?(\s+\S+?)*)\/(?![\w\/\*])/i', '<i><span class="style">/</span>$1<span class="style">/</span></i>', $msg);
		return $out;
	}

	function _tm($msg, $params) {
		$out = preg_replace('/(?<=\w)(?<!\.h)TM\b/i', '&trade;', $msg);
		return $out;
	}

	function _r($msg, $params) {
		$out = preg_replace('/(\w)\(r\)(?!\w)/i', '$1&reg;', $msg);
		return $out;
	}

	function _tldr($msg, $params) {
		$out = preg_replace('/\btl;dr\b/i', '(I\'m an ass who can\'t read long things, by the way.)', $msg);
		return $out;
	}

	function _hexcolor($msg, $params) {
		$color = $params['color'];
		$rep = '<a class="hexcolor" style="background-color:#$1" href="http://www.colourlovers.com/color/$1" target="_blank">#$1</a>'; 
		$out = preg_replace('/(?<!\w)#(?P<color>[0-9a-f]{6})\b/i', $rep, $msg);
		return $out;
	}
	
	function _morning($params){
		$user = $params['user'];
		$channel = $params['channel'];
		
		$meanderfile = file_get_contents(dirname(__FILE__) . '/meander.txt');
		$meanderlines = explode("\n", $meanderfile);
		$index = '%starts';
		$meander = array();
		foreach($meanderlines as $line) {
			$line = trim($line);
			if(preg_match('#^%(%.+)$#', $line, $matches)) {
				$index = $matches[1];
			}
			else if($line[0]=='#') {
				$meander[$index][] = str_rot13(substr($line, 1));
			}
			else {
				$meander[$index][] = $line;
			}
		}

		if(isset($params['target']) && isset($meander['%' . $params['target']])) {
			$starts = $meander['%' . $params['target']];
		}
		else if(isset($meander['%' . $user->username])) {
			$starts = $meander['%' . $user->username];
		}
		else {
			$starts = $meander['%starts'];
		}
		
		shuffle($starts);
		$morning = reset($starts);
		while(preg_match('#%\w+#', $morning, $match)) {
			$replacements = &$meander[$match[0]];
			shuffle($replacements);
			$morning = preg_replace('#%\w+#', reset($replacements), $morning, 1);
		}

		
		Status::create()
			->data($morning)
			->user_id($user->id)
			->channel($channel)
			->cssclass('morning')
			->insert();
		
		return true;
	}
	
	function _flyers($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$rmsg = ' says Lets go Rocco!';
		$js = 'bareffect(function(){
	loader.js("/plugins/effectpack1/flyers/flyers.js", "flyers", function(){
		flyers.start();
		play("http://audio.hark.com/000/406/647/406647.mp3");
	});
});';

		DB::get()->query("INSERT INTO presence (data, type, user_id, channel, cssclass, js) VALUES (:msg, 'emote', :user_id, :channel, 'flyers quiet', :js)", array('msg' => $rmsg, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		return true;
	}
	
	function _redflag($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$rmsg = ' waves a red flag.';
		$js = 'bareffect(function(){
	loader.js("/plugins/effectpack1/effectpack1.js", "effectpack1", function(){
		redflag.start();
	});
});';

		DB::get()->query("INSERT INTO presence (data, type, user_id, channel, cssclass, js) VALUES (:msg, 'emote', :user_id, :channel, 'redflag quiet', :js)", array('msg' => $rmsg, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		return true;
	}
	
	function _popcorn($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$rmsg = ' makes popcorn.';
		$js = 'bareffect(function(){
	loader.js("/plugins/effectpack1/effectpack1.js", "effectpack1", function(){
		popcorn.start();
		play("/plugins/effectpack1/popcorn/popcorn.mp3");
	});
});';

		DB::get()->query("INSERT INTO presence (data, type, user_id, channel, cssclass, js) VALUES (:msg, 'emote', :user_id, :channel, 'popcorn quiet', :js)", array('msg' => $rmsg, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		return true;
	}
	
	function _brains($params){
		$user = $params['user'];
		$channel = $params['channel'];
		$condition = $params['condition'];
		switch(strtolower($condition)) {
			case 'off':
			case 'end':
				$rmsg = ' turns off the throbbing brain.';
				$js = 'bareffect(function(){
	loader.js("/plugins/effectpack1/effectpack1.js", "effectpack1", function(){
		brains.stop();
	});
});';
				break;
			default:
				$rmsg = ' calls for brains. ';
				$conditions = explode(' ', $condition);
				$rmsg .= '<div class="braininfo">Screen Share URL: <a target="_blank" href="' . $conditions[0] . '">' . $conditions[0] . '</a>';
				$rmsg .= '<br>Phone Call-in: ' . $conditions[1] . '</div>';
				$js = 'bareffect(function(){
	loader.js("/plugins/effectpack1/effectpack1.js", "effectpack1", function(){
		brains.start();
		play("/plugins/effectpack1/brain/brains.mp3");
	});
});';
				break;
			case '':
				$rmsg = ' calls for brains.';
				$js = 'bareffect(function(){
	loader.js("/plugins/effectpack1/effectpack1.js", "effectpack1", function(){
		brains.start();
		play("/plugins/effectpack1/brain/brains.mp3");
	});
});';
				break;
		}

		DB::get()->query("INSERT INTO presence (data, type, user_id, channel, cssclass, js) VALUES (:msg, 'emote', :user_id, :channel, 'brains quiet', :js)", array('msg' => $rmsg, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		return true;
	}
	
	function _asplode($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$rmsg = ' asplodes.';
		$js = 'bareffect(function(){
	loader.js("/plugins/effectpack1/effectpack1.js", "effectpack1", function(){
		asplode.start();
		play("/plugins/effectpack1/asplode/asplode.mp3");
	});
});';

		DB::get()->query("INSERT INTO presence (data, type, user_id, channel, cssclass, js) VALUES (:msg, 'emote', :user_id, :channel, 'asplode quiet', :js)", array('msg' => $rmsg, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		return true;
	}

	function _crickets($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$rmsg = ' hears crickets.';
		$js = 'bareffect(function(){
	loader.js("/plugins/effectpack1/effectpack1.js", "effectpack1", function(){
		crickets.start();
		play("/plugins/effectpack1/cricket/cricket.mp3");
	});
});';

		DB::get()->query("INSERT INTO presence (data, type, user_id, channel, cssclass, js) VALUES (:msg, 'emote', :user_id, :channel, 'crickets quiet', :js)", array('msg' => $rmsg, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		return true;
	}

	function _ninja($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$rmsg = ' moves silently.';
		$js = 'bareffect(function(){
	loader.js("/plugins/effectpack1/ninja/ninja.js", "ninja", function(){
		ninja.start();
		play("/plugins/effectpack1/ninja/ninja.mp3");
	});
});';

		DB::get()->query("INSERT INTO presence (data, type, user_id, channel, cssclass, js) VALUES (:msg, 'emote', :user_id, :channel, 'ninja quiet', :js)", array('msg' => $rmsg, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		return true;
	}

	function _facepunch($params){
		$user = $params['user'];
		$channel = $params['channel'];
		$target = $params['name'];

		$rmsg = ' punches ' . htmlspecialchars($target) . ' in the <em>face</em>.';
		$js = 'bareffect(function(){
	loader.js("/plugins/effectpack1/facepunch/facepunch.js", "facepunch", function(){
		facepunch.start();
		play("/plugins/effectpack1/facepunch/punch1.mp3");
	});
});';

		DB::get()->query("INSERT INTO presence (data, type, user_id, channel, cssclass, js) VALUES (:msg, 'emote', :user_id, :channel, 'facepunch quiet', :js)", array('msg' => $rmsg, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		return true;
	}

	function herald($packed)
	{
		extract($packed); // herald, js, cssclass
		if(strpos($herald, ':redflag:') !== false) {
			$herald = str_replace(':redflag:', '', $herald);
			$js = 'bareffect(function(){
	loader.js("/plugins/effectpack1/effectpack1.js", "effectpack1", function(){
		redflag.start();
	});
});'; // javascript here
			$cssclass .= ' redflag';
		}
		return array('herald' => $herald, 'js' => $js, 'cssclass' => $cssclass);
	}

}

?>
