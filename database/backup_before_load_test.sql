/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.2-MariaDB, for osx10.19 (x86_64)
--
-- Host: localhost    Database: WebsiteTailors
-- ------------------------------------------------------
-- Server version	11.8.2-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('superadmin','admin') NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admins_username` (`username`),
  UNIQUE KEY `uk_admins_email` (`email`),
  KEY `idx_admins_status` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `admins` VALUES
(1,'admin','websietailorss@gmail.com','$2y$12$ht.4P0FXjJ7ev7MsltCeSeQpBGGj/is8I.XzZ/WMTbz2NTau8dX4y','Website Tailors Administrator','superadmin',1,'2026-10-11 11:27:56','2026-10-11 11:14:10','2026-10-11 16:57:56'),
(2,'superadmin','superadmin@websitetailors.com','$2y$12$ht.4P0FXjJ7ev7MsltCeSeQpBGGj/is8I.XzZ/WMTbz2NTau8dX4y','WebsiteTailors Administrator','superadmin',1,'2026-10-11 11:25:30','2026-10-11 10:57:13','2026-10-11 16:55:30');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `calls`
--

DROP TABLE IF EXISTS `calls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `calls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` bigint(20) unsigned DEFAULT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `contact_name` varchar(150) NOT NULL,
  `company` varchar(150) DEFAULT NULL,
  `phone` varchar(50) NOT NULL,
  `call_datetime` datetime DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'discovery',
  `status` varchar(50) NOT NULL DEFAULT 'scheduled',
  `scheduled_at` datetime NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 0,
  `priority` varchar(20) NOT NULL DEFAULT 'normal',
  `notes` text DEFAULT NULL,
  `outcome` text DEFAULT NULL,
  `next_followup_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_calls_lead_id` (`lead_id`),
  KEY `idx_calls_client_id` (`client_id`),
  KEY `idx_calls_scheduled` (`scheduled_at`),
  KEY `idx_calls_datetime` (`call_datetime`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calls`
--

LOCK TABLES `calls` WRITE;
/*!40000 ALTER TABLE `calls` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `calls` VALUES
(1,3,NULL,'Vikram Mehta 6acb2259ed955','Fintech Growth Labs Private Ltd','+91 99887 76655','2026-10-11 04:44:57','outbound','completed','2026-10-11 04:44:57',18,'normal','Client approved project statement of work.','Converted','2026-10-12 11:00:00','2026-10-11 05:44:57');
/*!40000 ALTER TABLE `calls` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_name` varchar(150) NOT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `alternate_phone` varchar(50) DEFAULT NULL,
  `service` varchar(100) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `status` enum('New','Active','Inactive','Completed') NOT NULL DEFAULT 'New',
  `assigned_to` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_clients_status` (`status`),
  KEY `idx_clients_service` (`service`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `clients` VALUES
(2,'Apex Solutions 6acb2259ee892','Apex Solutions 6acb2259ee892','accounts@apexsolutions.com','+91 91234 56789',NULL,'Website',NULL,'Active',NULL,'Contract client','2026-10-11 05:44:57','2026-10-11 05:44:57');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `crm_call_logs`
--

DROP TABLE IF EXISTS `crm_call_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_call_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` bigint(20) unsigned DEFAULT NULL,
  `agent_phone` varchar(50) NOT NULL,
  `client_phone` varchar(50) NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'exotel',
  `call_sid` varchar(100) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'initiated',
  `duration` int(11) NOT NULL DEFAULT 0,
  `recording_url` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ccl_lead` (`lead_id`),
  KEY `idx_ccl_sid` (`call_sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_call_logs`
--

LOCK TABLES `crm_call_logs` WRITE;
/*!40000 ALTER TABLE `crm_call_logs` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `crm_call_logs` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `hero_content`
--

DROP TABLE IF EXISTS `hero_content`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hero_content` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `badge_text` varchar(100) NOT NULL DEFAULT '✦ HIGH-IMPACT DIGITAL SERVICES',
  `headline` varchar(255) NOT NULL DEFAULT 'We Make Digital Things Work.',
  `subheadline` varchar(255) NOT NULL DEFAULT 'Digital Architecture, Software Engineering & Brand Platforms',
  `description` text NOT NULL,
  `primary_button_text` varchar(100) NOT NULL DEFAULT 'Explore Our Work',
  `primary_button_link` varchar(255) NOT NULL DEFAULT '#work',
  `secondary_button_text` varchar(100) NOT NULL DEFAULT 'Start a Project',
  `secondary_button_link` varchar(255) NOT NULL DEFAULT '#contact',
  `stats_json` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hero_content`
--

LOCK TABLES `hero_content` WRITE;
/*!40000 ALTER TABLE `hero_content` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `hero_content` VALUES
(1,'Digital studio / 2026','WE BUILD\nDIGITAL\nPRODUCTS\nTHAT SCALE.','We turn ideas into websites, software and digital experiences that actually work.','From the first sketch to the final launch, Website Tailors designs and builds digital products around the way your business actually works.','Start a Project','#contact','Explore Services','#services','[{\"label\":\"Client Satisfaction\",\"value\":\"99.4%\"},{\"label\":\"Projects Shipped\",\"value\":\"150+\"},{\"label\":\"Avg Performance\",\"value\":\"98/100\"},{\"label\":\"System Reliability\",\"value\":\"99.9%\"}]','2026-10-11 16:57:36');
/*!40000 ALTER TABLE `hero_content` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_type` varchar(50) NOT NULL DEFAULT 'advance',
  `client_id` int(10) unsigned DEFAULT NULL,
  `client_name` varchar(150) NOT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `project_name` varchar(150) DEFAULT NULL,
  `project_phase` varchar(100) DEFAULT NULL,
  `service` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `project_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `advance_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_received` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` varchar(50) NOT NULL DEFAULT 'paid',
  `due_date` date NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `payment_mode` varchar(50) DEFAULT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `payment_method_desc` varchar(150) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `line_items` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `client_billing_address` text DEFAULT NULL,
  `office_address` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_invoices_client` (`client_id`),
  KEY `idx_invoices_project` (`project_id`),
  KEY `idx_invoices_type` (`invoice_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `leads`
--

DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `leads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company` varchar(100) DEFAULT NULL,
  `service` varchar(100) DEFAULT NULL,
  `service_interested` varchar(100) DEFAULT NULL,
  `budget` varchar(50) DEFAULT NULL,
  `message` text NOT NULL,
  `source` varchar(50) DEFAULT 'Website Form',
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'New',
  `call_status` varchar(50) NOT NULL DEFAULT 'Not Called',
  `last_called_at` datetime DEFAULT NULL,
  `next_followup_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leads_status_created` (`status`,`created_at`),
  KEY `idx_leads_call_status` (`call_status`),
  KEY `idx_leads_client_id` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leads`
--

LOCK TABLES `leads` WRITE;
/*!40000 ALTER TABLE `leads` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `leads` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(100) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_successful` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_ip_time` (`ip_address`,`attempted_at`),
  KEY `idx_attempts_user_time` (`username`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `login_attempts` VALUES
(1,'127.0.0.1','superadmin@websitetailors.com','2026-10-11 05:42:06',1),
(2,'127.0.0.1','superadmin','2026-10-11 05:42:17',1),
(3,'127.0.0.1','superadmin@websitetailors.com','2026-10-11 05:43:56',1),
(8,'127.0.0.1','websietailorss@gmail.com','2026-10-11 05:44:46',1),
(9,'127.0.0.1','websietailorss@gmail.com','2026-10-11 05:44:46',1),
(10,'127.0.0.1','superadmin@websitetailors.com','2026-10-11 05:47:23',1),
(11,'127.0.0.1','superadmin@websitetailors.com','2026-10-11 11:25:30',1),
(13,'127.0.0.1','websietailorss@gmail.com','2026-10-11 11:27:56',1),
(14,'127.0.0.1','websietailorss@gmail.com','2026-10-11 11:27:56',1);
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `process_steps`
--

DROP TABLE IF EXISTS `process_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `process_steps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `step_number` varchar(10) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('published','draft') NOT NULL DEFAULT 'published',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_process_status_order` (`status`,`display_order`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `process_steps`
--

LOCK TABLES `process_steps` WRITE;
/*!40000 ALTER TABLE `process_steps` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `process_steps` VALUES
(1,'01','Tell us.','Tell us what you\'re trying to build, fix or improve.',1,'published','2026-10-11 10:57:13','2026-10-11 16:57:36'),
(2,'02','We plan.','We define the experience, technology and scope.',2,'published','2026-10-11 10:57:13','2026-10-11 16:57:36'),
(3,'03','We build.','Design, development and testing happen together.',3,'published','2026-10-11 10:57:13','2026-10-11 16:57:36'),
(4,'04','You launch.','Your product goes live and starts doing its job.',4,'published','2026-10-11 10:57:13','2026-10-11 16:57:36');
/*!40000 ALTER TABLE `process_steps` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `category` varchar(100) NOT NULL,
  `client_name` varchar(100) DEFAULT NULL,
  `description` text NOT NULL,
  `image` varchar(255) NOT NULL,
  `project_url` varchar(255) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('published','draft') NOT NULL DEFAULT 'published',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_projects_slug` (`slug`),
  KEY `idx_projects_client_id` (`client_id`),
  KEY `idx_projects_status_featured_order` (`status`,`is_featured`,`display_order`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `projects` VALUES
(1,NULL,'Apex Logistics Portal','apex-logistics-portal','Website','Apex Global Freight','A real-time dispatch and fleet management dashboard handling thousands of daily freight consignments with sub-second response times.','/assets/images/projects/apex-portal.webp','https://example.com/apex','PHP 8, MySQL, Custom Dashboard, Vanilla JS',1,1,'published','2026-10-11 10:57:13','2026-10-11 16:57:36'),
(2,NULL,'Kroma Design Studio','kroma-design-studio','Software','Kroma Creative','An immersive, award-winning agency portfolio showcasing typography excellence, dynamic dark-mode interactions, and smooth transitions.','/assets/images/projects/kroma-studio.webp','https://example.com/kroma','Vanilla CSS, Animation, Semantic HTML5',2,1,'published','2026-10-11 10:57:13','2026-10-11 16:57:36'),
(3,NULL,'Veloce E-Commerce Engine','veloce-ecommerce-engine','AI + Automation','Veloce Luxury Wear','Custom lightweight e-commerce storefront with instantaneous product filtering, zero framework bloat, and frictionless checkout.','/assets/images/projects/veloce-engine.webp','https://example.com/veloce','Custom Cart, Payment APIs, SEO Optimized',3,1,'published','2026-10-11 10:57:13','2026-10-11 16:57:36'),
(4,NULL,'OmniFlow Workflow Automation','omniflow-workflow-automation','Digital System','OmniFlow Tech','Internal operational engine synchronizing CRM data, automated invoicing, and multi-tier employee approval pipelines.','/assets/images/projects/omniflow.webp','https://example.com/omniflow','REST APIs, Background Workers, Role Security',4,0,'published','2026-10-11 10:57:13','2026-10-11 16:57:36');
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `revenue`
--

DROP TABLE IF EXISTS `revenue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `revenue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned DEFAULT NULL,
  `client_name` varchar(150) DEFAULT NULL,
  `lead_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `project_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_type` enum('UPI','Bank Transfer','Cash','Card','Other') NOT NULL DEFAULT 'Bank Transfer',
  `payment_status` enum('Pending','Partially Paid','Paid','Refunded') NOT NULL DEFAULT 'Paid',
  `payment_date` datetime NOT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `service` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rev_client` (`client_id`),
  KEY `idx_rev_project` (`project_id`),
  KEY `idx_rev_status` (`payment_status`),
  KEY `idx_rev_date` (`payment_date`),
  KEY `idx_rev_service` (`service`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `revenue`
--

LOCK TABLES `revenue` WRITE;
/*!40000 ALTER TABLE `revenue` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `revenue` VALUES
(1,2,NULL,3,NULL,NULL,55000.00,'UPI','Paid','2026-10-11 05:44:57',NULL,NULL,'Website','Adjusted for additional responsive revisions','2026-10-11 05:44:57','2026-10-11 05:44:57');
/*!40000 ALTER TABLE `revenue` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `short_description` varchar(300) NOT NULL,
  `long_description` text DEFAULT NULL,
  `icon` varchar(100) NOT NULL DEFAULT 'code',
  `features` text DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('published','draft') NOT NULL DEFAULT 'published',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_services_slug` (`slug`),
  KEY `idx_services_status_order` (`status`,`display_order`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `services` VALUES
(1,'Website Development','website-development','High-performing business websites and landing pages designed to look professional, load fast and turn visitors into customers.','High-performing business websites and landing pages designed to look professional, load fast and turn visitors into customers.','code','Bespoke Design & Development\nResponsive Across Devices\nFast Load Speeds\nBuilt for Business Conversion',1,'published','2026-10-11 10:57:13','2026-10-11 16:57:36'),
(2,'Website Refinement','website-refinement','Already have a website? We refine its design, UX, responsiveness and performance to WebsiteTailors cleaner, faster and easier to use.','Already have a website? We refine its design, UX, responsiveness and performance to WebsiteTailors cleaner, faster and easier to use.','layout','UI/UX & Visual Refinement\nPerformance & Speed Optimization\nMobile Responsiveness Fixes\nNavigation & Content Structure',2,'published','2026-10-11 10:57:13','2026-10-11 16:57:36'),
(3,'WhatsApp Automation','whatsapp-automation','Automate enquiries, follow-ups, notifications and repetitive customer workflows through WhatsApp.','Automate enquiries, follow-ups, notifications and repetitive customer workflows through WhatsApp.','message-square','Instant Enquiry Responses\nAutomated Follow-up Sequences\nOrder & Status Notifications\nCustom Workflow Integration',3,'published','2026-10-11 10:57:13','2026-10-11 16:57:36');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(50) NOT NULL DEFAULT 'general',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`setting_key`),
  KEY `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `site_settings` VALUES
(1,'company_name','Website Tailors','general','2026-10-11 10:57:13','2026-10-11 10:57:13'),
(2,'tagline','Engineering Digital Precision.','general','2026-10-11 10:57:13','2026-10-11 11:27:56'),
(3,'email','websietailorss@gmail.com','contact','2026-10-11 10:57:13','2026-10-11 10:57:13'),
(4,'phone','9380552034','contact','2026-10-11 10:57:13','2026-10-11 10:57:13'),
(5,'address','Bangalore-560010, karnataka. India','contact','2026-10-11 10:57:13','2026-10-11 10:57:13'),
(6,'linkedin','https://linkedin.com/company/websitetailors','social','2026-10-11 10:57:13','2026-10-11 10:57:13'),
(7,'instagram','https://instagram.com/websitetailors','social','2026-10-11 10:57:13','2026-10-11 10:57:13'),
(8,'facebook','https://facebook.com/websitetailors','social','2026-10-11 10:57:13','2026-10-11 10:57:13'),
(9,'github','https://github.com/websitetailors','social','2026-10-11 10:57:13','2026-10-11 10:57:13'),
(10,'twitter','https://x.com/websitetailors','social','2026-10-11 10:57:13','2026-10-11 10:57:13'),
(11,'logo','/assets/images/logo.svg','branding','2026-10-11 10:57:13','2026-10-11 10:57:13'),
(12,'favicon','/assets/images/favicon.svg','branding','2026-10-11 10:57:13','2026-10-11 10:57:13'),
(13,'meta_title','Website Tailors — Engineering Digital Precision','seo','2026-10-11 10:57:13','2026-10-11 10:57:13'),
(14,'meta_description','Website Tailors designs and builds websites, software, AI automation and digital experiences that actually work.','seo','2026-10-11 10:57:13','2026-10-11 10:57:13'),
(15,'primary_color','#b8ff3d','branding','2026-10-11 10:57:13','2026-10-11 10:57:13'),
(16,'announcement_text','Now booking client projects for Q3/Q4','general','2026-10-11 10:57:13','2026-10-11 10:57:13');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `testimonials`
--

DROP TABLE IF EXISTS `testimonials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `testimonials` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_name` varchar(100) NOT NULL,
  `company` varchar(100) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `content` text NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `image` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('published','draft') NOT NULL DEFAULT 'published',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_testimonials_status_order` (`status`,`display_order`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `testimonials`
--

LOCK TABLES `testimonials` WRITE;
/*!40000 ALTER TABLE `testimonials` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `testimonials` VALUES
(1,'Rajesh Menon','Apex Logistics India','Chief Technology Officer','Website Tailors transformed our dispatch platform from a sluggish legacy headache into a blisteringly fast powerhouse. The speed and clarity of their engineering is unparalleled.',5,'/assets/images/testimonials/rajesh.webp',1,'published','2026-10-11 10:57:13','2026-10-11 16:57:36'),
(2,'Ananya Sen','Kroma Design Studio','Founder & Creative Director','Working with Website Tailors was seamless. They understood both the delicate aesthetic nuances of our brand and the strict architectural requirements under the hood.',5,'/assets/images/testimonials/ananya.webp',2,'published','2026-10-11 10:57:13','2026-10-11 16:57:36'),
(3,'Rohan Singhania','Veloce E-Commerce','Managing Director','Our online store conversion jumped by 34% within the first month after Website Tailors rebuilt our checkout flow. Zero framework bloat, lightning speed, and total reliability.',5,'/assets/images/testimonials/rohan.webp',3,'published','2026-10-11 10:57:13','2026-10-11 16:57:36');
/*!40000 ALTER TABLE `testimonials` ENABLE KEYS */;
UNLOCK TABLES;
commit;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-10-11 17:03:29
