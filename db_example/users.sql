-- phpMyAdmin SQL Dump
-- version 3.4.11.1deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Aug 03, 2013 at 05:04 PM
-- Server version: 5.5.28
-- PHP Version: 5.4.4-9

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `mydb`
--

CREATE DATABASE IF NOT EXISTS `social-digest-test`;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `social-digest-test` . `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `mail` varchar(255) DEFAULT NULL,
  `blog` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `calendar` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=6 ;

--
-- Dumping data for table `users`
--

INSERT INTO `social-digest-test` . `users` (`id`, `name`, `mail`, `blog`, `category`, `calendar`) VALUES
(1, 'El País', NULL, 'http://ep00.epimg.net/rss/elpais/portada.xml', 'Medis de comunicació tradicionals', ''),
(2, 'El Mundo', 'socialdigest1@mailinator.com', 'http://estaticos.elmundo.es/elmundo/rss/portada.xml', 'Medis de comunicació tradicionals', NULL),
(3, 'Festivos España', NULL, NULL, NULL, 'es.spain#holiday@group.v.calendar.google.com'),
(4, 'Setmanari La Directa', 'socialdigest2@mailinator.com', 'http://directa.cat/rss/noticies', 'Medis de comunicació alternatius', NULL),
(5, 'La Marea', NULL, 'http://feeds.feedburner.com/lamarea/jUvB', NULL, NULL);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
