<?php

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
	
	$database = new Database();
	$postProcessor = new PostProcessor( $database );
	$postProcessor->process();
?>