<?

	class Database extends PDO {
		
		public function __construct() {
			$host = 'localhost';
			$db = 'spotless_fb_data';
			$user = 'spotless_spot';
			$password = 'ghW8@1hT';
			$charset = 'utf8';

			$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
			$options = array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES => false,
			);
			parent::__construct( $dsn, $user, $password, $options );
		}
		
		public function prepare( $query ) {
			try {
				$stmt = parent::prepare( $query );
				return $stmt;
			} catch ( Exception $exception ) {
				$this->catchException( $exception, $query );
				return false;
			}
		}
		
		public function queryPrepared( $query, $variables = array() ) {
			try {
				$stmt = parent::prepare( $query );
				$stmt->execute( $variables );
				return $stmt;
			} catch ( Exception $exception ) {
				$this->catchException( $exception, $query, $variables );
				return false;
			}
		}
		
		public function fetchRowPrepared( $query, $variables = array() ) {
			$stmt = $this->queryPrepared( $query, $variables );
			return $stmt->fetch();
		}
		
		public function fetchColumnPrepared( $query, $variables = array(), $column = 0 ) {
			$stmt = $this->queryPrepared( $query, $variables );
			return $stmt->fetchColumn( $column );
		}
		
		public function query( $query ) {
			try {
				$stmt = parent::query( $query );
				return $stmt;
			} catch ( Exception $exception ) {
				$this->catchException( $exception, $query );
				return false;
			}
		}
		
		public function fetchRow( $query ) {
			$stmt = $this->query( $query );
			return $stmt->fetch();
		}
		
		public function fetchColumn( $query, $column = 0 ) {
			$stmt = $this->query( $query );
			return $stmt->fetchColumn( $column );
		}
		
		private function catchException( $exception, $query, $variables = array() ) {
			if ( $this->inTransaction() === true ) $this->rollBack();
			throw new Exception( $exception->getMessage() . ".\nQuery: \"".$query."\"\nVariables: ".implode( ', ', $variables ), 0, $exception );
		}
	}
?>