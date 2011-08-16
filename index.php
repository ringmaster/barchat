<?php

// MICROSITE LOADER


// Do not change this line, it indicates where the base filestructure starts
define('MICROSITE_PATH', dirname(__FILE__));

// Change this line if your config file is in a different directory
define('MICROSITE_CONFIG', dirname(__FILE__) . '/microsite_config.php');

include 'microsite/classes/db.php';

$db = false;
if(file_exists(MICROSITE_CONFIG)) {
	include MICROSITE_CONFIG;
	$db = new DB($config['connect_string'], $config['db_user'], $config['db_pass']);
	/*
	   $db->exec('
	   CREATE TABLE presence (
	   status   integer PRIMARY KEY AUTO_INCREMENT NOT NULL UNIQUE,
	   type     varchar(50) DEFAULT 'message',
	   channel  varchar(50) DEFAULT 'global',
	   data     text DEFAULT NULL,
	   msgtime  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	   user_id  integer
	   );

	   CREATE INDEX presence_channel
	   ON presence
	   (channel);

	   CREATE TABLE sessions (
	   id           integer PRIMARY KEY AUTO_INCREMENT NOT NULL UNIQUE,
	   session_key  varchar(50) NOT NULL UNIQUE,
	   user_id      integer NOT NULL,
	   pingtime     timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
	   );

	   CREATE UNIQUE INDEX sessions_key
	   ON sessions
	   (session_key);

	   CREATE TABLE users (
	   id        integer PRIMARY KEY AUTO_INCREMENT NOT NULL UNIQUE,
	   username  varchar(50) NOT NULL UNIQUE,
	   password  varchar(50) NOT NULL
	   );

	   CREATE UNIQUE INDEX users_username
	   ON users
	   (username);
		*/
	
}


include 'microsite/index.php';

?>