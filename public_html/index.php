<?php

	/* SYSTEM SETTINGS */

	// set error reporting level
	error_reporting( E_ERROR | E_WARNING | E_PARSE );
	// display errors on page (set to false on production servers)
	ini_set( 'display_errors', false );
	// set timezone
	date_default_timezone_set( 'America/Los_Angeles' );
	
	/* APPLICATION START */

	set_include_path( '../application/' );
	session_save_path( '../private_data/sessions/' );
	
	require( 'Autoloader.php' );
	require( 'ExceptionHandler.php' );
	
	$databasePassword = file_get_contents( '../private_data/db_key.txt' ); // txt file contains DB password. hidden from git
	$database = new Database( 'spotless_fb_data', 'spotless_spot', $databasePassword );
	$app = new App( $database );
	$app->run();
?>