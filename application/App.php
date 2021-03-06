<?

	/*
		requests come in via $_GET['r'] in format 'action/{ id }'
		
		$_GET['token'] is required for write operations
		a POST request with action 'process' will begin after scrape data processor
		
		options
			
		GET - choosing fields to return
			$_GET['fields'] (comma separated field list) default '*'
			$_GET['count'] (true/empty, true returns query row count as 'total' but no other fields)
			
		GET, POST, DELETE - conditional operations (can each be comma separated for multiple search conditions)
			$_GET['where_fields'] (field name)
			$_GET['where_operators'] ('e', 'gt', 'gte', 'lt', 'lte', 'match')
			$_GET['where_values'] (value to compare)
			
		GET - ordering
			$_GET['order_by'] (field name) default 'id'
			$_GET['order_direction'] ('ASC'/'DESC') default 'ASC'
		
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
		protected $whereFields;
		protected $whereOperators;
		protected $whereValues;
		
		public function __construct( $database ) {
			$this->database = $database;
			$this->accessToken = file_get_contents( '../private_data/api_key.txt' ); // txt file contains API key. hidden from git
		}
		
		public function run() {
			if ( $this->hasAccess() ) {
				$request = explode( '/', $_GET['r'] );
				$this->processRequest( $request[0], $request[1] );
			}
		}
		
		private function hasAccess() {
			return ( $_SERVER['REQUEST_METHOD'] === 'GET' || $_GET['token'] === $this->accessToken );
		}
		
		private function processRequest( $action, $id ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Content-type: application/json' );
			$response = new stdClass();
			
			if ( !$this->actionExists( $action ) ) {
				$response->error = "Error: This action does not exist (action: {$action}, id: {$id}).";
			} else if ( !$this->extractWhereArguments() ) {
				$response->error = "Error: You must have the same number of where_fields, where_operators, and where_values.";
			} else {
				switch( $_SERVER['REQUEST_METHOD'] ) {
					case 'GET':
						$response = $this->getRequest( $action, $id, $response );
						break;
					case 'POST':
						// posts without WHERE clauses will insert or update depending if primary key already exists in DB
						$response = $this->postRequest( $action, $id, $response );
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
			$query = "SELECT {$fields} FROM {$action}";
			$variables = array();
			
			if ( $id ) {
				$query .= $this->generateDefaultIDQuerySegment();
				$variables[] = $id;
				$response = $this->decodeDatabaseJSON( $this->database->fetchRowPrepared( $query, $variables ) );
			} else {
				$response = array();
				$query .= $this->generateWhereQuerySegment().$this->generateOrderQuerySegment().$this->generateLimitQuerySegment();
				$variables = $this->addLimitQueryVariables( $this->addWhereQueryVariables( $variables ) );
				$stmt = $this->database->queryPrepared( $query, $variables );
				foreach ( $stmt as $row ) $response[$row['id']] = $this->decodeDatabaseJSON( $row );
			}
			
			return $response;
		}
		
		private function postRequest( $action, $id, $response, $data = null ) {
			$variables = array();
			if ( !$data ) $data = $this->getRecordData();
			$query = $this->generatePostQuery( $action, $id, $data );
			$variables = $this->generatePostVariables( $data, $id, $variables );
			
			if ( $_GET['where_field'] ) {
				$query .= $this->generateWhereQuerySegment();
				$variables = $this->addWhereQueryVariables( $variables );
			}
			$stmt = $this->database->queryPrepared( $query, $variables );
			$response->rowCount = $stmt->rowCount();
			if ( $repsonse->rowCount == 1 ) $response->id = ( ( $action->id ) ? $action->id : PDO::lastInsertId() );
			
			return $response;
		}
		
		private function deleteRequest( $action, $id, $response ) {
			$query = "DELETE * FROM {$action}";
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
		
		private function generateFieldsList() {
			if ( $_GET['count'] ) {
				return 'COUNT( * ) AS total';
			} else if ( $_GET['fields'] ) {
				$fields = explode( ',', $_GET['fields'] );
				if ( !in_array( 'id', $fields ) ) $fields[] = 'id'; // always add id
				return implode( ', ', array_map( array( $this, 'sanitizeField' ), $fields ) );
			} else {
				return '*';
			}
		}
		
		private function generateDefaultIDQuerySegment() {
			return ' WHERE id = ? LIMIT 1';
		}
		
		private function extractWhereArguments() {
			$this->whereFields = explode( ',', $_GET['where_fields'] ); // trim not needed since we trim in field sanitization
			$this->whereOperators = array_map( 'trim', explode( ',', $_GET['where_operators'] ) );
			$this->whereValues = array_map( 'trim', explode( ',', $_GET['where_values'] ) );
			return ( count( $this->whereFields ) == count( $this->whereOperators ) && count( $this->whereFields ) == count( $this->whereValues ) );
		}
		
		private function generateWhereQuerySegment() {
			if ( $_GET['where_fields'] ) {
				$whereConditions = array();
				for ( $i = 0; $i < count( $this->whereFields ); $i++ ) {
					$whereConditions[] = $this->sanitizeField( $this->whereFields[$i] ).' '.$this->processWhereOperator( $this->whereOperators[$i] ).' ?';
				}
				return ' WHERE '.implode( ' AND ', $whereConditions );
			} else {
				return ' WHERE 1';
			}
		}
		
		private function processWhereOperator( $whereOperator ) {
			$returnValue = '=';
			switch( $whereOperator ) {
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
			if ( $_GET['where_values'] ) {
				for ( $i = 0; $i < count( $this->whereValues ); $i++ ) {
					if ( $this->whereOperators[$i] == 'match' ) {
						$variables[] = '%'.$this->whereValues[$i].'%';
					} else {
						$variables[] = $this->whereValues[$i];
					}
				}
			}
			return $variables;
		}
		
		private function generateOrderQuerySegment() {
			if ( $_GET['order_by'] ) {
				$orderDirection = ( $_GET['order_direction'] == 'DESC' ) ? 'DESC' : 'ASC';
				return ' ORDER BY '.$this->sanitizeField( $_GET['order_by'] ).' '.$orderDirection;
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
			if ( $_GET['where_field'] ) {
				$query = "UPDATE {$action} SET ";
			} else {
				$query = "INSERT INTO {$action} (";
				$thisRef = $this;
				$query .= implode( ', ', array_map( array( $this, 'sanitizeField' ), array_keys( ( array )$data ) ) );
				$query .= ') VALUES (';
				$query .= implode( ', ', array_fill( 0, count( ( array )$data ), '?' ) );
				$query .= ') ON DUPLICATE KEY UPDATE ';
			}
			$query .= $this->generatePostQuerySegment( $data );
			return $query;
		}
		
		private function generatePostVariables( $data, $id, $variables ) {
			foreach( $data as $value ) {
				if ( is_object( $value ) ) $value = json_encode( $value );
				$variables[] = $value;
			}
			if ( !$_GET['where_field'] ) {
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
				if ( is_object( $object ) ) $record[$field] = $this->decodeDatabaseJSON( $object );
			}
			return $record;
		}
	}
?>