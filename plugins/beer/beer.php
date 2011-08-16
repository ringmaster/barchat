<?php
class BeerPlugin extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['coffee1'] = array('%^/(coffee)\s+(?P<name>.+)$%i', array($this, '_coffee'), CMD_LAST);
		$cmds[1]['coffee2'] = array('%^/(coffee)\s*$%i', array($this, '_coffee'), CMD_LAST);
		$cmds[1]['beer'] = array('%^/(?P<drink>beer|lubricate)\s+(?P<name>.+)$%i', array($this, '_beer'), CMD_LAST);
		$cmds[1]['beer2'] = array('%^/(?P<drink>beer|lubricate)\s*$%i', array($this, '_beer'), CMD_LAST);
		$cmds[1]['sober'] = array('%^/soberup$%i', array($this, '_soberup'), CMD_LAST);
		$cmds[1]['tipsy'] = array('%^.+$%i', array($this, '_tipsy'), CMD_FORWARD);
		$cmds[1]['twitchy'] = array('%^.+$%i', array($this, '_twitchy'), CMD_FORWARD);
		return $cmds;
	}
	
	function _tipsy($msg, $params) {
		$user = $params['user'];
		$bal = DB::get()->val('SELECT value FROM options WHERE user_id = :user_id AND grouping = "beer" and name = "bal"', array('user_id' => $user->id));
		$lastbeer = DB::get()->val('SELECT value FROM options WHERE user_id = :user_id AND grouping = "beer" and name = "lastbeer"', array('user_id' => $user->id));
		$bal = max(0, $bal - ((strtotime('now') - $lastbeer) / 3600) * 0.04);

		if($bal >= 0.12) {
			$words = explode(' ', $msg);
			for($z = 0; $z < count($words); $z++) {
				$word = $words[$z];
				if(strlen($word) > 2) {
					$innards = substr($word, 1, -1);
					$innards = str_split($innards);
					shuffle($innards);
					$word = substr($word, 0, 1) . implode('', $innards) . substr($word, -1, 1);
					$words[$z] = $word;
				}
			}
			$words[] = '*>hic<*';
			
			$msg = implode(' ', $words);
		}
		return $msg;
	}

	function _twitchy($msg, &$params) {
		$user = $params['user'];
		$caffeine = DB::get()->val('SELECT value FROM options WHERE user_id = :user_id AND grouping = "coffee" and name = "caffeine"', array('user_id' => $user->id));
		$lastcoffee = DB::get()->val('SELECT value FROM options WHERE user_id = :user_id AND grouping = "coffee" and name = "lastcoffee"', array('user_id' => $user->id));
		$caffeine = max(0, $caffeine - ((strtotime('now') - $lastcoffee) / 3600) * 0.04);

		if($caffeine >= 0.12) {
			$params['cssclass'] .= ' twitchy';
		}
		return $msg;
	}

	function _soberup($params) {
		$user = $params['user'];
		$channel = $params['channel'];

		$output = Utils::cmdout($params);

		$msg = $user->nickname . ' takes an ice cold shower and attempts to sober up.'; 
		
		DB::get()->query('DELETE FROM options WHERE user_id = :user_id AND grouping = "beer" and name = "bal"', array('user_id' => $user->id));
		DB::get()->query('DELETE FROM options WHERE user_id = :user_id AND grouping = "beer" and name = "lastbeer"', array('user_id' => $user->id));
		DB::get()->query('DELETE FROM options WHERE user_id = :user_id AND grouping = "coffee" and name = "caffeine"', array('user_id' => $user->id));
		DB::get()->query('DELETE FROM options WHERE user_id = :user_id AND grouping = "coffee" and name = "lastcoffee"', array('user_id' => $user->id));

		$output .= $msg;

		DB::get()->query("INSERT INTO presence (data, user_id, channel, cssclass, js) VALUES (:msg, :user_id, :channel, 'beer', :js)", array('msg' => $output, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		return true;
	}
	
	function _beer($params){
		$user = $params['user'];
		$nickname = isset($params['name']) ? $params['name'] : 'me';
		$channel = $params['channel'];
		$drink = $params['drink'];
		switch(strtolower($drink)) {
			case 'lubricate':
				$drink = 'white russian';
				$drinkjs = 'whiterussian';
				$drinkbal = 0.14;
				break;
			default:
				$drinkjs = 'beer';
				$drink = 'beer';
				$drinkbal = 0.04;
				break;
		}

		$output = Utils::cmdout($params);
		
		$msg = $user->nickname . ' sends '.$nickname.' a '.$drink.'.';
		if( in_array(strtolower($nickname), array(strtolower($user->nickname), strtolower($user->username), 'me', 'myself'))) {
			$nickname = $user->nickname;
		}
		
		$target = Utils::user_from_name($nickname);
		if($target) {
			$restriction = DB::get()->val('SELECT value FROM options WHERE user_id = :user_id AND grouping = "beer" and name = "restriction"', array('user_id' => $target->id));
			if(!empty($restriction)) {
				$msg = $restriction;
			}
			else {
				// Get BAL
				$bal = DB::get()->val('SELECT value FROM options WHERE user_id = :user_id AND grouping = "beer" and name = "bal"', array('user_id' => $target->id));
				$lastbeer = DB::get()->val('SELECT value FROM options WHERE user_id = :user_id AND grouping = "beer" and name = "lastbeer"', array('user_id' => $target->id));
				$lastbeer = intval($lastbeer);
				if($lastbeer < strtotime('today') && (strtotime('now') - $lastbeer) > 2 * 60 * 60) {
					if( $target->id == $user->id ) {
						$msg = $user->nickname . ' orders a first '.$drink.' for himself and starts chugging.';
					}
					else {
						$msg = $user->nickname . ' sends '. $target->nickname .' the first '.$drink.' of the day.';
					}
					$bal = $drinkbal;
				}
				else {
					if($bal > 0.32) {
						if( $target->id == $user->id ) {
							$msg = $target->nickname . " dreams about ordering another {$drink} while passed out in a pool of his own vomit.";
						}
						else {
							$msg = $user->nickname . ' sends '. $target->nickname .' another '.$drink.' but ' . $target->nickname . " is unconscious, and can't drink any more.";
						}
					} 
					else {
						if( $target->id == $user->id ) {
							$msg = $user->nickname . ' orders another '.$drink.' and starts chugging.';
						}
						else {
							$msg = $user->nickname . ' sends '. $target->nickname .' another '.$drink.'.';
						}
						// process alcohol
						$bal = max(0, $bal - ((strtotime('now') - $lastbeer) / 3600) * 0.04) + $drinkbal;
						$balpct = floor($bal * 100);
						$msg .= '<br/>' . $target->nickname . "'s current BAL is {$balpct}%.";
						if( $bal > 0.32) {
							$msg .= '<br/>' . $target->nickname . " drinks the {$drink}, vomits violently all over the bar, and then passes out.";
						}
						elseif( $bal > 0.28) {
							$msg .= '<br/>' . $target->nickname . " drinks the {$drink}, but is unable to keep it down, and it and bits of lunch end up on the bar.";
						}
						elseif( $bal > 0.24) {
							$msg .= '<br/>' . $target->nickname . " sees the {$drink}, and being able to focus only on the beer, drinks it and burps loudly.";
						}
						elseif( $bal > 0.2) {
							$msg .= '<br/>' . $target->nickname . " can't stand up and is having trouble forming words, but drinks it anyway.";
						}
						elseif( $bal > 0.16) {
							$msg .= '<br/>' . $target->nickname . " has slurred speech and can't code any more, but drinks anyway.";
						}
						elseif( $bal > 0.12) {
							$msg .= '<br/>' . $target->nickname . " has amazing code quality for being so tipsy.";
						}
						elseif( $bal > 0.08) {
							$msg .= '<br/>' . $target->nickname . " is over the legal limit and feeling good.";
						}
					}
				}
				switch (intval(date('H'))) {
					case 0: // Where is happy hour at midnight?
						break;
					case 16:
					case 17:
					case 18:
						$msg .= "<br/>It's happy hour!";
						break;
				}
				DB::get()->query('DELETE FROM options WHERE user_id = :user_id AND grouping = "beer" and name = "bal"', array('user_id' => $target->id));
				DB::get()->query('DELETE FROM options WHERE user_id = :user_id AND grouping = "beer" and name = "lastbeer"', array('user_id' => $target->id));
				DB::get()->query('INSERT INTO options (user_id, grouping, name, value) VALUES (:user_id, "beer", "bal", :bal)', array('user_id' => $target->id, 'bal'=>$bal));
				DB::get()->query('INSERT INTO options (user_id, grouping, name, value) VALUES (:user_id, "beer", "lastbeer", :lastbeer)', array('user_id' => $target->id, 'lastbeer'=>time()));
			}
		}
		$output .= $msg;

		if(empty($restriction)) {

			$js = <<< BEERSCRIPT
bareffect(function(){beer('{$drinkjs}');});
BEERSCRIPT;
		}
		else {
			$js = '';
		}

		DB::get()->query("INSERT INTO presence (data, user_id, channel, cssclass, js) VALUES (:msg, :user_id, :channel, 'beer', :js)", array('msg' => $output, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		return true;
	}

	function _coffee($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$output = Utils::cmdout($params);
		$msg = array();

		if(isset($params['name'])) {
			if( in_array(strtolower($nickname), array(strtolower($user->nickname), strtolower($user->username), 'me', 'myself'))) {
				$nickname = $user->nickname;
			}
			else {
				$nickname = $params['name'];
			}
			$target = Utils::user_from_name($nickname);
		}
		else {
			$nickname = $user->nickname;
			$target = $user;
		}
		
		
		$restriction = DB::get()->val('SELECT value FROM options WHERE user_id = :user_id AND grouping = "coffee" and name = "restriction"', array('user_id' => $target->id));
		if(!empty($restriction)) {
			$msg[] = $restriction;
		}
		else {
			// process alcohol
			$bal = DB::get()->val('SELECT value FROM options WHERE user_id = :user_id AND grouping = "beer" and name = "bal"', array('user_id' => $target->id));
			$lastbeer = DB::get()->val('SELECT value FROM options WHERE user_id = :user_id AND grouping = "beer" and name = "lastbeer"', array('user_id' => $target->id));
			$lastbeer = intval($lastbeer);
			$bal = max(0, $bal - ((strtotime('now') - $lastbeer) / 3600) * 0.04);
			if($bal > 0) {
				$msg[] = 'The coffee helps ' . $target->nickname . " sober up a bit.";
				$bal = max(0, $bal - ((strtotime('now') - $lastbeer) / 3600) * 0.04 - 0.04);
				if($bal <= 0) {
					$msg[] = $target->nickname . " is now completely sober.";
				}
				DB::get()->query('DELETE FROM options WHERE user_id = :user_id AND grouping = "beer" and name = "bal"', array('user_id' => $target->id));
				DB::get()->query('DELETE FROM options WHERE user_id = :user_id AND grouping = "beer" and name = "lastbeer"', array('user_id' => $target->id));
				DB::get()->query('INSERT INTO options (user_id, grouping, name, value) VALUES (:user_id, "beer", "bal", :bal)', array('user_id' => $target->id, 'bal'=>$bal));
				DB::get()->query('INSERT INTO options (user_id, grouping, name, value) VALUES (:user_id, "beer", "lastbeer", :lastbeer)', array('user_id' => $target->id, 'lastbeer'=>time()));
			}

			// Get BAL
			$caffeine = DB::get()->val('SELECT value FROM options WHERE user_id = :user_id AND grouping = "coffee" and name = "caffeine"', array('user_id' => $target->id));
			$lastcoffee = DB::get()->val('SELECT value FROM options WHERE user_id = :user_id AND grouping = "coffee" and name = "lastcoffee"', array('user_id' => $target->id));
			$lastcoffee = intval($lastcoffee);
			if($lastcoffee < strtotime('today') && (strtotime('now') - $lastcoffee) > 2 * 60 * 60) {
				array_unshift($msg, $target->nickname . ' gets his first coffee of the day.');
				$caffeine = 0.04;
			}
			else {
				$precaf = $caffeine;
				$caffeine = max(0, $caffeine - ((strtotime('now') - $lastcoffee) / 3600) * 0.04) + 0.04;
				$caffeinepct = floor($caffeine * 100);
				$msg[] = $target->nickname . "'s caffeine level is {$caffeinepct}%.";
				if($precaf > 0.32) {
					array_unshift($msg, $target->nickname . " is maintaining a state of caffeinated nirvana.");
				} 
				else {
					array_unshift($msg, $target->nickname . ' gets another coffee.');

					// process caffeine
					if( $caffeine > 0.32) {
						$msg[] = $target->nickname . " is so jittery that he can barely hold his hands still enough to drink another coffee.";
					}
					elseif( $caffeine > 0.28) {
						$msg[] = $target->nickname . " is so caffeinated, he seems to be in three places at once.";
					}
					elseif( $caffeine > 0.24) {
						$msg[] = $target->nickname . " it speaking so fast that he sounds like Alvin from the chipmunks.";
					}
					elseif( $caffeine > 0.2) {
						$msg[] = $target->nickname . "'s left eye won't stop twitching.";
					}
					elseif( $caffeine > 0.16) {
						$msg[] = $target->nickname . " is frequently hitting the same key multiple times by accident.";
					}
					elseif( $caffeine > 0.12) {
						$msg[] = $target->nickname . " is buzzing between projects productively.";
					}
					elseif( $caffeine > 0.08) {
						$msg[] = $target->nickname . " is perked up.";
					}
				}
			}
			DB::get()->query('DELETE FROM options WHERE user_id = :user_id AND grouping = "coffee" and name = "caffeine"', array('user_id' => $target->id));
			DB::get()->query('DELETE FROM options WHERE user_id = :user_id AND grouping = "coffee" and name = "lastcoffee"', array('user_id' => $target->id));
			DB::get()->query('INSERT INTO options (user_id, grouping, name, value) VALUES (:user_id, "coffee", "caffeine", :bal)', array('user_id' => $target->id, 'bal'=>$caffeine));
			DB::get()->query('INSERT INTO options (user_id, grouping, name, value) VALUES (:user_id, "coffee", "lastcoffee", :lastcoffee)', array('user_id' => $target->id, 'lastcoffee'=>time()));
		}

		if(isset($params['name'])) {
			array_unshift($msg, $user->nickname . ' orders '.$target->nickname.' some coffee.');
		}		

		$output .= implode('<br />', $msg);

		if(empty($restriction)) {

			$js = <<< COFFEESCRIPT
bareffect(coffee);
COFFEESCRIPT;
		}
		else {
			$js = '';
		}
		
		DB::get()->query("INSERT INTO presence (data, user_id, channel, cssclass, js) VALUES (:msg, :user_id, :channel, 'coffee', :js)", array('msg' => $output, 'user_id' => $user->id, 'channel' => $channel, 'js' => $js));
		return true;
	}
	
	function header($args)
	{
		echo <<< HEADER
		<script src="/plugins/beer/beer.js" type="text/javascript"></script>
		<link href="/plugins/beer/beer.css" type="text/css" rel="stylesheet">
HEADER;
	}

		
}

?>