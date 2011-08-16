<?php

class BotrestController
{
	
	function __construct(){
		if(class_exists('Memcache')) {
			$this->m = new Memcache();
			$this->m->addServer('localhost', 11211);
		}
	}
	
	function _get_status(){
		$apc_failed = false;
		$memcached_failed = false;
		if(function_exists('apc_fetch')) {
			try{
				$laststatus = apc_fetch('presence_status');
			}
			catch(Exception $e) {
				$apc_failed = true;
			}
		}
		else {
			$apc_failed = true;
		}
		try{
			if(isset($this->m)) {
				$laststatus = $this->m->get('presence_status');
			}
		}
		catch(Exception $e) {
			$memcached_failed = true;
		}
		return $laststatus;
	}
	
	function _set_status($status){
		$apc_failed = false;
		$memcached_failed = false;
		if(function_exists('apc_store')) {
			try{
				$laststatus = apc_store('presence_status', $status);
			}
			catch(Exception $e) {
				$apc_failed = true;
			}
		}
		else {
			$apc_failed = true;
		}
		try{
			if(isset($this->m)) {
				$laststatus = $this->m->set('presence_status', $status);
			}
		}
		catch(Exception $e) {
			$memcached_failed = true;
		}
	}
	
	function notice($path)
	{
		global $config;
		
		$msg = $_GET['msg'];
		
		if(isset($_GET['html'])) {
		}
		else {
			$msg = htmlspecialchars($msg);
			$msg= preg_replace_callback('/\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i', array($this, '_urllink'), $msg);
		}
		$channel = '';
		if(isset($_GET['channel'])) {
			$channel = $_GET['channel'];
		}
		
		$cssclass = isset($_GET['cssclass']) ? $_GET['cssclass'] : '';
		
		$channels = DB::get()->col('SELECT name FROM channels GROUP BY name');
		DB::get()->query("INSERT INTO presence (data, user_id, channel, cssclass, type) VALUES (:msg, :user_id, :channel, :cssclass, 'notice')", array('msg' => $msg, 'user_id' => 0, 'channel' => $channel, 'cssclass' => $cssclass));

		$this->_set_status(DB::get()->val('SELECT max(status) FROM presence'));

		$laststatus = $this->_get_status();
		echo $laststatus;
	}
	
	function process($path)
	{
		global $config;
		
		Plugin::call('bot_process_' . reset($path));
	}
	
	private function _urllink($matches)
	{
		if(preg_match('%http://.*\.youtube\.com/watch\?v=([^?]+)%i', $matches[0], $yt)) {
			return <<< YOUTUBE
				<object width="425" height="344"><param name="movie" value="http://www.youtube-nocookie.com/v/{$yt[1]}&hl=en_US&fs=1&rel=0"></param><param name="wmode" value="transparent"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube-nocookie.com/v/{$yt[1]}&hl=en_US&fs=1&rel=0" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="425" height="344" wmode="transparent" ></embed></object>
YOUTUBE;
		}
		
		if(preg_match('%http://.*\.viddler\.com/explore/.+/videos/\d+%i', $matches[0], $vd)) {
			$v = file_get_contents($matches[0]);
			if(preg_match('%<link\s+rel="video_src"\s+href="([^"]+)"%', $v, $video)) {
				$url = $video[1];
				return <<< VIDDLER
					<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="437" height="370" id="viddler"><param name="movie" value="{$url}" /><param name="wmode" value="transparent"></param><param name="allowScriptAccess" value="always" /><param name="allowFullScreen" value="true" /><embed src="{$url}" width="437" height="370" type="application/x-shockwave-flash" allowScriptAccess="always" allowFullScreen="true" name="viddler" wmode="transparent" ></embed></object>
VIDDLER;
			}
		}

		if(preg_match('%http://.*\.hulu\.com/watch/\d+%i', $matches[0], $vd)) {
			$v = file_get_contents($matches[0]);
			if(preg_match('%<link\s+rel="video_src"\s+href="([^"]+)"%', $v, $video)) {
				$url = $video[1];
				return <<< HULU
					<object width="512" height="296"><param name="movie" value="{$url}"></param><param name="allowFullScreen" value="true"></param><embed src="{$url}" type="application/x-shockwave-flash" allowFullScreen="true"  width="512" height="296"></embed></object>
HULU;
			}
		}
				
		$purl = parse_url($matches[0]);
		$host = $purl['host'];
		$url = $purl['path'] . ($purl['query'] ? '?' . $purl['query'] : '');
		if($url == '') {
			$url = '/';
		}
		$port = $purl['port'] ? $purl['port'] : 80;
		$fp = fsockopen($host, $port, $errno, $errstr, 30);
		$ctype = array('','');
		if ($fp) {
			$out = "GET {$url} HTTP/1.1\r\n";
			$out .= "Host: {$host}\r\n";
			$out .= "Connection: Close\r\n\r\n";

			fwrite($fp, $out);
			while (!feof($fp)) {
				$header .= fgets($fp, 128);
				if(preg_match('%\n\n|\r\r|\n\r\n|\r\n\r%', $header)) {
					break;
				}
			}
			fclose($fp);
			preg_match('%Content-Type:\s*([^/]+)/([^;\n]+)%i', $header, $ctype);
		}
		if(strtolower($ctype[1]) == 'image') {
			return '<a href="'.$matches[0].'"><img src="'.$matches[0].'"></a>';
		}
		
		return '<a href="' . $matches[0] . '" target="_blank">' . $matches[0] . '</a>';
	}
	
}
?>