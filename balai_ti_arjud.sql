-- Sanitized database dump for balai_ti_arjud
-- Generated 2026-09-27 09:05:22
-- Full schema for all tables is included.
-- Row data was removed from the tables below to avoid committing PII and password hashes:
--   users, admins, staff_members, contact_messages, sessions, cache, cache_locks, notifications, user_notifications, activity_logs, otp_codes, password_reset_tokens, password_reset_codes, jobs, job_batches, failed_jobs, appointments, appointment_items, appointment_status_history, carts, cart_items, inventory_logs, reviews
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: balai_ti_arjud
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
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `model_type` varchar(255) DEFAULT NULL,
  `model_id` varchar(255) DEFAULT NULL,
  `ip_address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_action_index` (`user_id`,`action`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `username` varchar(64) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','manager','staff') NOT NULL DEFAULT 'staff',
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_username_unique` (`username`),
  UNIQUE KEY `admins_email_unique` (`email`),
  KEY `admins_role_index` (`role`),
  KEY `admins_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointment_items`
--

DROP TABLE IF EXISTS `appointment_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointment_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint(20) unsigned DEFAULT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `package_id` bigint(20) unsigned DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appointment_items_service_id_foreign` (`service_id`),
  KEY `appointment_items_package_id_foreign` (`package_id`),
  KEY `appointment_items_appointment_id_index` (`appointment_id`),
  CONSTRAINT `appointment_items_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointment_items_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointment_items_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_items`
--

LOCK TABLES `appointment_items` WRITE;
/*!40000 ALTER TABLE `appointment_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `appointment_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointment_service`
--

DROP TABLE IF EXISTS `appointment_service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointment_service` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `service_variant_id` bigint(20) unsigned DEFAULT NULL,
  `service_name` varchar(255) NOT NULL,
  `variant_name` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_minutes` smallint(5) unsigned NOT NULL,
  `quantity` smallint(5) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appointment_service_service_id_foreign` (`service_id`),
  KEY `appointment_service_service_variant_id_foreign` (`service_variant_id`),
  KEY `appointment_service_appointment_id_service_id_index` (`appointment_id`,`service_id`),
  CONSTRAINT `appointment_service_service_variant_id_foreign` FOREIGN KEY (`service_variant_id`) REFERENCES `service_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_service`
--

LOCK TABLES `appointment_service` WRITE;
/*!40000 ALTER TABLE `appointment_service` DISABLE KEYS */;
/*!40000 ALTER TABLE `appointment_service` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointment_status_history`
--

DROP TABLE IF EXISTS `appointment_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointment_status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `status` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `from_status` enum('pending','confirmed','in_progress','completed','cancelled') DEFAULT NULL,
  `to_status` enum('pending','confirmed','in_progress','completed','cancelled') DEFAULT NULL,
  `changed_by` enum('admin','customer','system') NOT NULL DEFAULT 'system',
  `changed_by_id` bigint(20) unsigned DEFAULT NULL,
  `changed_by_name` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appointment_status_history_appointment_id_foreign` (`appointment_id`),
  CONSTRAINT `appointment_status_history_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_status_history`
--

LOCK TABLES `appointment_status_history` WRITE;
/*!40000 ALTER TABLE `appointment_status_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `appointment_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `staff_member_id` bigint(20) unsigned DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `last_service_availed` varchar(255) DEFAULT NULL,
  `preferred_stylist` varchar(255) DEFAULT NULL,
  `special_request` text DEFAULT NULL,
  `booking_reference` varchar(255) DEFAULT NULL,
  `down_payment_reference` varchar(255) DEFAULT NULL,
  `down_payment_status` varchar(255) NOT NULL DEFAULT 'pending',
  `appointment_date` date DEFAULT NULL,
  `appointment_time` time DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `reference_number` varchar(32) NOT NULL DEFAULT '0',
  `customer_name` varchar(255) NOT NULL DEFAULT '0',
  `customer_phone` varchar(32) NOT NULL DEFAULT '0',
  `customer_email` varchar(255) DEFAULT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_time` time DEFAULT NULL,
  `last_services_availed` text DEFAULT NULL,
  `preferred_stylist_id` bigint(20) unsigned DEFAULT NULL,
  `down_payment_amount` decimal(10,2) DEFAULT NULL,
  `source` varchar(24) NOT NULL DEFAULT 'web',
  `admin_notes` text DEFAULT NULL,
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `reschedule_reason` varchar(255) DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointments_booking_reference_unique` (`booking_reference`),
  KEY `appointments_staff_member_id_foreign` (`staff_member_id`),
  KEY `appointments_user_id_appointment_date_index` (`user_id`,`appointment_date`),
  KEY `appointments_appointment_date_status_index` (`appointment_date`,`status`),
  CONSTRAINT `appointments_staff_member_id_foreign` FOREIGN KEY (`staff_member_id`) REFERENCES `staff_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blocked_dates`
--

DROP TABLE IF EXISTS `blocked_dates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blocked_dates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `date` date DEFAULT NULL,
  `scope` varchar(255) NOT NULL DEFAULT 'all',
  `reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blocked_dates_date_scope_unique` (`date`,`scope`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blocked_dates`
--

LOCK TABLES `blocked_dates` WRITE;
/*!40000 ALTER TABLE `blocked_dates` DISABLE KEYS */;
INSERT INTO `blocked_dates` VALUES (1,NULL,'all','Provincial holiday — salon closed','2026-09-27 06:45:54','2026-09-27 06:45:54','2026-10-06','2026-10-06',NULL,1),(2,NULL,'all','Team training day','2026-09-27 06:45:54','2026-09-27 06:45:54','2026-10-20','2026-10-20',NULL,1),(3,NULL,'all','Lash stock delivery delayed','2026-09-27 06:45:54','2026-09-27 06:45:54','2026-10-27','2026-10-27',NULL,1),(4,NULL,'all','Inventory audit and deep clean','2026-09-27 06:45:54','2026-09-27 06:45:54','2026-11-11','2026-11-13',NULL,1);
/*!40000 ALTER TABLE `blocked_dates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart_items`
--

DROP TABLE IF EXISTS `cart_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cart_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` bigint(20) unsigned DEFAULT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `package_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(2,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cart_items_cart_id_foreign` (`cart_id`),
  KEY `cart_items_service_id_foreign` (`service_id`),
  KEY `cart_items_package_id_foreign` (`package_id`),
  CONSTRAINT `cart_items_cart_id_foreign` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cart_items_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cart_items_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_items`
--

LOCK TABLES `cart_items` WRITE;
/*!40000 ALTER TABLE `cart_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `cart_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `carts`
--

DROP TABLE IF EXISTS `carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `carts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `carts_user_id_unique` (`user_id`),
  KEY `carts_session_id_index` (`session_id`),
  CONSTRAINT `carts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carts`
--

LOCK TABLES `carts` WRITE;
/*!40000 ALTER TABLE `carts` DISABLE KEYS */;
/*!40000 ALTER TABLE `carts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Manicure & Pedicure','manicure-pedicure-xxyc','Complete nail grooming, gel polish, and paraffin treatments.','2026-09-25 15:43:47','2026-09-25 15:43:47'),(2,'Nail Art & Extension','nail-art-extension-de5w','Extensions, repairs, and little added flair.','2026-09-25 15:43:47','2026-09-25 15:43:47'),(3,'Spa Services','spa-services-5ong','Hand, foot, and hair pampering sessions.','2026-09-25 15:43:47','2026-09-25 15:43:47'),(4,'Nail Care Packages','nail-care-packages-7eyt','Bundled nail care offers ? see the Packages section below.','2026-09-25 15:43:47','2026-09-25 15:43:47'),(5,'Brow & Lash Extension','brow-lash-extension-khz7','Lash extensions, lifts, tints, and brow lamination.','2026-09-25 15:43:47','2026-09-25 15:43:47'),(6,'Hair Waxing Removal','hair-waxing-removal-jey5','Hair removal treatments for face and body.','2026-09-25 15:43:47','2026-09-25 15:43:47'),(7,'Bleaching','bleaching-tldk','Gentle skin brightening for smaller areas or the whole body.','2026-09-25 15:43:47','2026-09-25 15:43:47'),(8,'Glutathione Push OR Drip','glutathione-push-or-drip-dclv','Skin brightening infusion services.','2026-09-25 15:43:47','2026-09-25 15:43:47'),(9,'Massage','massage-hyvt','Relaxing Swedish, Shiatsu, ventosa, and hot stone therapy.','2026-09-25 15:43:47','2026-09-25 15:43:47'),(10,'Co Spa Packages','co-spa-packages-cvg4','Bonding spa days for groups ? see the Packages section below.','2026-09-25 15:43:47','2026-09-25 15:43:47'),(11,'Hair Care','hair-care-u7gl','Haircuts, color, keratin, and deep conditioning treatments.','2026-09-25 15:43:47','2026-09-25 15:43:47'),(12,'Threading','threading-nuja','Precise eyebrow and facial hair shaping.','2026-09-25 15:43:47','2026-09-25 15:43:47'),(13,'Kiddie Services','kiddie-services-zgo2','Gentle nail and spa services for little ones.','2026-09-25 15:43:47','2026-09-25 15:43:47');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `topic` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `admin_reply` text DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `resolved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `replied_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_messages`
--

LOCK TABLES `contact_messages` WRITE;
/*!40000 ALTER TABLE `contact_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `homepage_settings`
--

DROP TABLE IF EXISTS `homepage_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `homepage_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) DEFAULT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`value`)),
  `section` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `homepage_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `homepage_settings`
--

LOCK TABLES `homepage_settings` WRITE;
/*!40000 ALTER TABLE `homepage_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `homepage_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_items`
--

DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) DEFAULT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `quantity_in_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `min_reorder_level` int(10) unsigned NOT NULL DEFAULT 0,
  `unit` varchar(255) NOT NULL DEFAULT 'pcs',
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reorder_threshold` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status_tag` enum('available','low_stock','best_seller','sold_out') NOT NULL DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_items_sku_unique` (`sku`),
  KEY `inventory_items_service_id_foreign` (`service_id`),
  KEY `inventory_items_category_index` (`category`),
  KEY `inventory_items_quantity_in_stock_min_reorder_level_index` (`quantity_in_stock`,`min_reorder_level`),
  CONSTRAINT `inventory_items_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_items`
--

LOCK TABLES `inventory_items` WRITE;
/*!40000 ALTER TABLE `inventory_items` DISABLE KEYS */;
INSERT INTO `inventory_items` VALUES (1,'Sensuality Nail Polish - Classic Reds','NP-CLR-001','Service supplies',28,10,'pcs',95.00,NULL,'active',1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Sensuality Nail Polish - Classic Reds',28.00,10.00,'available','Imported from legacy schema (cost per unit: 95.00)',1),(2,'Sensuality Nail Polish - Nudes','NP-NUD-002','Service supplies',22,10,'pcs',95.00,NULL,'active',1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Sensuality Nail Polish - Nudes',22.00,10.00,'available','Imported from legacy schema (cost per unit: 95.00)',1),(3,'Sensuality Gel Polish Set','GP-SET-003','Service supplies',15,6,'pcs',250.00,NULL,'active',3,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Sensuality Gel Polish Set',15.00,6.00,'available','Imported from legacy schema (cost per unit: 250.00)',1),(4,'Gel Polish Lamp (LED)','GP-LMP-004','Service supplies',4,2,'pcs',1200.00,NULL,'active',3,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Gel Polish Lamp (LED)',4.00,2.00,'available','Imported from legacy schema (cost per unit: 1200.00)',1),(5,'Cuticle Solution','CS-500-005','Service supplies',9,4,'pcs',140.00,NULL,'active',1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Cuticle Solution',9.00,4.00,'available','Imported from legacy schema (cost per unit: 140.00)',1),(6,'Nail Tips / Extension Kit','NT-KIT-006','Service supplies',11,5,'pcs',180.00,NULL,'active',11,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Nail Tips / Extension Kit',11.00,5.00,'available','Imported from legacy schema (cost per unit: 180.00)',1),(7,'Chrome Powder - Silver','CH-PDR-007','Service supplies',3,5,'pcs',60.00,NULL,'active',14,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Chrome Powder - Silver',3.00,5.00,'available','Imported from legacy schema (cost per unit: 60.00)',1),(8,'Chrome Powder - Assorted','CH-PDR-008','Service supplies',6,4,'pcs',60.00,NULL,'active',14,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Chrome Powder - Assorted',6.00,4.00,'available','Imported from legacy schema (cost per unit: 60.00)',1),(9,'Rhinestones / Gems Pack','NG-PCK-009','Service supplies',14,6,'pcs',40.00,NULL,'active',16,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Rhinestones / Gems Pack',14.00,6.00,'available','Imported from legacy schema (cost per unit: 40.00)',1),(10,'Nail Stickers & Foil Flakes','NS-PCK-010','Service supplies',17,6,'pcs',35.00,NULL,'active',17,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Nail Stickers & Foil Flakes',17.00,6.00,'available','Imported from legacy schema (cost per unit: 35.00)',1),(11,'Nail Stone Accents','NS-ACC-011','Service supplies',4,5,'pcs',45.00,NULL,'active',18,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Nail Stone Accents',4.00,5.00,'available','Imported from legacy schema (cost per unit: 45.00)',1),(12,'Paraffin Wax (Block)','PW-BLK-012','Service supplies',8,4,'pcs',320.00,NULL,'active',7,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Paraffin Wax (Block)',8.00,4.00,'available','Imported from legacy schema (cost per unit: 320.00)',1),(13,'Paraffin Wax Warmer','PW-WRM-013','Service supplies',2,1,'pcs',1500.00,NULL,'active',7,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Paraffin Wax Warmer',2.00,1.00,'available','Imported from legacy schema (cost per unit: 1500.00)',1),(14,'Hand Spa Cream','HS-CRM-014','Service supplies',19,8,'pcs',150.00,NULL,'active',19,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Hand Spa Cream',19.00,8.00,'available','Imported from legacy schema (cost per unit: 150.00)',1),(15,'Foot Spa Scrub','FS-SCR-015','Service supplies',12,5,'pcs',165.00,NULL,'active',20,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Foot Spa Scrub',12.00,5.00,'available','Imported from legacy schema (cost per unit: 165.00)',1),(16,'Foot Spa Tray','FS-TRY-016','Service supplies',6,3,'pcs',480.00,NULL,'active',20,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Foot Spa Tray',6.00,3.00,'available','Imported from legacy schema (cost per unit: 480.00)',1),(17,'Aromatic Scalp Oil','AS-OIL-017','Service supplies',7,3,'pcs',130.00,NULL,'active',23,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Aromatic Scalp Oil',7.00,3.00,'available','Imported from legacy schema (cost per unit: 130.00)',1),(18,'Lash Extension Trays (Assorted)','LE-TRY-018','Service supplies',10,5,'pcs',650.00,NULL,'active',24,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Lash Extension Trays (Assorted)',10.00,5.00,'available','Imported from legacy schema (cost per unit: 650.00)',1),(19,'Lash Adhesive Glue','LG-GLU-019','Service supplies',2,4,'pcs',350.00,NULL,'active',24,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Lash Adhesive Glue',2.00,4.00,'available','Imported from legacy schema (cost per unit: 350.00)',1),(20,'Lash Lift Kit','LE-LFT-020','Service supplies',5,3,'pcs',420.00,NULL,'active',28,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Lash Lift Kit',5.00,3.00,'available','Imported from legacy schema (cost per unit: 420.00)',1),(21,'Brow Lamination Kit','BL-KIT-021','Service supplies',5,3,'pcs',380.00,NULL,'active',30,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Brow Lamination Kit',5.00,3.00,'available','Imported from legacy schema (cost per unit: 380.00)',1),(22,'Brow Tint','BT-TNT-022','Service supplies',8,4,'pcs',120.00,NULL,'active',31,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Brow Tint',8.00,4.00,'available','Imported from legacy schema (cost per unit: 120.00)',1),(23,'Ear Candle 10-pack','EC-PCK-023','Service supplies',6,3,'pcs',90.00,NULL,'active',34,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Ear Candle 10-pack',6.00,3.00,'available','Imported from legacy schema (cost per unit: 90.00)',1),(24,'Hair Removal Soft Wax','HW-SFT-024','Service supplies',13,6,'pcs',220.00,NULL,'active',36,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Hair Removal Soft Wax',13.00,6.00,'available','Imported from legacy schema (cost per unit: 220.00)',1),(25,'Waxing Strips','WX-STR-025','Service supplies',9,5,'pcs',55.00,NULL,'active',36,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Waxing Strips',9.00,5.00,'available','Imported from legacy schema (cost per unit: 55.00)',1),(26,'Keratolytic Cream (Bleaching)','BL-CRM-026','Service supplies',6,4,'pcs',180.00,NULL,'active',48,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Keratolytic Cream (Bleaching)',6.00,4.00,'available','Imported from legacy schema (cost per unit: 180.00)',1),(27,'Bella Gluta Drip Kit','GD-KIT-027','Service supplies',4,3,'pcs',850.00,NULL,'active',49,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Bella Gluta Drip Kit',4.00,3.00,'available','Imported from legacy schema (cost per unit: 850.00)',1),(28,'IV Drip Set','IV-SET-028','Service supplies',12,6,'pcs',75.00,NULL,'active',49,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'IV Drip Set',12.00,6.00,'available','Imported from legacy schema (cost per unit: 75.00)',1),(29,'Massage Oil','MS-OIL-029','Service supplies',16,8,'pcs',160.00,NULL,'active',51,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Massage Oil',16.00,8.00,'available','Imported from legacy schema (cost per unit: 160.00)',1),(30,'Ventosa Cups Set','VT-CUP-030','Service supplies',2,6,'pcs',300.00,NULL,'active',54,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Ventosa Cups Set',2.00,6.00,'available','Imported from legacy schema (cost per unit: 300.00)',1),(31,'Hot Basalt Stones Set','HS-STN-031','Service supplies',3,2,'pcs',900.00,NULL,'active',57,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Hot Basalt Stones Set',3.00,2.00,'available','Imported from legacy schema (cost per unit: 900.00)',1),(32,'Essential Oil Set','EO-SET-032','Service supplies',7,4,'pcs',280.00,NULL,'active',56,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Essential Oil Set',7.00,4.00,'available','Imported from legacy schema (cost per unit: 280.00)',1),(33,'Hair Dye (Assorted)','HD-DYE-033','Service supplies',25,10,'pcs',85.00,NULL,'active',64,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Hair Dye (Assorted)',25.00,10.00,'available','Imported from legacy schema (cost per unit: 85.00)',1),(34,'Keratin Treatment Bottle','KN-BTL-034','Service supplies',9,4,'pcs',340.00,NULL,'active',67,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Keratin Treatment Bottle',9.00,4.00,'available','Imported from legacy schema (cost per unit: 340.00)',1),(35,'Rebonding Solution A + B','RB-SOL-035','Service supplies',11,5,'pcs',260.00,NULL,'active',68,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Rebonding Solution A + B',11.00,5.00,'available','Imported from legacy schema (cost per unit: 260.00)',1),(36,'Kerabond Serum','KB-SRM-036','Service supplies',7,4,'pcs',410.00,NULL,'active',70,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Kerabond Serum',7.00,4.00,'available','Imported from legacy schema (cost per unit: 410.00)',1),(37,'Hot Oil Treatment Sachet','HO-SCH-037','Service supplies',3,6,'pcs',35.00,NULL,'active',63,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Hot Oil Treatment Sachet',3.00,6.00,'available','Imported from legacy schema (cost per unit: 35.00)',1),(38,'Bleach Powder','BL-PDR-038','Service supplies',8,4,'pcs',120.00,NULL,'active',69,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Bleach Powder',8.00,4.00,'available','Imported from legacy schema (cost per unit: 120.00)',1),(39,'Cotton Rolls','CT-RLL-039','General',40,15,'pcs',25.00,NULL,'active',NULL,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Cotton Rolls',40.00,15.00,'available','Imported from legacy schema (cost per unit: 25.00)',1),(40,'Medical Gloves (Box)','MG-BOX-040','General',18,8,'pcs',150.00,NULL,'active',NULL,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Medical Gloves (Box)',18.00,8.00,'available','Imported from legacy schema (cost per unit: 150.00)',1),(41,'Disposable Razors','DR-RZR-041','General',35,15,'pcs',8.00,NULL,'active',NULL,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Disposable Razors',35.00,15.00,'available','Imported from legacy schema (cost per unit: 8.00)',1),(42,'Antiseptic Solution','AS-SOL-042','General',10,5,'pcs',95.00,NULL,'active',NULL,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Antiseptic Solution',10.00,5.00,'available','Imported from legacy schema (cost per unit: 95.00)',1),(43,'Paper Towel Rolls','PT-RLL-043','General',20,10,'pcs',65.00,NULL,'active',NULL,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'Paper Towel Rolls',20.00,10.00,'available','Imported from legacy schema (cost per unit: 65.00)',1);
/*!40000 ALTER TABLE `inventory_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_logs`
--

DROP TABLE IF EXISTS `inventory_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `change_type` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_logs_user_id_foreign` (`user_id`),
  KEY `inventory_logs_inventory_item_id_created_at_index` (`inventory_item_id`,`created_at`),
  CONSTRAINT `inventory_logs_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_logs`
--

LOCK TABLES `inventory_logs` WRITE;
/*!40000 ALTER TABLE `inventory_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_09_22_140656_create_categories_table',1),(5,'2026_09_22_140657_create_services_table',1),(6,'2026_09_22_140658_create_packages_table',1),(7,'2026_09_22_140659_create_staff_members_table',1),(8,'2026_09_22_140700_create_appointments_table',1),(9,'2026_09_22_140701_create_appointment_items_table',1),(10,'2026_09_22_140702_create_inventory_items_table',1),(11,'2026_09_22_140703_create_inventory_logs_table',1),(12,'2026_09_26_000100_create_service_variants_table',1),(13,'2026_09_26_000101_create_service_inventory_table',1),(14,'2026_09_26_000102_create_appointment_status_history_table',1),(15,'2026_09_26_000103_create_password_reset_codes_table',1),(16,'2026_09_26_000104_create_blocked_dates_table',1),(17,'2026_09_26_000105_create_terms_and_conditions_table',1),(18,'2026_09_26_000106_create_promos_table',1),(19,'2026_09_26_000107_create_reviews_table',1),(20,'2026_09_26_000108_add_extra_fields_to_tables',1),(21,'2026_09_26_000109_create_contact_messages_table',1),(22,'2026_09_27_000001_create_homepage_settings_table',2),(23,'2026_09_27_000002_create_activity_logs_table',2),(24,'2026_09_27_000003_create_user_notifications_table',2),(25,'2026_09_27_000004_create_carts_table',2),(26,'2026_09_27_000005_create_cart_items_table',2),(27,'2026_09_27_000006_add_fields_to_reviews_table',3),(28,'2026_09_27_000007_add_fields_to_contact_messages_table',3),(29,'2026_09_27_000008_create_otp_codes_table',3),(30,'2024_01_01_000100_create_bta_tables',4),(31,'2024_01_01_000200_create_notifications_table',4);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otp_codes`
--

DROP TABLE IF EXISTS `otp_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `otp_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `otp_codes_user_id_index` (`user_id`),
  CONSTRAINT `otp_codes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otp_codes`
--

LOCK TABLES `otp_codes` WRITE;
/*!40000 ALTER TABLE `otp_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `otp_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `packages`
--

DROP TABLE IF EXISTS `packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `packages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `max_pax` int(10) unsigned DEFAULT NULL,
  `duration_hours` decimal(5,2) DEFAULT NULL,
  `complimentary_items` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `packages_name_index` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `packages`
--

LOCK TABLES `packages` WRITE;
/*!40000 ALTER TABLE `packages` DISABLE KEYS */;
INSERT INTO `packages` VALUES (1,'Glow Solo Package 1','The best-selling glow combination for a single guest.',239.00,1,2.00,NULL,'2026-09-25 15:43:47','2026-09-25 15:43:47'),(2,'Glow Solo Package 2','Complete hand and foot glow-up for one.',949.00,1,3.00,NULL,'2026-09-25 15:43:47','2026-09-25 15:43:47'),(3,'Glow Solo Package 3','Our premium solo pampering bundle.',1199.00,1,4.00,NULL,'2026-09-25 15:43:47','2026-09-25 15:43:47'),(4,'Glow Solo Package 4','A quick weekday glow refresh for one.',669.00,1,2.00,NULL,'2026-09-25 15:43:47','2026-09-25 15:43:47'),(5,'Arjud Glow Package','The signature Balai ti Arjud experience. Enjoy 15% off this bundle.',1800.00,2,4.00,'15% off · Free express massage and refreshments.','2026-09-25 15:43:47','2026-09-25 15:43:47'),(6,'Co Spa Package 1','A relaxing spa day for small groups of up to three.',5000.00,3,3.00,'Includes light snacks and refreshments for the group.','2026-09-25 15:43:47','2026-09-25 15:43:47'),(7,'Co Spa Package 2','The ultimate group bonding spa day for up to five guests.',10000.00,5,3.00,'Includes a dedicated private room and refreshments for the group.','2026-09-25 15:43:47','2026-09-25 15:43:47');
/*!40000 ALTER TABLE `packages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_codes`
--

DROP TABLE IF EXISTS `password_reset_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `code_hash` varchar(255) NOT NULL DEFAULT '0',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `ip_address` varchar(45) DEFAULT 'NULL',
  PRIMARY KEY (`id`),
  KEY `password_reset_codes_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_codes`
--

LOCK TABLES `password_reset_codes` WRITE;
/*!40000 ALTER TABLE `password_reset_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promos`
--

DROP TABLE IF EXISTS `promos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `image_path` varchar(255) DEFAULT 'NULL',
  `notified` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promos`
--

LOCK TABLES `promos` WRITE;
/*!40000 ALTER TABLE `promos` DISABLE KEYS */;
INSERT INTO `promos` VALUES (1,'Glow Package Discount','Book any two signature services this month and enjoy 15% off your total. Perfect for pre-wedding prep or a mid-year reset.',NULL,'2026-09-22 00:00:00','2026-10-22 00:00:00',1,'2026-09-27 06:45:54','2026-09-27 06:45:54',NULL,'NULL',0),(2,'Loyalty Week: Free Manicure Upgrade','Book a Gelish Manicure during Loyalty Week and upgrade to Spa Pedicure pricing at no extra cost.',NULL,'2026-10-07 00:00:00','2026-10-14 00:00:00',1,'2026-09-27 06:45:54','2026-09-27 06:45:54',NULL,'NULL',0),(3,'Mother’s Day Special','A relaxing 60-minute massage plus a hydrating facial at a special Mother’s Day rate.',NULL,'2026-07-29 00:00:00','2026-08-05 00:00:00',0,'2026-09-27 06:45:54','2026-09-27 06:45:54',NULL,'NULL',0);
/*!40000 ALTER TABLE `promos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `review` text DEFAULT NULL,
  `admin_reply` text DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `message` text NOT NULL DEFAULT 0,
  `customer_name` varchar(255) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_user_id_appointment_id_unique` (`user_id`,`appointment_id`),
  KEY `reviews_appointment_id_foreign` (`appointment_id`),
  CONSTRAINT `reviews_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salon_settings`
--

DROP TABLE IF EXISTS `salon_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salon_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT 'Balai ti Arjud',
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `operating_hours` longtext NOT NULL,
  `slot_interval_minutes` smallint(5) unsigned NOT NULL DEFAULT 30,
  `booking_lead_days` smallint(5) unsigned NOT NULL DEFAULT 60,
  `down_payment_required` tinyint(1) NOT NULL DEFAULT 1,
  `down_payment_percentage` tinyint(3) unsigned NOT NULL DEFAULT 50,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salon_settings`
--

LOCK TABLES `salon_settings` WRITE;
/*!40000 ALTER TABLE `salon_settings` DISABLE KEYS */;
INSERT INTO `salon_settings` VALUES (1,'Balai ti Arjud — Glow & Co. Beauty Lounge','Purok 5, Abra, Philippines','+63 900 000 0000','hello@balaitiarjud.test','{\"monday\":[\"09:00\",\"18:00\"],\"tuesday\":[\"09:00\",\"18:00\"],\"wednesday\":[\"09:00\",\"18:00\"],\"thursday\":[\"09:00\",\"18:00\"],\"friday\":[\"09:00\",\"19:00\"],\"saturday\":[\"08:00\",\"19:00\"],\"sunday\":[\"09:00\",\"17:00\"]}',30,60,1,50,'2026-09-27 02:38:55','2026-09-27 03:10:41');
/*!40000 ALTER TABLE `salon_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_inventory`
--

DROP TABLE IF EXISTS `service_inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_inventory` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` bigint(20) unsigned NOT NULL,
  `inventory_item_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `quantity_per_service` decimal(12,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_inventory_service_id_inventory_item_id_unique` (`service_id`,`inventory_item_id`),
  KEY `service_inventory_inventory_item_id_foreign` (`inventory_item_id`),
  CONSTRAINT `service_inventory_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `service_inventory_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_inventory`
--

LOCK TABLES `service_inventory` WRITE;
/*!40000 ALTER TABLE `service_inventory` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_variants`
--

DROP TABLE IF EXISTS `service_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_variants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_minutes` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `service_variants_service_id_foreign` (`service_id`),
  CONSTRAINT `service_variants_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_variants`
--

LOCK TABLES `service_variants` WRITE;
/*!40000 ALTER TABLE `service_variants` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price_type` varchar(255) NOT NULL DEFAULT 'fixed',
  `price` decimal(10,2) DEFAULT NULL,
  `max_price` decimal(10,2) DEFAULT NULL,
  `duration_minutes` int(10) unsigned DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT 'NULL',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `services_slug_unique` (`slug`),
  KEY `services_category_id_name_index` (`category_id`,`name`),
  CONSTRAINT `services_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (1,1,'Glow Manicure',NULL,'fixed',119.00,NULL,45,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'glow-manicure','Manicure & Pedicure',NULL,0),(2,1,'Glow Pedicure',NULL,'fixed',159.00,NULL,60,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'glow-pedicure','Manicure & Pedicure',NULL,0),(3,1,'Glow Gel Manicure','With gel polish application.','fixed',349.00,NULL,75,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'glow-gel-manicure','Manicure & Pedicure',NULL,0),(4,1,'Glow Gel Pedicure','With gel polish application.','fixed',379.00,NULL,90,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'glow-gel-pedicure','Manicure & Pedicure',NULL,0),(5,1,'Gel Removal Off-House','Removal of gel polish applied by another salon.','fixed',100.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'gel-removal-off-house','Manicure & Pedicure',NULL,0),(6,1,'Gel Matte Top','Add-on matte top coat finish.','fixed',100.00,NULL,15,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'gel-matte-top','Manicure & Pedicure',NULL,0),(7,1,'Hand Paraffin Treatment','Deeply moisturizing warm wax dip for hands.','fixed',249.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'hand-paraffin-treatment','Manicure & Pedicure',NULL,0),(8,1,'Arm Paraffin Treatment','Warm wax treatment for the arms.','fixed',299.00,NULL,35,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'arm-paraffin-treatment','Manicure & Pedicure',NULL,0),(9,1,'Foot Paraffin Treatment','Warm wax treatment for the feet.','fixed',299.00,NULL,35,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'foot-paraffin-treatment','Manicure & Pedicure',NULL,0),(10,1,'Leg Paraffin Treatment','Warm wax treatment for the legs.','fixed',399.00,NULL,45,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'leg-paraffin-treatment','Manicure & Pedicure',NULL,0),(11,2,'Nail Extension w/ Gel Polish','Full set nail extension with gel polish.','tiered',799.00,999.00,120,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'nail-extension-w-gel-polish','Nail Art & Extension',NULL,0),(12,2,'Nail Extension Removal','Removal of a full set of extensions.','fixed',200.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'nail-extension-removal','Nail Art & Extension',NULL,0),(13,2,'Nail Repair (Per Nail)','Repair of a damaged nail.','starting_at',100.00,NULL,15,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'nail-repair-per-nail','Nail Art & Extension',NULL,0),(14,2,'Nail Art - Chrome','Mirror chrome accent on nails.','fixed',100.00,NULL,15,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'nail-art-chrome','Nail Art & Extension',NULL,0),(15,2,'Nail Art - Ombre','Fading gradient nail art.','fixed',100.00,NULL,20,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'nail-art-ombre','Nail Art & Extension',NULL,0),(16,2,'Nail Art - Gems / Rhinestones','Crystals and rhinestone accents.','starting_at',50.00,NULL,15,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'nail-art-gems-rhinestones','Nail Art & Extension',NULL,0),(17,2,'Nail Art - Stickers / Flakes','Charms, stickers, and foil flakes.','starting_at',50.00,NULL,15,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'nail-art-stickers-flakes','Nail Art & Extension',NULL,0),(18,2,'Nail Art - Nail Stone','Decorative nail stone accents.','starting_at',50.00,NULL,15,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'nail-art-nail-stone','Nail Art & Extension',NULL,0),(19,3,'Glow Hand Spa','Complete hand care ritual.','fixed',299.00,NULL,45,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'glow-hand-spa','Spa Services',NULL,0),(20,3,'Glow Foot Spa','Relaxing soak, scrub, and massage for the feet.','fixed',449.00,NULL,60,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'glow-foot-spa','Spa Services',NULL,0),(21,3,'Glow Signature Hand Spa','Extended hand spa with paraffin.','fixed',499.00,NULL,75,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'glow-signature-hand-spa','Spa Services',NULL,0),(22,3,'Glow Signature Foot Spa','Extended foot spa with warm towel treatment.','fixed',599.00,NULL,90,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'glow-signature-foot-spa','Spa Services',NULL,0),(23,3,'Hair Spa Aromatic Scalp Treat','Soothing aromatic scalp massage and treatment.','fixed',200.00,NULL,45,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'hair-spa-aromatic-scalp-treat','Spa Services',NULL,0),(24,5,'Fancy Classic Lashes','Classic 1:1 lash extension set.','fixed',749.00,NULL,120,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'fancy-classic-lashes','Brow & Lash Extension',NULL,0),(25,5,'Glamour Open Eye / Cat Eye Hybrid','Hybrid volume lash set.','fixed',999.00,NULL,150,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'glamour-open-eye-cat-eye-hybrid','Brow & Lash Extension',NULL,0),(26,5,'Diva Lashes','Full volume lash set.','fixed',999.00,NULL,150,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'diva-lashes','Brow & Lash Extension',NULL,0),(27,5,'Eye Lash Perming','Lift and curl of natural lashes.','fixed',250.00,NULL,45,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'eye-lash-perming','Brow & Lash Extension',NULL,0),(28,5,'Lash Lift','Lash curl that lasts several weeks.','fixed',450.00,NULL,60,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'lash-lift','Brow & Lash Extension',NULL,0),(29,5,'Lash Tint','Eyelash tinting.','fixed',250.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'lash-tint','Brow & Lash Extension',NULL,0),(30,5,'Brow Lamination','Tamed, lifted brows that stay brushed up.','fixed',450.00,NULL,45,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'brow-lamination','Brow & Lash Extension',NULL,0),(31,5,'Brow Tint','Brow color tint.','fixed',150.00,NULL,20,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'brow-tint','Brow & Lash Extension',NULL,0),(32,5,'Synthetic Hair Extension','Full set synthetic lashes.','fixed',450.00,NULL,90,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'synthetic-hair-extension','Brow & Lash Extension',NULL,0),(33,5,'Human Hair Extension','Full set human hair lashes.','fixed',600.00,NULL,120,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'human-hair-extension','Brow & Lash Extension',NULL,0),(34,5,'Ear Candling','Relaxing ear candling treatment.','fixed',150.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'ear-candling','Brow & Lash Extension',NULL,0),(35,6,'Upper / Lower Lip Waxing',NULL,'fixed',149.00,NULL,15,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'upper-lower-lip-waxing','Hair Waxing Removal',NULL,0),(36,6,'Eyebrow Waxing',NULL,'fixed',499.00,NULL,20,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'eyebrow-waxing','Hair Waxing Removal',NULL,0),(37,6,'Underarm Waxing',NULL,'fixed',199.00,NULL,20,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'underarm-waxing','Hair Waxing Removal',NULL,0),(38,6,'Half Arm Waxing',NULL,'fixed',249.00,NULL,25,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'half-arm-waxing','Hair Waxing Removal',NULL,0),(39,6,'Full Arms Waxing',NULL,'fixed',499.00,NULL,40,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'full-arms-waxing','Hair Waxing Removal',NULL,0),(40,6,'Half Legs Waxing',NULL,'fixed',349.00,NULL,40,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'half-legs-waxing','Hair Waxing Removal',NULL,0),(41,6,'Full Legs Waxing',NULL,'fixed',699.00,NULL,60,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'full-legs-waxing','Hair Waxing Removal',NULL,0),(42,6,'Bikini Waxing',NULL,'fixed',299.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'bikini-waxing','Hair Waxing Removal',NULL,0),(43,6,'Brazilian Waxing',NULL,'fixed',399.00,NULL,45,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'brazilian-waxing','Hair Waxing Removal',NULL,0),(44,7,'Nape Bleaching',NULL,'fixed',200.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'nape-bleaching','Bleaching',NULL,0),(45,7,'Underarm Bleaching',NULL,'fixed',250.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'underarm-bleaching','Bleaching',NULL,0),(46,7,'Elbow / Knees Bleaching',NULL,'fixed',250.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'elbow-knees-bleaching','Bleaching',NULL,0),(47,7,'Inner Thighs Bleaching',NULL,'fixed',250.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'inner-thighs-bleaching','Bleaching',NULL,0),(48,7,'Whole Body Bleaching',NULL,'fixed',1300.00,NULL,120,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'whole-body-bleaching','Bleaching',NULL,0),(49,8,'Bella Gluta Drip','Intravenous skin brightening drip.','fixed',1399.00,NULL,60,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'bella-gluta-drip','Glutathione Push OR Drip',NULL,0),(50,8,'IV Push','Quick intravenous skin brightening push.','fixed',699.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'iv-push','Glutathione Push OR Drip',NULL,0),(51,9,'Swedish Massage (60 min)','Relaxing full body massage.','fixed',400.00,NULL,60,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'swedish-massage-60-min','Massage',NULL,0),(52,9,'Shiatsu Massage (60 min)','Pressure point massage therapy.','fixed',400.00,NULL,60,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'shiatsu-massage-60-min','Massage',NULL,0),(53,9,'Combination Massage (60 min)','Mix of Swedish and Shiatsu techniques.','fixed',500.00,NULL,60,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'combination-massage-60-min','Massage',NULL,0),(54,9,'Moving Ventosa (75 min)','Cupping therapy with moving cups.','fixed',799.00,NULL,75,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'moving-ventosa-75-min','Massage',NULL,0),(55,9,'Stationary Ventosa (75 min)','Cupping therapy with stationary cups.','tiered',799.00,899.00,75,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'stationary-ventosa-75-min','Massage',NULL,0),(56,9,'Aromatherapy Massage (90 min)','Massage with essential oil blends.','fixed',899.00,NULL,90,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'aromatherapy-massage-90-min','Massage',NULL,0),(57,9,'Hotstone Massage (90 min)','Warm basalt stones on key pressure points.','fixed',799.00,NULL,90,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'hotstone-massage-90-min','Massage',NULL,0),(58,9,'Spot Massage - Head & Shoulder (30 min)',NULL,'fixed',200.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'spot-massage-head-shoulder-30-min','Massage',NULL,0),(59,9,'Spot Massage - Back (30 min)',NULL,'fixed',200.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'spot-massage-back-30-min','Massage',NULL,0),(60,9,'Spot Massage - Hands & Arms (30 min)',NULL,'fixed',200.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'spot-massage-hands-arms-30-min','Massage',NULL,0),(61,9,'Spot Massage - Feet & Legs (30 min)',NULL,'fixed',200.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'spot-massage-feet-legs-30-min','Massage',NULL,0),(62,11,'Glow Haircut','Includes shampoo and blow-dry.','starting_at',120.00,NULL,45,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'glow-haircut','Hair Care',NULL,0),(63,11,'Hot Oil Treatment','Restorative hot oil treatment.','starting_at',350.00,NULL,60,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'hot-oil-treatment','Hair Care',NULL,0),(64,11,'Hair Color','Full color application.','starting_at',500.00,NULL,120,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'hair-color','Hair Care',NULL,0),(65,11,'Hair Glowout','Glossy deep-conditioning treatment.','starting_at',850.00,NULL,90,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'hair-glowout','Hair Care',NULL,0),(66,11,'Hair Spa','Deep conditioning spa treatment.','starting_at',450.00,NULL,60,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'hair-spa','Hair Care',NULL,0),(67,11,'Keratin Treatment','Smoothing keratin treatment.','starting_at',800.00,NULL,150,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'keratin-treatment','Hair Care',NULL,0),(68,11,'Hair Rebond','Straightening rebond treatment.','starting_at',1500.00,NULL,240,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'hair-rebond','Hair Care',NULL,0),(69,11,'Bleach','Pre-lightening treatment.','starting_at',500.00,NULL,120,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'bleach','Hair Care',NULL,0),(70,11,'Kerabond Treatment','Premium smoothing treatment.','starting_at',2000.00,NULL,180,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'kerabond-treatment','Hair Care',NULL,0),(71,11,'Hair Color + Rebond','Combo color and rebond service.','starting_at',1800.00,NULL,300,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'hair-color-rebond','Hair Care',NULL,0),(72,11,'Hair Color + Kerabond','Combo color and kerabond service.','starting_at',2300.00,NULL,300,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'hair-color-kerabond','Hair Care',NULL,0),(73,12,'Upper Lip Threading',NULL,'tiered',70.00,100.00,10,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'upper-lip-threading','Threading',NULL,0),(74,12,'Chin Threading',NULL,'tiered',90.00,150.00,15,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'chin-threading','Threading',NULL,0),(75,12,'Eyebrow Threading',NULL,'tiered',100.00,150.00,15,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'eyebrow-threading','Threading',NULL,0),(76,12,'Underarms Threading',NULL,'fixed',150.00,NULL,20,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'underarms-threading','Threading',NULL,0),(77,12,'Full Face Threading',NULL,'fixed',300.00,NULL,40,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'full-face-threading','Threading',NULL,0),(78,13,'Kiddie Mani','Gentle manicure for kids.','fixed',69.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'kiddie-mani','Kiddie Services',NULL,0),(79,13,'Kiddie Pedi','Gentle pedicure for kids.','fixed',89.00,NULL,40,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'kiddie-pedi','Kiddie Services',NULL,0),(80,13,'Kiddie Hand Spa',NULL,'fixed',149.00,NULL,30,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'kiddie-hand-spa','Kiddie Services',NULL,0),(81,13,'Kiddie Foot Spa',NULL,'fixed',199.00,NULL,45,NULL,1,'2026-09-25 15:43:47','2026-09-25 15:43:47',NULL,'kiddie-foot-spa','Kiddie Services',NULL,0);
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_members`
--

DROP TABLE IF EXISTS `staff_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_members`
--

LOCK TABLES `staff_members` WRITE;
/*!40000 ALTER TABLE `staff_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `terms_and_conditions`
--

DROP TABLE IF EXISTS `terms_and_conditions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `terms_and_conditions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `terms_and_conditions_category_version_unique` (`category`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `terms_and_conditions`
--

LOCK TABLES `terms_and_conditions` WRITE;
/*!40000 ALTER TABLE `terms_and_conditions` DISABLE KEYS */;
/*!40000 ALTER TABLE `terms_and_conditions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_notifications`
--

DROP TABLE IF EXISTS `user_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `data` longtext DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_notifications_user_id_read_at_index` (`user_id`,`read_at`),
  CONSTRAINT `user_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_notifications`
--

LOCK TABLES `user_notifications` WRITE;
/*!40000 ALTER TABLE `user_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'customer',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `username` varchar(64) DEFAULT NULL,
  `contact_number` varchar(32) DEFAULT NULL,
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'balai_ti_arjud'
--

--
-- Dumping routines for database 'balai_ti_arjud'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-27 15:04:00
