<?

	// uncaught exception handler
	set_exception_handler( function ( $exception ) {
		$response = new stdClass();
		if ( ini_get( 'display_errors' ) == true ) {
			$response->error = 'Exception Error (file: '.$exception->getFile().', line: '.$exception->getLine().'): '.$exception->getMessage();
		}
		exit( json_encode( $response ) );
	} );
?>