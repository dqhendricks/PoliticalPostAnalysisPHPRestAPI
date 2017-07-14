-- phpMyAdmin SQL Dump
-- version 4.0.10.14
-- http://www.phpmyadmin.net
--
-- Host: localhost:3306
-- Generation Time: Jul 12, 2017 at 06:03 PM
-- Server version: 5.5.50-38.0-log
-- PHP Version: 5.4.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `spotless_fb_data`
--

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE IF NOT EXISTS `comments` (
  `id` varchar(64) NOT NULL,
  `comment_count` int(11) NOT NULL,
  `created_time` varchar(24) NOT NULL,
  `from` varchar(128) NOT NULL,
  `message` varchar(512) NOT NULL,
  `parent` varchar(128) NOT NULL,
  `permalink_url` varchar(128) NOT NULL,
  `like_count` int(11) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `post_id` varchar(64) NOT NULL,
  `page_id` bigint(20) NOT NULL,
  `created_time_mysql` datetime NOT NULL,
  `attachment` varchar(512) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `created_time` (`created_time`),
  KEY `user_id` (`user_id`),
  KEY `post_id` (`post_id`),
  KEY `page_id` (`page_id`),
  KEY `created_time_mysql` (`created_time_mysql`),
  KEY `message` (`message`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `meta_data`
--

CREATE TABLE IF NOT EXISTS `meta_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(32) NOT NULL,
  `value` text NOT NULL,
  `name` varchar(32) NOT NULL,
  `description` varchar(256) NOT NULL,
  `type` varchar(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE IF NOT EXISTS `pages` (
  `id` bigint(20) NOT NULL,
  `about` varchar(256) NOT NULL,
  `affiliation` float NOT NULL COMMENT 'from 0 = left to 1 = right',
  `category` varchar(32) NOT NULL,
  `fan_count` int(11) NOT NULL,
  `link` varchar(128) NOT NULL,
  `name` varchar(64) NOT NULL,
  `picture` varchar(256) NOT NULL,
  `website` varchar(128) NOT NULL,
  `total_posts` int(11) NOT NULL,
  `total_comments` int(11) NOT NULL,
  `total_reactions` int(11) NOT NULL,
  `highest_reaction_type` varchar(16) NOT NULL,
  `total_comment_likes` int(11) NOT NULL,
  `total_love_reactions` int(11) NOT NULL,
  `total_wow_reactions` int(11) NOT NULL,
  `total_haha_reactions` int(11) NOT NULL,
  `total_sad_reactions` int(11) NOT NULL,
  `total_angry_reactions` int(11) NOT NULL,
  `total_comments_zero_likes` int(11) NOT NULL,
  `controversiality_score` float NOT NULL COMMENT 'from 0 = not to 1 = very',
  `average_hours_to_comment` float NOT NULL,
  `total_like_reactions` int(11) NOT NULL,
  `posts_over_time` varchar(512) NOT NULL,
  `comments_over_time` varchar(512) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `about`, `affiliation`, `category`, `fan_count`, `link`, `name`, `picture`, `website`, `total_posts`, `total_comments`, `total_reactions`, `highest_reaction_type`, `total_comment_likes`, `total_love_reactions`, `total_wow_reactions`, `total_haha_reactions`, `total_sad_reactions`, `total_angry_reactions`, `total_comments_zero_likes`, `controversiality_score`, `average_hours_to_comment`, `total_like_reactions`, `posts_over_time`, `comments_over_time`) VALUES
(5281959998, 'Welcome to The New York Times on Facebook - a hub for conversation about news and ideas. Like our page and connect with Times journalists and readers. ', 0, 'Newspaper', 14195771, 'https://www.facebook.com/nytimes/', 'The New York Times', '{"data":{"is_silhouette":false,"url":"https:\\/\\/scontent.xx.fbcdn.net\\/v\\/t1.0-1\\/p50x50\\/420194_448960124998_2006714158_n.jpg?oh=e12a94a6e533db4478aa225dcbfad838&oe=5A09B380"}}', 'nytimes.com/chat', 53, 17243, 154890, 'ANGRY', 64361, 9510, 5582, 1986, 9384, 16195, 9818, 0.449431, 5.97247, 104546, '{"00": 2, "01": 1, "02": 2, "03": 2, "04": 2, "05": 2, "06": 2, "07": 2, "08": 2, "09": 2, "10": 3, "11": 2, "12": 4, "13": 3, "14": 2, "15": 2, "16": 2, "17": 2, "18": 2, "19": 1, "20": 2, "21": 2, "22": 2, "23": 2}', ''),
(5550296508, 'Instant breaking news alerts and the most talked about stories.', 0, 'Media/News Company', 27779340, 'https://www.facebook.com/cnn/', 'CNN', '{"data":{"is_silhouette":false,"url":"https:\\/\\/scontent.xx.fbcdn.net\\/v\\/t1.0-1\\/p50x50\\/12289622_10154246192721509_1897912583584847639_n.png?oh=78803854c26e3b337f870fbec0c310c3&oe=59C858DF"}}', 'www.cnn.com', 50, 87509, 302260, 'ANGRY', 215130, 20579, 16417, 19929, 23076, 34627, 56283, 0.702009, 4.03062, 184761, '{"00": 2, "01": 1, "02": 2, "03": 2, "04": 2, "05": 2, "06": 2, "07": 2, "08": 2, "09": 2, "10": 3, "11": 2, "12": 4, "13": 3, "14": 2, "15": 2, "16": 2, "17": 2, "18": 2, "19": 1, "20": 2, "21": 2, "22": 2, "23": 2}', ''),
(15704546335, 'Welcome to the official Fox News facebook page.  Get breaking news, must see videos and exclusive interviews from the #1 name in news.', 1, 'Media/News Company', 15515072, 'https://www.facebook.com/FoxNews/', 'Fox News', '{"data":{"is_silhouette":false,"url":"https:\\/\\/scontent.xx.fbcdn.net\\/v\\/t1.0-1\\/p50x50\\/417751_10150581617531336_1949382366_n.jpg?oh=be3d3d628a5615a4e3d63fe3127e6d22&oe=59CA5686"}}', 'http://foxnews.com/, http://insider.foxnews.com', 52, 122948, 802238, 'LOVE', 276281, 92799, 21020, 36782, 53135, 52773, 85494, 0.817311, 4.96556, 545256, '{"00": 2, "01": 1, "02": 2, "03": 2, "04": 2, "05": 2, "06": 2, "07": 2, "08": 2, "09": 2, "10": 3, "11": 2, "12": 4, "13": 3, "14": 2, "15": 2, "16": 2, "17": 2, "18": 2, "19": 1, "20": 2, "21": 2, "22": 2, "23": 2}', ''),
(95475020353, 'Breitbart News (www.breitbart.com) is a conservative news and opinion website founded by the late Andrew Breitbart.', 1, 'Media/News Company', 3492598, 'https://www.facebook.com/Breitbart/', 'Breitbart', '{"data":{"is_silhouette":false,"url":"https:\\/\\/scontent.xx.fbcdn.net\\/v\\/t1.0-1\\/p50x50\\/227458_10152346853555354_25751187_n.jpg?oh=3af67d0350000dd449c131fb419fd52e&oe=5A0E324B"}}', 'http://www.breitbart.com', 38, 88465, 285234, 'ANGRY', 180344, 10385, 15791, 47210, 18508, 56031, 66204, 0.772683, 5.17086, 137181, '{"00": 2, "01": 1, "02": 2, "03": 2, "04": 2, "05": 2, "06": 2, "07": 2, "08": 2, "09": 2, "10": 3, "11": 2, "12": 4, "13": 3, "14": 2, "15": 2, "16": 2, "17": 2, "18": 2, "19": 1, "20": 2, "21": 2, "22": 2, "23": 2}', '');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE IF NOT EXISTS `posts` (
  `id` varchar(64) NOT NULL,
  `created_time` varchar(24) NOT NULL,
  `from` varchar(128) NOT NULL,
  `link` varchar(128) NOT NULL,
  `message` text NOT NULL,
  `name` varchar(128) NOT NULL,
  `page_id` bigint(20) NOT NULL,
  `permalink_url` varchar(128) NOT NULL,
  `picture` varchar(512) NOT NULL,
  `shares` int(11) NOT NULL,
  `highest_reaction_type` varchar(16) NOT NULL,
  `last_allowed_comment_time` varchar(24) NOT NULL,
  `total_comments` int(11) NOT NULL,
  `total_reactions` int(11) NOT NULL,
  `created_time_mysql` datetime NOT NULL,
  `total_love_reactions` int(11) NOT NULL,
  `total_wow_reactions` int(11) NOT NULL,
  `total_haha_reactions` int(11) NOT NULL,
  `total_sad_reactions` int(11) NOT NULL,
  `total_angry_reactions` int(11) NOT NULL,
  `total_comment_likes` int(11) NOT NULL,
  `total_comments_zero_likes` int(11) NOT NULL,
  `controversiality_score` float NOT NULL COMMENT 'from 0 = not to 1 = very',
  `average_hours_to_comment` float NOT NULL,
  `total_like_reactions` int(11) NOT NULL,
  `comments_over_time` varchar(512) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `created_time` (`created_time`),
  KEY `page_id` (`page_id`),
  KEY `created_time_mysql` (`created_time_mysql`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `post_reactions`
--

CREATE TABLE IF NOT EXISTS `post_reactions` (
  `id` varchar(128) NOT NULL,
  `link` varchar(128) NOT NULL,
  `name` varchar(64) NOT NULL,
  `picture` varchar(256) NOT NULL,
  `type` varchar(16) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `post_id` varchar(64) NOT NULL,
  `page_id` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `user_id` (`user_id`),
  KEY `post_id` (`post_id`),
  KEY `page_id` (`page_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) NOT NULL,
  `link` varchar(128) NOT NULL,
  `name` varchar(64) NOT NULL,
  `picture` varchar(256) NOT NULL,
  `affiliation` float NOT NULL COMMENT 'from 0 = left to 1 = right',
  `total_reactions` int(11) NOT NULL,
  `total_comments` int(11) NOT NULL,
  `total_comment_likes` int(11) NOT NULL,
  `total_pages_interacted_with` int(11) NOT NULL,
  `highest_reaction_type` varchar(16) NOT NULL,
  `total_love_reactions` int(11) NOT NULL,
  `total_wow_reactions` int(11) NOT NULL,
  `total_haha_reactions` int(11) NOT NULL,
  `total_sad_reactions` int(11) NOT NULL,
  `total_angry_reactions` int(11) NOT NULL,
  `pages_interacted_with` varchar(256) NOT NULL,
  `total_posts_interacted_with` int(11) NOT NULL,
  `total_comments_zero_likes` int(11) NOT NULL,
  `average_hours_to_comment` float NOT NULL,
  `duplicate_comment_count` int(11) NOT NULL,
  `total_like_reactions` int(11) NOT NULL,
  `image_comment_count` int(11) NOT NULL,
  `comments_over_time` varchar(512) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `total_comments` (`total_comments`),
  KEY `total_comment_likes` (`total_comment_likes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
