-- phpMyAdmin SQL Dump
-- version 4.0.10.14
-- http://www.phpmyadmin.net
--
-- Host: localhost:3306
-- Generation Time: Jun 30, 2017 at 05:38 PM
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
  PRIMARY KEY (`id`),
  KEY `created_time` (`created_time`),
  KEY `user_id` (`user_id`),
  KEY `post_id` (`post_id`),
  KEY `page_id` (`page_id`),
  KEY `created_time_mysql` (`created_time_mysql`),
  KEY `message` (`message`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
