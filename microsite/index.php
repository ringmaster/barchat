<?php

include MICROSITE_PATH . '/microsite/classes/cloudfusion/cloudfusion.class.php';

function __autoload($class_name) {
	static $files = null;

	$success = false;
	$class_file = strtolower($class_name) . '.php';

	if ( empty($files) ) {
		$files = array();

		$glob = glob( MICROSITE_PATH . '/microsite/classes/*.php' );
		$fnames = array_map(create_function('$a', 'return strtolower(basename($a));'), $glob);
		$files = array_merge($files, array_combine($fnames, $glob));

		$glob = glob( MICROSITE_PATH . '/microsite/controllers/*.php' );
		$fnames = array_map(create_function('$a', 'return strtolower(basename($a, ".php") . "controller.php");'), $glob);
		$files = array_merge($files, array_combine($fnames, $glob));

	}

	// Search in the available files for the undefined class file.
	if ( isset($files[$class_file]) ) {
		require $files[$class_file];
		// If the class has a static method named __static(), execute it now, on initial load.
		if ( class_exists($class_name, false) && method_exists($class_name, '__static') ) {
			call_user_func(array($class_name, '__static'));
		}
		$success = true;
	}
	if(!$success) {
		var_dump($class_name);
		var_dump($files);
debug_print_backtrace();
	}
}

spl_autoload_register('__autoload');



/*
include 'classes/application.php';
include 'classes/view.php';
include 'classes/auth.php';
include 'classes/simple_html_dom.php';
include 'classes/dice.php';
include 'classes/utils.php';
include 'classes/plugin.php';
*/
	
Application::start();


?>
