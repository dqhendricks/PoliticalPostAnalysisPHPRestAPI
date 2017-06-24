-- phpMyAdmin SQL Dump
-- version 4.0.10.14
-- http://www.phpmyadmin.net
--
-- Host: localhost:3306
-- Generation Time: Jun 24, 2017 at 04:02 PM
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
  PRIMARY KEY (`id`),
  KEY `total_comments` (`total_comments`),
  KEY `total_comment_likes` (`total_comment_likes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
