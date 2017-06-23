<?

	class PostProcessor {
		
		protected database;
		
		public function __construct( $database ) {
			$this->database = $database;
		}
		
		public function process( $response ) {
			$this->deleteOldRecords();
			$this->updateMetaData();
			
			return $response;
		}
		
		private function deleteOldRecords() {
			$query = 'SELECT * FROM posts WHERE created_time < ?';
			$variables = array( $_GET['earliestPostCullDate'] );
			$stmt = $this->database->queryPrepared( $query, $variables );
			foreach( $stmt as $post ) {
				$variables = array( $post->id );
				$query = 'DELETE FROM post_reactions WHERE post_id = ?';
				$this->database->queryPrepared( $query, $variables );
				$query = 'DELETE FROM comments WHERE post_id = ?';
				$this->database->queryPrepared( $query, $variables );
			}
			$query = 'DELETE FROM posts WHERE created_time < ?';
			$variables = array( $_GET['earliestPostCullDate'] );
			$this->database->queryPrepared( $query, $variables );
		}
		
		private function updateMetaData() {
			$query = 'UPDATE meta_data SET earliestPostTime = ?, latestPostTime = ?';
			$variables = array( $_GET['earliestPostCullDate'], $_GET['latestPostDate'] );
			$this->database->queryPrepared( $query, $variables );
		}
	}
?>