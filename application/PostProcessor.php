<?

	class PostProcessor {
		
		protected $database;
		protected $meta_data_query_stmt;
		
		public function __construct( $database ) {
			$this->database = $database;
			$query = 'INSERT INTO meta_data ( key, value, name, description ) VALUES ( ?, ?, ?, ? ) ON DUPLICATE KEY UPDATE value = ?, name = ?, description = ?';
			$this->meta_data_query_stmt = $this->database->prepare( $query );
		}
		
		public function process( $response ) {
			set_time_limit( 0 );
			$this->deleteOldRecords();
			$this->updateMissingUsers();
			$this->updatePostMetaData();
			$this->updateUserMetaData();
			$this->updatePageMetaData();
			$this->updateMetaData();
			
			$response->success = true;
			return $response;
		}
		
		private function setMetaData( $key, $value, $name = '', $description = '' ) {
			$variables = array( $key, $value, $name, $description, $value, $name, $description );
			return $stmt->execute( $variables );
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
						." SELECT post_reactions.id FROM post_reactions WHERE post_reactions.user_id = {$user->id}"
						." UNION SELECT comments.id FROM comments WHERE comments.user_id = {$user->id}"
					.' ) AS x'
					.' WHERE 1';
				$record = $this->database->fetchRow( $query );
				if ( $record->total_interactions == 0 ) {
					$query = "DELETE FROM users WHERE id = {$user->id}";
					$this->database->query( $query );
				}
			}
		}
		
		private function updateMissingUsers() {
			// some users cannot be scraped
			$missing_users_count = 0;
			$query = 'SELECT comments.from FROM comments LEFT JOIN users ON users.id = comments.user_id WHERE users.is IS NULL';
			$stmt = $this->database->query( $query );
			foreach( $stmt as $comment ) {
				$user = json_decode( $comment->from );
				$query = 'INSERT INTO users ( id, name, link )'
					." VALUES ( {$user->id}, '{$user->name}', 'https://www.facebook.com/app_scoped_user_id/{$user->id}/' )"
					.' ON DUPLICATE KEY UPDATE id = id';
				$this->database->query( $query );
			}
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
				$this->updateDuplicateCommentCount( $user->user_id );
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
				$query = "UPDATE users SET affiliation = {$affiliation} WHERE users.id = {$user->user_id}";
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
					." SET pages_interacted_with = '{$user->pages_interacted_with}'"
					.", total_pages_interacted_with = {$user->total_pages_interacted_with}"
					." WHERE id = {$user->user_id}";
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
					." SET total_posts_interacted_with = {$user->total_posts_interacted_with}"
					." WHERE id = {$user->user_id}";
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
				$query = "UPDATE pages SET total_posts = {$page->total_posts} WHERE id = {$page->page_id}";
				$this->database->query( $query );
			}
		}
		
		private function getTotalReactionsBy( $field ) {
			$query = "SELECT {$field}"
				.', COUNT( id ) AS total_reactions'
				.', SUM( CASE WHEN type = "LIKE" THEN 1 ELSE 0 END ) AS total_like_reactions'
				.', SUM( CASE WHEN type = "LOVE" THEN 1 ELSE 0 END ) AS total_love_reactions'
				.', SUM( CASE WHEN type = "WOW" THEN 1 ELSE 0 END ) AS total_wow_reactions'
				.', SUM( CASE WHEN type = "HAHA" THEN 1 ELSE 0 END ) AS total_haha_reactions'
				.', SUM( CASE WHEN type = "SAD" THEN 1 ELSE 0 END ) AS total_sad_reactions'
				.', SUM( CASE WHEN type = "ANGRY" THEN 1 ELSE 0 END ) AS total_angry_reactions'
				.' FROM post_reactions'
				.' WHERE 1'
				." GROUP BY {$field}";
			return $this->database->query( $query );
		}
		
		private function updateTotalReactions( $table, $totalsRecord, $id ) {
			$highestReactionType = $this->getHighestReactionType( $totalsRecord );
			$query = "UPDATE {$table}"
				." SET total_reactions = {$totalsRecord->total_reactions}"
				.", total_like_reactions = {$totalsRecord->total_like_reactions}"
				.", total_love_reactions = {$totalsRecord->total_love_reactions}"
				.", total_wow_reactions = {$totalsRecord->total_wow_reactions}"
				.", total_haha_reactions = {$totalsRecord->total_haha_reactions}"
				.", total_sad_reactions = {$totalsRecord->total_sad_reactions}"
				.", total_angry_reactions = {$totalsRecord->total_angry_reactions}"
				.", highest_reaction_type = {$highestReactionType}"
				." WHERE id = {$id}";
			$this->database->query( $query );
		}
		
		private function updateControversialityScore( $table, $totalsRecord, $id ) {
			// basically, the closer the total positive and total negative reactions are to each other, the more controversial
			$total_positive_reactions = $totalsRecord->total_love_reactions + $totalsRecord->total_haha_reactions;
			$total_negative_reactions = $totalsRecord->total_sad_reactions + $totalsRecord->total_angry_reactions;
			if ( $total_positive_reactions != 0 && $total_negative_reactions != 0 ) {
				if ( $total_positive_reactions > $total_negative_reactions ) {
					$controversiality_score = $total_negative_reactions / $total_positive_reactions;
				} else {
					$controversiality_score = $total_positive_reactions / $total_negative_reactions;
				}
				$query = "UPDATE {$table} SET controversiality_score = {$controversiality_score} WHERE id = {$id}";
				$this->database->query( $query );
			}
		}
		
		private function getHighestReactionType( $totalsRecord ) {
			$totalsRecord = (array)$totalsRecord;
			$reactionTypes = array(
				'LIKE' => 'total_like_reactions',
				'LOVE' => 'total_love_reactions',
				'WOW' => 'total_wow_reactions',
				'HAHA' => 'total_haha_reactions',
				'SAD' => 'total_sad_reactions',
				'ANGRY' => 'total_angry_reactions'
			);
			$highestType = array( 'type' => '', 'total' => 0 );
			foreach( $reactionTypes as $type => $field ) {
				if ( $totalsRecord[$field] > $highestType['total'] ) {
					$highestType['type'] = $type;
					$highestType['total'] = $totalsRecord[$field];
				}
			}
			return $highestType['type'];
		}
		
		private function getTotalCommentsBy( $field ) {
			$query = "SELECT comments.{$field}"
				.', COUNT( comments.id ) AS total_comments'
				.', SUM( comments.like_count ) AS total_comment_likes'
				.', SUM( CASE WHEN comments.like_count = 0 THEN 1 ELSE 0 END ) AS total_comments_zero_likes'
				.', AVG( TIME_TO_SEC( TIMEDIFF( comments.created_time_mysql, posts.created_time_mysql ) ) / ( 60 * 60 ) ) AS average_hours_to_comment'
				.' FROM comments'
				.' LEFT JOIN posts ON posts.id = comments.post_id'
				.' WHERE 1'
				." GROUP BY comments.{$field}";
			return $this->database->query( $query );
		}
		
		private function updateTotalComments( $table, $totalsRecord, $id ) {
			$query = "UPDATE {$table}"
				." SET total_comments = {$totalsRecord->total_comments}"
				.", total_comment_likes = {$totalsRecord->total_comment_likes}"
				.", total_comments_zero_likes = {$totalsRecord->total_comments_zero_likes}"
				.", average_hours_to_comment = {$totalsRecord->average_hours_to_comment}"
				." WHERE id = {$id}";
			$this->database->query( $query );
		}
		
		private function updateDuplicateCommentCount( $user_id ) {
			$query = "SELECT message, ( COUNT(*) - 1 ) AS duplicates FROM comments GROUP BY message WHERE user_id = {$user_id} HAVING duplicates > 0";
			$stmt = $this->database->query( $query );
			$duplicate_comment_count = 0;
			foreach( $stmt as $comment ) {
				$duplicate_comment_count += $comment->duplicates;
			}
			$query = "UPDATE users SET duplicate_comment_count = {$duplicate_comment_count} WHERE id = {$user_id}";
			$this->database->query( $query );
		}
		
		private function updateMetaData() {
			
			/* time range */
			$this->setMetaData( 'earliestPostTime', $_GET['earliestPostCullDate'], 'Earliest Post Time', 'Posts used in the analysis will have been published no earlier than this date.' );
			$this->setMetaData( 'latestPostTime', $_GET['latestPostDate'], 'Latest Post Time', 'Posts used in the analysis will have been published no later than this date.' );
			
			/* CACHING */
			
			/* page metadata */
			// highest fan count
			$record = $this->database->fetchRow( 'SELECT pages.* FROM pages WHERE 1 ORDER BY pages.fan_count DESC LIMIT 1' );
			$this->setMetaData( 'pageHighestFanCount', json_encode( $record ), 'Highest Fan Count', 'This is the page with the highest number of fans.' );
			// most active
			$record = $this->database->fetchRow( 'SELECT pages.* FROM pages WHERE 1 ORDER BY pages.total_posts DESC LIMIT 1' );
			$this->setMetaData( 'pageMostActive', json_encode( $record ), 'Most Active', 'This is the page with the highest number of posts.' );
			// most controversial
			$record = $this->database->fetchRow( 'SELECT pages.* FROM pages WHERE 1 ORDER BY pages.controversiality_score DESC LIMIT 1' );
			$this->setMetaData( 'pageMostControversial', json_encode( $record ), 'Most Controversial', 'This is the page with the closest number of positive and negative reactions to their posts.' );
			// most ( reaction type / total reactions )
			$reactions = array( 'like', 'love', 'wow', 'haha', 'sad', 'angry' );
			foreach( $reactions as $reaction ) {
				$record = $this->database->fetchRow( $this->generateMostReactionTypeQuery( 'pages', $reaction ) );
				$ucReaction = ucfirst( $reaction );
				$this->setMetaData( "pageMost{$ucReaction}Reactions", json_encode( $record ), "Most {$ucReaction} Reactions", "This is the page with the highest ratio of {$ucReaction} reactions per reaction to their posts. Pages with only one reaction are eliminated." );
			}
			
			/* post metadata */
			// most active
			$record = $this->database->fetchRow( 'SELECT posts.* FROM posts WHERE 1 ORDER BY posts.total_comments DESC LIMIT 1' );
			$this->setMetaData( 'postMostActive', json_encode( $record ), 'Most Active', 'This is the post with the highest number of comments.' );
			// most controversial
			$record = $this->database->fetchRow( 'SELECT posts.* FROM posts WHERE 1 ORDER BY posts.controversiality_score DESC LIMIT 1' );
			$this->setMetaData( 'postMostControversial', json_encode( $record ), 'Most Controversial', 'This is the post with the closest number of positive and negative reactions.' );
			// most ( reaction type / total reactions )
			$reactions = array( 'like', 'love', 'wow', 'haha', 'sad', 'angry' );
			foreach( $reactions as $reaction ) {
				$record = $this->database->fetchRow( $this->generateMostReactionTypeQuery( 'posts', $reaction ) );
				$ucReaction = ucfirst( $reaction );
				$this->setMetaData( "postMost{$ucReaction}Reactions", json_encode( $record ), "Most {$ucReaction} Reactions", "This is the post with the highest ratio of {$ucReaction} reactions per reaction. Posts with only one reaction are eliminated." );
			}
			
			/* user metadata */
			// most active
			$record = $this->database->fetchRow( 'SELECT users.* FROM users WHERE 1 ORDER BY users.total_comments DESC LIMIT 1' );
			$this->setMetaData( 'userMostActive', json_encode( $record ), 'Most Active', 'This is the user with the highest number of comments.' );
			// most influential
			$record = $this->database->fetchRow( 'SELECT users.* FROM users WHERE 1 ORDER BY users.total_comment_likes DESC LIMIT 1' );
			$this->setMetaData( 'userMostInfluential', json_encode( $record ), 'Most Influential', 'This is the user with highest total of likes on their comments.' );
			// biggest troll
			$record = $this->database->fetchRow( 'SELECT users.* FROM users WHERE 1 ORDER BY users.total_comments_zero_likes DESC LIMIT 1' );
			$this->setMetaData( 'userBiggestTroll', json_encode( $record ), 'Biggest Troll', 'This is the user with the highest number of comments with zero likes.' );
			// biggest spammer
			$record = $this->database->fetchRow( 'SELECT users.* FROM users WHERE 1 ORDER BY users.duplicate_comment_count DESC LIMIT 1' );
			$this->setMetaData( 'userBiggestSpammer', json_encode( $record ), 'Biggest Spammer', 'This is the user with the highest number of duplicate comments.' );
			// quick draw award
			$record = $this->database->fetchRow( 'SELECT users.* FROM users WHERE 1 ORDER BY users.average_hours_to_comment ASC LIMIT 1' );
			$this->setMetaData( 'userQuickDrawAward', json_encode( $record ), 'Quick Draw Award', 'This is the user with the lowest average number of hours between the time a post is published, and the time they post a comment on it.' );
			// most ( reaction type / total reactions )
			$reactions = array( 'like', 'love', 'wow', 'haha', 'sad', 'angry' );
			foreach( $reactions as $reaction ) {
				$record = $this->database->fetchRow( $this->generateMostReactionTypeQuery( 'users', $reaction ) );
				$ucReaction = ucfirst( $reaction );
				$this->setMetaData( "userMost{$ucReaction}Reactions", json_encode( $record ), "Most {$ucReaction} Reactions", "This is the user with the highest ratio of {$ucReaction} reactions per reaction. Users with only one reaction are eliminated." );
			}
		}
		
		private function generateMostReactionTypeQuery( $table, $type ) {
			return "SELECT {$table}.* FROM {$table} WHERE 1 ORDER BY ( CASE WHEN {$table}.total_reactions <= 1 THEN 0 ELSE ( {$table}.total_{$type}_reactions / {$table}.total_reactions ) END ) DESC, {$table}.total_reactions DESC LIMIT 1";
		}
	}
?>