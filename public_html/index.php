<?php

	/* SYSTEM SETTINGS */

	// set error reporting level
	error_reporting(E_ERROR | E_WARNING | E_PARSE);
	// display errors on page (set to false on production servers)
	ini_set('display_errors', true);
	// set timezone
	date_default_timezone_set('America/Los_Angeles');
	
	/* APPLICATION START */

	set_include_path( '../application/' );
	session_save_path( '../private_data/sessions/');
	
	require('Autoloader.php');
	require('ExceptionHandler.php');
	
	$app = new App();
	$app->run();
?>