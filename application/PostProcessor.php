<?

	/*
		many of these queries could be more performant, but I was running into memory issues with the larger ones. I had to break these down into smaller, less performant queries and routines in order to complete processing without memory errors. this class could probably be broken down into three smaller classes, separating users, posts, and pages. statement caching seems to make the code less readable, but it is faster
	*/

	class PostProcessor {
		
		protected $database;
		protected $updateTotalReactionsStmt = array();
		protected $updateTotalCommentsStmt = array();
		protected $updateControversialityScoreStmt = array();
		protected $getCommentsOverTimeStmt = array();
		protected $setCommentsOverTimeStmt = array();
		protected $setMetaDataQueryStmt;
		protected $getMetaDataQueryStmt;
		protected $getDuplicateCommentCountStmt;
		protected $updateDuplicateCommentCountStmt;
		protected $getImageCommentCountStmt;
		protected $updateImageCommentCountStmt;
		
		public function __construct( $database ) {
			$this->database = $database;
			
			$this->setMetaDataQueryStmt = $this->database->prepare( 'INSERT INTO meta_data ( `key`, `value`, `type`, `name`, `description` ) VALUES ( ?, ?, ?, ?, ? ) ON DUPLICATE KEY UPDATE value = ?, type = ?, name = ?, description = ?' );
			
			$this->getMetaDataQueryStmt = $this->database->prepare( 'SELECT `value` FROM meta_data WHERE `key` = ? LIMIT 1' );
			
			$this->getDuplicateCommentCountStmt = $this->database->prepare( 'SELECT message, COUNT( id ) AS duplicates FROM comments WHERE user_id = ? AND message != "" GROUP BY message HAVING duplicates > 1' );
			
			$this->updateDuplicateCommentCountStmt = $this->database->prepare( 'UPDATE users SET duplicate_comment_count = ? WHERE id = ?' );
			
			$this->getImageCommentCountStmt = $this->database->prepare( 'SELECT COUNT( id ) AS image_comment_count FROM comments WHERE user_id = ? AND message = ""' );
			
			$this->updateImageCommentCountStmt = $this->database->prepare( 'UPDATE users SET image_comment_count = ? WHERE id = ?' );
		}
		
		private function generateOverTimeQuerySegment( $table ) {
			$overTimeQuerySegment = '';
			for ( $i = 0; $i < 24; $i++ ) {
				$hour = str_pad( $i, 2, '0', STR_PAD_LEFT );
				$overTimeQuerySegment .= ", SUM( CASE WHEN HOUR( {$table}.created_time_mysql ) = '{$hour}' THEN 1 ELSE 0 END ) AS total_{$hour}";
			}
			return $overTimeQuerySegment;
		}
		
		public function process() {
			if ( $this->getMetaData( 'currentlyRunningPostProcessor' ) == '0' && $this->getMetaData( 'newLatestPostTime' ) != $this->getMetaData( 'latestPostTime' ) ) {
				set_time_limit( 0 );
				$this->setMetaData( 'currentlyRunningPostProcessor', '1' );
				try {
					$this->deleteOldRecords();
					$this->updateMissingUsers();
					$this->updatePostMetaData();
					$this->updateUserMetaData();
					$this->updatePageMetaData();
					$this->updateMetaData();
					$this->setMetaData( 'currentlyRunningPostProcessor', '0' );
				} catch ( Exception $exception ) {
					//mail( 'someone@somewhere.com', 'cron error', $exception->getMessage(), 'From: no-reply@somewhere.com' );
				}
			}
		}
		
		private function setMetaData( $key, $value, $type = 'system', $name = '', $description = '' ) {
			return $this->setMetaDataQueryStmt->execute( array( $key, $value, $type, $name, $description, $value, $type, $name, $description ) );
		}
		
		private function getMetaData( $key ) {
			$this->getMetaDataQueryStmt->execute( array( $key ) );
			$column = $this->getMetaDataQueryStmt->fetchColumn( 0 );
			$this->getMetaDataQueryStmt->closeCursor();
			return $column;
		}
		
		private function deleteOldRecords() {
			// remove reactions and comments for cull posts
			$variables = array( $this->getMetaData( 'newEarliestPostTime' ) );
			$query = 'DELETE comments'
				.' FROM comments'
				.' WHERE comments.created_time < ?';
			$this->database->queryPrepared( $query, $variables );
			$query = 'DELETE post_reactions'
				.' FROM post_reactions'
				.' LEFT JOIN posts ON post_reactions.post_id = posts.id'
				.' WHERE posts.created_time < ?';
			$this->database->queryPrepared( $query, $variables );
			$query = 'DELETE posts'
				.' FROM posts'
				.' WHERE posts.created_time < ?';
			$this->database->queryPrepared( $query, $variables );
			// remove orphaned users
			$query = 'DELETE users'
				.' FROM users'
				.' WHERE NOT EXISTS ('
					.' SELECT post_reactions.*'
					.' FROM post_reactions'
					.' WHERE post_reactions.user_id = users.id'
					.' UNION ALL'
					.' SELECT comments.*'
					.' FROM comments'
					.' WHERE comments.user_id = users.id'
				.' )';
			$this->database->query( $query );
		}
		
		private function updateMissingUsers() {
			// turn reactions into users
			$query = 'SELECT post_reactions.user_id, post_reactions.name, post_reactions.link, post_reactions.picture'
				.' FROM post_reactions'
				.' WHERE NOT EXISTS ( SELECT * FROM users WHERE users.id = post_reactions.user_id )';
			$stmt = $this->database->query( $query );
			$insertStmt = $this->database->prepare( 'INSERT IGNORE INTO users ( users.id, users.name, users.link, users.picture ) VALUES ( ?, ?, ?, ? )' );
			foreach( $stmt as $postReaction ) {
				$insertStmt->execute( array( $postReaction['user_id'], $postReaction['name'], $postReaction['link'], $postReaction['picture'] ) );
			}
			mail( 'dqhendricks@hotmail.com', 'cron 1b', 'cron stage', 'From: no-reply@dustinhendricks.com' );
			// some users cannot be scraped
			$query = 'SELECT DISTINCT comments.from FROM comments WHERE NOT EXISTS ( SELECT users.* FROM users WHERE users.id = comments.user_id )';
			$stmt = $this->database->query( $query );
			$insertStmt = $this->database->prepare( 'INSERT INTO users ( id, name, link ) VALUES ( ?, ?, ? )' );
			foreach( $stmt as $comment ) {
				$user = json_decode( $comment['from'] );
				$insertStmt->execute( array( $user['id'], $user['name'], "https://www.facebook.com/app_scoped_user_id/{$user['id']}/" ) ); 
			}
		}
		
		private function updatePostMetaData() {
			// reaction data
			$stmt = $this->getTotalReactionsBy( 'post_id' );
			foreach( $stmt as $post ) {
				if ( $post['total_reactions'] != 0 ) {
					$this->updateTotalReactions( 'posts', $post, $post['post_id'] );
					$this->updateControversialityScore( 'posts', $post, $post['post_id'] );
				}
			}
			mail( 'dqhendricks@hotmail.com', 'cron 2b', 'cron stage', 'From: no-reply@dustinhendricks.com' );
			// comment data
			$stmt = $this->getTotalCommentsBy( 'post_id' );
			foreach( $stmt as $post ) {
				if ( $post['total_comments'] != 0 ) {
					$this->updateTotalComments( 'posts', $post, $post['post_id'] );
				}
			}
		}
		
		private function updateUserMetaData() {
			// reaction data
			/*
			$stmt = $this->getTotalReactionsBy( 'user_id' );
			foreach( $stmt as $user ) {
				$this->updateTotalReactions( 'users', $user, $user['user_id'] );
			}
			*/
			mail( 'dqhendricks@hotmail.com', 'cron 3b', 'cron stage', 'From: no-reply@dustinhendricks.com' );
			// comment data
			$stmt = $this->getTotalCommentsBy( 'user_id' );
			foreach( $stmt as $user ) {
				$this->updateTotalComments( 'users', $user, $user['user_id'] );
				$this->updateDuplicateCommentCount( $user['user_id'] );
				$this->updateImageCommentCount( $user['user_id'] );
			}
			mail( 'dqhendricks@hotmail.com', 'cron 3c', 'cron stage', 'From: no-reply@dustinhendricks.com' );
			// affiliation data
			$query = 'SELECT post_reactions.user_id'
				.', COUNT( post_reactions.id ) AS total_likes'
				.', SUM( pages.affiliation ) AS affiliation_total'
				.' FROM post_reactions'
				.' LEFT JOIN pages ON pages.id = post_reactions.page_id'
				.' WHERE post_reactions.type = "LIKE"'
				.' GROUP BY post_reactions.user_id';
			$stmt = $this->database->query( $query );
			$insertStmt = $this->database->prepare( 'UPDATE users SET affiliation = ? WHERE users.id = ?' );
			foreach( $stmt as $user ) {
				$affiliation = $user['affiliation_total'] / $user['total_likes'];
				$insertStmt->execute( array( $affiliation, $user['user_id'] ) );
			}
			mail( 'dqhendricks@hotmail.com', 'cron 3d', 'cron stage', 'From: no-reply@dustinhendricks.com' );
			// pages interacted with data
			$query = 'SELECT comments.user_id'
				.', GROUP_CONCAT( DISTINCT comments.page_id ) AS pages_interacted_with'
				.', COUNT( DISTINCT comments.page_id ) AS total_pages_interacted_with'
				.' FROM comments'
				.' WHERE 1'
				.' GROUP BY comments.user_id';
			$stmt = $this->database->query( $query );
			$insertStmt = $this->database->prepare( 'UPDATE users'
				.' SET pages_interacted_with = ?'
				.', total_pages_interacted_with = ?'
				.' WHERE id = ?' );
			foreach( $stmt as $user ) {
				$insertStmt->execute( array( $user['pages_interacted_with'], $user['total_pages_interacted_with'], $user['user_id'] ) );
			}
			mail( 'dqhendricks@hotmail.com', 'cron 3e', 'cron stage', 'From: no-reply@dustinhendricks.com' );
			// posts interacted with data
			$query = 'SELECT comments.user_id, COUNT( DISTINCT comments.post_id ) AS total_posts_interacted_with'
				.' FROM comments'
				.' WHERE 1'
				.' GROUP BY comments.user_id';
			$stmt = $this->database->query( $query );
			$insertStmt = $this->database->prepare( 'UPDATE users'
				.' SET total_posts_interacted_with = ?'
				.' WHERE id = ?' );
			foreach( $stmt as $user ) {
				$insertStmt->execute( array( $user['total_posts_interacted_with'], $user['user_id'] ) );
			}
		}
		
		private function updatePageMetaData() {
			// reaction data
			$stmt = $this->getTotalReactionsBy( 'page_id' );
			foreach( $stmt as $page ) {
				$this->updateTotalReactions( 'pages', $page, $page['page_id'] );
				$this->updateControversialityScore( 'pages', $page, $page['page_id'] );
			}
			mail( 'dqhendricks@hotmail.com', 'cron 4b', 'cron stage', 'From: no-reply@dustinhendricks.com' );
			// comment data
			$stmt = $this->getTotalCommentsBy( 'page_id' );
			foreach( $stmt as $page ) {
				$this->updateTotalComments( 'pages', $page, $page['page_id'] );
			}
			mail( 'dqhendricks@hotmail.com', 'cron 4c', 'cron stage', 'From: no-reply@dustinhendricks.com' );
			// posts data
			$query = 'SELECT page_id'
				.', COUNT( id ) AS total_posts'
				.$this->generateOverTimeQuerySegment( 'posts' )
				.' FROM posts'
				.' WHERE 1'
				.' GROUP BY page_id';
			$stmt = $this->database->query( $query );
			$insertStmt = $this->database->prepare( 'UPDATE pages SET total_posts = ?, posts_over_time = ? WHERE id = ?' );
			foreach( $stmt as $page ) {
				$overTimeValues = $this->formatOverTimeValues( $page );
				$insertStmt->execute( array( $page['total_posts'], $overTimeValues, $page['page_id'] ) );
			}
		}
		
		private function formatOverTimeValues( $record ) {
			$overTimeValues = array();
			for ( $i = 0; $i < 24; $i++ ) {
				$hour = str_pad( $i, 2, '0', STR_PAD_LEFT );
				$overTimeValues[$hour] = $record["total_{$hour}"];
			}
			return json_encode( $overTimeValues );
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
			$stmt = $this->getUpdateTotalReactionsStmt( $table );
			$stmt->execute( array( $totalsRecord['total_reactions'],
				$totalsRecord['total_like_reactions'],
				$totalsRecord['total_love_reactions'],
				$totalsRecord['total_wow_reactions'],
				$totalsRecord['total_haha_reactions'],
				$totalsRecord['total_sad_reactions'],
				$totalsRecord['total_angry_reactions'],
				$highestReactionType,
				$id ) );
		}
		
		private function getUpdateTotalReactionsStmt( $table ) {
			if ( !$this->updateTotalReactionsStmt[ $table ] ) $this->updateTotalReactionsStmt[ $table ] = $this->database->prepare( "UPDATE {$table}"
				.' SET total_reactions = ?'
				.', total_like_reactions = ?'
				.', total_love_reactions = ?'
				.', total_wow_reactions = ?'
				.', total_haha_reactions = ?'
				.', total_sad_reactions = ?'
				.', total_angry_reactions = ?'
				.', highest_reaction_type = ?'
				.' WHERE id = ?' ); 
			return $this->updateTotalReactionsStmt[ $table ];
		}
		
		private function updateControversialityScore( $table, $totalsRecord, $id ) {
			// basically, the closer the total positive and total negative reactions are to each other, the more controversial
			$total_positive_reactions = $totalsRecord['total_love_reactions'] + $totalsRecord['total_haha_reactions'];
			$total_negative_reactions = $totalsRecord['total_sad_reactions'] + $totalsRecord['total_angry_reactions'];
			if ( $total_positive_reactions != 0 && $total_negative_reactions != 0 ) {
				if ( $total_positive_reactions > $total_negative_reactions ) {
					$controversiality_score = $total_negative_reactions / $total_positive_reactions;
				} else {
					$controversiality_score = $total_positive_reactions / $total_negative_reactions;
				}
				
				$stmt = $this->getUpdateControversialityScoreStmt( $table );
				$stmt->execute( array( $controversiality_score, $id ) );
			}
		}
		
		private function getUpdateControversialityScoreStmt( $table ) {
			if ( !$this->updateControversialityScoreStmt[ $table ] ) $this->updateControversialityScoreStmt[ $table ] = $this->database->prepare( "UPDATE {$table} SET controversiality_score = ? WHERE id = ?" ); 
			return $this->updateControversialityScoreStmt[ $table ];
		}
		
		private function getHighestReactionType( $totalsRecord ) {
			$totalsRecord = (array)$totalsRecord;
			$reactionTypes = array(
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
				.$this->generateOverTimeQuerySegment( 'comments' )
				.' FROM comments'
				.' LEFT JOIN posts ON posts.id = comments.post_id'
				.' WHERE 1'
				." GROUP BY comments.{$field}";
			return $this->database->query( $query );
		}
		
		private function updateTotalComments( $table, $totalsRecord, $id ) {
			$stmt = $this->getUpdateTotalCommentsStmt( $table );
			$stmt->execute( array( $totalsRecord['total_comments'],
				$totalsRecord['total_comment_likes'],
				$totalsRecord['total_comments_zero_likes'],
				$totalsRecord['average_hours_to_comment'],
				$this->formatOverTimeValues( $totalsRecord ),
				$id ) );
		}
		
		private function getUpdateTotalCommentsStmt( $table ) {
			if ( !$this->updateTotalCommentsStmt[ $table ] ) $this->updateTotalCommentsStmt[ $table ] = $this->database->prepare( "UPDATE {$table}"
				.' SET total_comments = ?'
				.', total_comment_likes = ?'
				.', total_comments_zero_likes = ?'
				.', average_hours_to_comment = ?'
				.', comments_over_time = ?'
				.' WHERE id = ?' );
			return $this->updateTotalCommentsStmt[ $table ];
		}
		
		private function updateDuplicateCommentCount( $userID ) {
			$this->getDuplicateCommentCountStmt->execute( array( $userID ) );
			$duplicateCommentCount = 0;
			foreach( $this->getDuplicateCommentCountStmt as $comment ) {
				$duplicateCommentCount += $comment['duplicates'];
			}
			$this->updateDuplicateCommentCountStmt->execute( array( $duplicateCommentCount, $userID ) );
		}
		
		private function updateImageCommentCount( $userID ) {
			$this->getImageCommentCountStmt->execute( array( $userID ) );
			$imageCommentCount = $this->getImageCommentCountStmt->fetchColumn( 0 );
			$this->getImageCommentCountStmt->closeCursor();
			$this->updateImageCommentCountStmt->execute( array( $imageCommentCount, $userID ) );
		}
		
		private function updateMetaData() {
			
			/* CACHING */
			
			/* page metadata */
			// highest fan count
			$record = $this->database->fetchRow( 'SELECT pages.* FROM pages WHERE 1 ORDER BY pages.fan_count DESC LIMIT 1' );
			$record = $this->decodeDatabaseJSON( $record );
			$this->setMetaData( 'pageHighestFanCount', json_encode( $record ), 'pages', 'Highest Fan Count', 'This is the page with the highest number of fans.' );
			// most active
			$record = $this->database->fetchRow( 'SELECT pages.* FROM pages WHERE 1 ORDER BY pages.total_posts DESC LIMIT 1' );
			$record = $this->decodeDatabaseJSON( $record );
			$this->setMetaData( 'pageMostActive', json_encode( $record ), 'pages', 'Most Active', 'This is the page with the highest number of posts.' );
			// most controversial
			$record = $this->database->fetchRow( 'SELECT pages.* FROM pages WHERE 1 ORDER BY pages.controversiality_score DESC LIMIT 1' );
			$record = $this->decodeDatabaseJSON( $record );
			$this->setMetaData( 'pageMostControversial', json_encode( $record ), 'pages', 'Most Controversial', 'This is the page with the closest number of positive and negative reactions to their posts.' );
			// most ( reaction type / total reactions )
			$reactions = array( 'like', 'love', 'wow', 'haha', 'sad', 'angry' );
			foreach( $reactions as $reaction ) {
				$record = $this->database->fetchRow( $this->generateMostReactionTypeQuery( 'pages', $reaction ) );
				$record = $this->decodeDatabaseJSON( $record );
				$ucReaction = ucfirst( $reaction );
				$this->setMetaData( "pageMost{$ucReaction}Reactions", json_encode( $record ), 'pages', "Most {$ucReaction} Reactions", "This is the page with the highest ratio of {$ucReaction} reactions per reaction to their posts. Pages with only one reaction are eliminated." );
			}
			
			/* post metadata */
			// most active
			$record = $this->database->fetchRow( 'SELECT posts.* FROM posts WHERE 1 ORDER BY posts.total_comments DESC LIMIT 1' );
			$record = $this->decodeDatabaseJSON( $record );
			$this->setMetaData( 'postMostActive', json_encode( $record ), 'posts', 'Most Active', 'This is the post with the highest number of comments.' );
			// most controversial
			$record = $this->database->fetchRow( 'SELECT posts.* FROM posts WHERE 1 ORDER BY posts.controversiality_score DESC LIMIT 1' );
			$record = $this->decodeDatabaseJSON( $record );
			$this->setMetaData( 'postMostControversial', json_encode( $record ), 'posts', 'Most Controversial', 'This is the post with the closest number of positive and negative reactions.' );
			// most ( reaction type / total reactions )
			$reactions = array( 'like', 'love', 'wow', 'haha', 'sad', 'angry' );
			foreach( $reactions as $reaction ) {
				$record = $this->database->fetchRow( $this->generateMostReactionTypeQuery( 'posts', $reaction ) );
				$record = $this->decodeDatabaseJSON( $record );
				$ucReaction = ucfirst( $reaction );
				$this->setMetaData( "postMost{$ucReaction}Reactions", json_encode( $record ), 'posts', "Most {$ucReaction} Reactions", "This is the post with the highest ratio of {$ucReaction} reactions per reaction. Posts with only one reaction are eliminated." );
			}
			
			/* user metadata */
			// most active
			$record = $this->database->fetchRow( 'SELECT users.* FROM users WHERE 1 ORDER BY users.total_comments DESC LIMIT 1' );
			$record = $this->decodeDatabaseJSON( $record );
			$this->setMetaData( 'userMostActive', json_encode( $record ), 'users', 'Most Active', 'This is the user with the highest number of comments.' );
			// most influential
			$record = $this->database->fetchRow( 'SELECT users.* FROM users WHERE 1 ORDER BY users.total_comment_likes DESC LIMIT 1' );
			$record = $this->decodeDatabaseJSON( $record );
			$this->setMetaData( 'userMostInfluential', json_encode( $record ), 'users', 'Most Influential', 'This is the user with highest total of likes on their comments.' );
			// biggest troll
			$record = $this->database->fetchRow( 'SELECT users.* FROM users WHERE 1 ORDER BY users.total_comments_zero_likes DESC LIMIT 1' );
			$record = $this->decodeDatabaseJSON( $record );
			$this->setMetaData( 'userBiggestTroll', json_encode( $record ), 'users', 'Biggest Troll', 'This is the user with the highest number of comments with zero likes.' );
			// biggest spammer
			$record = $this->database->fetchRow( 'SELECT users.* FROM users WHERE 1 ORDER BY users.duplicate_comment_count DESC LIMIT 1' );
			$record = $this->decodeDatabaseJSON( $record );
			$this->setMetaData( 'userBiggestSpammer', json_encode( $record ), 'users', 'Biggest Spammer', 'This is the user with the highest number of duplicate comments.' );
			// meme master
			$record = $this->database->fetchRow( 'SELECT users.* FROM users WHERE 1 ORDER BY users.image_comment_count DESC LIMIT 1' );
			$record = $this->decodeDatabaseJSON( $record );
			$this->setMetaData( 'userMemeMaster', json_encode( $record ), 'users', 'Meme Master', 'This is the user with the highest number of image comments.' );
			// quick draw award
			$record = $this->database->fetchRow( 'SELECT users.* FROM users WHERE users.average_hours_to_comment > 0 ORDER BY users.average_hours_to_comment ASC LIMIT 1' );
			$record = $this->decodeDatabaseJSON( $record );
			$this->setMetaData( 'userQuickDrawAward', json_encode( $record ), 'users', 'Quick Draw Award', 'This is the user with the lowest average number of hours between the time a post is published, and the time they post a comment on it.' );
			// most ( reaction type / total reactions )
			$reactions = array( 'like', 'love', 'wow', 'haha', 'sad', 'angry' );
			foreach( $reactions as $reaction ) {
				$record = $this->database->fetchRow( $this->generateMostReactionTypeQuery( 'users', $reaction ) );
				$record = $this->decodeDatabaseJSON( $record );
				$ucReaction = ucfirst( $reaction );
				$this->setMetaData( "userMost{$ucReaction}Reactions", json_encode( $record ), 'users', "Most {$ucReaction} Reactions", "This is the user with the highest ratio of {$ucReaction} reactions per reaction. Users with only one reaction are eliminated." );
			}
			
			/* time range */
			$newEarliestTime = $this->getMetaData( 'newEarliestPostTime' );
			$this->setMetaData( 'earliestPostTime', $newEarliestTime );
			$newLatestTime = $this->getMetaData( 'newLatestPostTime' );
			$this->setMetaData( 'latestPostTime', $newLatestTime );
		}
		
		private function generateMostReactionTypeQuery( $table, $type ) {
			return "SELECT {$table}.* FROM {$table} WHERE 1 ORDER BY ( CASE WHEN {$table}.total_reactions <= 1 THEN 0 ELSE ( {$table}.total_{$type}_reactions / {$table}.total_reactions ) END ) DESC, {$table}.total_reactions DESC LIMIT 1";
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