-- MySQL dump 10.11
--
-- Host: localhost    Database: barchat
-- ------------------------------------------------------
-- Server version	5.0.75-0ubuntu10.2

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `channels`
--

DROP TABLE IF EXISTS `channels`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `channels` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(50) default NULL,
  `user_id` int(10) unsigned default NULL,
  `last` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `active` tinyint(4) default '0',
  PRIMARY KEY  (`id`),
  KEY `name` (`name`,`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `drawers`
--

DROP TABLE IF EXISTS `drawers`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `drawers` (
  `id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL,
  `channel` varchar(255) default NULL,
  `message` text,
  `js` text,
  `added` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `indexed` varchar(255) default NULL,
  `cssclass` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `files` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `user_id` int(10) unsigned default NULL,
  `filename` varchar(255) NOT NULL,
  `filedate` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `filesize` int(10) unsigned default '0',
  `filetype` varchar(50) default NULL,
  `url` tinytext NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `id_2` (`id`,`user_id`,`filename`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `karma`
--

DROP TABLE IF EXISTS `karma`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `karma` (
  `word` varchar(255) NOT NULL,
  `karma` int(11) default NULL,
  `user_id` int(11) default NULL,
  `lastvote` timestamp NOT NULL default CURRENT_TIMESTAMP,
  KEY `words` (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `options`
--

DROP TABLE IF EXISTS `options`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `options` (
  `id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL default '0',
  `name` varchar(255) NOT NULL,
  `value` text NOT NULL,
  `room` varchar(255) default NULL,
  `ispassword` tinyint(4) NOT NULL default '0',
  `istoggle` tinyint(4) NOT NULL default '0',
  `grouping` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Dumping data for table `options`
--

LOCK TABLES `options` WRITE;
/*!40000 ALTER TABLE `options` DISABLE KEYS */;
INSERT INTO `options` VALUES (1,0,'Open Registration','1',NULL,0,1,'Registration'),(2,0,'AWS Access Key ID','zz',NULL,0,0,'Amazon Web Services'),(3,0,'AWS Secret Access Key','zz',NULL,1,0,'Amazon Web Services'),(4,0,'S3 Bucket Name','s3.chat.rockriverstar.com',NULL,0,0,'Amazon Web Services'),(5,0,'Map API Key','ABQIAAAApeGLl9kUv5rXVftlE_FDrBRq_Toypo-wsDfa_hnO3cUvEYNruxSjIHWy9HylSy6NN3nFIYjAw2l-vw',NULL,0,0,'Google API');
/*!40000 ALTER TABLE `options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presence`
--

DROP TABLE IF EXISTS `presence`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `presence` (
  `status` int(11) NOT NULL auto_increment,
  `type` varchar(50) default 'message',
  `channel` varchar(50) default 'global',
  `data` text,
  `msgtime` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `user_id` int(11) default NULL,
  `cssclass` varchar(255) default NULL,
  `js` text,
  `user_to` int(10) unsigned default '0',
  `received` timestamp NULL default NULL,
  PRIMARY KEY  (`status`),
  UNIQUE KEY `status` (`status`),
  KEY `presence_channel` (`channel`),
  KEY `user_to` (`user_to`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `sessions` (
  `id` int(11) NOT NULL auto_increment,
  `session_key` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pingtime` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `session_key` (`session_key`),
  UNIQUE KEY `sessions_key` (`session_key`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `users` (
  `id` int(11) NOT NULL auto_increment,
  `username` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL,
  `lastping` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `users_username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2010-01-19 19:24:56
