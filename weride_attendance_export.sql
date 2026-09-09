-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: u251904595_lunchbreak
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `access_logs`
--

DROP TABLE IF EXISTS `access_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `access_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(50) NOT NULL,
  `login_time` datetime NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Success',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=173 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `access_logs`
--

LOCK TABLES `access_logs` WRITE;
/*!40000 ALTER TABLE `access_logs` DISABLE KEYS */;
INSERT INTO `access_logs` VALUES (1,'A001','2026-08-19 15:40:09','::1','Failed','2026-08-19 07:40:09'),(2,'A001','2026-08-19 15:40:18','::1','Success','2026-08-19 07:40:18'),(3,'IN-2606-002','2026-08-19 15:43:26','::1','Failed','2026-08-19 07:43:26'),(4,'IN-2606-002','2026-08-19 15:43:36','::1','Success','2026-08-19 07:43:36'),(5,'A001','2026-08-19 15:44:53','::1','Success','2026-08-19 07:44:53'),(6,'IN-2606-002','2026-08-19 15:45:44','::1','Success','2026-08-19 07:45:44'),(7,'A001','2026-08-19 15:45:54','::1','Success','2026-08-19 07:45:54'),(8,'IN-2606-002','2026-08-19 15:46:09','::1','Failed','2026-08-19 07:46:09'),(9,'IN-2606-002','2026-08-19 15:46:22','::1','Success','2026-08-19 07:46:22'),(10,'A001','2026-08-19 15:48:08','::1','Failed','2026-08-19 07:48:08'),(11,'A001','2026-08-19 15:48:16','::1','Success','2026-08-19 07:48:16'),(12,'A001','2026-08-20 09:50:04','::1','Success','2026-08-20 01:50:04'),(13,'IN-2606-002','2026-08-20 10:53:41','::1','Success','2026-08-20 02:53:41'),(14,'A001','2026-08-20 10:59:18','::1','Success','2026-08-20 02:59:18'),(15,'A001','2026-08-20 13:37:33','::1','Success','2026-08-20 05:37:33'),(16,'IN-2606-002','2026-08-20 14:17:43','::1','Failed','2026-08-20 06:17:43'),(17,'IN-2606-002','2026-08-20 14:17:49','::1','Success','2026-08-20 06:17:49'),(18,'A001','2026-08-20 14:23:09','::1','Failed','2026-08-20 06:23:09'),(19,'A001','2026-08-20 14:23:16','::1','Failed','2026-08-20 06:23:16'),(20,'A001','2026-08-20 14:23:22','::1','Success','2026-08-20 06:23:22'),(21,'IN-2606-002','2026-08-20 14:41:42','::1','Success','2026-08-20 06:41:42'),(22,'A001','2026-08-20 14:44:51','::1','Success','2026-08-20 06:44:51'),(23,'IN-2606-002','2026-08-20 14:45:37','::1','Success','2026-08-20 06:45:37'),(24,'A001','2026-08-20 14:56:57','::1','Failed','2026-08-20 06:56:57'),(25,'A001','2026-08-20 14:57:12','::1','Success','2026-08-20 06:57:12'),(26,'A001','2026-08-20 15:04:12','::1','Success','2026-08-20 07:04:12'),(27,'IN-2606-002','2026-08-20 15:08:54','::1','Success','2026-08-20 07:08:54'),(28,'A001','2026-08-20 15:18:57','::1','Success','2026-08-20 07:18:57'),(29,'A001','2026-08-20 15:19:03','::1','Success','2026-08-20 07:19:03'),(30,'IN-2606-002','2026-08-20 15:19:46','::1','Success','2026-08-20 07:19:46'),(31,'A001','2026-08-20 15:21:54','::1','Success','2026-08-20 07:21:54'),(32,'IN-2606-002','2026-08-20 15:32:24','::1','Success','2026-08-20 07:32:24'),(33,'A001','2026-08-20 15:40:54','::1','Failed','2026-08-20 07:40:54'),(34,'A001','2026-08-20 15:41:00','::1','Success','2026-08-20 07:41:00'),(35,'IN-2606-002','2026-08-20 15:41:19','::1','Success','2026-08-20 07:41:19'),(36,'A001','2026-08-21 08:40:12','::1','Success','2026-08-21 00:40:12'),(37,'IN-2606-002','2026-08-21 08:40:50','::1','Success','2026-08-21 00:40:50'),(38,'IN-2606-002','2026-08-21 10:01:01','::1','Success','2026-08-21 02:01:01'),(39,'A001','2026-08-21 10:01:20','::1','Success','2026-08-21 02:01:20'),(40,'IN-2606-002','2026-08-24 15:00:29','::1','Success','2026-08-24 07:00:29'),(41,'GL001','2026-08-26 12:52:14','::1','Success','2026-08-26 04:52:14'),(42,'GL001','2026-08-26 14:43:14','::1','Success','2026-08-26 06:43:14'),(43,'GL001','2026-08-26 15:42:49','::1','Success','2026-08-26 07:42:49'),(44,'GL001','2026-08-26 15:42:53','::1','Success','2026-08-26 07:42:53'),(45,'A001','2026-08-26 15:45:04','::1','Success','2026-08-26 07:45:04'),(46,'A001','2026-08-26 16:00:17','::1','Success','2026-08-26 08:00:17'),(47,'GL001','2026-08-26 16:10:54','::1','Success','2026-08-26 08:10:54'),(48,'A001','2026-08-26 16:13:26','::1','Success','2026-08-26 08:13:26'),(49,'GL001','2026-08-27 08:11:31','::1','Success','2026-08-27 00:11:31'),(50,'A001','2026-08-27 08:12:24','::1','Success','2026-08-27 00:12:24'),(51,'GL001','2026-08-27 08:19:11','::1','Success','2026-08-27 00:19:11'),(52,'GL001','2026-08-27 08:19:26','::1','Success','2026-08-27 00:19:26'),(53,'A001','2026-08-27 08:22:19','::1','Success','2026-08-27 00:22:19'),(54,'GL001','2026-08-27 08:24:54','::1','Success','2026-08-27 00:24:54'),(55,'A001','2026-08-27 08:29:46','::1','Success','2026-08-27 00:29:46'),(56,'A001','2026-08-27 08:30:35','::1','Success','2026-08-27 00:30:35'),(57,'IN-2606-002','2026-08-27 08:30:58','::1','Success','2026-08-27 00:30:58'),(58,'GL001','2026-08-27 08:35:28','::1','Success','2026-08-27 00:35:28'),(59,'IN-2606-002','2026-08-27 08:37:27','::1','Success','2026-08-27 00:37:27'),(60,'A001','2026-08-27 08:38:03','::1','Success','2026-08-27 00:38:03'),(61,'GL001','2026-08-27 08:39:43','::1','Success','2026-08-27 00:39:43'),(62,'IN-2606-002','2026-08-27 08:43:47','::1','Success','2026-08-27 00:43:47'),(63,'A001','2026-08-27 09:03:35','::1','Success','2026-08-27 01:03:35'),(64,'A001','2026-08-27 09:59:23','::1','Failed','2026-08-27 01:59:23'),(65,'A001','2026-08-27 09:59:36','::1','Failed','2026-08-27 01:59:36'),(66,'A001','2026-08-27 09:59:50','::1','Failed','2026-08-27 01:59:50'),(67,'A001','2026-08-27 10:01:24','::1','Success','2026-08-27 02:01:24'),(68,'A001','2026-08-27 10:03:55','::1','Success','2026-08-27 02:03:55'),(69,'A001','2026-08-27 10:53:20','::1','Success','2026-08-27 02:53:20'),(70,'IN-2606-002','2026-08-27 10:54:51','::1','Success','2026-08-27 02:54:51'),(71,'A001','2026-08-27 11:22:58','::1','Success','2026-08-27 03:22:58'),(72,'IN-2606-002','2026-08-27 13:01:46','::1','Success','2026-08-27 05:01:46'),(73,'A001','2026-08-27 13:05:42','::1','Success','2026-08-27 05:05:42'),(74,'A001','2026-08-27 13:06:13','::1','Success','2026-08-27 05:06:13'),(75,'A001','2026-08-27 13:06:54','::1','Success','2026-08-27 05:06:54'),(76,'GL001','2026-08-27 13:07:30','::1','Success','2026-08-27 05:07:30'),(77,'A001','2026-08-27 13:20:52','::1','Success','2026-08-27 05:20:52'),(78,'A001','2026-08-27 14:11:09','::1','Success','2026-08-27 06:11:09'),(79,'IN-2606-002','2026-08-27 14:13:28','::1','Success','2026-08-27 06:13:28'),(80,'A001','2026-08-27 14:38:03','::1','Success','2026-08-27 06:38:03'),(81,'GL001','2026-08-28 15:08:06','::1','Success','2026-08-28 07:08:06'),(82,'GL001','2026-08-28 15:08:19','::1','Success','2026-08-28 07:08:19'),(83,'GL001','2026-08-28 15:08:24','::1','Success','2026-08-28 07:08:24'),(84,'A001','2026-08-28 15:26:46','::1','Success','2026-08-28 07:26:46'),(85,'A001','2026-08-28 15:29:39','::1','Success','2026-08-28 07:29:39'),(86,'GL002','2026-08-28 15:30:13','::1','Success','2026-08-28 07:30:13'),(87,'GL002','2026-08-28 15:31:38','::1','Success','2026-08-28 07:31:38'),(88,'A001','2026-08-28 15:58:44','::1','Success','2026-08-28 07:58:44'),(89,'IN-2606-002','2026-08-28 15:59:12','::1','Success','2026-08-28 07:59:12'),(90,'IN-2606-002','2026-08-28 16:00:44','::1','Success','2026-08-28 08:00:44'),(91,'A001','2026-08-28 16:03:12','::1','Success','2026-08-28 08:03:12'),(92,'GL001','2026-08-28 16:03:26','::1','Success','2026-08-28 08:03:26'),(93,'A001','2026-08-28 16:03:52','::1','Success','2026-08-28 08:03:52'),(94,'IN-2606-002','2026-08-28 16:04:39','::1','Success','2026-08-28 08:04:39'),(95,'A001','2026-08-28 16:07:28','::1','Success','2026-08-28 08:07:28'),(96,'A001','2026-09-02 14:17:26','::1','Success','2026-09-02 06:17:26'),(97,'GL001','2026-09-02 14:17:41','::1','Success','2026-09-02 06:17:41'),(98,'A001','2026-09-02 14:26:35','::1','Success','2026-09-02 06:26:35'),(99,'GL001','2026-09-02 14:30:27','::1','Success','2026-09-02 06:30:27'),(100,'A001','2026-09-02 15:10:41','::1','Success','2026-09-02 07:10:41'),(101,'GL001','2026-09-02 15:27:42','::1','Success','2026-09-02 07:27:42'),(102,'IN-2606-002','2026-09-02 15:31:12','::1','Success','2026-09-02 07:31:12'),(103,'GL001','2026-09-02 15:33:05','::1','Success','2026-09-02 07:33:05'),(104,'A001','2026-09-03 08:43:07','::1','Success','2026-09-03 00:43:07'),(105,'A001','2026-09-03 08:43:17','::1','Success','2026-09-03 00:43:17'),(106,'GL001','2026-09-03 08:48:58','::1','Success','2026-09-03 00:48:58'),(107,'GL001','2026-09-03 08:49:43','::1','Success','2026-09-03 00:49:43'),(108,'A001','2026-09-03 09:02:06','::1','Success','2026-09-03 01:02:06'),(109,'GL001','2026-09-03 09:02:24','::1','Success','2026-09-03 01:02:24'),(110,'GL001','2026-09-03 09:02:32','::1','Success','2026-09-03 01:02:32'),(111,'A001','2026-09-03 09:20:14','::1','Success','2026-09-03 01:20:14'),(112,'A001','2026-09-03 09:20:38','::1','Success','2026-09-03 01:20:38'),(113,'FT-2606-003','2026-09-03 09:22:32','::1','Success','2026-09-03 01:22:32'),(114,'IN-2606-002','2026-09-03 09:33:14','::1','Success','2026-09-03 01:33:14'),(115,'GL001','2026-09-03 09:46:05','::1','Success','2026-09-03 01:46:05'),(116,'A001','2026-09-03 09:48:54','::1','Success','2026-09-03 01:48:54'),(117,'GL001','2026-09-03 09:57:49','::1','Success','2026-09-03 01:57:49'),(118,'A001','2026-09-03 10:05:44','::1','Success','2026-09-03 02:05:44'),(119,'GL001','2026-09-03 10:13:58','::1','Success','2026-09-03 02:13:58'),(120,'A001','2026-09-03 10:15:27','::1','Success','2026-09-03 02:15:27'),(121,'A001','2026-09-03 10:15:39','::1','Success','2026-09-03 02:15:39'),(122,'IN-2606-002','2026-09-03 10:18:24','::1','Success','2026-09-03 02:18:24'),(123,'GL001','2026-09-03 10:37:54','::1','Failed','2026-09-03 02:37:54'),(124,'GL001','2026-09-03 10:38:01','::1','Success','2026-09-03 02:38:01'),(125,'A001','2026-09-03 11:06:55','::1','Success','2026-09-03 03:06:55'),(126,'A001','2026-09-03 11:30:34','::1','Success','2026-09-03 03:30:34'),(127,'A001','2026-09-03 11:30:35','::1','Success','2026-09-03 03:30:35'),(128,'A001','2026-09-03 13:20:14','::1','Success','2026-09-03 05:20:14'),(129,'A001','2026-09-03 13:22:54','::1','Success','2026-09-03 05:22:54'),(130,'IN-2606-002','2026-09-03 13:24:35','::1','Success','2026-09-03 05:24:35'),(131,'GL001','2026-09-03 14:20:03','::1','Success','2026-09-03 06:20:03'),(132,'A001','2026-09-03 14:22:10','::1','Success','2026-09-03 06:22:10'),(133,'GL001','2026-09-03 14:29:20','::1','Success','2026-09-03 06:29:20'),(134,'IN-2606-002','2026-09-03 14:48:39','::1','Success','2026-09-03 06:48:39'),(135,'GL001','2026-09-03 14:48:55','::1','Success','2026-09-03 06:48:55'),(136,'IN-2606-002','2026-09-03 15:03:52','::1','Success','2026-09-03 07:03:52'),(137,'IN-2606-002','2026-09-03 15:06:14','::1','Success','2026-09-03 07:06:14'),(138,'A001','2026-09-03 15:06:28','::1','Success','2026-09-03 07:06:28'),(139,'A001','2026-09-03 15:14:01','::1','Success','2026-09-03 07:14:01'),(140,'GL002','2026-09-03 15:14:48','::1','Success','2026-09-03 07:14:48'),(141,'A001','2026-09-03 15:15:22','::1','Success','2026-09-03 07:15:22'),(142,'IN-2606-002','2026-09-03 15:17:53','::1','Success','2026-09-03 07:17:53'),(143,'A001','2026-09-03 15:19:47','::1','Success','2026-09-03 07:19:47'),(144,'IN-2606-002','2026-09-03 15:21:44','::1','Success','2026-09-03 07:21:44'),(145,'A001','2026-09-03 15:21:55','::1','Success','2026-09-03 07:21:55'),(146,'A001','2026-09-03 15:22:12','::1','Success','2026-09-03 07:22:12'),(147,'IN-2606-002','2026-09-03 15:22:46','::1','Success','2026-09-03 07:22:46'),(148,'IN-2606-002','2026-09-03 15:22:50','::1','Success','2026-09-03 07:22:50'),(149,'A001','2026-09-03 15:26:50','::1','Success','2026-09-03 07:26:50'),(150,'IN-2606-002','2026-09-03 15:29:26','::1','Success','2026-09-03 07:29:26'),(151,'A001','2026-09-03 15:30:40','::1','Success','2026-09-03 07:30:40'),(152,'GL002','2026-09-03 15:39:07','::1','Success','2026-09-03 07:39:07'),(153,'IN-2606-002','2026-09-03 15:39:34','::1','Success','2026-09-03 07:39:34'),(154,'A001','2026-09-03 15:39:41','::1','Success','2026-09-03 07:39:41'),(155,'A001','2026-09-03 15:43:45','::1','Success','2026-09-03 07:43:45'),(156,'A001','2026-09-03 16:00:40','::1','Success','2026-09-03 08:00:40'),(157,'IN-2606-002','2026-09-03 16:04:15','::1','Success','2026-09-03 08:04:15'),(158,'A001','2026-09-03 16:05:08','::1','Success','2026-09-03 08:05:08'),(159,'GL001','2026-09-03 16:05:39','::1','Success','2026-09-03 08:05:39'),(160,'IN-2606-002','2026-09-03 16:07:15','::1','Success','2026-09-03 08:07:15'),(161,'GL001','2026-09-03 16:07:39','::1','Success','2026-09-03 08:07:39'),(162,'IN-2606-002','2026-09-03 16:08:29','::1','Success','2026-09-03 08:08:29'),(163,'GL001','2026-09-03 16:09:54','::1','Success','2026-09-03 08:09:54'),(164,'IN-2606-002','2026-09-03 16:12:46','::1','Success','2026-09-03 08:12:46'),(165,'GL001','2026-09-03 16:13:28','::1','Success','2026-09-03 08:13:28'),(166,'A001','2026-09-03 16:15:15','::1','Success','2026-09-03 08:15:15'),(167,'IN-2606-002','2026-09-03 16:16:20','::1','Success','2026-09-03 08:16:20'),(168,'IN-2606-002','2026-09-03 16:20:54','::1','Success','2026-09-03 08:20:54'),(169,'A001','2026-09-03 16:24:10','::1','Success','2026-09-03 08:24:10'),(170,'IN-2606-002','2026-09-03 16:38:15','::1','Success','2026-09-03 08:38:15'),(171,'A001','2026-09-03 16:43:04','::1','Success','2026-09-03 08:43:04'),(172,'GL001','2026-09-03 16:45:41','::1','Success','2026-09-03 08:45:41');
/*!40000 ALTER TABLE `access_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_logs`
--

DROP TABLE IF EXISTS `attendance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `work_hours` decimal(5,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'P',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `flag` enum('None','Late','Early','Both') DEFAULT 'None',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_emp_date` (`emp_id`,`date`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_logs`
--

LOCK TABLES `attendance_logs` WRITE;
/*!40000 ALTER TABLE `attendance_logs` DISABLE KEYS */;
INSERT INTO `attendance_logs` VALUES (1,'IN-2606-002','2026-08-19','2026-08-19 15:43:46','2026-08-19 15:43:50',0.00,'P',NULL,'2026-08-19 07:43:46','Both'),(2,'IN-2606-002','2026-08-21',NULL,NULL,0.00,'HL',NULL,'2026-08-19 07:45:19','None'),(3,'IN-2606-001','2026-08-21',NULL,NULL,0.00,'UPL',NULL,'2026-08-20 02:59:41','None'),(4,'FT-2606-002','2026-08-21',NULL,NULL,0.00,'',NULL,'2026-08-20 07:05:42','None'),(5,'A001','2026-08-21',NULL,NULL,0.00,'SL',NULL,'2026-08-20 07:05:44','None'),(6,'IN-2606-002','2026-08-20','2026-08-20 15:33:00','2026-08-20 15:33:07',0.00,'P',NULL,'2026-08-20 07:05:50','Early'),(7,'FT-2606-003','2026-08-21',NULL,NULL,0.00,'AL',NULL,'2026-08-20 07:06:24','None'),(8,'FT-2606-001','2026-08-21',NULL,NULL,0.00,'',NULL,'2026-08-20 07:06:40','None'),(9,'HR001','2026-08-21',NULL,NULL,0.00,'SL',NULL,'2026-08-20 07:06:42','None'),(10,'FT-2606-002','2026-08-24',NULL,NULL,0.00,'',NULL,'2026-08-20 07:06:56','None'),(15,'IN-2606-002','2026-08-24','2026-08-24 15:06:42','2026-08-24 15:11:26',0.07,'P',NULL,'2026-08-24 07:06:42','Early'),(16,'IN-2606-002','2026-08-27','2026-08-27 08:31:54',NULL,0.00,'P',NULL,'2026-08-27 00:31:54','None'),(17,'GL001','2026-08-27','2026-08-27 08:37:01',NULL,0.00,'P',NULL,'2026-08-27 00:37:01','None'),(18,'IN-2606-002','0000-00-00',NULL,NULL,0.00,'AL',NULL,'2026-08-27 00:42:49','None'),(19,'GL002','2026-08-28','2026-08-28 15:30:29',NULL,0.00,'P',NULL,'2026-08-28 07:30:29','Late'),(20,'IN-2606-002','2026-08-28','2026-08-28 16:00:49',NULL,0.00,'P',NULL,'2026-08-28 08:00:49','Late'),(21,'GL003','2026-09-04',NULL,NULL,0.00,'',NULL,'2026-09-02 06:56:03','None'),(22,'FT-2606-001','2026-09-03',NULL,NULL,0.00,'',NULL,'2026-09-02 06:58:11','None'),(23,'HR001','2026-09-03',NULL,NULL,0.00,'',NULL,'2026-09-02 06:58:13','None'),(24,'GL001','2026-09-03','2026-09-03 08:49:34',NULL,0.00,'P',NULL,'2026-09-03 00:49:34','None'),(25,'FT-2606-003','2026-09-03','2026-09-03 09:22:41',NULL,0.00,'P',NULL,'2026-09-03 01:22:41','Late'),(26,'IN-2606-002','2026-09-03','2026-09-03 10:18:28',NULL,0.00,'P',NULL,'2026-09-03 02:18:28','Late'),(27,'IN-2606-002','2026-09-22',NULL,NULL,0.00,'SL',NULL,'2026-09-03 07:27:09','None'),(28,'IN-2606-002','2026-09-23',NULL,NULL,0.00,'AL',NULL,'2026-09-03 07:27:09','None'),(29,'IN-2606-002','2026-09-24',NULL,NULL,0.00,'AL',NULL,'2026-09-03 07:27:09','None'),(30,'IN-2606-015','2026-09-10',NULL,NULL,0.00,'',NULL,'2026-09-03 08:03:43','None'),(33,'IN-2606-002','2026-09-04',NULL,NULL,0.00,'L',NULL,'2026-09-03 08:15:00','None'),(34,'IN-2606-002','2026-09-05',NULL,NULL,0.00,'L',NULL,'2026-09-03 08:15:00','None');
/*!40000 ALTER TABLE `attendance_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `break_change_requests`
--

DROP TABLE IF EXISTS `break_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `break_change_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(50) NOT NULL,
  `current_window` varchar(50) NOT NULL,
  `requested_window` varchar(50) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_bcr_emp` (`emp_id`),
  CONSTRAINT `fk_bcr_emp` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `break_change_requests`
--

LOCK TABLES `break_change_requests` WRITE;
/*!40000 ALTER TABLE `break_change_requests` DISABLE KEYS */;
INSERT INTO `break_change_requests` VALUES (3,'IN-2606-001','12:00-13:00','13:00-14:00','approved','2026-06-10 05:10:14'),(4,'IN-2606-002','12:00-13:00','13:00-14:00','approved','2026-06-10 05:14:18'),(7,'IN-2606-001','13:00-14:00','13:00-14:00','rejected','2026-06-10 07:10:09'),(9,'GL001','12:00-13:00','13:00-14:00','approved','2026-09-03 01:47:36'),(10,'IN-2606-002','13:00-14:00','12:00-13:00','approved','2026-09-03 02:20:01');
/*!40000 ALTER TABLE `break_change_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `break_override_requests`
--

DROP TABLE IF EXISTS `break_override_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `break_override_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(50) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_bor_emp` (`emp_id`),
  CONSTRAINT `fk_bor_emp` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `break_override_requests`
--

LOCK TABLES `break_override_requests` WRITE;
/*!40000 ALTER TABLE `break_override_requests` DISABLE KEYS */;
INSERT INTO `break_override_requests` VALUES (3,'FT-2606-001','Meeting ',60,'approved','2026-06-10 14:54:12');
/*!40000 ALTER TABLE `break_override_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_notifications`
--

DROP TABLE IF EXISTS `employee_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_notifications`
--

LOCK TABLES `employee_notifications` WRITE;
/*!40000 ALTER TABLE `employee_notifications` DISABLE KEYS */;
INSERT INTO `employee_notifications` VALUES (1,'IN-2606-002','Your lunchtime override request was approved by your manager.',1,'2026-06-10 14:09:00'),(2,'FT-2606-001','Your lunchtime override request was approved by your manager.',1,'2026-06-10 15:08:57'),(3,'IN-2606-001','Your request to change your lunch window to 13:00-14:00 was rejected.',0,'2026-08-04 14:16:32'),(4,'IN-2606-002','Your leave request (Sick Leave for 2026-08-21) was rejected. Reason: you noob',0,'2026-08-20 14:45:30'),(5,'IN-2606-002','Your request to change your lunch window to 12:00-13:00 was approved.',0,'2026-08-27 08:38:33'),(6,'IN-2606-002','Your leave request (Annual Leave for 0000-00-00) was approved.',0,'2026-08-27 08:42:49'),(7,'IN-2606-002','Your request to change your shift hour to 08:00-17:00 was approved.',0,'2026-08-27 08:42:58'),(8,'GL001','Your request to change your shift hour to 08:30-17:30 was approved.',0,'2026-09-03 10:16:24'),(9,'GL001','Your request to change your lunch window to 13:00-14:00 was approved.',0,'2026-09-03 10:16:43'),(10,'IN-2606-002','Your request to change your shift hour to 08:00-17:00 was approved.',0,'2026-09-03 10:20:28'),(11,'IN-2606-002','Your request to change your lunch window to 12:00-13:00 was approved.',0,'2026-09-03 10:23:18'),(12,'IN-2606-002','Your request to change your lunch window to 12:00-13:00 was approved.',0,'2026-09-03 10:23:25'),(13,'IN-2606-002','Your leave request (Unpaid Leave for 2026-09-16) was rejected. Reason: due to high leave on the day',0,'2026-09-03 15:22:39'),(14,'IN-2606-002','Your leave request (Sick Leave for 2026-09-22) was approved.',0,'2026-09-03 15:27:09'),(15,'IN-2606-002','Your leave request (Annual Leave for 2026-09-23) was approved.',0,'2026-09-03 16:05:14'),(16,'IN-2606-002','Your leave request (Unpaid Leave for 2026-09-04) was approved.',0,'2026-09-03 16:15:00');
/*!40000 ALTER TABLE `employee_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(50) NOT NULL,
  `welabel_id` varchar(100) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `employment_type` varchar(50) NOT NULL,
  `role` varchar(50) DEFAULT 'employee',
  `lunch_window` varchar(50) DEFAULT '12:00-13:00',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone_number` varchar(20) DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `lockout_time` datetime DEFAULT NULL,
  `phone_verified` tinyint(1) DEFAULT 0,
  `phone_verification_code` varchar(10) DEFAULT NULL,
  `phone_verification_expiry` datetime DEFAULT NULL,
  `leave_status` varchar(50) DEFAULT 'active',
  `onboarding_date` date DEFAULT NULL,
  `annual_leave_quota` int(11) DEFAULT 8,
  `sick_leave_quota` int(11) DEFAULT 14,
  `unpaid_leave_quota` int(11) DEFAULT 30,
  `shift_start` time DEFAULT '09:00:00',
  `shift_end` time DEFAULT '18:00:00',
  `department` varchar(100) DEFAULT 'General',
  `annual_leave_balance` int(11) DEFAULT 8,
  `sick_leave_balance` int(11) DEFAULT 14,
  `shift_hour` varchar(20) DEFAULT '09:00-18:00',
  `group_leader_id` varchar(50) DEFAULT NULL,
  `casual_leave_balance` int(11) DEFAULT 7,
  PRIMARY KEY (`id`),
  UNIQUE KEY `emp_id` (`emp_id`),
  UNIQUE KEY `unique_emp_id` (`emp_id`),
  UNIQUE KEY `emp_id_2` (`emp_id`),
  UNIQUE KEY `emp_id_3` (`emp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=151 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (3,'A001',NULL,'Super Admin','$2y$10$r8.Bpv3RRcKV7UyvtrBI9ug83qKjinGvzZAHe0GfpBhn4Rx73.Vj2','Full time','admin','12:00-13:00','2026-05-25 07:08:22',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(4,'TL001',NULL,'Team Lead','$2y$10$r8.Bpv3RRcKV7UyvtrBI9ug83qKjinGvzZAHe0GfpBhn4Rx73.Vj2','Full time','tl','12:00-13:00','2026-05-25 07:08:22',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(5,'HR001',NULL,'HR Manager','$2y$10$r8.Bpv3RRcKV7UyvtrBI9ug83qKjinGvzZAHe0GfpBhn4Rx73.Vj2','Full time','hr','12:00-13:00','2026-05-25 07:08:22',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(35,'FT-2606-001',NULL,'ID ONE','$2y$10$sVvV/O9UC.DHQoh0LjNJXu6o5YKKuJf3xfJLPjD48k3cqaSXj9fbu','Full time','qa','12:00-13:00','2026-06-10 05:01:06',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(36,'IN-2606-001',NULL,'ID TWO','$2y$10$x2gqbfv4jvbbtoOcoNJsM.GMJzfLTH33f670/tNknhgIPXPpeTgNG','Intern','employee','13:00-14:00','2026-06-10 05:01:06',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00','GL001',7),(37,'IN-2606-002','','Serene test','$2y$10$eBT5OJzYVL06Hk3p/qyaveplAxj5kTpHq4NYvvb9IVnkqmvUwwshe','Full time','qa','12:00-13:00','2026-06-10 05:01:06',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',12,11,'08:00-17:00','',7),(38,'FT-2606-003',NULL,'ID THREE','$2y$10$sG.AT8FiuPDx1CqQOvIqA.IAOwI3TstApoMJXbcfU.rVvdMHSrGPG','Full time','employee','12:00-13:00','2026-06-10 05:36:32',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00','GL001',7),(105,'GL001','','vvvvv','$2y$10$YGrOK4HWeXbsUwGvmmjZnOoQggof8lc5i8PmjEp/6Oj0Hol57P6GW','Full-Time','gl','13:00-14:00','2026-08-27 05:07:22',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'08:30-17:30','',7),(106,'GL002','','hi2','$2y$10$.wwtL900svC4unpPIbgRzetVD1LAYE/s0TN0YCII//KbJurUvhnx.','Intern','gl','12:00-13:00','2026-08-28 07:27:18',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00','',7),(107,'GL003','','hi3','$2y$10$LIbZ6W.ZWpR3jlmV8qTlUuzRBjMCnpJSY3V0sJ0E8W1Y6DOPLLAnS','Full-Time','gl','12:00-13:00','2026-08-28 07:27:33',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00','',7),(108,'IN-2606-003','','Kavi test','$2y$10$3YaA4e9uVCHDLao4n8dPEerf4gwqCMVi829y.9k0ip5n/Gex6.wqy','Intern','employee','12:00-13:00','2026-09-03 02:03:29',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(109,'IN-2606-004','KL_Kavines_Ganesen@welabel.ai','Kavines Ganesen','$2y$10$xoGYb3rHjbZhWxWJ.B8z4OBnBUeF9CsGQT4II7xwNyxqJMw4suKJS','Intern','employee','12:00-13:00','2026-09-03 02:03:29',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(110,'IN-2606-005','KL_Zuhan_Lee@welabel.ai','Zuhan Lee','$2y$10$.NmAL0/TN.d6Vji3w6YrgO.klNYtj/pBsddYvIjYE8pcDU65Y/A8i','Intern','employee','12:00-13:00','2026-09-03 02:03:29',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(111,'FT-2606-004','KL_Zulkarnain_Jamal@welabel.ai','Zulkarnain Jamal','$2y$10$y6/JoZmrG3F5QHYzwfi8z.6xY.zVMyHxTpfendyEv2Q9Kzt4jQPaW','Full-Time','qa','12:00-13:00','2026-09-03 02:03:29',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(112,'IN-2606-006','KL_Farisha_Zuraimi@welabel.ai','Farisha Zuraimi','$2y$10$/dJaZB/DLYWou6ebU55aSuXL1yFKgxwHLx7F/yf5Y.jJd.vEZ1kma','Intern','employee','12:00-13:00','2026-09-03 02:03:29',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(113,'IN-2606-007','KL_Marianne_Tan@welabel.ai','Marianne Tan','$2y$10$ByDTWv0M2QnSHbZd06WUkOqSGpyzl6RUXIIoLWKeXTxWuYk9fpB1i','Intern','qa','12:00-13:00','2026-09-03 02:03:29',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(114,'IN-2606-008','KL_Yuan_Loh@welabel.ai','Yuan Loh','$2y$10$jcqPw.inXARoUSIfTKQeMerqGByWOvM93HgzaDMDKZi/NNT4jo0Xq','Intern','qa','12:00-13:00','2026-09-03 02:03:29',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(115,'FT-2606-005','KL_Judith_Lantau@welabel.ai','Judith Lantau','$2y$10$LQkYLMdJzYY/GREA1DSjlu6yp4TlBSMBKbCuk8eVIpZWpqynXLuLy','Full-Time','qa','12:00-13:00','2026-09-03 02:03:29',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(116,'FT-2606-006','KL_Syazana_Azman@welabel.ai','Syazana Azman','$2y$10$8vC5utiXPWzKPiTgVdUmcuhtRG78oep89XN3e7HaorPACz.FU4s8W','Full-Time','qa','12:00-13:00','2026-09-03 02:03:29',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(117,'IN-2606-009','KL_Fazlin_Zulkiflee@welabel.ai','Fazlin Zulkiflee','$2y$10$kuA0oOkx56xZG3ycF8GRHeU31Zg/MJxjaVbKKrHxiYjruEw29Ptk2','Intern','employee','12:00-13:00','2026-09-03 02:03:29',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(118,'IN-2606-010','KL_Aina_Najib@welabel.ai','Aina Najib','$2y$10$iWDG63HZPuMiY96sYU27r.IUicYVgdSc0NXfcMKa7TuB2QReAIUWW','Intern','employee','12:00-13:00','2026-09-03 02:03:29',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(119,'IN-2606-011','KL_Dania_Raziman@welabel.ai','Dania Raziman','$2y$10$VyAGHP5cE2DOT2qmrlX3AOQEc6zFd0ZsEPXnZW9xLQvQZvvtXskpi','Intern','qa','12:00-13:00','2026-09-03 02:03:29',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(120,'IN-2606-012','KL_Aina_Natasha@welabel.ai','Aina Natasha','$2y$10$5IIgXxpA.ZeW20E7pqB.o.RM9YoEjVbUpZCXG87EHoY1Sv9D4MS4G','Intern','employee','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(121,'FT-2606-007','KL_Ajmal_Muhammad@welabel.ai','Ajmal Muhammad','$2y$10$gvRpzN3cgQjGELbAT7NbBOzXX84RuJhWdtChWf2ZH.vyLQT/ZwwBq','Full-Time','employee','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(122,'IN-2606-013','KL_fatin_rozali@welabel.ai','Fatin Rozali','$2y$10$5mFUCG6XTKNVP88p1VjITujj.3tYIaBiDKzSaeIPJRwAorR1EMBPe','Intern','qa','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(123,'FT-2606-008','KL_izatul_zainal@welabel.ai','Izatul Zainal','$2y$10$bT.HFz9.qEqGD6H2piDo2OCBNrrt41Sp/S6sPRWZMHNoVDbJoigiu','Full-Time','employee','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(124,'FT-2606-009','KL_eleeza_redzuan@welabel.ai','Eleeza Redzuan','$2y$10$bCqR57bGh98MK9yjpLOCuO18UBzw5y4sDJ96zliSM7xffQftYTiWS','Full-Time','qa','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(125,'IN-2606-014','KL_Afifa.Mohamed@welabel.ai','Afifa.Mohamed','$2y$10$ox1wfzLu5lsjkDmGqUH/3.tvyVbbVa17jZ5.tgTiaut2fqXqYbcDm','Intern','employee','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(126,'IN-2606-015','KL_Aisyah.Halim@welabel.ai','Aisyah.Halim','$2y$10$ugwxZxIqpq2gL9qTX72dteNLjidS5iPlfhpSEq7qRqOial/5lKCZG','Intern','employee','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(127,'IN-2606-016','KL_Aina.Fitrah@welabel.ai','Aina.Fitrah','$2y$10$7Vt6gWWAjSRbNRrjatX3C.FBCssveGrVCOvBDAzWbYyHUuVXyz6Aq','Intern','qa','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(128,'FT-2606-010','KL_Rubashieny.Moogan@welabel.ai','Rubashieny.Moogan','$2y$10$fyQtOCMP8hS99H/ZDP1QGe.NSKzMZ1/0VmIwkqLUY2foWVW/kjYTW','Full-Time','employee','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(129,'FT-2606-011','KL_Prithivi.Ram@welabel.ai','Prithivi.Ram','$2y$10$dGVfHJ6F7VfP0ORm77ZeMexZ0kKuqquBJe/ICT/5oLGkumwByK1Ty','Full-Time','employee','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(130,'FT-2606-012','KL_Liyana.Syafiqah@welabel.ai','Liyana.Syafiqah','$2y$10$M0pdegZ8wTmvSYphtFtQpONGii53HD/ZKieeC.nhnXwa2hYtvC6q2','Full-Time','employee','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(131,'FT-2606-013','KL_Luqman.Hakim@welabel.ai','Luqman.Hakim','$2y$10$0LwjFWqzC3ZHn68s17blvOzE.XrBcY6mOIQDC.9A2L.A9o/h9i60u','Full-Time','qa','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(132,'FT-2606-014','KL_Hasanah.Farha@welabel.ai','Hasanah.Farha','$2y$10$H61/qUmbV/v9SqGfdmMJk.VtVDt4Om5oSG94E0g.WkoIFv6VPAcri','Full-Time','employee','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(133,'FT-2606-015','KL_Tulasi.Saledurai@welabel.ai','Tulasi.Saledurai','$2y$10$GxBPXjw7N0xOrFRBGq6rneGpOl/sSQfkuNybf3p6rUJ62auk5azDu','Full-Time','employee','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(134,'FT-2606-016','KL_Mohd.Fadhil@welabel.ai','Mohd.Fadhil','$2y$10$h0p1d1GJmB8lLVDnvlpGF.tUx58q4Pw9Rb/nMOJisVLR.aHTcschq','Full-Time','employee','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(135,'FT-2606-017','KL_Mageswary.Vadivello@welabel.ai','Mageswary.Vadivello','$2y$10$z5i4SJ/qFhKNZNU6Z4G4v.3I5FlKhvnt.j3P1XsNpvY5FO8W515yS','Full-Time','employee','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(136,'FT-2606-018','KL_Sangeetha.Shanmuganathan@welabel.ai','Sangeetha.Shanmuganathan','$2y$10$3aQ89dIoPTEJUDUfyjC4YOpTZ0M3EXtx1fK3eQvaVmEGhFbx2OI6.','Full-Time','employee','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(137,'FT-2607-001','KL_Alyshya_Azis@welabel.ai','Nurul Alis Alyshya binti Abdul Azis','$2y$10$kAhRxI9RMN2ZP4uFOIsjouyu8VFKUZ6.mqU5bNUaZQja1Y.1FfqYe','Full-Time','employee','12:00-13:00','2026-09-03 02:03:30',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(138,'FT-2608-001','KL_Salma_Soh@welabel.ai','Salma Soh','$2y$10$vESIwXlyU3EwKJ3m6.wCLey/QWZVCNZFKleiCQCEHH2nkJYxrpNzi','Full-Time','employee','12:00-13:00','2026-09-03 02:03:31',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(139,'FT-2608-002','KL_Thananiy_Selvanathan@welabel.ai','Thananiy Selvanathan','$2y$10$K/3.FKwNoBA2PT8ayDCwHenrKWWolMhmidhD0u.CvTiK4119RbKgW','Full-Time','employee','12:00-13:00','2026-09-03 02:03:31',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(140,'IN-2608-001','KL_HockSeng_Teo@welabel.ai','HockSeng Teo','$2y$10$jjQnYj74foJn7v6VsZFveeNq0oQb/1KyzokurWNwU2s6wjbA48KZ.','Intern','employee','12:00-13:00','2026-09-03 02:03:31',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(141,'IN-2608-002','KL_Darren_DanielLeopold@welabel.ai','Darren DanielLeopold','$2y$10$iSIrzM.AuDMcfdaliuWrk.Z4iE8lnS4LpxSgIRAoZknex1eNhe5Va','Intern','employee','12:00-13:00','2026-09-03 02:03:31',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(142,'IN-2608-003','KL_Saw_WaiKang@welabel.ai','Saw WaiKang','$2y$10$/YqhkSeElBbJ6DXDOFIUcupsnSIE4yJtVtzC3e/Z/bgT6aUkgAbTW','Intern','employee','12:00-13:00','2026-09-03 02:03:31',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(143,'FT-2608-003','KL_Danish_Ansori@welabel.ai','Danish Ansori','$2y$10$NLyzGkeNJ7DNAtmszoKqhO5qddy8ZDRAJ5TnE6nXTHc1TyBZZp2zW','Full-Time','employee','12:00-13:00','2026-09-03 02:03:31',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(144,'IN-2608-004','KL_Pevenash_Tamilhselvan@welabel.ai','Pevenash Tamilhselvan','$2y$10$HZwYwl4s2kMVx99vUGYfU.eQIbKGDP7QqYBdhDV.VcJd1z7UJyJhS','Intern','employee','12:00-13:00','2026-09-03 02:03:31',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(145,'FT-2608-004','KL_LoongJin_Wong@welabel.ai','LoongJin Wong','$2y$10$KsyDW.1w4YhBiwADDuAyVOVyz6hN7qPoZ4cAJ9lqp1ZuzODi62Fgq','Full-Time','employee','12:00-13:00','2026-09-03 02:03:31',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(146,'IN-2608-005','KL_Adriana_Abdullah@welabel.ai','Adriana Abdullah','$2y$10$DgcyTsyCMSbNucs.Cvp9BO6lTznk1.YmdS3HBdPjYpoQQ8MRd9sRq','Intern','employee','12:00-13:00','2026-09-03 02:03:31',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(147,'FT-2608-005','KL_Aiman_Zain@welabel.ai','Aiman Zain','$2y$10$axYsy1HYIflBqv3O1GFtyuwodh4G5V0H/J.08F7JK71bkYxYZCZcS','Full-Time','employee','12:00-13:00','2026-09-03 02:03:31',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(148,'IN-2608-006','KL_Hwong_YongBing@welabel.ai','Hwong YongBing','$2y$10$bq6Y67iFymuqhq9V3r50rOh.YntFYaBslyJQyJgLtzB/aKCPY0e3i','Intern','employee','12:00-13:00','2026-09-03 02:03:31',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(149,'IN-2608-007','KL_Vishvan_Varma@welabel.ai','Vishvan Varma','$2y$10$2kZhBqw0jwefpGQrbVHZNeNQSShcAyOP/HmjKWbffLPSE1PsEI6FW','Intern','employee','12:00-13:00','2026-09-03 02:03:31',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7),(150,'IN-2609-001','KL_Lavannya_Chandran@welabel.ai','Lavannya Chandran','$2y$10$UmoKot6vQbsSzi1/2PsZKOOkCwUvOjagWqbavVH8sfM3wnaFIkfAC','Intern','employee','12:00-13:00','2026-09-03 02:03:31',NULL,0,NULL,0,NULL,NULL,'active',NULL,14,14,30,'09:00:00','18:00:00','General',14,14,'09:00-18:00',NULL,7);
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_requests`
--

DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(50) NOT NULL,
  `leave_type` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_date` date NOT NULL,
  `end_time` time DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `application_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `duration` varchar(50) DEFAULT '1 day',
  `mc_attachment` varchar(255) DEFAULT NULL,
  `reviewed_by` varchar(50) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `emp_id` (`emp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_requests`
--

LOCK TABLES `leave_requests` WRITE;
/*!40000 ALTER TABLE `leave_requests` DISABLE KEYS */;
INSERT INTO `leave_requests` VALUES (1,'A001','Annual Leave','2026-09-01',NULL,'2026-09-02',NULL,'Vacation','rejected',NULL,'2026-08-18 08:35:08','2 Days',NULL,NULL,NULL,''),(2,'A001','Annual Leave','2026-09-01',NULL,'2026-09-02',NULL,'Vacation','rejected',NULL,'2026-08-18 08:40:30','2 Days',NULL,NULL,NULL,'.'),(3,'A001','Annual Leave','2026-09-01',NULL,'2026-09-02',NULL,'Vacation','rejected',NULL,'2026-08-18 08:42:04','2 Days',NULL,NULL,NULL,'.'),(4,'IN-2606-002','Unpaid Leave','2026-08-21',NULL,'2026-08-21',NULL,'personal','approved','2026-08-19','2026-08-19 07:44:34','1','',NULL,NULL,NULL),(5,'IN-2606-002','Sick Leave','2026-08-21',NULL,'2026-08-21',NULL,'test','rejected','2026-08-20','2026-08-20 06:44:42','1','uploads/medical_certificates/IN-2606-002_1787208282.jpg',NULL,NULL,'you noob'),(6,'IN-2606-002','Annual Leave','0000-00-00',NULL,'0000-00-00',NULL,'TEST','approved','2026-08-27','2026-08-27 00:34:16','1','',NULL,NULL,NULL),(7,'IN-2606-002','Unpaid Leave','2026-09-16','08:00:00','2026-09-18','17:00:00','personal','rejected','2026-09-03','2026-09-03 07:22:01','1','',NULL,NULL,'due to high leave on the day'),(8,'IN-2606-002','Sick Leave','2026-09-22','08:00:00','2026-09-24','17:00:00','personal','approved','2026-09-03','2026-09-03 07:24:38','1','',NULL,NULL,NULL),(9,'IN-2606-002','Annual Leave','2026-09-23','08:00:00','2026-09-24','17:00:00','personal','approved','2026-09-03','2026-09-03 08:04:49','1','',NULL,NULL,NULL),(10,'IN-2606-002','Unpaid Leave','2026-09-04','08:00:00','2026-09-05','17:00:00','personal','approved','2026-09-03','2026-09-03 08:13:10','1','',NULL,NULL,NULL);
/*!40000 ALTER TABLE `leave_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lunch_breaks`
--

DROP TABLE IF EXISTS `lunch_breaks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lunch_breaks` (
  `break_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(50) DEFAULT NULL,
  `break_start` datetime DEFAULT current_timestamp(),
  `break_end` datetime DEFAULT NULL,
  `status` enum('on_break','returned') DEFAULT 'on_break',
  `category` varchar(50) DEFAULT 'Lunch',
  `is_edited` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`break_id`),
  KEY `fk_lb_emp` (`employee_id`),
  CONSTRAINT `fk_lb_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lunch_breaks`
--

LOCK TABLES `lunch_breaks` WRITE;
/*!40000 ALTER TABLE `lunch_breaks` DISABLE KEYS */;
INSERT INTO `lunch_breaks` VALUES (6,'IN-2606-002','2026-06-10 13:19:10','2026-06-10 13:19:19','returned','Lunch',0),(7,'IN-2606-002','2026-06-10 13:19:33','2026-06-10 13:20:15','returned','Lunch',0),(11,'IN-2606-002','2026-08-27 13:02:14','2026-08-27 13:02:31','returned','Lunch',0),(12,'IN-2606-002','2026-08-27 13:02:32','2026-08-27 13:02:36','returned','Lunch',0),(13,'IN-2606-002','2026-08-27 13:02:38','2026-08-27 13:02:48','returned','Lunch',0),(14,'IN-2606-002','2026-08-27 13:02:49','2026-08-27 13:03:14','returned','Lunch',0),(15,'IN-2606-002','2026-08-27 13:03:15','2026-09-02 15:54:43','returned','Lunch',0);
/*!40000 ALTER TABLE `lunch_breaks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `public_holidays`
--

DROP TABLE IF EXISTS `public_holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `public_holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `holiday_name` varchar(100) NOT NULL,
  `holiday_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_holiday` (`holiday_date`,`holiday_name`)
) ENGINE=InnoDB AUTO_INCREMENT=278 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `public_holidays`
--

LOCK TABLES `public_holidays` WRITE;
/*!40000 ALTER TABLE `public_holidays` DISABLE KEYS */;
INSERT INTO `public_holidays` VALUES (254,'New Year\'s Day','2026-01-01','2026-08-13 07:51:59'),(255,'Thaipusam','2026-02-01','2026-08-13 07:51:59'),(256,'Federal Territory Day Holiday','2026-02-02','2026-08-13 07:51:59'),(257,'Thaipusam Holiday','2026-02-03','2026-08-13 07:51:59'),(258,'Chinese New Year','2026-02-17','2026-08-13 07:51:59'),(259,'Chinese New Year','2026-02-18','2026-08-13 07:51:59'),(260,'Nuzul A1-Quran','2026-03-07','2026-08-13 07:51:59'),(261,'Pre- Hari Rava Aidilftri','2026-03-20','2026-08-13 07:51:59'),(262,'Hari Raya Aldilfitri','2026-03-21','2026-08-13 07:51:59'),(263,'Hari Raya Aldilfitri','2026-03-22','2026-08-13 07:51:59'),(264,'Post - Hari Rava Aidilfitri','2026-03-23','2026-08-13 07:51:59'),(265,'Labor Dav','2026-05-01','2026-08-13 07:51:59'),(266,'Hari Raya Haji','2026-05-27','2026-08-13 07:51:59'),(267,'Wesak Day','2026-05-31','2026-08-13 07:51:59'),(268,'Agong\'s Birthday','2026-06-01','2026-08-13 07:51:59'),(269,'Wesak Day Holiday (Carry Forward)','2026-06-02','2026-08-13 07:51:59'),(270,'Awal Muharram','2026-06-17','2026-08-13 07:51:59'),(271,'Prophet Muhammad\'s Birthday','2026-08-25','2026-08-13 07:51:59'),(272,'Malaysia National Day','2026-08-31','2026-08-13 07:51:59'),(273,'Malaysia Day','2026-09-16','2026-08-13 07:51:59'),(274,'Deepavali','2026-11-08','2026-08-13 07:51:59'),(275,'Deepavali Holiday','2026-11-09','2026-08-13 07:51:59'),(276,'Christmas','2026-12-25','2026-08-13 07:51:59');
/*!40000 ALTER TABLE `public_holidays` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shift_change_requests`
--

DROP TABLE IF EXISTS `shift_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shift_change_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(50) NOT NULL,
  `current_shift` varchar(20) NOT NULL,
  `requested_shift` varchar(20) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shift_change_requests`
--

LOCK TABLES `shift_change_requests` WRITE;
/*!40000 ALTER TABLE `shift_change_requests` DISABLE KEYS */;
INSERT INTO `shift_change_requests` VALUES (1,'IN-2606-002','09:00-18:00','08:00-17:00','approved','2026-08-27 00:33:08'),(2,'GL001','09:00-18:00','08:30-17:30','approved','2026-09-03 01:47:34'),(3,'IN-2606-002','09:00-18:00','08:00-17:00','approved','2026-09-03 02:20:06'),(4,'IN-2606-002','08:00-17:00','08:30-17:30','pending','2026-09-03 08:07:22');
/*!40000 ALTER TABLE `shift_change_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `short_breaks`
--

DROP TABLE IF EXISTS `short_breaks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `short_breaks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(50) NOT NULL,
  `period` enum('pre_lunch','post_lunch') NOT NULL,
  `break_start` datetime DEFAULT current_timestamp(),
  `break_end` datetime DEFAULT NULL,
  `duration_min` int(11) DEFAULT NULL,
  `status` enum('on_break','returned') DEFAULT 'on_break',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category` varchar(50) DEFAULT 'Short Break',
  `is_edited` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `short_breaks`
--

LOCK TABLES `short_breaks` WRITE;
/*!40000 ALTER TABLE `short_breaks` DISABLE KEYS */;
INSERT INTO `short_breaks` VALUES (1,'FT-2606-001','post_lunch','2026-06-09 12:00:00','2026-06-09 12:30:00',30,'returned','2026-06-09 02:33:18','Short Break',1),(2,'FT-2606-001','post_lunch','2026-06-09 10:33:22','2026-06-09 10:33:22',1,'returned','2026-06-09 02:33:22','Short Break',0),(3,'FT-2606-001','post_lunch','2026-06-09 10:33:23','2026-06-09 10:33:24',1,'returned','2026-06-09 02:33:23','Short Break',0),(4,'FT-2606-001','post_lunch','2026-06-09 10:33:24','2026-06-09 10:33:25',1,'returned','2026-06-09 02:33:24','Short Break',0),(5,'FT-2606-001','post_lunch','2026-06-09 10:33:25','2026-06-09 10:33:26',1,'returned','2026-06-09 02:33:25','Short Break',0),(6,'FT-2606-001','post_lunch','2026-06-09 10:33:26','2026-06-09 10:33:27',1,'returned','2026-06-09 02:33:26','Short Break',0),(7,'FT-2606-001','post_lunch','2026-06-09 10:35:43','2026-06-09 10:35:46',0,'returned','2026-06-09 02:35:43','Short Break',0),(8,'FT-2606-001','post_lunch','2026-06-09 10:35:47','2026-06-09 10:35:47',0,'returned','2026-06-09 02:35:47','Short Break',0),(9,'FT-2606-001','post_lunch','2026-06-09 10:35:48','2026-06-09 10:35:48',0,'returned','2026-06-09 02:35:48','Short Break',0),(10,'FT-2606-001','post_lunch','2026-06-09 10:35:48','2026-06-09 10:35:49',0,'returned','2026-06-09 02:35:48','Short Break',0),(11,'FT-2606-001','post_lunch','2026-06-09 10:38:03','2026-06-09 10:38:03',0,'returned','2026-06-09 02:38:03','Short Break',0),(12,'FT-2606-001','post_lunch','2026-06-09 10:39:53','2026-06-09 10:39:54',0,'returned','2026-06-09 02:39:53','Short Break',0),(13,'FT-2606-001','post_lunch','2026-06-09 10:39:55','2026-06-09 10:39:57',0,'returned','2026-06-09 02:39:55','Short Break',0),(14,'FT-2606-001','post_lunch','2026-06-09 10:43:51','2026-06-09 10:43:52',0,'returned','2026-06-09 02:43:51','Short Break',0),(15,'FT-2606-001','post_lunch','2026-06-09 10:43:53','2026-06-09 10:43:54',0,'returned','2026-06-09 02:43:53','Short Break',0),(16,'FT-2606-001','post_lunch','2026-06-09 10:43:54','2026-06-09 10:43:55',0,'returned','2026-06-09 02:43:54','Short Break',0),(17,'FT-2606-001','post_lunch','2026-06-09 10:43:55','2026-06-09 10:43:56',0,'returned','2026-06-09 02:43:55','Short Break',0),(18,'FT-2606-001','post_lunch','2026-06-09 10:43:56','2026-06-09 10:43:57',0,'returned','2026-06-09 02:43:56','Short Break',0),(19,'FT-2606-001','post_lunch','2026-06-09 13:48:04','2026-06-10 14:53:47',1505,'returned','2026-06-09 05:48:04','Short Break',0),(20,'IN-2606-001','pre_lunch','2026-06-10 13:10:37','2026-06-10 13:10:41',0,'returned','2026-06-10 05:10:37','Short Break',0),(21,'IN-2606-001','pre_lunch','2026-06-10 13:13:23','2026-06-10 13:13:30',0,'returned','2026-06-10 05:13:23','Short Break',0),(22,'FT-2606-002','pre_lunch','2026-06-10 13:14:41','2026-06-10 13:14:52',0,'returned','2026-06-10 05:14:41','Short Break',0),(23,'FT-2606-002','post_lunch','2026-06-10 13:20:48','2026-06-10 13:22:01',1,'returned','2026-06-10 05:20:48','Short Break',0),(24,'IN-2606-002','','2026-06-10 13:37:33','2026-06-10 13:37:35',0,'returned','2026-06-10 05:37:33','Short Break',0),(25,'IN-2606-001','','2026-06-10 15:09:53','2026-09-02 15:54:41',NULL,'returned','2026-06-10 07:09:53','Short Break',0),(26,'FT-2606-001','','2026-06-10 15:43:06','2026-06-10 15:43:37',0,'returned','2026-06-10 07:43:06','Short Break',0),(27,'FT-2606-001','','2026-06-10 15:43:38','2026-06-10 15:43:59',0,'returned','2026-06-10 07:43:38','Short Break',0),(28,'IN-2606-002','','2026-08-27 08:35:52','2026-08-27 08:35:53',0,'returned','2026-08-27 00:35:52','Short Break',0),(29,'IN-2606-002','','2026-08-27 08:35:54','2026-08-27 08:35:55',0,'returned','2026-08-27 00:35:54','Short Break',0),(30,'IN-2606-002','','2026-08-27 08:35:56','2026-08-27 08:36:30',0,'returned','2026-08-27 00:35:56','Short Break',0),(31,'IN-2606-002','','2026-08-27 08:36:31','2026-08-27 08:36:33',0,'returned','2026-08-27 00:36:31','Short Break',0),(32,'IN-2606-002','','2026-08-27 08:36:34','2026-08-27 08:36:35',0,'returned','2026-08-27 00:36:34','Short Break',0),(33,'IN-2606-002','','2026-08-27 08:36:36','2026-08-27 08:36:38',0,'returned','2026-08-27 00:36:36','Short Break',0),(34,'IN-2606-002','','2026-08-27 08:37:32','2026-08-27 08:37:33',0,'returned','2026-08-27 00:37:32','Short Break',0),(35,'IN-2606-002','','2026-08-27 08:37:33','2026-08-27 08:37:36',0,'returned','2026-08-27 00:37:33','Short Break',0),(36,'IN-2606-002','','2026-08-27 08:37:45','2026-08-27 08:37:48',0,'returned','2026-08-27 00:37:45','Short Break',0),(37,'IN-2606-002','','2026-08-27 10:55:09','2026-08-27 11:13:46',18,'returned','2026-08-27 02:55:09','Short Break',0),(38,'IN-2606-002','','2026-08-27 11:13:48','2026-08-27 11:13:55',0,'returned','2026-08-27 03:13:48','Short Break',0),(39,'IN-2606-002','','2026-08-27 13:04:28','2026-08-27 13:04:32',0,'returned','2026-08-27 05:04:28','Short Break',0),(40,'IN-2606-002','','2026-08-27 13:04:35','2026-08-27 13:04:38',0,'returned','2026-08-27 05:04:35','Short Break',0),(41,'GL002','','2026-08-28 15:30:32','2026-08-28 15:30:35',0,'returned','2026-08-28 07:30:32','Short Break',0),(42,'IN-2606-002','','2026-08-28 16:00:50','2026-08-28 16:00:52',0,'returned','2026-08-28 08:00:50','Short Break',0),(43,'GL001','pre_lunch','2026-09-03 08:52:18','2026-09-03 08:52:20',0,'returned','2026-09-03 00:52:18','Short Break',0),(44,'GL001','pre_lunch','2026-09-03 08:52:20','2026-09-03 08:52:22',0,'returned','2026-09-03 00:52:20','Short Break',0),(45,'GL001','pre_lunch','2026-09-03 08:54:27','2026-09-03 08:54:33',0,'returned','2026-09-03 00:54:27','Short Break',0),(46,'GL001','pre_lunch','2026-09-03 08:56:11','2026-09-03 08:56:13',0,'returned','2026-09-03 00:56:11','Short Break',0),(47,'GL001','pre_lunch','2026-09-03 08:56:14','2026-09-03 08:56:15',0,'returned','2026-09-03 00:56:14','Short Break',0),(48,'GL001','pre_lunch','2026-09-03 08:56:16','2026-09-03 08:56:27',0,'returned','2026-09-03 00:56:16','Short Break',0),(49,'GL001','pre_lunch','2026-09-03 09:13:27','2026-09-03 09:13:31',0,'returned','2026-09-03 01:13:27','Short Break',0),(50,'GL001','pre_lunch','2026-09-03 09:14:56','2026-09-03 09:14:58',0,'returned','2026-09-03 01:14:56','Short Break',0),(51,'FT-2606-003','pre_lunch','2026-09-03 09:22:55','2026-09-03 09:23:07',0,'returned','2026-09-03 01:22:55','Short Break',0),(52,'GL001','pre_lunch','2026-09-03 09:46:53','2026-09-03 09:46:58',0,'returned','2026-09-03 01:46:53','Short Break',0),(53,'GL001','pre_lunch','2026-09-03 14:29:23','2026-09-03 14:29:25',0,'returned','2026-09-03 06:29:23','Short Break',0),(54,'IN-2606-002','pre_lunch','2026-09-03 15:04:20','2026-09-03 15:06:16',1,'returned','2026-09-03 07:04:20','Short Break',0),(55,'IN-2606-002','pre_lunch','2026-09-03 15:06:17','2026-09-03 15:06:21',0,'returned','2026-09-03 07:06:17','Short Break',0),(56,'IN-2606-002','pre_lunch','2026-09-03 15:10:51','2026-09-03 15:13:18',2,'returned','2026-09-03 07:10:51','Short Break',0),(57,'IN-2606-002','pre_lunch','2026-09-03 16:08:31','2026-09-03 16:08:34',0,'returned','2026-09-03 08:08:31','Short Break',0),(58,'IN-2606-002','pre_lunch','2026-09-03 16:09:42','2026-09-03 16:10:16',NULL,'returned','2026-09-03 08:09:42','Short Break',0);
/*!40000 ALTER TABLE `short_breaks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suggestions`
--

DROP TABLE IF EXISTS `suggestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `suggestion_text` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `suggestions_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suggestions`
--

LOCK TABLES `suggestions` WRITE;
/*!40000 ALTER TABLE `suggestions` DISABLE KEYS */;
/*!40000 ALTER TABLE `suggestions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_access_logs`
--

DROP TABLE IF EXISTS `system_access_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_access_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_emp_action` (`emp_id`,`action_type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_access_logs`
--

LOCK TABLES `system_access_logs` WRITE;
/*!40000 ALTER TABLE `system_access_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_access_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_alerts`
--

DROP TABLE IF EXISTS `system_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_alerts`
--

LOCK TABLES `system_alerts` WRITE;
/*!40000 ALTER TABLE `system_alerts` DISABLE KEYS */;
INSERT INTO `system_alerts` VALUES (1,'Employee FT-2606-001 has exceeded their lunch limit (0 minutes).','2026-06-09 10:30:57',1),(2,'Employee FT-2606-001 has exceeded their lunch limit (0 minutes).','2026-06-09 10:31:26',1);
/*!40000 ALTER TABLE `system_alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES ('lark_app_id','cli_aaab93d70e79de17'),('lark_app_secret','YOUR_LARK_APP_SECRET'),('sandbox_time','0'),('sandbox_wifi','0');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-04  8:58:09

-- ==========================================
-- CUSTOM PATCHES APPLIED BY ANTIGRAVITY
-- ==========================================

-- 1. Fix Leave Request Status Enum & Employee Leave Balance Default (from fix.php)
ALTER TABLE `leave_requests` MODIFY COLUMN `status` ENUM('pending', 'pending_hr', 'approved', 'rejected') DEFAULT 'pending';
ALTER TABLE `employees` ALTER COLUMN `annual_leave_balance` SET DEFAULT 8;

-- 2. Rename 'Early Leave' to 'Emergency Leave' (from db_update.php)
UPDATE `leave_requests` SET `leave_type` = 'Emergency Leave' WHERE `leave_type` = 'Early Leave';
-- Note: checkout_status is NOT a database column (it is computed in PHP). Removing this invalid query.
-- UPDATE `attendance_logs` SET `checkout_status` = 'Emergency Leave' WHERE `checkout_status` = 'Early Leave';

-- 3. Reset Interns Leave Balances to 0 (from update_interns_leave.php)
UPDATE `employees` 
SET `annual_leave_quota` = 0, 
    `annual_leave_balance` = 0, 
    `sick_leave_quota` = 0, 
    `sick_leave_balance` = 0 
WHERE `employment_type` LIKE 'Intern%';
