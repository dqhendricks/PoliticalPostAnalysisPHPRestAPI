<?php

	/*
		i have this running on a cron job every 5 minutes. if it sees a new scrape has finished running, it begins the post processing, otherwise does nothing.
	*/

	/* SYSTEM SETTINGS */

	// set error reporting level
	error_reporting( E_ERROR | E_WARNING | E_PARSE );
	// display errors on page (set to false on production servers)
	ini_set( 'display_errors', true );
	// set timezone
	date_default_timezone_set('America/Los_Angeles');
	
	/* APPLICATION START */

	require( __DIR__.'/application/Database.php' );
	require( __DIR__.'/application/PostProcessor.php' );
	
	$databasePassword = file_get_contents( __DIR__.'/private_data/db_key.txt' ); // txt file contains DB password. hidden from git
	$database = new Database( 'spotless_fb_data', 'spotless_spot', $databasePassword );
	$postProcessor = new PostProcessor( $database );
	$postProcessor->process();
?>