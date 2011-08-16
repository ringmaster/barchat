<?php

Class MarkovPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[2]['markov'] = array('/^\/markov$/i', array($this, '_markov'), CMD_LAST);
		return $cmds;
	}

	function _markov($params){
		$user = $params['user'];
		$channel = $params['channel'];
		
		$msg = Utils::cmdout($params);
		
		$data = DB::get()->val("SELECT data FROM presence WHERE data <> '' AND cssclass='' AND data NOT LIKE '%<%' AND user_id = :user_id ORDER BY RAND() LIMIT 1", array('user_id'=>$user->id));


		list($word,) = explode(' ', $data, 2);
		$output = $word . ' ';
		for($z = 0; $z < 20; $z++) {
			$data = DB::get()->val("SELECT data FROM presence WHERE data LIKE :chain AND cssclass='' AND data NOT LIKE '%<%' AND user_id = :user_id ORDER BY RAND() LIMIT 1", array('user_id'=>$user->id, 'chain' => '%' . $word . '%'));
			if($data) {
				$words = explode(' ', $data);
				$index = array_search($word, $words);
				if($index !== false) {
					if($word = $words[$index+1]) {
						$output .= $word . ' ';
					}
				}
			}
			else {
				break;
			}
		}
		$msg .= trim($output); 

		Status::create()
			->data($msg)
			->user_id($user->id)
			->cssclass('markov')
			->channel($channel)
			->insert();

		return true;
	}
}

?>