<?

	/*
		requests come in via $_GET['r'] in format 'action/{ id }'
		
		$_GET['token'] is required for write operations
		a POST request with action 'process' will begin post scrape data processor
		
		options
			
		GET - choosing fields to return
			$_GET['fields'] (comma separated field list) default '*'
			
		GET, POST, DELETE - conditional operations
			$_GET['where_field'] (field name)
			$_GET['where_operator'] ('e', 'gt', 'gte', 'lt', 'lte', 'match') default 'e'
			$_GET['where_value'] (value to compare)
			
		GET - ordering
			$_GET['order_by'] (field name) default 'id'
			$_GET['order'] ('ASC'/'DESC') default 'ASC'
		
		GET - limiting return data
			$_GET['row_count'] (number of rows)
			$_GET['offset'] (start row) default 0
			
		PUT, POST - record data
			$_GET['data'] (JSON string)
	*/
	
	class App {
		
		protected $access_token;
		protected $database;
		protected $availableActions = array( 'pages', 'posts', 'users', 'comments', 'post_reactions', 'process' );
		
		public function run() {
			$this->access_token = file_get_contents( '../private_data/api_key.txt' ); // txt file contains API key. hidden from git
			if ( $this->hasAccess() ) {
				$request = explode( '/', $_GET['r'] );
				$this->processRequest( $request[0], $request[1] );
			}
		}
		
		private function hasAccess() {
			return ( $_SERVER['REQUEST_METHOD'] === 'GET' || $_GET['token'] === $this->access_token );
		}
		
		private function processRequest( $action, $id ) {
			$response = new stdClass();
			
			if ( !$this->actionExists( $action ) ) {
				$response->error = 'Error: This action does not exist.';
			} else {
				$this->database = new Database();
				switch( $_SERVER['REQUEST_METHOD'] ) {
					case 'GET':
						$response = $this->getRequest( $action, $id, $response );
						break;
					case 'PUT':
						$response = $this->putRequest( $action, $response );
						break;
					case 'POST':
						if ( $action == 'process' ) { // special POST action to begin post processing
							$postProcessor = new PostProcessor( $this->database );
							$response = $postProcessor->process( $response );
						} else {
							$response = $this->postRequest( $action, $id, $response );
						}
						break;
					case 'DELETE':
						$response = $this->deleteRequest( $action, $id, $response );
						break;
				}
			}
			
			exit( json_encode( $response ) );
		}
		
		private function actionExists( $action ) {
			return ( in_array( $action, $this->availableActions ) );
		}
		
		private function getRequest( $action, $id, $response ) {
			$fields = $this->generateFieldsList();
			$query = "SELECT $fields FROM $action";
			$variables = array();
			
			if ( $id ) {
				$query .= $this->generateDefaultIDQuerySegment();
				$variables[] = $id;
				$response->data = $this->decodeDatabaseJSON( $this->database->fetchRowPrepared( $query, $variables ) );
			} else {
				$response->data = new stdClass();
				$query .= $this->generateWhereQuerySegment().$this->generateOrderQuerySegment().$this->generateLimitQuerySegment();
				$variables = $this->addLimitQueryVariables( $this->addWhereQueryVariables( $variables ) );
				$stmt = $this->database->queryPrepared( $query, $variables );
				foreach ( $stmt as $row ) $response->data[$row['id']] = $this->decodeDatabaseJSON( $row );
			}
			
			return $response;
		}
		
		private function putRequest( $action, $response ) {
			$variables = array();
			$data = $this->getRecordData();
			
			$query = $this->generatePutQuery( $action, $data );
			$variables = $this->generatePutVariables( $data, $variables );
			$this->database->queryPrepared( $query, $variables );
			$response->id = ( ( $action->id ) ? $action->id : PDO::lastInsertId() );
			
			return $response;
		}
		
		private function postRequest( $action, $id, $response ) {
			$query = "UPDATE $action SET";
			$variables = array();
			$data = $this->getRecordData();
			$query .= $this->generatePostQuerySegment( $data );
			$variables = $this->generatePostVariables( $data, $variables );
			
			if ( $id ) {
				$query .= $this->generateDefaultIDQuerySegment();
				$variables[] = $id;
			} else {
				$query .= $this->generateWhereQuerySegment();
				$variables = $this->addWhereQueryVariables( $variables );
			}
			$stmt = $this->database->queryPrepared( $query, $variables );
			$response->rowCount = $stmt->rowCount();
			
			return $response;
		}
		
		private function deleteRequest( $action, $id, $response ) {
			$query = "DELETE * FROM $action";
			$variables = array();
			
			if ( $id ) {
				$query .= $this->generateDefaultIDQuerySegment();
				$variables[] = $id;
			} else {
				$query .= $this->generateWhereQuerySegment();
				$variables = $this->addWhereQueryVariables( $variables );
			}
			$stmt = $this->database->queryPrepared( $query, $variables );
			$response->rowCount = $stmt->rowCount();
			
			return $response;
		}
		
		private function sanitizeField( $input ) {
			return '`'.trim( str_replace( '`', '', $input ) ).'`';
		}
		
		private function generateDefaultIDQuerySegment() {
			return ' WHERE id = ? LIMIT 1';
		}
		
		private function generateFieldsList() {
			if ( $_GET['fields'] ) {
				$fields = explode( ',', $_GET['fields'] );
				if ( !in_array( 'id', $fields ) ) $fields[] = 'id';
				return implode( ', ', array_map( function( $input ) {
					return $this->sanitizeField( $input );
				}, $fields ) );
			} else {
				return '*';
			}
		}
		
		private function generateWhereQuerySegment() {
			if ( $_GET['where_field'] ) {
				return ' WHERE '.$this->sanitizeField( $_GET['where_field'] ).' '.$this->generateWhereOperator().' ?';
			} else {
				return ' WHERE 1';
			}
		}
		
		private function generateWhereOperator() {
			$returnValue = '=';
			switch( $_GET['where_operator'] ) {
				case 'e':
					$returnValue = '=';
					break;
				case 'gt':
					$returnValue = '>';
					break;
				case 'gte':
					$returnValue = '>=';
					break;
				case 'lt':
					$returnValue = '<';
					break;
				case 'lte':
					$returnValue = '<=';
					break;
				case 'match':
					$returnValue = 'LIKE';
					break;
			}
			return $returnValue;
		}
		
		private function addWhereQueryVariables( $variables ) {
			if ( $_GET['where_value'] ) {
				if ( $_GET['where_operator'] == 'match' ) {
					$variables[] = '%'.$_GET['where_value'].'%';
				} else {
					$variables[] = $_GET['where_value'];
				}
			}
			return $variables;
		}
		
		private function generateOrderQuerySegment() {
			if ( $_GET['order_by'] ) {
				$order = ( $_GET['order'] == 'DESC' ) ? 'DESC' : 'ASC';
				return ' ORDER BY '.$this->sanitizeField( $_GET['order_by'] ).' '.$order;
			} else {
				return ' ORDER BY id ASC';
			}
		}
		
		private function generateLimitQuerySegment() {
			if ( $_GET['row_count'] ) {
				$offset = ( $_GET['offset'] ) ? ' ?,' : '';
				return ' LIMIT'.$offset.' ?';
			} else {
				return '';
			}
		}
		
		private function addLimitQueryVariables( $variables ) {
			if ( $_GET['row_count'] && $_GET['offset'] ) $variables[] = $_GET['offset'];
			if ( $_GET['row_count'] ) $variables[] = $_GET['row_count'];
			return $variables;
		}
		
		private function getRecordData() {
			return json_decode( $_GET['data'] );
		}
		
		private function generatePutQuery( $action, $data ) {
			$query = "INSERT INTO $action (";
			$query .= implode( ', ', array_map( function( $input ) {
				return $this->sanitizeField( $input );
			}, array_keys( $data ) ) );
			$query .= ') VALUES(';
			$query .= implode( ', ', array_fill( 0, count( $data ), '?' ) );
			$query .= ') ON DUPLICATE KEY UPDATE ';
			$query .= $this->generatePostQuerySegment( $data );
			return $query;
		}
		
		private function generatePutVariables( $data, $variables ) {
			$variables = $this->generatePostVariables( $data, $variables );
			$variables = $this->generatePostVariables( $data, $variables );
			return $variables;
		}
		
		private function generatePostQuerySegment( $data ) {
			$query = '';
			foreach( $data as $field => $value ) {
				$query .= ' '.$this->sanitizeField( $field ).' = ?';
			}
			return $query;
		}
		
		private function generatePostVariables( $data, $variables ) {
			foreach( $data as $value ) {
				if ( is_object( $value ) ) $value = json_encode( $value );
				$variables[] = $value;
			}
			return $variables;
		}
		
		private function decodeDatabaseJSON( $record ) {
			foreach( $record as $field => $value ) {
				$object = json_decode( $value );
				if ( is_object( $object ) ) $record[$field] = $object;
			}
			return $record;
		}
	}
?>