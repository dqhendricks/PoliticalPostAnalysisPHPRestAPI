-- phpMyAdmin SQL Dump
-- version 4.0.10.14
-- http://www.phpmyadmin.net
--
-- Host: localhost:3306
-- Generation Time: Jun 24, 2017 at 05:38 PM
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
  `picture` varchar(128) NOT NULL,
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `about`, `affiliation`, `category`, `fan_count`, `link`, `name`, `picture`, `website`, `total_posts`, `total_comments`, `total_reactions`, `highest_reaction_type`, `total_comment_likes`, `total_love_reactions`, `total_wow_reactions`, `total_haha_reactions`, `total_sad_reactions`, `total_angry_reactions`, `total_comments_zero_likes`, `controversiality_score`) VALUES
(5281959998, 'Welcome to The New York Times on Facebook - a hub for conversation about news and ideas. Like our page and connect with Times journalists and readers. ', 0, 'Newspaper', 14130659, 'https://www.facebook.com/nytimes/', 'The New York Times', '{"data":{"is_silhouette":false,"url":"https:\\/\\/scontent.xx.fbcdn.net\\/v\\/t1.0-1\\/p50x50\\/420194_448960124998_2006714158_n.jpg?o', 'nytimes.com/chat', 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 0, 0),
(5550296508, 'Instant breaking news alerts and the most talked about stories.', 0, 'Media/News Company', 27690501, 'https://www.facebook.com/cnn/', 'CNN', '{"data":{"is_silhouette":false,"url":"https:\\/\\/scontent.xx.fbcdn.net\\/v\\/t1.0-1\\/p50x50\\/12289622_10154246192721509_18979125835', 'www.cnn.com', 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 0, 0),
(15704546335, 'Welcome to the official Fox News facebook page.  Get breaking news, must see videos and exclusive interviews from the #1 name in news.', 0, 'Media/News Company', 15472767, 'https://www.facebook.com/FoxNews/', 'Fox News', '{"data":{"is_silhouette":false,"url":"https:\\/\\/scontent.xx.fbcdn.net\\/v\\/t1.0-1\\/p50x50\\/417751_10150581617531336_1949382366_n.', 'http://foxnews.com/, http://insider.foxnews.com', 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 0, 0),
(95475020353, 'Breitbart News (www.breitbart.com) is a conservative news and opinion website founded by the late Andrew Breitbart.', 0, 'Media/News Company', 3463146, 'https://www.facebook.com/Breitbart/', 'Breitbart', '{"data":{"is_silhouette":false,"url":"https:\\/\\/scontent.xx.fbcdn.net\\/v\\/t1.0-1\\/p50x50\\/227458_10152346853555354_25751187_n.jp', 'http://www.breitbart.com', 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 0, 0);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
