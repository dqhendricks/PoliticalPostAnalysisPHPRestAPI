<?

	// PSR-0 compliant class autoloader
	spl_autoload_register( function ( $class_name ) {
		$class_name = ltrim( $class_name, '\\' );
		$file_name = '';
		if ( $last_slash_position = strripos( $class_name, '\\' ) ) {
			$namespace = substr( $class_name, 0, $last_slash_position );
			$class_name = substr( $class_name, $last_slash_position + 1 );
			$file_name = str_replace( '\\', '/', $namespace ) . '/';
		}
		$file_name .= str_replace( '_', '/', $class_name ) . '.php';
		$file_name = stream_resolve_include_path( $file_name );
		if ( $file_name !== false ) {
			require $file_name;
		}
	}, true, true);
?>