-- phpMyAdmin SQL Dump
-- version 4.0.10.14
-- http://www.phpmyadmin.net
--
-- Host: localhost:3306
-- Generation Time: Jun 23, 2017 at 06:44 PM
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
  `message` int(11) NOT NULL,
  `parent` varchar(128) NOT NULL,
  `permalink_url` varchar(128) NOT NULL,
  `like_count` int(11) NOT NULL,
  `user_id` int(12) NOT NULL,
  `post_id` varchar(64) NOT NULL,
  `page_id` int(12) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `created_time` (`created_time`,`user_id`,`post_id`,`page_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `meta_data`
--

CREATE TABLE IF NOT EXISTS `meta_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(32) NOT NULL,
  `value` varchar(128) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `meta_data`
--

INSERT INTO `meta_data` (`id`, `key`, `value`) VALUES
(1, 'earliestPostTime', ''),
(2, 'latestPostTime', '');

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE IF NOT EXISTS `pages` (
  `id` bigint(20) NOT NULL,
  `about` varchar(256) NOT NULL,
  `affiliation` float NOT NULL,
  `category` varchar(32) NOT NULL,
  `fan_count` int(11) NOT NULL,
  `link` varchar(128) NOT NULL,
  `name` varchar(64) NOT NULL,
  `picture` varchar(128) NOT NULL,
  `website` varchar(128) NOT NULL,
  `total_posts` int(11) NOT NULL,
  `total_comments` int(11) NOT NULL,
  `total_reactions` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `about`, `affiliation`, `category`, `fan_count`, `link`, `name`, `picture`, `website`, `total_posts`, `total_comments`, `total_reactions`) VALUES
(5281959998, '', 0, '', 0, '', '', '', '', 0, 0, 0),
(5550296508, '', 0, '', 0, '', '', '', '', 0, 0, 0),
(15704546335, '', 0, '', 0, '', '', '', '', 0, 0, 0),
(95475020353, '', 0, '', 0, '', '', '', '', 0, 0, 0);

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
  `name` varchar(64) NOT NULL,
  `page_id` bigint(20) NOT NULL,
  `permalink_url` varchar(128) NOT NULL,
  `picture` varchar(256) NOT NULL,
  `shares` int(11) NOT NULL,
  `reaction_type` varchar(16) NOT NULL,
  `last_allowed_comment_time` varchar(24) NOT NULL,
  `total_comments` int(11) NOT NULL,
  `total_reactions` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `created_time` (`created_time`,`page_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `post_reactions`
--

CREATE TABLE IF NOT EXISTS `post_reactions` (
  `id` varchar(128) NOT NULL,
  `link` int(11) NOT NULL,
  `name` int(11) NOT NULL,
  `picture` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `post_id` varchar(64) NOT NULL,
  `page_id` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`,`post_id`,`page_id`)
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
  `affiliation` float NOT NULL,
  `total_reactions` int(11) NOT NULL,
  `total_comments` int(11) NOT NULL,
  `total_comment_likes` int(11) NOT NULL,
  `total_pages_interacted_with` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
