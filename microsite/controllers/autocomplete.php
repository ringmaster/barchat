<?php

class AutocompleteController
{
	function index($path){
		$cmd = $_POST['cmd'];

		$auto = array($_POST['cmd']);

		$auto = $this->_autocomplete($auto, $cmd);

		$users = DB::get()->col('SELECT username FROM users;');
		$nicks = DB::get()->col("SELECT value FROM options WHERE grouping = 'Identity' AND name = 'Nickname';");
		$users = $users + $nicks;
		$userregex = implode('|', array_map('preg_quote', $users));

		$cmd = preg_replace('%(' . $userregex . ')%i', '{$nickname}', $cmd);

		$autos = Plugin::call('autocomplete', $auto, $cmd);

		$auto = array();
		foreach($autos as $op) {
			if(strpos($op, '{$nickname}') === false) {
				$auto[] = $op;
			}
			else {
				foreach($users as $user) {
					$auto[] = str_replace('{$nickname}', $user, $op);
				}
			}
		}

		foreach($auto as $k => $v) {
			if($v[0] == '*') {
				$auto[$k] = substr($v,1);
				continue;
			}
			if(strncasecmp($cmd, $v, strlen($cmd)) != 0) {
				unset($auto[$k]);
			}
		}

		sort($auto);

		echo json_encode($auto);
	}

	function _autocomplete($auto, $cmd)
	{
		$users = DB::get()->col('SELECT username FROM users;');
		$nicks = DB::get()->col("SELECT value FROM options WHERE grouping = 'Identity' AND name = 'Nickname';");
		$users = $users + $nicks;

		$auto[] = "/msg {$nickname}";
		$auto[] = "/join \tchannel";
		$auto[] = "/part \tchannel";
		$auto[] = "/me \tdoes something";
		$auto[] = "/woodshed \ttarget";
		$auto[] = "/guitar";
		$auto[] = "/drag \tusername";
		$auto[] = "/kick \tusername";
		$auto[] = "s/";
		$auto[] = "/herald \t" . Auth::user()->nickname . " joins {\$room}";
		$auto[] = "-->\tcurrent status";
		$auto[] = "/img \tcriteria";
		$auto[] = "/wolframalpha \tquery";
		$auto[] = "/encode \tmessage";
		$auto[] = "/nickname \tnew nickname";
		$auto[] = "/quit \tparting message";
		$auto[] = "/lock";
		$auto[] = "/unlock";

		foreach($users as $user) {
			$auto[] = "{$user}, ";
		}
		if(strncasecmp($cmd, '/msg ', 5) == 0) {
			$auto[] = '/msg {$nickname}';
		}
		if($cmd == 'd' || strncasecmp($cmd, 'd ', 2) == 0) {
			$auto[] = 'd {$nickname}';
		}
		if(strncasecmp($cmd, '/drag ', 6) == 0) {
			$auto[] = '/drag {$nickname}';
		}

		return $auto;
	}

}

?>