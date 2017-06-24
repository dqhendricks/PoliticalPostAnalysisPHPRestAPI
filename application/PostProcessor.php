<?

	class PostProcessor {
		
		protected $database;
		
		public function __construct( $database ) {
			$this->database = $database;
		}
		
		public function process( $response ) {
			$this->deleteOldRecords();
			$this->updateMetaData();
			$this->updatePostMetaData();
			$this->updateUserMetaData();
			$this->updatePageMetaData();
			
			$response->success = true;
			return $response;
		}
		
		private function deleteOldRecords() {
			// remove reactions and comments for cull posts
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
			// remove cull posts
			$query = 'DELETE FROM posts WHERE created_time < ?';
			$variables = array( $_GET['earliestPostCullDate'] );
			$this->database->queryPrepared( $query, $variables );
			// remove orphaned users
			$query = 'SELECT * FROM users WHERE 1';
			$stmt = $this->database->query( $query );
			foreach( $stmt as $user ) {
				$query = 'SELECT COUNT( x.id ) AS total_interactions'
					.' FROM ('
						.' SELECT post_reactions.id FROM post_reactions WHERE post_reactions.user_id = '.$user->id
						.' UNION SELECT comments.id FROM comments WHERE comments.user_id = '.$user->id
					.' ) AS x'
					.' WHERE 1';
				$record = $this->database->fetchRow( $query );
				if ( $record->total_interactions == 0 ) {
					$query = 'DELETE FROM users WHERE id = '.$user->id;
					$this->database->query( $query );
				}
			}
		}
		
		private function updateMetaData() {
			$query = 'UPDATE meta_data SET value = ? WHERE key = "earliestPostTime"';
			$variables = array( $_GET['earliestPostCullDate'] );
			$this->database->queryPrepared( $query, $variables );
			$query = 'UPDATE meta_data SET value = ? WHERE key = "latestPostTime"';
			$variables = array( $_GET['latestPostDate'] );
			$this->database->queryPrepared( $query, $variables );
		}
		
		private function updatePostMetaData() {
			// reaction data
			$stmt = $this->getTotalReactionsBy( 'post_id' );
			foreach( $stmt as $post ) {
				if ( $post->total_reactions != 0 ) {
					$this->updateTotalReactions( 'posts', $post, $post->post_id );
					$this->updateControversialityScore( 'posts', $post, $post->post_id );
				}
			}
			// comment data
			$stmt = $this->getTotalCommentsBy( 'post_id' );
			foreach( $stmt as $post ) {
				if ( $post->total_comments != 0 ) {
					$this->updateTotalComments( 'posts', $post, $post->post_id );
				}
			}
		}
		
		private function updateUserMetaData() {
			// reaction data
			$stmt = $this->getTotalReactionsBy( 'user_id' );
			foreach( $stmt as $user ) {
				$this->updateTotalReactions( 'users', $user, $user->user_id );
			}
			// comment data
			$stmt = $this->getTotalCommentsBy( 'user_id' );
			foreach( $stmt as $user ) {
				$this->updateTotalComments( 'users', $user, $user->user_id );
			}
			// affiliation data
			$query = 'SELECT post_reactions.user_id'
				.', COUNT( post_reactions.id ) AS total_likes'
				.', SUM( pages.affiliation ) AS affiliation_total'
				.' FROM post_reactions'
				.' LEFT JOIN pages ON pages.id = post_reactions.page_id'
				.' WHERE 1'
				.' GROUP BY post_reactions.user_id';
			$stmt = $this->database->query( $query );
			foreach( $stmt as $user ) {
				$affiliation = $user->affiliation_total / $user->total_likes;
				$query = 'UPDATE users SET affiliation = '.$affiliation.' WHERE users.id = '.$user->user_id;
				$this->database->query( $query );
			}
			// pages interacted with data
			$query = 'SELECT x.user_id'
				.', GROUP_CONCAT( DISTINCT x.page_id ) AS pages_interacted_with'
				.', COUNT( DISTINCT x.page_id ) AS total_pages_interacted_with'
				.' FROM ('
					.' SELECT post_reactions.page_id, post_reactions.user_id FROM post_reactions WHERE 1'
					.' UNION SELECT comments.page_id, comments.user_id FROM comments WHERE 1'
				.' ) AS x'
				.' WHERE 1'
				.' GROUP BY x.user_id';
			$stmt = $this->database->query( $query );
			foreach( $stmt as $user ) {
				$query = 'UPDATE users'
					.' SET pages_interacted_with = "'.$user->pages_interacted_with.'"'
					.', total_pages_interacted_with = '.$user->total_pages_interacted_with
					.' WHERE id = '.$user->user_id;
				$this->database->query( $query );
			}
			// posts interacted with data
			$query = 'SELECT x.user_id'
				.', COUNT( DISTINCT x.post_id ) AS total_posts_interacted_with'
				.' FROM ('
					.' SELECT post_reactions.post_id, post_reactions.user_id FROM post_reactions WHERE 1'
					.' UNION SELECT comments.post_id, comments.user_id FROM comments WHERE 1'
				.' ) AS x'
				.' WHERE 1'
				.' GROUP BY x.user_id';
			$stmt = $this->database->query( $query );
			foreach( $stmt as $user ) {
				$query = 'UPDATE users'
					.' SET total_posts_interacted_with = '.$user->total_posts_interacted_with
					.' WHERE id = '.$user->user_id;
				$this->database->query( $query );
			}
		}
		
		private function updatePageMetaData() {
			// reaction data
			$stmt = $this->getTotalReactionsBy( 'page_id' );
			foreach( $stmt as $page ) {
				$this->updateTotalReactions( 'pages', $page, $page->page_id );
				$this->updateControversialityScore( 'pages', $page, $page->page_id );
			}
			// comment data
			$stmt = $this->getTotalCommentsBy( 'page_id' );
			foreach( $stmt as $page ) {
				$this->updateTotalComments( 'pages', $page, $page->page_id );
			}
			// posts data
			$query = 'SELECT page_id, COUNT( id ) AS total_posts FROM posts WHERE 1 GROUP BY page_id';
			$stmt = $this->database->query( $query );
			foreach( $stmt as $page ) {
				$query = 'UPDATE pages SET total_posts = '.$page->total_posts.' WHERE id = '.$page->page_id;
				$this->database->query( $query );
			}
		}
		
		private function getTotalReactionsBy( $field ) {
			$query = 'SELECT '.$field
				.', COUNT( id ) AS total_reactions'
				.', SUM( CASE WHEN type = "LOVE" THEN 1 ELSE 0 END ) AS total_love_reactions'
				.', SUM( CASE WHEN type = "WOW" THEN 1 ELSE 0 END ) AS total_wow_reactions'
				.', SUM( CASE WHEN type = "HAHA" THEN 1 ELSE 0 END ) AS total_haha_reactions'
				.', SUM( CASE WHEN type = "SAD" THEN 1 ELSE 0 END ) AS total_sad_reactions'
				.', SUM( CASE WHEN type = "ANGRY" THEN 1 ELSE 0 END ) AS total_angry_reactions'
				.' FROM post_reactions'
				.' WHERE 1'
				.' GROUP BY '.$field;
			return $this->database->query( $query );
		}
		
		private function updateTotalReactions( $table, $totals_record, $id ) {
			$highestReactionType = $this->getHighestReactionType( $totals_record );
			$query = 'UPDATE '.$table
				.' SET total_reactions = '.$totals_record->total_reactions
				.', total_love_reactions = '.$totals_record->total_love_reactions
				.', total_wow_reactions = '.$totals_record->total_wow_reactions
				.', total_haha_reactions = '.$totals_record->total_haha_reactions
				.', total_sad_reactions = '.$totals_record->total_sad_reactions
				.', total_angry_reactions = '.$totals_record->total_angry_reactions
				.', highest_reaction_type = '.$highestReactionType
				.' WHERE id = '.$id;
			$this->database->query( $query );
		}
		
		private function updateControversialityScore( $table, $totals_record, $id ) {
			// basically, the closer the total positive and total negative reactions are to each other, the more controversial
			$total_positive_reactions = $totals_record->total_love_reactions + $totals_record->total_haha_reactions;
			$total_negative_reactions = $totals_record->total_sad_reactions + $totals_record->total_angry_reactions;
			if ( $total_positive_reactions != 0 && $total_negative_reactions != 0 ) {
				if ( $total_positive_reactions > $total_negative_reactions ) {
					$controversiality_score = $total_negative_reactions / $total_positive_reactions;
				} else {
					$controversiality_score = $total_positive_reactions / $total_negative_reactions;
				}
				$query = "UPDATE $table SET controversiality_score = $controversiality_score WHERE id = $id";
				$this->database->query( $query );
			}
		}
		
		private function getHighestReactionType( $totals_record ) {
			$totals_record = (array)$totals_record;
			$reaction_types = array(
				'LOVE' => 'total_love_reactions',
				'WOW' => 'total_wow_reactions',
				'HAHA' => 'total_haha_reactions',
				'SAD' => 'total_sad_reactions',
				'ANGRY' => 'total_angry_reactions'
			);
			$highest_type = array( 'type' => '', 'total' => 0 );
			foreach( $reaction_types as $type => $field ) {
				if ( $totals_record[$field] > $highest_type['total'] ) {
					$highest_type['type'] = $type;
					$highest_type['total'] = $totals_record[$field];
				}
			}
			return $highest_type['type'];
		}
		
		private function getTotalCommentsBy( $field ) {
			$query = 'SELECT '.$field
				.', COUNT( id ) AS total_comments'
				.', SUM( like_count ) AS total_comment_likes'
				.', SUM( CASE WHEN like_count = 0 THEN 1 ELSE 0 END ) AS total_comments_zero_likes'
				.' FROM comments'
				.' WHERE 1'
				.' GROUP BY '.$field;
			return $this->database->query( $query );
		}
		
		private function updateTotalComments( $table, $totals_record, $id ) {
			$query = 'UPDATE '.$table
				.' SET total_comments = '.$totals_record->total_comments
				.', total_comment_likes = '.$totals_record->total_comment_likes
				.', total_comments_zero_likes = '.$totals_record->total_comments_zero_likes
				.' WHERE id = '.$id;
			$this->database->query( $query );
		}
	}
?>