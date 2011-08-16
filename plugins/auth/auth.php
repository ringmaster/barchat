<?php

Class HttpAuth extends Plugin
{
	
	function commands($cmds){
		$cmds[1]['auth_adduser'] = array('%^/auth\s+adduser\s+(?P<user>\S+)\s+(?P<password>\S+)$%i', array($this, '_auth_add_user'), CMD_LAST);
		$cmds[1]['auth_setpassword'] = array('%^/auth\s+setpassword\s+(?P<user>\S+)\s+(?P<password>\S+)$%i', array($this, '_auth_set_password'), CMD_LAST);
		$cmds[1]['auth_addsite'] = array('%^/auth\s+siteadd\s+(?P<user>\S+)\s+(?P<site>\S+)$%i', array($this, '_auth_add_site'), CMD_LAST);
		$cmds[1]['auth_removesite'] = array('%^/auth\s+siteremove\s+(?P<user>\S+)\s+(?P<site>\S+)$%i', array($this, '_auth_remove_site'), CMD_LAST);
		$cmds[1]['auth_removeuser'] = array('%^/auth\s+removeuser\s+(?P<user>\S+)$%i', array($this, '_auth_remove_user'), CMD_LAST);
		$cmds[1]['auth_explain'] = array('%^/auth\s+explain\s+(?P<user>\S+)$%i', array($this, '_auth_explain'), CMD_LAST);

		return $cmds;
	}
	
	function _auth_add_user($params) {
		$username = $params['user'];
		$password = $params['password'];
		$user = Auth::user();
		$channel = $params['channel'];
		
		$auth = new DB('mysql:host=localhost;dbname=auth', 'root', '', 'auth');
		if(!$auth) {
			return $this->_cant_connect();
		}

		if($auth->val("SELECT count(*) FROM user_info WHERE user_name = :username", array('username' => $username)) == 0) { 		
			$auth->query("INSERT INTO user_info (user_name, user_password) VALUES (:username, md5(:password));", array('username' => $username, 'password' => $password));
			Status::create()
				->data(Utils::cmdout($params) . 'Added user "' . $username. '" with password to http_auth.')
				->user_id($user->id)
				->channel($channel)
				->cssclass('httpauth')
				->insert();
		}
		else {
			Status::create()
				->data(Utils::cmdout($params) . 'User "' . $username. '" already exists in http_auth.')
				->user_id($user->id)
				->channel($channel)
				->cssclass('httpauth')
				->insert();
		}
		
		return true;
	}
	
	function _auth_set_password($params) {
		$username = $params['user'];
		$password = $params['password'];
		$user = Auth::user();
		$channel = $params['channel'];
		
		$auth = new DB('mysql:host=localhost;dbname=auth', 'root', '', 'auth');
		if(!$auth) {
			return $this->_cant_connect();
		}

		if($auth->val("SELECT count(*) FROM user_info WHERE user_name = :username", array('username' => $username)) > 0) { 		
			if($auth->val("SELECT count(*) FROM user_group WHERE user_name = :username AND user_group = 'rrs'", array('username' => $username)) == 0) { 		
				$auth->query("UPDATE user_info SET user_password = md5(:password) WHERE user_name = :username;", array('username' => $username, 'password' => $password));
				Status::create()
					->data(Utils::cmdout($params) . 'Changed the http_auth password for user "' . $username. '".')
					->user_id($user->id)
					->channel($channel)
					->cssclass('httpauth')
					->insert();
			}
			else {
				Status::create()
					->data(Utils::cmdout($params) . 'User "' . $username. '" is in the rrs group and can\'t be modified.')
					->user_id($user->id)
					->channel($channel)
					->cssclass('httpauth')
					->insert();
			}
		}
		else {
			Status::create()
				->data(Utils::cmdout($params) . 'User "' . $username. '" does not exist in http_auth.')
				->user_id($user->id)
				->channel($channel)
				->cssclass('httpauth')
				->insert();
		}
		
		return true;
	}
	
	function _auth_add_site($params) {
		$username = $params['user'];
		$site = $params['site'];
		$user = Auth::user();
		$channel = $params['channel'];
		
		$auth = new DB('mysql:host=localhost;dbname=auth', 'root', '', 'auth');
		if(!$auth) {
			return $this->_cant_connect();
		}

		if($auth->val("SELECT count(*) FROM user_group WHERE user_name = :username and user_group = :group", array('username' => $username, 'group' => $site)) == 0) { 		
			$auth->query("INSERT INTO user_group (user_name, user_group) VALUES (:username, :group);", array('username' => $username, 'group' => $site));
			Status::create()
				->data(Utils::cmdout($params) . 'Added user "' . $username. '" to site "' . $site . '" in http_auth.')
				->user_id($user->id)
				->channel($channel)
				->cssclass('httpauth')
				->insert();
		}
		else {
			Status::create()
				->data(Utils::cmdout($params) . 'User "' . $username. '" already exists in site "' . $site . '" in http_auth.')
				->user_id($user->id)
				->channel($channel)
				->cssclass('httpauth')
				->insert();
		}
		
		return true;
	}

	function _auth_remove_site($params) {
		$username = $params['user'];
		$site = $params['site'];
		$user = Auth::user();
		$channel = $params['channel'];
		
		$auth = new DB('mysql:host=localhost;dbname=auth', 'root', '', 'auth');
		if(!$auth) {
			return $this->_cant_connect();
		}

		if($auth->val("SELECT count(*) FROM user_group WHERE user_name = :username and user_group = :group", array('username' => $username, 'group' => $site)) > 0) { 		
			$auth->query("DELETE FROM user_group where user_name = :username AND user_group = :group;", array('username' => $username, 'group' => $site));
			Status::create()
				->data(Utils::cmdout($params) . 'Removed user "' . $username. '" from site "' . $site . '" in http_auth.')
				->user_id($user->id)
				->channel($channel)
				->cssclass('httpauth')
				->insert();
		}
		else {
			Status::create()
				->data(Utils::cmdout($params) . 'User "' . $username. '" doesn\'t exist in site "' . $site . '" in http_auth.')
				->user_id($user->id)
				->channel($channel)
				->cssclass('httpauth')
				->insert();
		}
		
		return true;
	}

	function _auth_remove_user($params) {
		$username = $params['user'];
		$user = Auth::user();
		$channel = $params['channel'];
		
		$auth = new DB('mysql:host=localhost;dbname=auth', 'root', '', 'auth');
		if(!$auth) {
			return $this->_cant_connect();
		}

		if($auth->val("SELECT count(*) FROM user_info WHERE user_name = :username", array('username' => $username)) > 0) { 		
			$auth->query("DELETE FROM user_info where user_name = :username;", array('username' => $username));
			$auth->query("DELETE FROM user_group where user_name = :username;", array('username' => $username));
			Status::create()
				->data(Utils::cmdout($params) . 'Removed user "' . $username. '" from http_auth.')
				->user_id($user->id)
				->channel($channel)
				->cssclass('httpauth')
				->insert();
		}
		else {
			Status::create()
				->data(Utils::cmdout($params) . 'User "' . $username. '" doesn\'t exist in http_auth.')
				->user_id($user->id)
				->channel($channel)
				->cssclass('httpauth')
				->insert();
		}
		
		return true;
	}
	
	function _auth_explain($params) {
		$username = $params['user'];
		$password = $params['password'];
		$user = Auth::user();
		$channel = $params['channel'];
		
		$auth = new DB('mysql:host=localhost;dbname=auth', 'root', '', 'auth');
		if(!$auth) {
			return $this->_cant_connect();
		}

		$groups = $auth->col("SELECT g.user_group FROM user_group g inner join user_info i on i.user_name = g.user_name WHERE g.user_name = :username", array('username' => $username));
		$groups = implode(', ', $groups);
		$users = $auth->col("SELECT g.user_name FROM user_group g inner join user_info i on i.user_name = g.user_name WHERE g.user_group = :username", array('username' => $username));
		$users = implode(', ', $users);
		Status::create()
			->data(Utils::cmdout($params) . 'User "' . $username. '" is in these groups: ' . $groups . '<br>Group "' . $username . '" has these users: ' . $users)
			->user_id($user->id)
			->channel($channel)
			->cssclass('httpauth')
			->insert();
		
		return true;
	}

	function autocomplete($auto, $cmd){
		$auto[] = "/auth adduser \tusername password";
		$auto[] = "/auth setpassword \tusername password";
		$auto[] = "/auth siteadd \tusername site";
		$auto[] = "/auth siteremove \tusername site";
		$auto[] = "/auth removeuser \tusername";
		$auto[] = "/auth explain \tusername or group";
		return $auto;
	}
	
	function _cant_connect() {
		Status::create()
			->data(Utils::cmdout($params) . 'Couldn\'t connect to http_auth database.')
			->user_id($user->id)
			->channel($channel)
			->cssclass('httpauth')
			->insert();
		return true;
	}
	
}

?>
