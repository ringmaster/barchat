<?php

class Application 
{
	static $path;
	
	public static function Start()
	{
		self::set_path();

		$glob = glob(MICROSITE_PATH . '/microsite/controllers/*.php');
		$controllers = array();
		foreach($glob as $controller) {
			$controllers[basename($controller, '.php')] = $controller;
		}

		if(self::$path[0] == '') {
			$controller = 'index';
		}
		else {
			$controller = self::$path[0];
		}

		// Am I logged in?
		
		$hasauth = false;
		if(self::$path[0] == 'presence' && self::$path[1] == 'dosmartstatus') {
			$hasauth = true;
		}
		if(Auth::loggedin()) {
			$hasauth = true;
		}
		if(in_array($controller, array('auth', 'botrest'))) {
			$hasauth = true;
		}
		
		if(!$hasauth) {
			header('location: /auth?path=' . implode('/', self::$path));
			exit;
		}

		if(isset($controllers[$controller])) {
			include $controllers[$controller];
			$class = $controller . 'Controller';
			$obj = new $class(self::$path);
			if(isset(self::$path[1])) {
				if(self::$path[1][0] != '_' && ($obj instanceof AjaxController || method_exists($obj, self::$path[1]))) {
					$args = array_slice(self::$path, 2);
					$method = self::$path[1];
					$obj->$method($args);
				}
				else {
					if(method_exists($obj, 'index')) {
						$args = array_slice(self::$path, 1);
						$obj->index($args);
					}
				}
			}
			else{
				if(method_exists($obj, 'index')) {
					$obj->index(self::$path);
				}
			}
		}
		else {
			print_r(self::$path);
		}
	}
	
	public function __construct($db)
	{
		$this->db = $db;
		$this->set_path();

		$page = $db->row('SELECT * FROM pages p WHERE p.url = :url', array('url' => $this->path));
		
		$view = null;

		switch($_SERVER['REQUEST_METHOD']) {
			case 'POST':
				if(!$page) {
					$db->query('INSERT INTO pages (url) VALUES (:url)', array('url' => $this->path));
					$page = $db->row('SELECT * FROM pages p WHERE p.id = :page_id', array('page_id' => $db->lastInsertId()));
				}

				$db->query('DELETE FROM components WHERE page_id = :page_id', array('page_id' => $page->id));
				
				foreach($_POST['vars'] as $k => $v) {
					if(in_array($k, array('path'))) {
						continue;
					}
					if($v != '') {
						if(get_magic_quotes_gpc()) {
							$v = stripslashes($v);
						}
						$db->query("INSERT INTO components (page_id, name, type, value) VALUES (:page_id, :name, '', :value);", array('page_id' => $page->id, 'name' => $k, 'value' => $v));
					}
				}
				if($_POST['newfield'] != '' && $_POST['newvalue'] != '') {
					$db->query("INSERT INTO components (page_id, name, type, value) VALUES (:page_id, :name, '', :value);", array('page_id' => $page->id, 'name' => $_POST['newfield'], 'value' => $_POST['newvalue']));
				}
				if($_POST['vars']['path'] != $page->url) {
					$db->query('UPDATE pages SET url = :url WHERE id = :page_id', array('url' => $_POST['path'], $page->id));
				}

				header('location: ' . $_SERVER['REQUEST_URI']);
				
				break;
			case 'GET':
				$components = $db->assoc('SELECT c.name, c.value FROM components c WHERE c.page_id = :page_id', array('page_id' => $page->id));
				$v = new View($components);
				$v->path = $this->path;
		
				if($_SERVER['QUERY_STRING'] != '') {
					switch($_SERVER['QUERY_STRING']) {
						case 'edit':
							$view = 'edit';
							break;
					}
				}

				$v->render($view);
				break;
		}
		
	}
	
	public function set_path()
	{
		if(isset($_GET['p'])) {
			$path = $_GET['p'];
		}
		else {
			$base_url = rtrim(dirname(self::script_name()));

			$start_url = (isset($_SERVER['REQUEST_URI'])
				? $_SERVER['REQUEST_URI']
				: $_SERVER['SCRIPT_NAME'] .
					( isset($_SERVER['PATH_INFO'])
					? $_SERVER['PATH_INFO']
					: '') );
			
			if(strpos($start_url, '?')) {
				list($start_url) = explode('?', $start_url, 2);
			}

			if('/' != $base_url) {
				$start_url = str_replace($base_url, '', $start_url);
			}

			$path = trim($start_url, '/');
		}
		
		self::$path = explode('/', $path);
	}
	
	public static function script_name()
	{
		switch (true) {
			case isset($scriptname):
				break;
			case isset($_SERVER['SCRIPT_NAME']):
				$scriptname = $_SERVER['SCRIPT_NAME'];
				break;
			case isset($_SERVER['PHP_SELF']):
				$scriptname = $_SERVER['PHP_SELF'];
				break;
			default:
				throw new exception('Could not determine script name.');
				die();
		}
		return $scriptname;
	}
	
}

?>