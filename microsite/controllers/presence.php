<?php

/**
 * These are constants associated with command processors which determine how
 * the command callbacks are called (their function signatures).
 * 
 * CMD_LAST
 *  - Indicates that a command might be the last command to be processed for the
 *    message (if the callback returns true).
 *  - Callback Signature: callback(array $params) => boolean
 *  
 * CMD_FORWARD
 *  - Allows the callback to modify the message before it is stored/sent. The
 *    return value of the callback is the string message that will be used. This
 *    would be useful, for instance, for implementing emoticons, that replace
 *    part of the message.
 *  - Callback Signature: callback(string $message, array $params) => string
 * 
 * CMD_CONTINUE
 *  - Indicates that the original message should be stored/sent, but allows the
 *    callback to do something (such as add another message). This is basically
 *    the same as a CMD_LAST the returns false. The return for callbacks of this
 *    type is not used, but this could change -- so for now, return FALSE.
 *  - Callback Signature: callback(array $params) => FALSE
 */
define('CMD_LAST', 1);
define('CMD_FORWARD', 2);
define('CMD_CONTINUE', 3);

class PresenceController
{
	const COMET_SLEEP = 30;

	function poll($path)
	{
		global $config;
		$statuscode = $_POST['s'];
		$channel = $_POST['chan'];
		$user = Auth::user();

		$laststatus = Immediate::get_status();
		$obj = new StdClass();
		$obj->comet_change = false;
		for($z=0;$z<self::COMET_SLEEP;$z++) {
			$laststatus = Immediate::get_status();
			$laststatus = Plugin::call('poll', $laststatus, $statuscode, $user);
			if(intval($laststatus) != intval($statuscode)) {
				$obj->comet_change = true;
				break;
			}
			sleep(1);
		}

		$obj->status = $laststatus;
		$obj->reported_status = $statuscode;
		$obj->chanbar = $this->chanbar();
		$obj->channels = DB::get()->col("SELECT name FROM channels WHERE user_id = :user_id ORDER BY name ASC", array('user_id' => $user->id));
		$obj->channel = DB::get()->val("SELECT name FROM channels WHERE user_id = :user_id and active = 1", array('user_id' => $user->id));
		$obj->jsdate = filemtime(dirname(__FILE__) . '/../../js/sp.js');

		//$obj->updates = DB::get()->results('SELECT presence.status, presence.type, presence.channel, presence.data, presence.msgtime, presence.user_id, presence.class, presence.js, users.username FROM presence LEFT JOIN users ON presence.user_id = users.id WHERE (status > :oldstatus AND user_to = 0) OR (user_to = :user_id AND msgtime >= NOW()) OR (user_to <> 0 AND user_id = :user_id AND status > :oldstatus) ORDER BY msgtime DESC, status DESC LIMIT 100', array('oldstatus' => $statuscode, 'user_id' => Auth::user_id()));
		$obj->updates = DB::get()->results("
SELECT
	presence.*,
	users.username,
	options.value as nickname,
	channels.active as active
FROM
	presence
LEFT JOIN
	users
	ON presence.user_id = users.id
LEFT JOIN
	options
	ON options.user_id = users.id AND options.name = 'Nickname' AND options.grouping = 'Identity'
LEFT JOIN
	channels
	ON channels.user_id = :user_id 
	AND channels.name = presence.channel
WHERE
	(presence.status > :oldstatus)
	AND
	(
		(user_to = 0)
		OR
		(user_to = :user_id and isnull(received))
	)
ORDER BY
	status DESC
LIMIT 100
		", array('oldstatus' => $statuscode, 'user_id' => $user->id), 'StdClass');
		DB::get()->query('UPDATE presence SET received = msgtime, msgtime = NOW() WHERE isnull(received) AND user_to = :user_id', array('user_id' => $user->id));
		foreach($obj->updates as $k => $v) {
			$obj->updates[$k]->msgtime = date('Y-m-d H:i:s', strtotime($obj->updates[$k]->msgtime) + intval((string)Option::get('Time', 'Zone Offset')) * 3600);
		}


		$obj->updates = array_reverse($obj->updates);

		// Clean out logged-out users
		$loggedout = DB::get()->results('select channels.name, users.id, users.username, sessions.pingtime, channels.last, channels.active from users inner join sessions on sessions.user_id = users.id inner join channels on users.id = channels.user_id where timestampdiff(SECOND, sessions.pingtime, CURRENT_TIMESTAMP) > 300;');
		$loggedout = array();
		foreach($loggedout as $lo) {
			DB::get()->query('DELETE FROM channels WHERE name = :name AND user_id = :user_id;', array('name' => $lo->name, 'user_id' => $lo->id));

			$nick = DB::get()->val('SELECT username FROM users WHERE id = :user_id', array('user_id' => $lo->id));
			$msg = "{$nick} has parted from {$lo->name}";

			Status::create()
				->data($msg)
				->type('part')
				->channel($lo->name)
				->insert();
		}
		$loggedout = DB::get()->col('select sessions.id from sessions left join channels on channels.user_id = sessions.user_id where isnull(channels.user_id );');
		$loggedout = array();
		foreach($loggedout as $sid) {
			DB::get()->query('DELETE FROM sessions where id = ?', array($sid));
		}

		$obj->names = DB::get()->results('select channels.name, users.id, username, pingtime, last, active, value as nickname from channels, sessions, users, options where channels.user_id = users.id AND users.id = sessions.user_id and pingtime > now() - 120000 and channels.name = :channel and options.user_id = users.id and options.grouping = "Identity" and options.name="Nickname";', array('channel'=>$obj->channel), 'StdClass');
		$obj->namebar = $this->namebar();
		$obj->namebarmd5 = md5($obj->namebar);
		$obj->sups = DB::get()->val('SELECT count(*) as ct FROM presence, channels WHERE presence.channel = channels.name AND presence.msgtime > channels.last AND channels.active = 0 AND channels.user_id = :user_id AND presence.user_id <> :user_id AND presence.type <> "status";', array('user_id' => Auth::user_id()));
		$obj->drawers = DB::get()->results('SELECT * FROM drawers WHERE (channel = :channel OR isnull(channel) OR channel = "") AND user_id = :user_id ORDER BY added DESC;', array('channel' => $channel, 'user_id' => Auth::user_id()), 'StdClass');
		$obj->decor = DB::get()->assoc("SELECT name, value FROM options WHERE room = :channel AND grouping = 'decor';", array('channel' => $obj->channel));
		$obj->user_id = $user->id;

		if(strpos($obj->channel, ':') > 0) {
			list($roomtype, $criteria) = explode(':', $obj->channel, 2);
		}
		else {
			$roomtype = '';
			$criteria = $obj->channel;
		}
		$obj = Plugin::call('response_obj', $obj, $roomtype, $criteria);

		Immediate::set_status();
		
		echo json_encode($obj);
	}
	
	function getdrawers($path) {
		$channel = $_POST['channel'];
		
		echo json_encode(DB::get()->results('SELECT * FROM drawers WHERE (channel = :channel OR isnull(channel) OR channel = "") AND user_id = :user_id ORDER BY added DESC;', array('channel' => $channel, 'user_id' => Auth::user_id()), 'StdClass'));
	}

	function _clear($params){
		if(treu || substr($_SERVER["SERVER_ADDR"],0,8) == '192.168.') {
			DB::get()->query('DELETE FROM presence;');
			$msg = "<strong>Cleared.</strong>";
		}
		else { 
			$msg = "<strong>I told {$params['user']->nickname} not to type /clear, but they did anyway.</strong>";
		}
		
		Status::create()
			->data($msg)
			->type('notice')
			->user_id($params['user']->id)
			->insert();
		Immediate::set_status(0);
		return true;
	}
	
	function _room_alias($name) {
		$pjoin = DB::get()->val("SELECT value FROM options WHERE grouping = 'Rooms' AND name = 'alias' AND user_id = 0 AND room = :join", array('join' => $name));
		return $pjoin ? $pjoin : $name;
	}

	function _join($params){
		$commands = $params['commands'];
		$user = $params['user'];
		$channel = $params['channel'];
		$join = $params['join'];

		$pjoin = DB::get()->val("SELECT room FROM options WHERE grouping = 'Rooms' AND name = 'alias' AND user_id = 0 AND value = :join", array('join' => $join));
		if($pjoin != '') {
			$join = $pjoin;
		}
		
		
		$allowedchannels = DB::get()->col("SELECT room FROM options WHERE grouping = 'Permissions' AND name = 'allowedchannel' AND user_id = :user_id", array('user_id' => $user->id));
		if($allowedchannels) {
			if(!in_array($join, $allowedchannels)) {
				Status::create()
					->data('Sorry, you do not have permission to enter that channel.')
					->user_id($user->id)
					->type('system')
					->cssclass('error')
					->user_to($user->id)
					->insert();
				return true;
			}
		}
		$deniedchannels = DB::get()->col("SELECT room FROM options WHERE grouping = 'Permissions' AND name = 'deniedchannel' AND user_id = :user_id", array('user_id' => $user->id));
		if($deniedchannels) {
			if(in_array($join, $deniedchannels)) {
				Status::create()
					->data('Sorry, you do not have permission to enter that channel.')
					->user_id($user->id)
					->type('system')
					->cssclass('error')
					->user_to($user->id)
					->insert();
				return true;
			}
		}
		
		$inchannel = DB::get()->val('SELECT count(*) FROM channels WHERE name = :name AND user_id = :user_id', array('name' => $join, 'user_id' => $user->id));
		
		//is the channel locked?
		if(!isset($params['silent'])) {
			$locks = DB::get()->val("SELECT count(*) FROM options WHERE grouping = 'locks' AND room = :channel AND user_id <> :user_id", array('channel' => $join, 'user_id' => $user->id));
			if($locks > 0 && $inchannel == 0) {
				Status::create()
					->data(htmlspecialchars($user->nickname) . ' is knocking on the locked door of this channel.')
					->user_id($user->id)
					->type('system')
					->cssclass('error')
					->channel($join)
					->insert();
				Status::create()
					->data('Sorry, that channel is currently locked.')
					->user_id($user->id)
					->type('system')
					->cssclass('error')
					->user_to($user->id)
					->insert();
				
				Immediate::set_status();
				return true;
			}
		}
		
		if($inchannel == 0) {
			DB::get()->query('INSERT INTO channels (name, user_id, last) VALUES (:name, :user_id, NOW());', array('name' => $join, 'user_id' => $user->id));

			if(!($herald = DB::get()->val("SELECT value FROM options WHERE user_id = :user_id AND name = :name AND grouping = :grouping", array('user_id' => $user->id, 'name' => 'Herald', 'grouping' => 'Identity')))) {
				$herald = '{$nickname} has joined {$room}';
			}
			$js = '';
			$cssclass = '';
			$packed = Plugin::call('herald', array('herald' => $herald, 'js' => $js, 'cssclass' => $cssclass));
			extract($packed);
			$herald = str_replace('{$nickname}', $user->nickname, $herald);
			$herald = str_replace('{$room}', $this->_room_alias($join), $herald);
			$msg = htmlspecialchars($herald);
			Status::create()
				->data($msg)
				->type('join')
				->cssclass($cssclass)
				->channel($join)
				->js($js)
				->insert();
		}

		if(!isset($params['silent'])) {
			Immediate::create()
				->laststatus()
				->js('setRoom("'.addslashes($join).'");');
		}

		return true;
	}

	function _search($params){
		$commands = $params['commands'];
		$user = $params['user'];
		$search = $params['search'];

		$searchexists = DB::get()->val("SELECT name FROM options WHERE grouping = 'searches' AND user_id = :user_id AND value = :value", array('user_id' => $user->id, 'value' => $search));

		if($searchexists) {
			Immediate::create()
				->laststatus()
				->js('setRoom("search:'.addslashes($searchexists).'");');
		}
		else {
		
			$s = $p = '';
			$searchname = $this->_get_search_sql($s, $p, $params['search'], true);
			$join = 'search:' . $searchname;
	
			$inchannel = DB::get()->val('SELECT count(*) FROM channels WHERE name = :name AND user_id = :user_id', array('name' => $join, 'user_id' => $user->id));
			$index = 0;
			while($inchannel > 0) {
				$inchannel = DB::get()->val('SELECT count(*) FROM channels WHERE name = :name AND user_id = :user_id', array('name' => $join . '-' . ++$index, 'user_id' => $user->id));
			}
			if($index > 0) {
				$searchname .= '-' . $index;
				$join = 'search:' . $searchname;
			}
			DB::get()->query('INSERT INTO channels (name, user_id, last) VALUES (:name, :user_id, NOW());', array('name' => $join, 'user_id' => $user->id));
			
			DB::get()->query("INSERT INTO options (user_id, name, grouping, value) VALUES (:user_id, :name, 'searches', :value);", array('user_id' => $user->id, 'name' => $searchname, 'value' => $search));
			
			Immediate::create()
				->laststatus()
				->js('setRoom("search:'.addslashes($searchname).'");');
		}

		return true;
	}
	
	function _part($params){
		$partchan = $params['channel'];
		$user = $params['user'];
		if(isset($params['part']) && $params['part'] != '') {
			$partchan = $params['part'];
		}
		DB::get()->query('DELETE FROM channels WHERE name = :name AND user_id = :user_id;', array('name' => $partchan, 'user_id' => $user->id));
		if(preg_match('%^search:(?P<criteria>.+)$%i', $partchan, $searchmatches)) {
			DB::get()->query("DELETE FROM options WHERE name = :name AND grouping = 'searches' AND user_id = :user_id;", array('name' => $searchmatches['criteria'], 'user_id' => $user->id));
		}
		else {
			Status::create()
				->data("{$user->username} has parted from {$partchan}")
				->type('part')
				->channel($partchan)
				->insert();
		}
		
		if(DB::get()->val('SELECT count(*) FROM channels WHERE user_id = :user_id;', array('user_id' => $user->id)) == 0) {
			$this->_quit($params);
		}
		else {
			DB::get()->query('UPDATE channels SET active = 1 where user_id = :user_id order by active desc, name limit 1', array('user_id' => $user->id));
			$room = DB::get()->val('SELECT name FROM channels WHERE user_id = :user_id and active = 1;', array('user_id' => $user->id));

			Immediate::create()
				->laststatus()
				->js('setRoom("'.addslashes($room).'");');
		}
		return true;
	}

	function _emote($params){
		$user = $params['user'];
		$channel = $params['channel'];

		Status::create()
			->data(htmlspecialchars($params['me']))
			->user_id($user->id)
			->type('emote')
			->channel($channel)
			->insert();

		return true;
	}

	function _woodshed($params){
		$user = $params['user'];
		$channel = $params['channel'];

		Status::create()
			->data('takes ' . htmlspecialchars($params['target']) . ' to the woodshed...')
			->type('emote')
			->user_id($user->id)
			->cssclass('woodshed')
			->channel($channel)
			->js('bareffect(effect_woodshed);')
			->insert();

		return true;
	}

	function _guitar($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$rmsg = $params['guitar'];
		if(trim($rmsg) == '') {
			$rmsg = '>>> AiR gUiTaR <<<';
		}
		$rmsg = htmlspecialchars($rmsg);
		$rnd = rand(1, 5);
		$rmsg = '<button onclick="play(\'/effects/guitar/airguitar' . $rnd . '.mp3\');">Play</button>' . $rmsg;
		$js = 'bareffect(function(){play("/effects/guitar/airguitar' . $rnd . '.mp3");});';

		Status::create()
			->data($rmsg)
			->user_id($user->id)
			->cssclass('guitar')
			->channel($channel)
			->js($js)
			->insert();
		
		return true;
	}

	function _userstr(&$m){
		static $userlist = false;

		if(!$userlist) {
			$userlist = DB::get()->results("SELECT users.*, options.value as nickname FROM users LEFT JOIN options ON options.user_id = users.id AND name = 'Nickname' AND grouping = 'Identity' ORDER BY LENGTH(username) DESC");
		}
		$m = trim($m);
		foreach($userlist as $user) {
			if(strlen($user->username) > strlen($user->nickname)) {
				$us = array($user->username, $user->nickname);
			}
			else {
				$us = array($user->nickname, $user->username);
			}
			foreach($us as $u) {
				if(!$u) continue;
				if(stripos($m, $u) === 0) {
					$m = trim(substr($m, strlen($u)));
					return $user;
				}
			}
		}
		return false;
	}

	function _msg($params){
		$user = $params['user'];
		$d = $params['d'];

		$myusername = $user->username;
		$user_to = $this->_userstr($d);
		$rmsg = htmlspecialchars($d);
		$rmsg = nl2br($rmsg);

		if(is_object($user_to) && $user_to->id > 0) {

			Status::create()
				->data('<div class="slash">Direct message from <em>' . htmlspecialchars($myusername) . '</em></div>' . $rmsg)
				->type('direct')
				->user_id($user->id)
				->user_to($user_to->id)
				->cssclass('direct')
				->insert();

			$pms = intval((string)Option::get('pm', $user_to->username));
			if($pms > 2) {
				$addon = '<br/><a href="#" style="font-size:xx-small;" onclick="send(\'/join office:' . $user->username . '\');send(\'/drag ' . $user_to->username . '\');return false;">Why not invite ' . htmlspecialchars($user_to->username) . ' to your office?</a>'; 
			}
			else {
				$addon = '';
			}

			Status::create()
				->data('<div class="slash">Direct message to <em>' . htmlspecialchars($user_to->username) . '</em></div>' . $rmsg . $addon)
				->type('direct')
				->user_id($user->id)
				->user_to($user->id)
				->cssclass('direct')
				->insert();
			
			if($pms == 0) {
				DB::get()->query("INSERT INTO options (user_id, name, grouping, value) VALUES (:user_id, :name, 'pm', 1);", array('user_id' => $user->id, 'name' => $user_to->username));
			}
			else {
				DB::get()->query("UPDATE options SET value = :pms WHERE user_id = :user_id and name = :name and grouping = 'pm';", array('user_id' => $user->id, 'name' => $user_to->username, 'pms' => $pms + 1));
			}
			
		}
		else {
			Status::create()
				->data('That is not a valid username.')
				->type('system')
				->user_to($user->id)
				->cssclass('error')
				->insert();
		}
		return true;
	}

	function _drag($params){
		$user = $params['user'];
		$channel = $params['channel'];
		$names = explode(',', $params['names']);
		$myusername = $user->username;
		while(count($names) > 0) {
			$username = trim(array_shift($names));
			$user_to = DB::get()->row("SELECT users.id, ISNULL(pingtime) as never, timestampdiff(second, pingtime, now()) as since FROM users left join sessions on sessions.user_id = users.id WHERE username = :username", array('username' => $username));
			
			if($user_to) {
				$heavy = DB::get()->val("SELECT value FROM options WHERE grouping = 'Identity' AND name = 'heavy' AND user_id = ?", array($user_to->id));

				if($heavy == 'true') {
					DB::get()->query("INSERT INTO presence (data, user_id, type, cssclass, user_to, channel, js) VALUES (:msg, :user_id, 'system', 'error', :user_to, '', :js)", array('msg' => '"' . htmlspecialchars($username) . '" is too heavy to drag.', 'user_id' => $user->id, 'user_to' => $user->id, 'js'=>'bareffect(function(){play("bass")});'));
				}
				elseif($user_to->since > 60 || $user_to->never) {
					DB::get()->query("INSERT INTO presence (data, user_id, type, cssclass, user_to, channel, js) VALUES (:msg, :user_id, 'system', 'error', :user_to, '', :js)", array('msg' => '"' . htmlspecialchars($username) . '" is not currently online.', 'user_id' => $user->id, 'user_to' => $user->id, 'js'=>'bareffect(function(){play("bass")});'));
				}
				else {
					$inchannel = DB::get()->val('SELECT count(*) FROM channels WHERE name = :name AND user_id = :user_id', array('name' => $channel, 'user_id' => $user_to->id));
					if($inchannel == 0) {
						//DB::get()->query('INSERT INTO channels (name, user_id, last) VALUES (:name, :user_id, NOW());', array('name' => $channel, 'user_id' => $user_to->id));
						$nick = DB::get()->val("SELECT value FROM options WHERE name = 'Nickname' AND options.grouping = 'Identity' AND user_id = :user_id", array('user_id' => $user_to->id));
						if($nick) {
							$user_to->nickname = $nick; 
						}
						else {
							$user_to->nickname = $user_to->username; 
						}
						$this->_join(array(
							'user' => $user_to,
							'join' => $channel,
							'silent' => true,
						));
						Status::create()
							->data('<div class="slash"><em>' . htmlspecialchars($myusername) . '</em> has dragged you into the channel <a href="#" onclick="joinRoom(\'' . addslashes($channel) . '\');return false;">' . $channel . '</a></div>')
							->type('direct')
							->user_id($user->id)
							->user_to($user_to->id)
							->cssclass('direct')
							->insert();
					}
					else {
						Status::create()
							->data('"' . htmlspecialchars($username) . '" is already in this channel.')
							->type('system')
							->user_id($user->id)
							->user_to($user->id)
							->cssclass('error')
							->js('bareffect(function(){play("bass")});')
							->insert();
					}
				}
			}
			else {
				Status::create()
					->data('"' . htmlspecialchars($username) . '" is not a valid username.  <br><small>(Syntax to drag into current channel:  /drag {username1}[,{username2}[,&hellip;]])</small>')
					->type('system')
					->user_id($user->id)
					->user_to($user->id)
					->cssclass('error')
					->js('bareffect(function(){play("bass")});')
					->insert();
			}
		}
		return true;
	}

	function _kick($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$names = explode(',', $params['names']);
		$myusername = $user->username;
		while(count($names) > 0) {
			$username = trim(array_shift($names));
			$user_to = DB::get()->row("SELECT users.id, ISNULL(pingtime) as never, timestampdiff(second, pingtime, now()) as since FROM users left join sessions on sessions.user_id = users.id WHERE username = :username", array('username' => $username));

			if($user_to) {
				if($user_to->since > 60 || $user_to->never) {
					DB::get()->query("INSERT INTO presence (data, user_id, type, cssclass, user_to, channel, js) VALUES (:msg, :user_id, 'system', 'error', :user_to, '', :js)", array('msg' => '"' . htmlspecialchars($username) . '" is not currently online.', 'user_id' => $user->id, 'user_to' => $user->id, 'js'=>'bareffect(function(){play("bass")});'));
				}
				else {
					$inchannel = DB::get()->val('SELECT count(*) FROM channels WHERE name = :name AND user_id = :user_id', array('name' => $channel, 'user_id' => $user_to->id));
					if($inchannel == 0) {
						DB::get()->query("INSERT INTO presence (data, user_id, type, cssclass, user_to, channel, js) VALUES (:msg, :user_id, 'system', 'error', :user_to, '', :js)", array('msg' => '"' . htmlspecialchars($username) . '" is not in this channel.', 'user_id' => $user->id, 'user_to' => $user->id, 'js'=>'bareffect(function(){play("bass")});'));
					}
					else {
						$kickid = 'kid' . Immediate::get_status() . '_' . $user_to->id;
						if($channel == 'office:' . $user->username) {
							DB::get()->query('DELETE FROM channels WHERE name = :name AND user_id = :user_id;', array('name' => $channel, 'user_id' => $user_to->id));
							DB::get()->query("INSERT INTO presence (data, user_id, type, cssclass, user_to, channel, js) VALUES (:msg, :user_id, 'direct', 'direct', :user_to, '', :js)", array('msg' => '<div class="slash"><em>' . htmlspecialchars($myusername) . '</em> has kicked you from his office.</div>', 'user_id' => $user->id, 'user_to' => $user_to->id, 'js'=>'bareffect(function(){partRoom(\'' . htmlspecialchars($channel) . '\')});'));
						}
						else {
							Status::create()
								->data('<div class="slash"><em>' . htmlspecialchars($myusername) . '</em> has kicked you from this channel.  <a href="#" onclick="return abortKick(\'' . $kickid . '\');">Abort?</a></div>')
								->type('direct')
								->user_id($user->id)
								->user_to($user_to->id)
								->cssclass('direct')
								->js('bareffect(function(){startKick(\'' . $kickid . '\', \'' . htmlspecialchars($channel) . '\')});')
								->insert();
						}
					}
				}
			}
			else {
				Status::create()
					->data('"' . htmlspecialchars($username) . '" is not a valid username.  <br><small>(Syntax to kick from the current channel:  /kick {username1}[,{username2}[,&hellip;]])</small>')
					->type('system')
					->user_id($user->id)
					->user_to($user->id)
					->cssclass('error')
					->js('bareffect(function(){play("bass")});')
					->insert();
			}
		}
		return true;
	}

	function _lock($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		
		DB::get()->query("DELETE FROM options WHERE user_id = :user_id AND name = 'lock' AND grouping = 'locks' AND room = :channel;", array('user_id' => $user->id, 'channel' => 'office:' . $user->username));
		DB::get()->query("INSERT INTO options (user_id, name, room, grouping, value) VALUES (:user_id, 'lock', :channel, 'locks', 1);", array('user_id' => $user->id, 'channel' => 'office:' . $user->username));

		Status::create()
			->data('Your office is now locked.')
			->type('system')
			->user_id($user->id)
			->user_to($user->id)
			->cssclass('ok')
			->insert();

		return true;
	}

	function _unlock($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		
		DB::get()->query("DELETE FROM options WHERE user_id = :user_id AND name = 'lock' AND grouping = 'locks' AND room = :channel;", array('user_id' => $user->id, 'channel' => 'office:' . $user->username));

		Status::create()
			->data('Your office is now unlocked.')
			->type('system')
			->user_id($user->id)
			->user_to($user->id)
			->cssclass('ok')
			->insert();

		return true;
	}

	function _replace($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$flags = $params['flags'];
		if($flags == '') {
			$flags = 'gi';
		}

		$statuses = DB::get()->assoc("SELECT status, data FROM presence WHERE user_id = :user_id AND type = 'message' AND data <> '' ORDER BY msgtime DESC LIMIT 10", array('user_id' => $user->id));
		$status = false;
		foreach($statuses as $istatus => $data) {
			if(preg_match('%' . str_replace('%', '\\%', $params['from']) . '%i', $data, $patterns)) {
				if($patterns[0] == $data && $params['to'] == '') {
					$params['to'] = '<em>redacted</em>';
				}
				$status = $istatus;
				break;
			}
		}
		$params['to'] = addslashes($params['to']);
		$params['from'] = addslashes($params['from']);
		
		if($status) {

	    include_once "Text/Diff.php";
	    include_once "Text/Diff/Renderer.php";
	    include_once "Text/Diff/Renderer/inline.php";
	
			$diff = &new Text_Diff(explode("\n", $statuses[$status]), explode("\n", preg_replace('%' . str_replace('%', '\\%', $params['from']) . '%i' , $params['to'], $statuses[$status]) ));
			$renderer = &new Text_Diff_Renderer_inline();
	  	$replacement = $renderer->render($diff);
			$replacement = addslashes($replacement);
	  	$replacement = str_replace("\n", '\n', $replacement);
	
			$js = <<< REPLJS
retcon({$status}, '{$replacement}');
REPLJS;

			Status::create()
				->user_id($user->id)
				->js($js)
				->insert();

			return true;
		}
		return false;
	}

	function _alias($params){
		$user = $params['user'];
		$channel = $params['channel'];

		if($params['from'][0] != '/') {
			Status::create()
				->data('An alias must start with a slash.')
				->user_id($user->id)
				->type('system')
				->cssclass('error')
				->user_to($user->id)
				->insert();
		}
		else {
			$from = '%^' . str_replace('%', '\%', $params['from']) . '$%i';
			$to = $params['to'];
		
			DB::get()->query("DELETE from options WHERE grouping = 'Alias' and name = :from and room = :room and user_id = :user_id;", array('from' => $from, 'room' => $channel, 'user_id' => $user->id));
			DB::get()->query("INSERT INTO options (grouping, name, value, room, user_id) VALUES ('Alias', :from, :to, :room, :user_id);", array('from' => $from, 'to' => $to, 'room' => $channel, 'user_id' => $user->id));

			Status::create()
				->data('Added alias "' . htmlspecialchars($params['from']) . '" for "' . htmlspecialchars($to) . '".')
				->user_id($user->id)
				->type('system')
				->channel($channel)
				->cssclass('ok')
				->insert();
		}
		return true;
	}

	function _reload($params){
		$user = $params['user'];
		$channel = $params['channel'];

		Status::create()
			->data(htmlspecialchars($user->nickname) . ' initiated a barchat reload.')
			->user_id($user->id)
			->type('system')
			->cssclass('ok')
			->js('if(!allmute) location.reload();')
			->insert();
		return true;
	}

	function _image($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$json = file_get_contents('http://api.search.live.net/json.aspx?Appid=DD6B0932E689774B8CF034392600CFEEE56A5636&sources=image&Image.Count=5&query=' . urlencode($params['query']));
		$data = json_decode($json);
		$rmsg = '<span class="query" style="display:none;">' . $params['query'] . '</span>';
		foreach($data->SearchResponse->Image->Results as $result) {
			$rmsg .= '<a href="' . $result->MediaUrl . '" target="_blank" class="choice cmd_image"><img src="' . $result->Thumbnail->Url . '" width="100px"></a>';
		}

		$rmsg .= '<div style="clear:both;">Choose one of the images above to send, or <a href="#" class="cancel" onclick="$(this).parents(\'.drawers\').remove();send(\'/image_complete * ' . addslashes($params['query']) . '\');return false;">cancel this request</a>.</div>';

		Status::create()
			->user_id($user->id)
			->type('choice')
			->cssclass('image')
			->user_to($user->id)
			->channel($channel)
			->insert();

		DB::get()->query(
		"INSERT INTO drawers (user_id, channel, message, js, indexed) VALUES (:user_id, :channel, :message, :js, :iid);",
		array(
				'user_id' => $user->id,
				'channel' => $channel,
				'message' => $rmsg,
				'js' => '',
				'iid' => 'i__' . $params['query'],
			)
		);
		
		
		return true;
	}

	function _image_complete($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$src = $params['src'];
		$href = $params['href'];
		$query = $params['query'];

		$src = UrlController::tos3($src);
		
		if($href != '*') {

			$href = UrlController::tos3($href);
			$rmsg = '<div class="slash">/image <em>' . htmlspecialchars($params['query']) . '</em></div>';
			$rmsg .= '<a href="' . $href . '" target="_blank" class="dragfloat"><img onload="do_scroll();" src="' . $src . '"></a>';
	
			Status::create()
				->data($rmsg)
				->user_id($user->id)
				->cssclass('image')
				->channel($channel)
				->insert();
		}
		DB::get()->query("DELETE FROM drawers WHERE indexed = :iid", array('iid' => 'i__' . $params['query']));

		return true;
	}

	function _wolframalpha($params){
		$user = $params['user']; 
		$channel = $params['channel'];

		$data = file_get_contents('http://www.wolframalpha.com/input/?i=' . urlencode($params['query']));
		preg_match_all('%<div\s[^>]*class="pod\s*".+?<h\d>(?P<title>.+?):</h\d>.*?(?P<image><img.+?'.'>)%si', $data, $podmatches, PREG_SET_ORDER);
		$output = Utils::cmdout($params);
		$index = 0;
		$slug = preg_replace('%\W+%', '-', $params['query']);
		foreach($podmatches as $pod) {
			$index++;
			if(preg_match('%\bsrc\s*=\s*(?P<quote>["\'])(?P<src>.+?)\1%', $pod['image'], $matches)) {
				$s3src = URLController::tos3($matches['src'], $slug . '-' . $index . '.gif');
				$output .= '<div><div class="podtitle">' . $pod['title'] . '</div><img class="s3ed" src="' . $s3src . '" onload="do_scroll();"></div>';
			}
			else {
				$output .= '<div><div class="podtitle">' . $pod['title'] . '</div>' . $pod['image'] . '</div>';
			}
		}

		Status::create()
			->data($output)
			->user_id($user->id)
			->channel($channel)
			->cssclass('wolframalpha')
			->insert();
		return true;
	}

	function _say($params){
		$user = $params['user'];
		$channel = $params['channel'];

		if(preg_match('%^search:%i', $channel)) {
			$obj = new StdClass();
			$obj->laststatus = $laststatus;
			$obj->js = "addSystem({user_id:{$user->id}, data: 'You cannot send messages to a search channel.', cssclass: 'error', username: '{$user->username}', nickname: '{$user->nickname}', status: " . microtime(true) . ", js:''}, '#notices');";
			echo json_encode($obj);
			die();
		}
		
		$rmsg = $params['msg'];

		if($rmsg == '') {
			return;
		}
		
		$lastmsg = DB::get()->row("SELECT status, user_id, data, type, msgtime, NOW() as n FROM presence WHERE channel = :channel ORDER BY msgtime DESC LIMIT 1", array('channel' => $channel));
		$oldermsg = true;
		while($lastmsg->data == '' && $oldermsg){
			$oldermsg = DB::get()->row("SELECT status, data FROM presence WHERE channel = :channel and status < :status ORDER BY msgtime DESC LIMIT 1", array('channel' => $channel, 'status' => $lastmsg->status));
			$lastmsg->status = $oldermsg->status;
			$lastmsg->data = $oldermsg->data;
		}

		$lastdata = str_replace("<br/>", '', $lastmsg->data);
		if(
			strpos($rmsg, '<') === false && // the new message has no html in it
			$lastmsg && // the last message exists
			$lastmsg->user_id == $user->id && // the last message was by the last user
			$lastmsg->type == 'message' && // the last message was a message
			strpos($lastdata, '<') === false && // the last message contains no HTML
			strpos($lastmsg->data, '/') !== 0 && // the last message was not a command
			strpos($rmsg, '/') !== 0 && // this message is not a command
			strtotime($lastmsg->n) - strtotime($lastmsg->msgtime) <= 30 // the last message was sent in the last 30 seconds
		) {
			$rmsg = "<br/>\n" . $rmsg;
			DB::get()->query("UPDATE presence SET data = CONCAT(data, :msg) WHERE status = :status", array('msg' => $rmsg, 'status' => $lastmsg->status));
			// Insert the smartstatus update into the database
			$js = "bareffect(function(){reloadstatus({$lastmsg->status});});";
			Status::create()
				->data('')
				->user_id($user->id)
				->cssclass('quiet')
				->channel($channel)
				->js($js)
				->insert();
		}
		else {
			$cssclass = isset($params['cssclass']) ? $params['cssclass'] : '';
			
			// Insert the display value into the database
			Status::create()
				->data($rmsg)
				->user_id($user->id)
				->cssclass($cssclass)
				->channel($channel)
				->insert();
		}

		DB::get()->query("DELETE FROM options WHERE user_id = :user_id AND grouping = 'pm';", array('user_id'=>$user->id));

		return false;
	}

	function _kill($params){
		$user = $params['user'];
		$channel = $params['channel'];
		$status = $params['status'];

		if($params['last'] == 'last') {
			$status = DB::get()->val('SELECT max(status) FROM presence WHERE channel = :channel AND user_id = :user_id', array('user_id' => $user->id, 'channel' => $channel));
			DB::get()->query('DELETE FROM presence WHERE status = :status', array('status' => $status));
			echo $status;
		}
		else {
			DB::get()->query('DELETE FROM presence WHERE status = :status AND channel = :channel AND user_id = :user_id', array('status' => $status, 'user_id' => $user->id, 'channel' => $channel));
		}

		return true;
	}

	function _nickname($params){
		$nickname = $params['nickname'];
		$user = $params['user'];

		$ct = DB::get()->val("SELECT count(*) FROM users WHERE username = :nick AND id <> :user_id", array('nick' => $nickname, 'user_id' => $user->id));
		$ct += DB::get()->val("SELECT count(*) FROM options WHERE value = :nick AND name = 'Nickname' AND grouping = 'Identity'", array('nick' => $nickname));
		if($ct > 0) {
			Status::create()
				->data('The name "' . htmlspecialchars($nickname) . '" is in use.')
				->type('system')
				->cssclass('error')
				->user_to($user->id)
				->insert();
		}
		elseif(preg_match('#[^a-z0-9_]#i', $nickname)) {
			Status::create()
				->data('The name "' . htmlspecialchars($nickname) . '" cannot be used as a nickname.')
				->type('system')
				->cssclass('error')
				->user_to($user->id)
				->insert();
		}
		else {
			DB::get()->query("DELETE FROM options WHERE user_id = :user_id AND name = 'Nickname' AND grouping = 'Identity'", array('user_id' => $user->id));
			DB::get()->query("INSERT INTO options (user_id, name, grouping, value) VALUES (:user_id, 'Nickname', 'Identity', :nickname)", array('user_id' => $user->id, 'nickname' => $nickname));

			$msg = "{$user->username} has changed nicknames to {$nickname}";

			Status::create()
				->data($msg)
				->channel($params['channel'])
				->type('notice')
				->insert();
		}
		return true;
	}

	function _herald($params){
		$herald = $params['herald'];
		$user = $params['user'];

		DB::get()->query("DELETE FROM options WHERE user_id = :user_id AND name = 'Herald' AND grouping = 'Identity'", array('user_id' => $user->id));
		DB::get()->query("INSERT INTO options (user_id, name, grouping, value) VALUES (:user_id, 'Herald', 'Identity', :herald)", array('user_id' => $user->id, 'herald' => $herald));

		$msg = "{$user->username} has updated his herald.";

		Status::create()
			->data($msg)
			->channel($params['channel'])
			->type('notice')
			->insert();
		return true;
	}

	function _status($params){
		$status = htmlspecialchars($params['status']);
		$user = $params['user'];

		DB::get()->query("DELETE FROM options WHERE user_id = :user_id AND name = 'Status' AND grouping = 'Identity'", array('user_id' => $user->id));
		if($status != '') {
			DB::get()->query("INSERT INTO options (user_id, name, grouping, value) VALUES (:user_id, 'Status', 'Identity', :status)", array('user_id' => $user->id, 'status' => $status));
			$msg = "<span class=\"arrow\">--&gt;</span> <span class=\"userstatus\">{$status}</span>";
		}
		else {
			$msg = "<span class=\"arrow\">--&gt;</span> <span class=\"userstatus\" title=\"Back At Keyboard\">BAK</span>";
		}


		$rooms = DB::get()->results("SELECT * FROM channels WHERE user_id = :user_id ORDER BY name ASC", array('user_id' => Auth::user_id()));
		foreach($rooms as $room) {
			Status::create()
				->data($msg)
				->user_id($user->id)
				->type('status')
				->channel($room->name)
				->cssclass('status')
				->insert();
		}
		return true;
	}

	function _file($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$filenumber = $params['filenumber'];

		$file = DB::get()->row("SELECT files.*, users.username FROM files INNER JOIN users ON users.id = files.user_id WHERE files.id = :id", array('id' => $filenumber));
		$rmsg = '<pre>' . htmlspecialchars(print_r($filenumber,1)) . '</pre>';

		if($file->user_id == $user->id) {
			$rmsg = ' uploads <a href="/files/get?file=' . $filenumber . '">' . htmlspecialchars($file->filename) . '</a>';
		}
		else {
			$rmsg = ' references ' . htmlspecialchars($file->username) . '\'s file, <a href="/files/get?file=' . $filenumber . '">' . htmlspecialchars($file->filename) . '</a>';
		}

		// Insert the display value into the database
		Status::create()
			->data($rmsg)
			->user_id($user->id)
			->channel($channel)
			->type('emote')
			->cssclass('file')
			->insert();

		return true;
	}

	function _syntax($params){
		$user = $params['user'];
		$channel = $params['channel'];
		$language = $params['language'];
		$code = preg_replace('%^/hilite\s+(?P<language>\w+)\s+%si', '', $params['msg'], 1);
		
		if($language == 'decorcss') {
			DB::get()->query("DELETE FROM options WHERE name = 'css' AND grouping = 'decor' AND room = :channel;", array('channel' => $channel));
			DB::get()->query("INSERT INTO options (name, room, grouping, value) VALUES ('css', :channel, 'decor', :file);", array('file' => $file, 'channel' => $channel));
	
			Status::create()
				->data('Set the channel css')
				->user_id($user->id)
				->channel($channel)
				->insert();
		
			return true;
		}
		if($language == 'decorhtm') {
			DB::get()->query("DELETE FROM options WHERE name = 'htm' AND grouping = 'decor' AND room = :channel;", array('channel' => $channel));
			DB::get()->query("INSERT INTO options (name, room, grouping, value) VALUES ('htm', :channel, 'decor', :file);", array('file' => $file, 'channel' => $channel));
	
			Status::create()
				->data('Set the channel htm')
				->user_id($user->id)
				->channel($channel)
				->insert();
		
			return true;
		}

		if($language == 'php') {
			$language .= '; html-script: true';
		}

		$rmsg = '<pre class="brush: ' . $language . '" style="max-height: 5em;overflow: hidden;">' . str_replace('<', '&lt;', $code) . '</pre>';
		
		preg_match_all("%\n%", $code, $sub, PREG_SET_ORDER);
		if(count($sub) > 4) {
			$rmsg .= '<a href="#" onclick="var sy=$(this).siblings(\'.syntaxhighlighter\');sy.css(\'max-height\', sy.css(\'max-height\') == \'none\' ? \'5em\' : \'none\');return false;">Toggle code</a>';
		}

		$js = 'SyntaxHighlighter.highlight({}, $(\'#notices tr:last-child pre\')[0])';

		Status::create()
			->data($rmsg)
			->user_id($user->id)
			->channel($channel)
			->cssclass($cssclass)
			->js($js)
			->insert();

		return true;
	}

	function _quit($params){
		$user = $params['user'];
		$channel = $params['channel'];

		$channels = DB::get()->col('SELECT name FROM channels where user_id = :user_id', array('user_id' => $user->id));

		DB::get()->query('DELETE FROM channels WHERE user_id = :user_id;', array('user_id' => $user->id));

		$msg = "{$user->username} has quit";
		if($params['partmsg'] != '') {
			$msg .= ' &rarr; ' . htmlspecialchars($params['partmsg']);

			DB::get()->query("DELETE FROM options WHERE user_id = :user_id AND name = 'Status' AND grouping = 'Identity'", array('user_id' => $user->id));
			DB::get()->query("INSERT INTO options (user_id, name, grouping, value) VALUES (:user_id, 'Status', 'Identity', :status)", array('user_id' => $user->id, 'status' => $params['partmsg']));
		}
		foreach($channels as $partchan) {
			Status::create()
				->data($msg)
				->type('part')
				->channel($partchan)
				->insert();
		}

		Auth::logout();
		
		Immediate::create()
			->laststatus()
			->js('location.reload();');

		return true;
	}
	
	function _addwidget($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$widget = $params['widget'];
		$data = $params['data'];

		$lastwidgetid = DB::get()->val("SELECT MAX(id) FROM options");
		if(!$lastwidgetid) {
			$lastwidgetid = 0;
		}
		$lastwidgetid++;
		
		$data = array(
			'name' => $widget,
			'params' => $data,
		);
		
		DB::get()->query("INSERT INTO options (name, grouping, value, user_id) VALUES (:name, 'widgets', :value, :user_id);", array('name' => $lastwidgetid, 'value' => serialize($data), 'user_id' => $user->id));

		Immediate::create()
			->js("reloadWidgets();addSystem({user_id:{$user->id}, data: 'Added widget \'" . addslashes($widget) . "\' to toolpanel.', cssclass: 'ok', username: '{$user->username}', nickname: '{$user->nickname}', status: " . microtime(true) . ", js:''}, '#notices');do_scroll();");

		return true;
	}
	
	function _closedrawer($params) {
		$user = $params['user'];
		$channel = $params['channel'];
		$drawerid = $params['drawerid'];

		DB::get()->query("DELETE FROM drawers WHERE id = :drawerid AND user_id = :user_id;", array('drawerid' => $drawerid, 'user_id' => $user->id));

		Immediate::create()->js("refreshDrawers();commandstatus(false);");

		return true;
	}

	function _watch($params){
		$user = $params['user'];
		$channel = $params['channel'];
		$watch = $params['watch'];
		
		$wid = 'w__' . $watch;
		$rmsg = '<a href="#" class="close" onclick="return closedrawer({$drawer_id});">close this drawer</a><small>In ' . $watch . ':</small><table id="' . $wid . '" class="watch notices"></table>';
		//updateHandlers.drawerHandler = function (update, updates, channels, target) {alert(update);}

		DB::get()->query('DELETE FROM drawers WHERE user_id = :user_id AND indexed = :wid', array('user_id' => $user->id, 'wid' => $wid));
		DB::get()->query(
			"INSERT INTO drawers (user_id, channel, message, js, indexed) VALUES (:user_id, :channel, :message, :js, :wid);",
			array(
				'user_id' => $user->id,
				'channel' => $channel,
				'message' => $rmsg,
				'js' => 'updateHandlers.drawerHandler' . $wid . ' = function (update, updates, channels, target) {
	if(update.channel == \'' . $watch . '\') {
		ttarget = "#' . $wid . '";
		$(ttarget + " tr").remove();
		return updateHandlers.primaryHandler(update, updates, channels, ttarget);
	}
};
refreshDrawers();',
				'wid' => $wid,
			)
		);
		
		return true;
	}
	
	function _decor($params) {
		preg_match('%^/decor\s+(?P<type>\S+)(?:\s+(?P<decordata>.+))?$%ims', $params['msg'], $inmatch);
		
		$decordata = $inmatch['decordata'];
		$type = $params['type'];
		$channel = $params['channel'];
		$user = Auth::user();

		switch($type) {
			case 'css':
			case 'htm':
				break;
			default:
				Status::create()
					->data('The only valid decor types are: css, htm')
					->user_id($user->id)
					->type('system')
					->cssclass('error')
					->user_to($user->id)
					->insert();
				return true;
		}
		
		if(preg_match('%office:(\w+)%i', $channel, $officematch)) {
			if($user->username == 'owen' && $officematch[1] != $user->username) {
				Status::create()
					->data('You can only set the decor in your own office, not ' . htmlspecialchars($officematch[1]) . '\'s.')
					->user_id($user->id)
					->type('system')
					->cssclass('error')
					->user_to($user->id)
					->insert();
				return true;
			}
		}
		else {
			Status::create()
				->data('You can only set the decor in your own office, not "' . htmlspecialchars($channel) . '".')
				->user_id($user->id)
				->type('system')
				->cssclass('error')
				->user_to($user->id)
				->insert();
			return true;
		}
		
		if($decordata == '') {
			$decordata = DB::get()->val("SELECT value FROM options WHERE name = :filetype AND grouping = 'decor' AND room = :channel;", array('channel' => $channel, 'filetype' => $type));

			Status::create()
				->data(Utils::cmdout($params))
				->user_id($user->id)
				->channel($channel)
				->type('direct')
				->user_to($user->id)
				->js('bareffect(function(){toggleCodeEdit(unescape("' . urlencode($decordata) . '").replace(/\+/g, " "), "' . $type . '");});')
				->insert();

			return true;
		}
		else {
			DB::get()->query("DELETE FROM options WHERE name = :filetype AND grouping = 'decor' AND room = :channel;", array('channel' => $channel, 'filetype' => $type));
			DB::get()->query("INSERT INTO options (name, room, grouping, value) VALUES (:filetype, :channel, 'decor', :file);", array('file' => $decordata, 'filetype' => $type, 'channel' => $channel));
	
			Status::create()
				->data('Set the "' . htmlspecialchars($type) . '" decor for the room "' . htmlspecialchars($channel) . '".')
				->cssclass('ok')
				->user_id($user->id)
				->channel($channel)
				->insert();
		
			return true;
		}
	}

	function _heavy($params) {
		$user = $params['user'];
		
		DB::get()->query("DELETE FROM options where user_id = ? and grouping = 'Identity' and name = 'heavy'", array($user->id));
		Option::create()
			->grouping('Identity')
			->name('heavy')
			->value('true')
			->user_id($user->id)
			->insert();

		Status::create()
			->data('You are now heavy.')
			->cssclass('ok')
			->user_id($user->id)
			->type('direct')
			->user_to($user->id)
			->insert();

		return true;
	}

	function _light($params) {
		$user = $params['user'];
		
		DB::get()->query("DELETE FROM options where user_id = ? and grouping = 'Identity' and name = 'heavy'", array($user->id));

		Status::create()
			->data('You are now light.')
			->cssclass('ok')
			->user_id($user->id)
			->type('direct')
			->user_to($user->id)
			->insert();
		
		return true;
	}

	function _archive($params) {
		$user = $params['user'];
		$channel = $params['channel'];

		DB::get()->query("INSERT INTO archive SELECT * FROM presence WHERE msgtime < ?", array(date('Y-m-d H:i:s', strtotime('-1 month'))));
		DB::get()->query("DELETE FROM presence WHERE msgtime < ?", array(date('Y-m-d H:i:s', strtotime('-1 month'))));
		DB::get()->query("OPTIMIZE TABLE presence");

		$output = Utils::cmdout($params);
		Status::create()
			->data($output . 'Messages prior to one month ago have been moved to the archive table.')
			->user_id($user->id)
			->cssclass('archive')
			->channel($channel)
			->insert();
		
		return true;
	}
	
	function _get_cmds() {
		$cmds = array(
			1 => array(
				// stage 1: commands
				'clear' => array('%^/clear$%i', array($this, '_clear'), CMD_LAST),
				'join' => array('%^/join\s+(?P<join>.+)$%i', array($this, '_join'), CMD_LAST),
				'part' => array('%^/part(?:\s+(?P<part>.*))?$%i', array($this, '_part'), CMD_LAST),
				'emote' => array('%^/(me|emote)\s+(?P<me>.+)$%i', array($this, '_emote'), CMD_LAST),
				'woodshed' => array('%^/woodshed\s+(?P<target>.+)$%i', array($this, '_woodshed'), CMD_LAST),
				'guitar' => array('%^/(ro|guitar)(?:\s+(?P<guitar>.+))?$%i', array($this, '_guitar'), CMD_LAST),
				'msg' => array('%^(/msg|/d|/m|/dm|d)\s+(?P<d>.+)$%ism', array($this, '_msg'), CMD_LAST),
				'drag' => array('%^/drag\s+(?P<names>.+)$%i', array($this, '_drag'), CMD_LAST),
				'kill' => array('%^/kill\s+(?:(?P<status>\d+)|(?P<last>last))$%i', array($this, '_kill'), CMD_LAST),
				'replace' => array('%^s/(?P<from>.+?)(?<!\\\\)/(?P<to>.*?)(?:/(?P<flags>[^/]*)|$)%i', array($this, '_replace'), CMD_LAST),
				'nickname' => array('%^/nick(?:name)?\s+(?P<nickname>.+)$%i', array($this, '_nickname'), CMD_LAST),
				'herald' => array('%^/herald\s+(?P<herald>.+)$%i', array($this, '_herald'), CMD_LAST),
				'status' => array('%^-+>\s*(?P<status>.*)$%i', array($this, '_status'), CMD_LAST),
				'file' => array('%^/file\s+(?P<filenumber>\d+)$%i', array($this, '_file'), CMD_LAST),
				'syntax' => array('%^/hilite\s+(?P<language>\w+)\s+%si', array($this, '_syntax'), CMD_LAST),
				'quit' => array('%^/quit(?:\s+(?P<partmsg>.+))?$%i', array($this, '_quit'), CMD_LAST),
				'search' => array('%^/search(?:\s+(?P<search>.+))$%i', array($this, '_search'), CMD_LAST),
				'addwidget' => array('%^/addwidget\s+(?P<widget>\w+)(?:\s+(?P<data>.+))?$%i', array($this, '_addwidget'), CMD_LAST),
				'closedrawer' => array('%^/closedrawer\s+(?P<drawerid>\d+)$%i', array($this, '_closedrawer'), CMD_LAST),
				'kick' => array('%^/kick\s+(?P<names>.+)$%i', array($this, '_kick'), CMD_LAST),
				'lock' => array('%^/lock$%i', array($this, '_lock'), CMD_LAST),
				'unlock' => array('%^/unlock$%i', array($this, '_unlock'), CMD_LAST),
				'watch' => array('%^/watch\s+(?P<watch>.+)$%i', array($this, '_watch'), CMD_LAST),
				'alias' => array('%^/alias\s+(?P<from>\S+?)\s+(?P<to>.+?)$%i', array($this, '_alias'), CMD_LAST),
				'reload' => array('%^/reload$%i', array($this, '_reload'), CMD_LAST),
				'decor' => array('%^/decor\s+(?P<type>\S+)(?:\s+(?P<decordata>.+))?$%ims', array($this, '_decor'), CMD_LAST),
				'heavy' => array('%^/heavy$%ims', array($this, '_heavy'), CMD_LAST),
				'light' => array('%^/light$%ims', array($this, '_light'), CMD_LAST),
				'archive' => array('%^/archive$%ims', array($this, '_archive'), CMD_LAST),

				'image' => array('%^/(img|image)\s+(?P<query>.+)$%i', array($this, '_image'), CMD_LAST),
				'image_complete_close' => array('%^/image_complete\s+(?P<href>\*)(?P<src>)\s+(?P<query>.+)$%i', array($this, '_image_complete'), CMD_LAST),
				'image_complete' => array('%^/image_complete\s+(?P<href>[^\s]+)\s+(?P<src>[^\s]+)\s+(?P<query>.+)$%i', array($this, '_image_complete'), CMD_LAST),
				'wolframalpha' => array('%^/(wa|wolframalpha)\s+(?P<query>.+)$%i', array($this, '_wolframalpha'), CMD_LAST),
			),

			2 => array(
				// stage 2: filters
				'urllink' => array('/(?P<url>\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|$!:\',.;\[\]\(\)]*[A-Z0-9+&@#\/%=~_|$\[\]\(\)])(?:\s+\[(?P<label>[^\]]+)\])?/i', array($this, '_urllink'), CMD_FORWARD),
				'wrap_dammit' => array('/\//i', array($this, '_wrap_dammit'), CMD_FORWARD),
			),

			3 => array(
				// stage 3: output
				'say' => array('/^(.*)$/ism', array($this, '_say'), CMD_CONTINUE),
			),

			4 => array(
				// stage 4: reaction
				// karma does this, but it's in a plugin now
			)
		);

		$cmds = Plugin::call_commands($cmds);
		$cmds = array_merge($cmds[1], $cmds[2], $cmds[3], $cmds[4]);
		return $cmds;
	}

	function send($path)
	{
		global $config;

		$msg = $_POST['msg'];
		$omsg = $msg;
		$current_channel = DB::get()->val('SELECT name FROM channels WHERE active = 1 AND user_id = :user_id', array('user_id' => Auth::user_id()));
		$post_chan = $_POST['chan'];
		$user = Auth::user();
		
		// Aliases
		$aliases = DB::get()->assoc("SELECT name, value FROM options WHERE grouping = 'Alias' ORDER BY coalesce(room,'') = :room ASC, user_id = :user_id DESC, isnull(room) DESC, name", array('user_id' => Auth::user_id(), 'room' => $post_chan));
		foreach($aliases as $from => $to) {
			if(preg_match($from, $msg)) {
				$msg = preg_replace($from, $to, $msg);
				break;
			}
		}

		$command = trim(substr($msg, 1));
		$commands = explode(' ', $command);

		$cmds = $this->_get_cmds();

		$entities = false;
		$smartstatus = false;
		$params = array('smartstatus'=>false);
		foreach($cmds as $cmdname => $cmd) {
			if($cmd[2] == CMD_FORWARD && !$entities) {
				$msg = htmlspecialchars($msg);
				$msg = nl2br($msg);
				$entities = true;
			}
			if(preg_match($cmd[0], strip_tags($msg), $matches)) {
//Immediate::ok('cmd:' . $cmdname, $user);
				$params = array_merge($params, array(
					'path' => $path,
					'msg' => $msg,
					'command' => $command,
					'matches' => $matches,
					'channel' => $current_channel,
					'user' => $user,
					'commands' => $commands,
					'cmd' => $cmd,
					'omsg' => $omsg,
					'presence' => $this,
				));
				$params = array_merge($params, $matches);
				switch($cmd[2]) {
					case CMD_LAST:
						error_log('using ' . $commandname);
						//var_dump($cmdname, $user);if($cmdname =='externals') die('zz');
						$result = call_user_func($cmd[1], &$params);
						if($result) {
							break 2;
						}
						break;
					case CMD_FORWARD:
						$msg = call_user_func($cmd[1], $msg, &$params);
						break;
					case CMD_CONTINUE:
						$result = call_user_func($cmd[1], &$params);
						break;
				}
				$smartstatus = $smartstatus | $params['smartstatus'];
			}
		}

		Plugin::call('send_done', $params);

		Immediate::set_status();

		$laststatus = Immediate::get_status();

		if($smartstatus) {
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . '/presence/dosmartstatus?status=' . $laststatus );
			curl_setopt( $ch, CURLOPT_MAXREDIRS, 0 ); // Maximum number of redirections to follow.
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 300 );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt( $ch, CURLOPT_TIMEOUT, 1 );
			curl_setopt( $ch, CURLOPT_CRLF, true ); // Convert UNIX newlines to \r\n
			$response = curl_exec( $ch );
			curl_close( $ch );
		}

		Immediate::output();
	}

	private function _urllink($msg, $params)
	{
		$re = $params['cmd'][0];
		$count = 0;
		$msg = preg_replace_callback($re, array($this, '_urllink_callback'), $msg, -1, $count);
		if($count == 1 && preg_match('%^<a.+</a>%i', $msg)) {
			$msg = preg_replace('%class="%', 'class="smartstatus ', $msg);
			$params['smartstatus'] = true;
		}
		return $msg;
	}
	
	private function _wrap_dammit($msg, $params)
	{
		if(preg_match('#\S{20,}#', $msg) && strpos($msg, '<') === false && $msg[0] != '/') {
			$msg = implode('<wbr/>', preg_split('/(?![\w\s"&=\';])/', $msg));
		}
		return $msg;
	}
	
	private function _urllink_callback($matches)
	{
		$urlmatch = $matches[1];
		
		if(isset($matches[3]) && trim($matches[3]) != '') {
			$pagetitle = implode('<wbr/>', str_split($matches[3], 10));
		}
		else {
			$pagetitle = implode('<wbr/>', str_split($urlmatch, 10));
		}
		return '<a href="' . $urlmatch . '" class="" target="_blank">' . $pagetitle .'</a>';
	}
	
	public function dosmartstatus($path) {
		$status = $_GET['status'];
		$row = DB::get()->row("SELECT * FROM presence WHERE status = ? ORDER BY status ASC LIMIT 1", array($status));
		$content = SimpleHTML::str_get_html($row->data);
		
		$smarts = $content->find('.smartstatus');
		
		foreach($smarts as $smart) {
			switch($smart->tag) {
				case 'a':
					$smart->outertext = $this->_smarturl($smart->href, $row->user_id);
					break;
			}
			print_r(htmlspecialchars($content->save()));
		}
		DB::get()->query("UPDATE presence SET data = :msg WHERE status = :status", array('msg'=>$content->save(), 'status'=>$status));
	}

	public function smartstatus($path) {
		$response = new StdClass();
		$statuses = $_POST['statuses'];

		$html = array();
		foreach($statuses as $status) {
			$row = DB::get()->row("SELECT * FROM presence WHERE status = ?", array($status));
			$html[$status] = $row->data;
		}
		$response->html = $html;
		
		echo json_encode($response);
		die();
	}
	
	private function _smarturl($urlmatch, $user_id) {

		if(preg_match('%http://.*\.youtube\.com/watch\?v=([^?]+)%i', $urlmatch, $yt)) {
			return <<< YOUTUBE
				<object width="425" height="344"><param name="movie" value="http://www.youtube-nocookie.com/v/{$yt[1]}&hl=en_US&fs=1&rel=0"></param><param name="wmode" value="transparent"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube-nocookie.com/v/{$yt[1]}&hl=en_US&fs=1&rel=0" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="425" height="344" wmode="transparent" ></embed></object>
YOUTUBE;
		}

		if(preg_match('%http://.*\.viddler\.com/explore/.+/videos/\d+%i', $urlmatch, $vd)) {
			$v = file_get_contents($urlmatch);
			if(preg_match('%<link\s+rel="video_src"\s+href="([^"]+)"%', $v, $video)) {
				$url = $video[1];
				return <<< VIDDLER
					<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="437" height="370" id="viddler"><param name="movie" value="{$url}" /><param name="wmode" value="transparent"></param><param name="allowScriptAccess" value="always" /><param name="allowFullScreen" value="true" /><embed src="{$url}" width="437" height="370" type="application/x-shockwave-flash" allowScriptAccess="always" allowFullScreen="true" name="viddler" wmode="transparent" ></embed></object>
VIDDLER;
			}
		}

		if(preg_match('%http://.*\.hulu\.com/watch/\d+%i', $urlmatch, $vd)) {
			$v = file_get_contents($urlmatch);
			if(preg_match('%<link\s+rel="video_src"\s+href="([^"]+)"%', $v, $video)) {
				$url = $video[1];
				return <<< HULU
					<object width="512" height="296"><param name="movie" value="{$url}"></param><param name="allowFullScreen" value="true"></param><param name="wmode" value="transparent"></param><embed src="{$url}" type="application/x-shockwave-flash" allowFullScreen="true"  width="512" height="296" wmode="transparent"></embed></object>
HULU;
			}
		}

		extract($this->urlinfo($urlmatch));

		if(strtolower($ctype[1]) == 'image') {
			return '<a href="'.$urlmatch.'" target="_blank"><img onload="do_scroll();" src="'.$urlmatch.'"></a>';
		}

		if(strtolower($ctype[1]) == 'audio') {
			$audiourl = urlencode($urlmatch);
			return <<< AUDIO_PLAYER
<object type="application/x-shockwave-flash" data="/js/player.swf" width="200" height="20">
	<param name="movie" value="/js/player.swf" />
	<param name="wmode" value="transparent" />
	<param name="FlashVars" value="showvolume=1&mp3={$audiourl}" />
</object>
<p><a href="{$urlmatch}">{$urlmatch}</a></p>
AUDIO_PLAYER;
		}

		if(strtolower(trim($ctype[2])) == 'x-shockwave-flash') {
			return <<< FLASH_PLAYER
<object type="application/x-shockwave-flash" data="{$urlmatch}" width="300">
	<param name="movie" value="{$urlmatch}" />
	<param name="wmode" value="transparent" />
</object>
<p><a href="{$urlmatch}">{$urlmatch}</a></p>
FLASH_PLAYER;
		}

		if(strpos($host, 'drp.ly') !== false) {
			$href = $content->find('img',0)->src;
			return '<a href="'.$urlmatch.'" class="dragfloat drply" target="_blank"><img onload="do_scroll();" src="'.$href.'" style="width: 30%;"></a>';
		}
		if(strpos($host, 'd.pr') !== false) {
			$href = $content->find('img',0)->src;
			return '<a href="'.$urlmatch.'" class="dragfloat drply" target="_blank"><img onload="do_scroll();" src="'.$href.'" style="width: 30%;"></a>';
		}
		if(strpos($host, 'awesomescreenshot.com') !== false) {
			$href = $content->find('#screenshot',0)->src;
			return '<a href="'.$urlmatch.'" class="dragfloat awesomescreenshot" target="_blank"><img onload="do_scroll();" src="'.$href.'" style="width: 30%;"></a>';
		}

		if(strpos($host, 'screencast.com') !== false) {
			switch($content->find('.embeddedObject',0)->tag) {
				case 'img':
					$href = $content->find('.embeddedObject',0)->src;
					$href = UrlController::tos3($href);
					return '<a href="'.$urlmatch.'" class="dragfloat screencast" target="_blank"><img onload="do_scroll();" src="'.$href.'" style="width: 30%;"></a>';
				case 'object':
					$rooturl = str_replace('www.screencast.com', 'content.screencast.com', $cururl) . '/';
					$thumburl = $rooturl . 'FirstFrame.jpg';
					$flashvars = $content->find('.embeddedObject param[name=flashVars]',0)->value;
					if(preg_match('%[^&/]+\.(swf|mp4)%i', $flashvars, $swfmatch)) {

						preg_match('%containerwidth=(\d+)%', $flashvars, $matchwidth);
						preg_match('%containerheight=(\d+)%', $flashvars, $matchheight);
						$containerwidth = $matchwidth[1];
						$containerheight = $matchheight[1];

						$swf = $rooturl . $swfmatch[0];
						$width = $content->find('.embeddedObject',0)->width;
						$height = $content->find('.embeddedObject',0)->height;
						if($width > 640) {
							$height = round($height*(640/$width));
							$width = 640;
						}
						if($height > 300) {
							$width = round($width*(300/$height));
							$height = 300;
						}
						return <<< SCREENCAST
<object width="{$width}" height="{$height}">
	<param name="movie" value="{$swf}"></param>
	<param name="quality" value="high"></param>
	<param name="bgcolor" value="#FFFFFF"></param>
	<param name="flashVars" value="thumb={$thumburl}&containerwidth={$containerwidth}&containerheight={$containerheight}&content={$swf}"></param>
	<param name="allowFullScreen" value="true"></param>
	<param name="scale" value="showall"></param>
	<param name="allowScriptAccess" value="always"></param>
  <param name="base" value="{$rooturl}"></param>
	<param name="wmode" value="transparent"></param>
	<embed
		src="{$rooturl}jingswfplayer.swf"
		quality="high"
		bgcolor="#FFFFFF"
		width="{$width}"
		height="{$height}"
		type="application/x-shockwave-flash"
		allowScriptAccess="always"
		flashVars="thumb={$thumburl}&containerwidth={$containerwidth}&containerheight={$containerheight}&content={$swf}"
		allowFullScreen="true"
		scale="showall"
		base="{$rooturl}"
		wmode="transparent">
	</embed>
</object>
					<br><a href="{$urlmatch}">View on Screencast</a>
SCREENCAST;
						return $swf;
					}
					else {
						$thumburl = UrlController::tos3($thumburl);
						return '<a href="'.$urlmatch.'"><img onload="do_scroll();" src="'.$thumburl.'" style="width: 30%;"></a>';
					}
			}
		}

		$thumbjob = UrlController::get_thumbnail($urlmatch);
		do{
			sleep(5);
			$img = UrlController::cache_thumb($thumbjob, $user_id);
		}
		while($img == false && $count++ < 10);
		if($img) {
			$smart->operation = '';
			$imgtag = '<img src="' . $img . '" class="smartthumb noresize" onload="thumbload(this);">';
			$class = 'withthumb';
		}
		else {
			$imgtag = '';
			$class = 'withoutthumb';
		}
		
		switch($status) {
			case 200:
				if($pagetitle = $content->find('title', 0)->innertext) {
					return '<a class="' . $class . '" href="' . $urlmatch . '" target="_blank">' . $imgtag . '</a>' . implode('<wbr/>', str_split($pagetitle, 10)) . '<br><a href="' . $urlmatch . '" target="_blank">' . implode('<wbr/>', str_split($urlmatch,10)) . '</a><hr style="clear:both;visibility:hidden;height:1px;" />';
				}
				$pagetitle = implode('<wbr/>', str_split($urlmatch, 10));
				return '<a class="' . $class . '" href="' . $urlmatch . '" target="_blank">' . $imgtag . '</a><a href="' . $urlmatch . '" target="_blank">' . $pagetitle . '</a><br><a href="' . $urlmatch . '" target="_blank">' . implode('<wbr/>', str_split($urlmatch,10)) . '</a><hr style="clear:both;visibility:hidden;height:1px;" />';
			default:
				$pagetitle = implode('<wbr/>', str_split($urlmatch, 10));
				return '<a class="' . $class . '" href="' . $urlmatch . '" target="_blank">' . $imgtag . '</a><a href="' . $urlmatch . '" target="_blank">' . $pagetitle . '</a><br><a href="' . $urlmatch . '" target="_blank">' . implode('<wbr/>', str_split($urlmatch,10)) . '</a><hr style="clear:both;visibility:hidden;height:1px;" />';
		}
	}

	function urlinfo($url)
	{
		$cururl = $url;
		$rcount = 0;
		$location = false;
		do {
			$rcount++;
			$location = false;
			$purl = parse_url($cururl);
			$host = $purl['host'];
			$url = $purl['path'] . ($purl['query'] ? '?' . $purl['query'] : '');
			if($url == '') {
				$url = '/';
			}
			$port = $purl['port'] ? $purl['port'] : 80;
			$fp = fsockopen($host, $port, $errno, $errstr, 30);
			$ctype = array('','');
			$getwhole = false;
			if ($fp) {
				$out = "GET {$url} HTTP/1.1\r\n";
				$out .= "Host: {$host}\r\n";
				$out .= "Connection: Close\r\n\r\n";

				fwrite($fp, $out);
				$header = '';
				while (!feof($fp)) {
					$header .= fgets($fp, 128);
					if(!$getwhole && preg_match('%\n\n|\r\r|\n\r\n|\r\n\r%', $header)) {

						preg_match('%Content-Type:\s*([^/]+)/([^;\n]+)%i', $header, $ctype);
						switch($ctype[1]){
							case 'text':
								break;
							case 'application':
								if(strpos($ctype[2], 'xml') === false) {
									break 2;
								}
								break;
							default:
								break 2;
						}
						$getwhole = true;
					}
				}
				fclose($fp);
				preg_match('%Content-Type:\s*([^/]+)/([^;\n]+)%i', $header, $ctype);
				if($getwhole) {
					list($header, $content) = preg_split('%\n\n|\r\r|\n\r\n|\r\n\r%', $header, 2);
					$content = SimpleHTML::str_get_html($content);
				}
				else {
					$content = SimpleHTML::str_get_html('');
				}
				$headers_temp = explode("\n", trim($header));
				$headers = array();
				foreach($headers_temp as $h) {
					if(strpos($h, ':') !== false) {
						list($k, $v) = explode(':', trim($h), 2);
					}
					else {
						$k = 'status';
						$v = $h;
					}
					$headers[strtolower($k)] = trim($v);
				}
				preg_match('%^HTTP/\d.\d (?P<code>\d{3})%i', $headers['status'], $httpstatus);
				if($httpstatus['code'][0] == '3') {
					$cururl = $headers['location'];
					$location = $headers['location'];
				}
			}
		} while($location && $rcount < 5);

		$result = array(
			'getwhole' => $getwhole,
			'headers' => $headers,
			'content' => $content,
			'ctype' => $ctype,
			'cururl' => $cururl,
			'status' => $httpstatus['code'],
		);
		$result = array_merge($result, $purl);
		return $result;
	}

	function setchan($path){
		echo $this->_response($_POST['chan']);
	}

	function _response($channel)
	{
		$user = Auth::user();
		
		$issearch = false;
		if(preg_match('%search:(?P<criteria>.+)%i', $channel, $searchmatches)) {
			$issearch = true;
		}
		preg_match('%(?P<roomtype>\w+):(?P<criteria>.+)%i', $channel, $searchmatches);
		$searchmatches['roomtype'] = isset($searchmatches['roomtype']) ? $searchmatches['roomtype'] : '';
		$searchmatches['criteria'] = isset($searchmatches['criteria']) ? $searchmatches['criteria'] : '';
		
		if(DB::get()->val("SELECT count(*) FROM channels WHERE user_id = :user_id", array('user_id' => Auth::user_id())) == 0) {
			$join = 'bar'; //$user->username;

			$allowedchannels = DB::get()->col("SELECT room FROM options WHERE grouping = 'Permissions' AND name = 'allowedchannel' AND user_id = :user_id", array('user_id' => $user->id));
			if($allowedchannels) {
				$join = reset($allowedchannels);
			}
			
			DB::get()->query("INSERT INTO channels (name, user_id, active) VALUES (:join, :user_id, 1);", array('join' => $join, 'user_id' => Auth::user_id()));
			
			if(!($herald = DB::get()->val("SELECT value FROM options WHERE user_id = :user_id AND name = :name AND grouping = :grouping", array('user_id' => Auth::user_id(), 'name' => 'Herald', 'grouping' => 'Identity')))) {
				$herald = '{$nickname} has joined {$room}';
			}
			$js = '';
			$cssclass = '';
			$packed = Plugin::call('herald', array('herald' => $herald, 'js' => $js, 'cssclass' => $cssclass));
			extract($packed);
			$herald = str_replace('{$nickname}', $user->nickname, $herald);
			$herald = str_replace('{$room}', $this->_room_alias($join), $herald);
			$msg = htmlspecialchars($herald);
			Status::create()
				->data($msg)
				->type('join')
				->channel($join)
				->cssclass($cssclass)
				->js($js)
				->insert();
		}

		DB::get()->query('UPDATE channels SET active = 0 WHERE user_id = :user_id', array('user_id' => Auth::user_id()));
		DB::get()->query('UPDATE channels SET active = 1, last = NOW() WHERE name = :channel AND user_id = :user_id', array('channel' => $channel, 'user_id' => Auth::user_id()));

		$laststatus = Immediate::get_status();
		$obj = new StdClass();
		$obj->comet_change = false;

		$obj->status = $laststatus;
		$obj->reported_status = 0;
		$obj->chanbar = $this->chanbar();
		$obj->channels = DB::get()->col("SELECT name FROM channels WHERE user_id = :user_id ORDER BY name ASC", array('user_id' => Auth::user_id()));
		$obj->channel = DB::get()->val("SELECT name FROM channels WHERE user_id = :user_id and active = 1", array('user_id' => Auth::user_id()));
		$obj->jsdate = filemtime(dirname(__FILE__) . '/../../js/sp.js');

		switch(strtolower($searchmatches['roomtype'])) {
			case 'search':
				$crit = DB::get()->val("SELECT value FROM options WHERE user_id = :user_id AND grouping = 'searches' AND name = :name", array('user_id' => $user->id, 'name' => $searchmatches['criteria']));
				
				$obj->crit = $crit;
				
				$sql = '';
				$params = array('user_id' => Auth::user_id(), 'crit' => $crit, 'searchchannel' => $obj->channel);
				$criteria = $this->_get_search_sql($sql, $params, $crit);
				
				$obj->updates = DB::get()->results($sql, $params, 'StdClass');
				$insert = new stdClass();
				$insert->status = 0;
				$insert->type = 'system';
				$insert->channel = '';
				$insert->data = 'Search Criteria: ' . $criteria;
				$insert->msgtime = 0;
				$insert->user_id = 0;
				$insert->cssclass = 'searchheader';
				$insert->js = '';
				$insert->user_to = '';
				$insert->received = '';
				$obj->updates[] = $insert;
				break;
			case 'office':
				$officeuser = $this->_userstr($searchmatches['criteria']);
				$qp = array('channel' => $obj->channel, 'user_id' => Auth::user_id());
				if($officeuser->id == $user->id) {
					$append = "((type = 'direct' AND user_to = :user_id AND presence.user_id <> :user_id) OR (user_to = :user_id) OR (user_to = 0)) AND (type <> 'notice')";
				}
				else {
					$append = "((type = 'direct' AND user_to = :user_id and presence.user_id = :office_user) OR (user_to = 0)) AND (type <> 'notice')";
					$qp['office_user'] = $officeuser->id;
				}
				$obj->updates = DB::get()->results("
SELECT
	presence.*,
	users.username,
	options.value as nickname,
	channels.active as active
FROM
	presence
LEFT JOIN
	users
	ON presence.user_id = users.id
LEFT JOIN
	options
	ON options.user_id = users.id AND options.name = 'Nickname' AND options.grouping = 'Identity'
LEFT JOIN
	channels
	ON channels.user_id = :user_id 
	AND channels.name = presence.channel
WHERE
	(channel = :channel OR channel = '')
	AND
	(
		{$append}
	)
ORDER BY
	status DESC
LIMIT 100
			", $qp, 'StdClass');
				break;
			default:
				$updates = Plugin::call('response', false, $searchmatches['roomtype'], $searchmatches['criteria']);
				
				if($updates) {
					$obj->updates = $updates;
				}
				else {
					$obj->updates = DB::get()->results("
SELECT
	presence.*,
	users.username,
	options.value as nickname
FROM
	presence
LEFT JOIN
	users
	ON presence.user_id = users.id
LEFT JOIN
	options
	ON options.user_id = users.id AND options.name = 'Nickname' AND options.grouping = 'Identity'
WHERE
	(channel = :channel OR channel = '')
	AND
	(
		(user_to = 0)
		OR
		(user_to = :user_id and isnull(received))
	)
ORDER BY
	status DESC
LIMIT 100
					", array('channel' => $obj->channel, 'user_id' => Auth::user_id()), 'StdClass');
				}
				break;
		}
		DB::get()->query('UPDATE presence SET received = msgtime, msgtime = NOW() WHERE isnull(received) AND user_to = :user_id', array('user_id' => Auth::user_id()));
		foreach($obj->updates as $k => $v) {
			$obj->updates[$k]->msgtime = date('Y-m-d H:i:s', strtotime($obj->updates[$k]->msgtime) + intval((string)Option::get('Time', 'Zone Offset')) * 3600);
		}
		$obj->updates = array_reverse($obj->updates);

		$obj->names = DB::get()->results('select channels.name, users.id, username, pingtime, last, active, value as nickname from channels, sessions, users, options where channels.user_id = users.id AND users.id = sessions.user_id and pingtime > now() - 120000 and channels.name = :channel and options.user_id = users.id and options.grouping = "Identity" and options.name="Nickname";', array('channel'=>$obj->channel), 'StdClass');
		$obj->namebar = $this->namebar();
		$obj->namebarmd5 = md5($obj->namebar);
		$obj->sups = DB::get()->val('SELECT count(*) as ct FROM presence, channels WHERE presence.channel = channels.name AND presence.msgtime > channels.last AND channels.active = 0 AND channels.user_id = :user_id AND presence.user_id <> :user_id AND presence.type <> "status"', array('user_id' => Auth::user_id()));
		$obj->drawers = DB::get()->results('SELECT * FROM drawers WHERE (channel = :channel OR isnull(channel) OR channel = "") AND user_id = :user_id ORDER BY added DESC;', array('channel' => $obj->channel, 'user_id' => Auth::user_id()), 'StdClass');
		$obj->decor = DB::get()->assoc("SELECT name, value FROM options WHERE room = :channel AND grouping = 'decor';", array('channel' => $obj->channel));

		$obj = Plugin::call('response_obj', $obj, $searchmatches['roomtype'], $searchmatches['criteria']);

		Immediate::set_status();

		return json_encode($obj);
	}


	function chanbar()
	{
		$channels = DB::get()->col("select channel from presence WHERE channel <> '' GROUP BY presence.channel order by presence.user_id = :user_id desc, count(status) desc limit 5", array('user_id' => Auth::user_id()));
		$user = DB::get()->row("SELECT users.*, options.value as nickname FROM users LEFT JOIN options ON options.user_id = users.id AND options.name = 'Nickname' AND grouping = 'Identity'  WHERE users.id = :user_id", array('user_id' => Auth::user_id()));
		if($user->nickname == '') {
			$user->nickname = $user->username;
		}

//		$rooms = DB::get()->results("SELECT * FROM channels WHERE user_id = :user_id ORDER BY name ASC", array('user_id' => Auth::user_id()));
		$rooms = DB::get()->results("SELECT channels.*, options.value as alias, sq.value as squelch FROM channels 
			LEFT JOIN options on channels.name = options.room and options.grouping = 'Rooms' and options.name = 'alias' and options.user_id = 0
			LEFT JOIN options sq on channels.name = sq.room and sq.grouping = 'squelch' and sq.user_id = channels.user_id
			WHERE channels.user_id = :user_id 
			ORDER BY channels.name ASC", array('user_id' => Auth::user_id()));
		$roomary = DB::get()->col("SELECT name FROM channels WHERE user_id = :user_id", array('user_id' => Auth::user_id()));

		$channels = array_diff($channels, $roomary);

		$channelhtml = '';
		foreach($channels as $channel) {
			if(!preg_match('%^\w+:%i', $channel)) {
				$channelhtml .= '<li><a href="#" onclick="joinRoom(\'' . $channel . '\');return false;">' . $channel . '</a></li>';
			}
		}

		$sups = DB::get()->assoc('SELECT channels.name, count(*) as ct FROM presence, channels WHERE presence.channel = channels.name AND presence.msgtime > channels.last AND channels.active = 0 AND channels.user_id = :user_id AND presence.user_id <> :user_id AND presence.type <> "status" GROUP BY channels.name;', array('user_id' => Auth::user_id()));

		$roomhtml = '';
		foreach($rooms as $room) {
			$classes = 'inroom';
			if($room->active == 1) {
				$classes .= ' active';
			}
			if(preg_match('%^(?P<roomtype>\w+):(?P<search>.+)$%i', $room->name, $searchmatch)) {
				$roomname = $searchmatch['search'];
				$classes .= ' ' . $searchmatch['roomtype'];
			}
			else {
				$searchmatch = array(
					'roomtype' => '',
					'search' => $room->name,
				);
				$roomname = $room->name;
			}
			if($room->alias != '') {
				$roomname = $room->alias;
			}
			$link = '<li class="' . $classes. '" id="tab__' . $room->name . '"><a href="#" onclick="setRoom(\'' . $room->name . '\', this);return false;">';
			$link .= $roomname;
			if(isset($sups[$room->name]) && $sups[$room->name] > 0 && !$room->active) {
				$link .= '<sup>' . $sups[$room->name] . '</sup>';
			}
			$link .= '</a>';
			$link = Plugin::call('chanbar_roomlink', $link, $room, $searchmatch['roomtype']);
			$roomhtml .= $link;
			$roomhtml .= '<div class="submenu"><ul><li><a href="#" onclick="partRoom(\'' . $room->name . '\');return false;">Part</a></li>';
			$roomsquelch = $room->squelch == 'true' ? 'on' : 'off';
			//DB::get()->val("SELECT value from options where grouping = 'squelch' AND user_id = :user_id AND room = :room", array('user_id' => $user->id, 'room' => $room->name))
			$roomhtml .= '<li><a href="#" onclick="toggler(this);squelchRoom(\'' . $room->name . '\',$(\'span\',this).hasClass(\'on\'));return false;"><span class="toggle '. $roomsquelch .' roomsquelch_' . $room->name . '"></span>Squelch </a></li>';
			$add = '';
			$add = Plugin::call('chanbar_menu', $add, $room, $searchmatch['roomtype']);
			$roomhtml .= $add;
			$roomhtml .= '</ul></div></li>';
		}
		
		$components = array(
			'roomhtml' => $roomhtml,
			'channelhtml' => $channelhtml,
		);
		$v = new View($components);
		return $v->capture('chanbar');
	}

	function namebar()
	{
		$channel = DB::get()->val('SELECT name FROM channels WHERE user_id = :user_id AND active = 1', array('user_id' => Auth::user_id()));
		$names = DB::get()->results("
			select
				channels.name,
				users.id,
				users.username,
				users.lastping,
				sessions.pingtime,
				channels.last,
				channels.active,
				nick.value as nickname,
				stat.value as status,
				coalesce(timestampdiff(SECOND, sessions.pingtime, CURRENT_TIMESTAMP) < 65) as loggedin
			from users
			LEFT JOIN options nick
				ON
					nick.user_id = users.id
					AND nick.name = 'Nickname'
					AND nick.grouping = 'Identity'
			LEFT JOIN options stat
				ON
					stat.user_id = users.id
					AND stat.name = 'Status'
					AND stat.grouping = 'Identity'
			left join sessions
				on
					sessions.user_id = users.id
			left join channels
				on
					users.id = channels.user_id
			and
				channels.name = :channel
			where users.id > 0
			ORDER by loggedin DESC, active DESC, username ASC;
		", array('channel'=>$channel));
		
		foreach($names as $k => $name) {
			$names[$k]->channels = DB::get()->col("SELECT coalesce(value, channels.name) FROM channels left join options on options.room = channels.name and options.grouping = 'Rooms' and options.name = 'alias' WHERE channels.user_id = :user_id", array('user_id' => $name->id));
		}
		
		$yourein = DB::get()->col("SELECT coalesce(value, channels.name) FROM channels left join options on options.room = channels.name and options.grouping = 'Rooms' and options.name = 'alias' WHERE channels.user_id = :user_id", array('user_id' => Auth::user_id()));
		
		$components['names'] = $names;
		$components['yourein'] = $yourein;

		$v = new View($components);

		return $v->capture('namebar');
	}

	function _get_search_sql(&$sql, &$params, $crit, $fortitle = false) {
		$where = <<< DEFAULT_WHERE
((user_to = 0)
OR
(user_to = :user_id)
OR
(presence.user_id = :user_id))
DEFAULT_WHERE;
		$criteria = array();
		$limited = false;
		$limit = '';
		$title = '';
		
		if(preg_match('%date\s*=\s*(?P<date>("[^"]+"|\S+))%i', $crit, $datematches)) {
			$crit = preg_replace('%date\s*=\s*(?P<date>("[^"]+"|\S+))%i', '', $crit);
			$date = trim($datematches['date'], '"');
			$df = date('Y-m-d', strtotime($date));
			$dt = date('Y-m-d', strtotime($date) + 86400);
			$where .= ' AND (presence.msgtime >= :fromtime) AND (presence.msgtime < :totime)';
			$params['fromtime'] = $df;
			$params['totime'] = $dt;
			$criteria[] = 'On ' . date('D, M j, Y', strtotime($df));
			$limited = true;
			$title = $df;
		}

		if(preg_match('%(channel|room)\s*=\s*(?P<channel>("[^"]+"|\S+))%i', $crit, $channelmatches)) {
			$channel = $channelmatches['channel'];
			$crit = preg_replace('%(channel|room)\s*=\s*(?P<channel>("[^"]+"|\S+))%i', '', $crit);
			$where .= ' AND (presence.channel = :channel)';
			$params['channel'] = $channel;
			$criteria[] = 'In channel "' . htmlspecialchars($channel) . '"';
			$title = htmlspecialchars($channel);
		}
		if($allowedchannels = DB::get()->col("SELECT room FROM options WHERE grouping = 'Permissions' AND name = 'allowedchannel' AND user_id = :user_id", array('user_id' => Auth::user_id()))) {
			$inclause = DB::inclause($allowedchannels, 'allowed');
			$where .= ' AND (presence.channel IN (' . implode(',', array_keys($inclause)) . '))';
			$params = array_merge($params, $inclause);
		}
		if($deniedchannels = DB::get()->col("SELECT room FROM options WHERE grouping = 'Permissions' AND name = 'deniedchannel' AND user_id = :user_id", array('user_id' => Auth::user_id()))) {
			$inclause = DB::inclause($deniedchannels, 'denied');
			$where .= ' AND (presence.channel NOT IN (' . implode(',', array_keys($inclause)) . '))';
			$params = array_merge($params, $inclause);
		}
		
		if(preg_match('%(type)\s*=\s*(?P<type>("[^"]+"|\S+))%i', $crit, $typematches)) {
			$type = $typematches['type'];
			$crit = preg_replace('%(type)\s*=\s*(?P<type>("[^"]+"|\S+))%i', '', $crit);
			$where .= ' AND (presence.type = :type)';
			$params['type'] = $type;
			$criteria[] = 'Message type "' . htmlspecialchars($type) . '"';
			$title = htmlspecialchars($type);
		}
		
		if(trim($crit) != '') {
			$where .= " AND data LIKE CONCAT('%', :crit, '%')";
			$params['crit'] = trim($crit);
			$criteria[] = '"' . htmlspecialchars(trim($crit)) . '"';
			$title = htmlspecialchars(trim($crit));
		}
		else {
			if(!$limited) {
				$limit = 'LIMIT 100';
				$criteria[] = 'Last 100 messages';
			}
		}
		
		$sql = <<< SEARCH_SQL
SELECT
	presence.status,
	presence.type,
	presence.data,
	presence.msgtime,
	presence.user_id,
	presence.cssclass,
	presence.js,
	presence.user_to,
	presence.received,
	presence.channel as inchannel,
	:searchchannel as channel,
	users.username,
	options.value as nickname,
	:crit as crit
FROM
	presence
LEFT JOIN
	users
	ON presence.user_id = users.id
LEFT JOIN
	options
	ON options.user_id = users.id AND options.name = 'Nickname' AND options.grouping = 'Identity'
WHERE
	{$where}
ORDER BY
	inchannel DESC,
	status DESC
{$limit}
SEARCH_SQL;
	
		if($fortitle) {
			return $title;
		}
		else {	
			return implode(' &middot ', $criteria);
		}
	}
	
}
?>
