-- ==============================================================================
-- WebsiteTailors - Production Database Schema & Seed Data
-- Database: WebsiteTailors
-- Compatible with MySQL 8.0+ and MariaDB 10.4+
-- Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- Table structure for: admins
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('superadmin', 'admin') NOT NULL DEFAULT 'admin',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admins_username` (`username`),
  UNIQUE KEY `uk_admins_email` (`email`),
  KEY `idx_admins_status` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table structure for: login_attempts (Rate limiting & brute force mitigation)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_successful` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_ip_time` (`ip_address`, `attempted_at`),
  KEY `idx_attempts_user_time` (`username`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table structure for: hero_content
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `hero_content`;
CREATE TABLE `hero_content` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `badge_text` VARCHAR(100) NOT NULL DEFAULT '✦ HIGH-IMPACT DIGITAL SERVICES',
  `headline` VARCHAR(255) NOT NULL DEFAULT 'We Make Digital Things Work.',
  `subheadline` VARCHAR(255) NOT NULL DEFAULT 'Digital Architecture, Software Engineering & Brand Platforms',
  `description` TEXT NOT NULL,
  `primary_button_text` VARCHAR(100) NOT NULL DEFAULT 'Explore Our Work',
  `primary_button_link` VARCHAR(255) NOT NULL DEFAULT '#work',
  `secondary_button_text` VARCHAR(100) NOT NULL DEFAULT 'Start a Project',
  `secondary_button_link` VARCHAR(255) NOT NULL DEFAULT '#contact',
  `stats_json` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table structure for: services
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `short_description` VARCHAR(300) NOT NULL,
  `long_description` TEXT NULL,
  `icon` VARCHAR(100) NOT NULL DEFAULT 'code',
  `features` TEXT NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('published', 'draft') NOT NULL DEFAULT 'published',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_services_slug` (`slug`),
  KEY `idx_services_status_order` (`status`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table structure for: projects
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` INT UNSIGNED NULL DEFAULT NULL,
  `title` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `client_name` VARCHAR(100) NULL,
  `description` TEXT NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `project_url` VARCHAR(255) NULL,
  `tags` VARCHAR(255) NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('published', 'draft') NOT NULL DEFAULT 'published',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_projects_slug` (`slug`),
  KEY `idx_projects_client_id` (`client_id`),
  KEY `idx_projects_status_featured_order` (`status`, `is_featured`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table structure for: process_steps
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `process_steps`;
CREATE TABLE `process_steps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `step_number` VARCHAR(10) NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('published', 'draft') NOT NULL DEFAULT 'published',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_process_status_order` (`status`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table structure for: testimonials
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `testimonials`;
CREATE TABLE `testimonials` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_name` VARCHAR(100) NOT NULL,
  `company` VARCHAR(100) NOT NULL,
  `position` VARCHAR(100) NULL,
  `content` TEXT NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `image` VARCHAR(255) NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('published', 'draft') NOT NULL DEFAULT 'published',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_testimonials_status_order` (`status`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table structure for: leads (Contact inquiries)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `leads`;
CREATE TABLE `leads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` INT UNSIGNED NULL DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) NULL DEFAULT NULL,
  `company` VARCHAR(100) NULL DEFAULT NULL,
  `service` VARCHAR(100) NULL DEFAULT NULL,
  `service_interested` VARCHAR(100) NULL DEFAULT NULL,
  `budget` VARCHAR(50) NULL DEFAULT NULL,
  `message` TEXT NOT NULL,
  `source` VARCHAR(50) NULL DEFAULT 'Website Form',
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'New',
  `call_status` VARCHAR(50) NOT NULL DEFAULT 'Not Called',
  `last_called_at` DATETIME NULL DEFAULT NULL,
  `next_followup_at` DATETIME NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leads_status_created` (`status`, `created_at`),
  KEY `idx_leads_call_status` (`call_status`),
  KEY `idx_leads_client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Table structure for: site_settings (Key-Value Store)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `site_settings`;
CREATE TABLE `site_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`setting_key`),
  KEY `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- SEED DATA
-- ==============================================================================

-- Seed Site Settings
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('company_name', 'Website Tailors', 'general'),
('tagline', 'Engineering Digital Precision.', 'general'),
('email', 'websietailorss@gmail.com', 'contact'),
('phone', '9380552034', 'contact'),
('address', 'Bangalore-560010, karnataka. India', 'contact'),
('linkedin', 'https://linkedin.com/company/websitetailors', 'social'),
('instagram', 'https://instagram.com/websitetailors', 'social'),
('facebook', 'https://facebook.com/websitetailors', 'social'),
('github', 'https://github.com/websitetailors', 'social'),
('twitter', 'https://x.com/websitetailors', 'social'),
('logo', '/assets/images/logo.svg', 'branding'),
('favicon', '/assets/images/favicon.svg', 'branding'),
('meta_title', 'Website Tailors — Engineering Digital Precision', 'seo'),
('meta_description', 'Website Tailors designs and builds websites, software, AI automation and digital experiences that actually work.', 'seo'),
('primary_color', '#b8ff3d', 'branding'),
('announcement_text', 'Now booking client projects for Q3/Q4', 'general');

-- Seed Hero Content
INSERT INTO `hero_content` (`id`, `badge_text`, `headline`, `subheadline`, `description`, `primary_button_text`, `primary_button_link`, `secondary_button_text`, `secondary_button_link`, `stats_json`) VALUES
(1,
 'Digital studio / 2026',
 'WE BUILD\nDIGITAL\nPRODUCTS\nTHAT SCALE.',
 'We turn ideas into websites, software and digital experiences that actually work.',
 'From the first sketch to the final launch, Website Tailors designs and builds digital products around the way your business actually works.',
 'Start a Project',
 '#contact',
 'Explore Services',
 '#services',
 '[{"label":"Client Satisfaction","value":"99.4%"},{"label":"Projects Shipped","value":"150+"},{"label":"Avg Performance","value":"98/100"},{"label":"System Reliability","value":"99.9%"}]'
);

-- Seed Services
INSERT INTO `services` (`title`, `slug`, `short_description`, `long_description`, `icon`, `features`, `display_order`, `status`) VALUES
('Website Development', 'website-development', 'High-performing business websites and landing pages designed to look professional, load fast and turn visitors into customers.', 'High-performing business websites and landing pages designed to look professional, load fast and turn visitors into customers.', 'code', 'Bespoke Design & Development\nResponsive Across Devices\nFast Load Speeds\nBuilt for Business Conversion', 1, 'published'),
('Website Refinement', 'website-refinement', 'Already have a website? We refine its design, UX, responsiveness and performance to WebsiteTailors cleaner, faster and easier to use.', 'Already have a website? We refine its design, UX, responsiveness and performance to WebsiteTailors cleaner, faster and easier to use.', 'layout', 'UI/UX & Visual Refinement\nPerformance & Speed Optimization\nMobile Responsiveness Fixes\nNavigation & Content Structure', 2, 'published'),
('WhatsApp Automation', 'whatsapp-automation', 'Automate enquiries, follow-ups, notifications and repetitive customer workflows through WhatsApp.', 'Automate enquiries, follow-ups, notifications and repetitive customer workflows through WhatsApp.', 'message-square', 'Instant Enquiry Responses\nAutomated Follow-up Sequences\nOrder & Status Notifications\nCustom Workflow Integration', 3, 'published');

-- Seed Projects
INSERT INTO `projects` (`title`, `slug`, `category`, `client_name`, `description`, `image`, `project_url`, `tags`, `display_order`, `is_featured`, `status`) VALUES
('Apex Logistics Portal', 'apex-logistics-portal', 'Website', 'Apex Global Freight', 'A real-time dispatch and fleet management dashboard handling thousands of daily freight consignments with sub-second response times.', '/assets/images/projects/apex-portal.webp', 'https://example.com/apex', 'PHP 8, MySQL, Custom Dashboard, Vanilla JS', 1, 1, 'published'),
('Kroma Design Studio', 'kroma-design-studio', 'Software', 'Kroma Creative', 'An immersive, award-winning agency portfolio showcasing typography excellence, dynamic dark-mode interactions, and smooth transitions.', '/assets/images/projects/kroma-studio.webp', 'https://example.com/kroma', 'Vanilla CSS, Animation, Semantic HTML5', 2, 1, 'published'),
('Veloce E-Commerce Engine', 'veloce-ecommerce-engine', 'AI + Automation', 'Veloce Luxury Wear', 'Custom lightweight e-commerce storefront with instantaneous product filtering, zero framework bloat, and frictionless checkout.', '/assets/images/projects/veloce-engine.webp', 'https://example.com/veloce', 'Custom Cart, Payment APIs, SEO Optimized', 3, 1, 'published'),
('OmniFlow Workflow Automation', 'omniflow-workflow-automation', 'Digital System', 'OmniFlow Tech', 'Internal operational engine synchronizing CRM data, automated invoicing, and multi-tier employee approval pipelines.', '/assets/images/projects/omniflow.webp', 'https://example.com/omniflow', 'REST APIs, Background Workers, Role Security', 4, 0, 'published');

-- Seed Process Steps
INSERT INTO `process_steps` (`step_number`, `title`, `description`, `display_order`, `status`) VALUES
('01', 'Tell us.', 'Tell us what you\'re trying to build, fix or improve.', 1, 'published'),
('02', 'We plan.', 'We define the experience, technology and scope.', 2, 'published'),
('03', 'We build.', 'Design, development and testing happen together.', 3, 'published'),
('04', 'You launch.', 'Your product goes live and starts doing its job.', 4, 'published');

-- Seed Testimonials
INSERT INTO `testimonials` (`client_name`, `company`, `position`, `content`, `rating`, `image`, `display_order`, `status`) VALUES
('Rajesh Menon', 'Apex Logistics India', 'Chief Technology Officer', 'Website Tailors transformed our dispatch platform from a sluggish legacy headache into a blisteringly fast powerhouse. The speed and clarity of their engineering is unparalleled.', 5, '/assets/images/testimonials/rajesh.webp', 1, 'published'),
('Ananya Sen', 'Kroma Design Studio', 'Founder & Creative Director', 'Working with Website Tailors was seamless. They understood both the delicate aesthetic nuances of our brand and the strict architectural requirements under the hood.', 5, '/assets/images/testimonials/ananya.webp', 2, 'published'),
('Rohan Singhania', 'Veloce E-Commerce', 'Managing Director', 'Our online store conversion jumped by 34% within the first month after Website Tailors rebuilt our checkout flow. Zero framework bloat, lightning speed, and total reliability.', 5, '/assets/images/testimonials/rohan.webp', 3, 'published');

-- Seed Superadmin Users
INSERT INTO `admins` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `is_active`, `created_at`) VALUES
(1, 'admin', 'websietailorss@gmail.com', '$2y$12$ht.4P0FXjJ7ev7MsltCeSeQpBGGj/is8I.XzZ/WMTbz2NTau8dX4y', 'Website Tailors Administrator', 'superadmin', 1, NOW()),
(2, 'superadmin', 'superadmin@rithamaya.com', '$2y$12$ht.4P0FXjJ7ev7MsltCeSeQpBGGj/is8I.XzZ/WMTbz2NTau8dX4y', 'Rithamaya Administrator', 'superadmin', 1, NOW());

-- --------------------------------------------------------
-- Table structure for table `clients`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_name` VARCHAR(150) NOT NULL,
  `company_name` VARCHAR(150) NULL,
  `email` VARCHAR(191) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `alternate_phone` VARCHAR(50) NULL,
  `service` VARCHAR(100) NULL,
  `source` VARCHAR(100) NULL,
  `status` ENUM('New', 'Active', 'Inactive', 'Completed') NOT NULL DEFAULT 'New',
  `assigned_to` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_clients_status` (`status`),
  INDEX `idx_clients_service` (`service`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `calls`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `calls` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lead_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `client_id` INT UNSIGNED NULL DEFAULT NULL,
  `contact_name` VARCHAR(150) NOT NULL,
  `company` VARCHAR(150) NULL,
  `phone` VARCHAR(50) NOT NULL,
  `call_datetime` DATETIME NULL DEFAULT NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'discovery',
  `status` VARCHAR(50) NOT NULL DEFAULT 'scheduled',
  `scheduled_at` DATETIME NOT NULL,
  `duration_minutes` INT NOT NULL DEFAULT 0,
  `priority` VARCHAR(20) NOT NULL DEFAULT 'normal',
  `notes` TEXT NULL,
  `outcome` TEXT NULL,
  `next_followup_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_calls_lead_id` (`lead_id`),
  INDEX `idx_calls_client_id` (`client_id`),
  INDEX `idx_calls_scheduled` (`scheduled_at`),
  INDEX `idx_calls_datetime` (`call_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `invoices`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
  `invoice_type` VARCHAR(50) NOT NULL DEFAULT 'advance',
  `client_id` INT UNSIGNED NULL DEFAULT NULL,
  `client_name` VARCHAR(150) NOT NULL,
  `project_id` INT UNSIGNED NULL DEFAULT NULL,
  `project_name` VARCHAR(150) NULL DEFAULT NULL,
  `project_phase` VARCHAR(100) NULL DEFAULT NULL,
  `service` VARCHAR(100) NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `project_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `advance_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount_received` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(50) NOT NULL DEFAULT 'paid',
  `due_date` DATE NOT NULL,
  `paid_at` DATETIME NULL,
  `payment_mode` VARCHAR(50) NULL DEFAULT NULL,
  `transaction_reference` VARCHAR(100) NULL DEFAULT NULL,
  `bank_name` VARCHAR(100) NULL DEFAULT NULL,
  `payment_method_desc` VARCHAR(150) NULL DEFAULT NULL,
  `payment_date` DATETIME NULL DEFAULT NULL,
  `line_items` TEXT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_invoices_client` (`client_id`),
  INDEX `idx_invoices_project` (`project_id`),
  INDEX `idx_invoices_type` (`invoice_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `revenue` (Internal Revenue Tracking)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `revenue` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT UNSIGNED NULL DEFAULT NULL,
  `client_name` VARCHAR(150) NULL DEFAULT NULL,
  `lead_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `invoice_id` INT NULL DEFAULT NULL,
  `project_id` INT UNSIGNED NULL DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_type` ENUM('UPI', 'Bank Transfer', 'Cash', 'Card', 'Other') NOT NULL DEFAULT 'Bank Transfer',
  `payment_status` ENUM('Pending', 'Partially Paid', 'Paid', 'Refunded') NOT NULL DEFAULT 'Paid',
  `payment_date` DATETIME NOT NULL,
  `transaction_reference` VARCHAR(100) NULL DEFAULT NULL,
  `bank_name` VARCHAR(100) NULL DEFAULT NULL,
  `service` VARCHAR(100) NOT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_rev_client` (`client_id`),
  INDEX `idx_rev_project` (`project_id`),
  INDEX `idx_rev_status` (`payment_status`),
  INDEX `idx_rev_date` (`payment_date`),
  INDEX `idx_rev_service` (`service`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;



