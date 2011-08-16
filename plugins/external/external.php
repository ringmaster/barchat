<?php

Class CExternal extends Plugin
{
	protected $statuses = array();
	
	function commands($cmds){
		$cmds[1]['external'] = array('%^/external\s+(?P<name>\w+)\s+(?P<url>.+?)$%i', array($this, '_register'), CMD_LAST);
		$cmds[1]['externals'] = array('%^.+$%i', array($this, '_externals'), CMD_LAST);
		return $cmds;
	}
	
	function _register($params) {
		$name = $params['name'];
		$url = $params['url'];
		$user = $params['user'];
		
		Option::create()
			->grouping('external')
			->name($name)
			->value($url)
			->insert();

		Status::create()
			->data('External created.')
			->type('system')
			->user_id($user->id)
			->user_to($user->id)
			->cssclass('ok')
			->insert();

		return true;
	}
	
	function _externals($params) {
		$p2 = $params;
		$p2['user'] = $p2['user']->std();
		$p2['chaturl'] = 'http://' . $_SERVER['HTTP_HOST'] . '/';
		$body = json_encode($p2);
		$user = $params['user'];
//Immediate::debug(htmlspecialchars(print_r($params,1)));return false;
		
		$externals = DB::get()->col("SELECT value FROM options WHERE grouping = 'external'");
		foreach($externals as $url) {
		
			$ch = curl_init();
			
			curl_setopt( $ch, CURLOPT_URL, $url ); // The URL.
			curl_setopt( $ch, CURLOPT_MAXREDIRS, 5 ); // Maximum number of redirections to follow.
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true ); // Follow 302's and the like.
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true ); // Return the data from the stream.
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 1 );

			curl_setopt( $ch, CURLOPT_POST, true ); // POST mode.
			curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query(array('p' => $body)) );
//			Immediate::create()
//				->js("addSystem({user_id:{$user->id}, data: '" . addslashes($body) . "', cssclass: 'error', username: '{$user->username}', nickname: '{$user->nickname}', status: " . microtime(true) . ", js:''}, '#notices');do_scroll();");
			
			$response = curl_exec( $ch );
			
			if ( curl_errno( $ch ) !== 0  ) {
				Immediate::create()
					->js("addSystem({user_id:{$user->id}, data: 'External \'" . addslashes($url) . "\' failed, with a curl error, " . curl_errno( $ch ) . ".', cssclass: 'error', username: '{$user->username}', nickname: '{$user->nickname}', status: " . microtime(true) . ", js:''}, '#notices');do_scroll();");
			}
			elseif( curl_getinfo( $ch, CURLINFO_HTTP_CODE ) !== 200 ) {
				//die('Not 200 response code');
				Immediate::create()
					->js("addSystem({user_id:{$user->id}, data: 'External \'" . addslashes($url) . "\' failed, with HTTP error code " . curl_getinfo( $ch, CURLINFO_HTTP_CODE ) . ".', cssclass: 'error', username: '{$user->username}', nickname: '{$user->nickname}', status: " . microtime(true) . ", js:''}, '#notices');do_scroll();");
			}
			else {
				Immediate::debug(htmlspecialchars($response));//return false;

				try {
					$xml = new SimpleXMLElement($response);
					
					foreach($xml->result as $result) {
						$status = Status::create();
						foreach($result->attributes() as $k => $v) {
							switch($k) {
								case 'append':
									break;
								case 'user':
									$remote_user = DB::get()->val("SELECT id FROM users WHERE username = ?", array($v));
									if(!$remote_user) {
										$remote_user = min(-1, DB::get()->val("SELECT min(id) FROM users") - 1);
										DB::get()->query("INSERT INTO users (id, username) VALUES (?, ?)", array($remote_user, $v));
										DB::get()->query("UPDATE users SET id = ? WHERE username = ?", array($remote_user, $v));
									}
									$status->user_id = $remote_user;
									break;
								default:
									$status->$k = $v;
									break;
							}
						}
						$status->data((string)$result);
						if(isset($result['append']) && $result['append'] == 'pre') {
							$status->insert();
						}
						else {
							$this->statuses[] = $status;
						}

						if($xml['silent'] == 'true') {
							return true;
						}

					}
				}
				catch(Exception $e) {
					Immediate::debug('RESPONSE: ' . htmlspecialchars($response));
				}
				
			}
			
			curl_close( $ch );
			
			return false;
		}
		
	}
	
	function send_done($param) {
		if(count($this->statuses) > 0) {
			foreach($this->statuses as $status) {
				$status->insert();
			}
		}
	}

	
	function bot_process_message() {
		$payload = $_POST['p'];
	
		try {
			$xml = new SimpleXMLElement($payload);
			
			if($xml['silent'] == 'true') {
				return true;
			}

			foreach($xml->result as $result) {
				$status = Status::create();
				foreach($result->attributes() as $k => $v) {
					switch($k) {
						case 'append':
							break;
						case 'user':
							$remote_user = DB::get()->val("SELECT id FROM users WHERE username = ?", array($v));
							if(!$remote_user) {
								$remote_user = min(-1, DB::get()->val("SELECT min(id) FROM users") - 1);
								DB::get()->query("INSERT INTO users (id, username) VALUES (?, ?)", array($remote_user, $v));
								DB::get()->query("UPDATE users SET id = ? WHERE username = ?", array($remote_user, $v));
							}
							$status->user_id = $remote_user;
							break;
						default:
							$status->$k = $v;
							break;
					}
				}
				$status->data((string)$result);
				$status->insert();
			}
		}
		catch(Exception $e) {
		}
	}

}

?>