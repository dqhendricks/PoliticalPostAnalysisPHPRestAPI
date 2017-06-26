<?

	/*
		requests come in via $_GET['r'] in format 'action/{ id }'
		
		$_GET['token'] is required for write operations
		a POST request with action 'process' will begin after scrape data processor
		
		options
			
		GET - choosing fields to return
			$_GET['fields'] (comma separated field list) default '*'
			$_GET['count'] (true/empty, true returns query row count as 'total' but no other fields)
			
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
			
		POST - record data
			$_POST['data'] (JSON string)
	*/
	
	class App {
		
		protected $accessToken;
		protected $database;
		protected $availableActions = array( 'pages', 'posts', 'users', 'comments', 'post_reactions', 'process', 'meta_data' );
		
		public function run() {
			$this->accessToken = file_get_contents( '../private_data/api_key.txt' ); // txt file contains API key. hidden from git
			if ( $this->hasAccess() ) {
				$request = explode( '/', $_GET['r'] );
				$this->processRequest( $request[0], $request[1] );
			}
		}
		
		private function hasAccess() {
			return ( $_SERVER['REQUEST_METHOD'] === 'GET' || $_GET['token'] === $this->accessToken );
		}
		
		private function processRequest( $action, $id ) {
			$response = new stdClass();
			
			if ( !$this->actionExists( $action ) ) {
				$response->error = "Error: This action does not exist (action: $action, id: $id).";
			} else {
				$this->database = new Database();
				switch( $_SERVER['REQUEST_METHOD'] ) {
					case 'GET':
						$response = $this->getRequest( $action, $id, $response );
						break;
					case 'POST':
						if ( $action == 'process' ) { // special POST action to begin after scrape processing
							$postProcessor = new PostProcessor( $this->database );
							$response = $postProcessor->process( $response );
						} else {
							$response = $this->postRequest( $action, $id, $response );
						}
						break;
					case 'DELETE':
						$response = $this->deleteRequest( $action, $id, $response );
						break;
					default:
						$response->error = 'Error: This method is not supported.';
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
				$response->data = array();
				$query .= $this->generateWhereQuerySegment().$this->generateOrderQuerySegment().$this->generateLimitQuerySegment();
				$variables = $this->addLimitQueryVariables( $this->addWhereQueryVariables( $variables ) );
				$stmt = $this->database->queryPrepared( $query, $variables );
				foreach ( $stmt as $row ) $response->data[$row['id']] = $this->decodeDatabaseJSON( $row );
			}
			
			return $response;
		}
		
		private function postRequest( $action, $id, $response ) {
			$variables = array();
			$data = $this->getRecordData();
			$query = $this->generatePostQuery( $action, $id, $data );
			$variables = $this->generatePostVariables( $data, $id, $variables );
			
			if ( !$id ) {
				$query .= $this->generateWhereQuerySegment();
				$variables = $this->addWhereQueryVariables( $variables );
			}
			$stmt = $this->database->queryPrepared( $query, $variables );
			$response->rowCount = $stmt->rowCount();
			if ( $repsonse->rowCount == 1 ) $response->id = ( ( $action->id ) ? $action->id : PDO::lastInsertId() );
			
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
		
		public function sanitizeField( $input ) {
			return '`'.trim( str_replace( '`', '', $input ) ).'`';
		}
		
		private function generateDefaultIDQuerySegment() {
			return ' WHERE id = ? LIMIT 1';
		}
		
		private function generateFieldsList() {
			if ( $_GET['count'] ) {
				return 'COUNT( * ) AS total';
			} else if ( $_GET['fields'] ) {
				$fields = explode( ',', $_GET['fields'] );
				if ( !in_array( 'id', $fields ) ) $fields[] = 'id'; // always add id
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
			$data = json_decode( $_REQUEST['data'] );
			return $data;
		}
		
		private function generatePostQuery( $action, $id, $data ) {
			if ( $id ) {
				$query = "INSERT INTO $action (";
				$thisRef = $this;
				$query .= implode( ', ', array_map( function ( $input ) use ( $thisRef ) {
					return $thisRef->sanitizeField( $input );
				}, array_keys( ( array )$data ) ) );
				$query .= ') VALUES (';
				$query .= implode( ', ', array_fill( 0, count( ( array )$data ), '?' ) );
				$query .= ') ON DUPLICATE KEY UPDATE ';
			} else {
				$query = "UPDATE $action SET ";
			}
			$query .= $this->generatePostQuerySegment( $data );
			return $query;
		}
		
		private function generatePostVariables( $data, $id, $variables ) {
			foreach( $data as $value ) {
				if ( is_object( $value ) ) $value = json_encode( $value );
				$variables[] = $value;
			}
			if ( $id ) {
				foreach( $data as $value ) {
					if ( is_object( $value ) ) $value = json_encode( $value );
					$variables[] = $value;
				}
			}
			return $variables;
		}
		
		private function generatePostQuerySegment( $data ) {
			$query = array();
			foreach( $data as $field => $value ) {
				$query[] = $this->sanitizeField( $field ).' = ?';
			}
			return implode( ', ', $query );
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