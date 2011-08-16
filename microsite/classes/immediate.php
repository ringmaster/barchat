<?php

class Immediate
{
	static $outs = array();
	
	static function create()
	{
		$i = new Immediate();
		self::$outs[] = $i;
		return $i;
	}
	
	public function laststatus($value = null) {
		$this->laststatus = self::set_status($value);
		return $this;
	}
	
	public function js($value) {
		$this->js = $value;
		return $this;
	}
	
	public static function output() {
		$js = '';
		$laststatus = 0;
		foreach(self::$outs as $out) {
			$laststatus = max($out->laststatus, $laststatus);
			$js .= $out->js;
		}

		$obj = new StdClass();
		$obj->laststatus = $laststatus;
		$obj->js = $js;
		
		echo json_encode($obj);
	}
	
	public static function debug($msg) {
		static $showdebug;
		
		if(!isset($showdebug)) {
			$showdebug = Option::get('Identity', 'debug');
		}
		if($showdebug) {
			Immediate::create()
				->js("addSystem({user_id:0, data: '<pre>" . str_replace("\n", '\n', addslashes($msg)) . "</pre>', cssclass: 'ok', username: '', nickname: '', status: " . microtime(true) . ", js:''}, '#notices');do_scroll();");
		}
	}
	
	public static function ok($msg, $class = 'ok') {
		if(!is_string($class)) {
			$class = 'ok';
		}
		Immediate::create()
			->js("addSystem({user_id:0, data: '" . str_replace("\n", '\n', addslashes($msg)) . "', cssclass: '" . $class . "', username: '', nickname: '', status: " . microtime(true) . ", js:''}, '#notices');do_scroll();");
	}

	public static function error($msg) {
		Immediate::create()
			->js("addSystem({user_id:0, data: '" . str_replace("\n", '\n', addslashes($msg)) . "', cssclass: 'error', username: '', nickname: '', status: " . microtime(true) . ", js:''}, '#notices');do_scroll();");
	}

	public static function get_status(){
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
/*
		try{
			if(class_exists('Memcache')) {
				$m = new Memcache();
				$m->addServer('localhost', 11211);
				if(isset($m)) {
					$laststatus = $m->get('presence_status');
				}
			}
		}
		catch(Exception $e) {
			$memcached_failed = true;
		}
*/
		return $laststatus;
	}

	public static function set_status($status = null){
		if(is_null($status)) {
			$status = DB::get()->val('SELECT max(status) FROM presence');
		}
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
/*
		try{
			if(class_exists('Memcache')) {
				$m = new Memcache();
				$m->addServer('localhost', 11211);
				if(isset($m)) {
					$laststatus = $m->set('presence_status', $status);
				}
			}
		}
		catch(Exception $e) {
			$memcached_failed = true;
		}
*/
		return $status;
	}
	
}

?>
