-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Dec 16, 2025 at 06:48 PM
-- Server version: 8.0.44-0ubuntu0.22.04.1
-- PHP Version: 8.1.2-1ubuntu2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `api2`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int NOT NULL,
  `accounts_id` int NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','agent','vendor') NOT NULL,
  `companyname` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `landline` varchar(255) NOT NULL,
  `mobile` varchar(255) NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `oo_activated` int NOT NULL DEFAULT '0',
  `processor` varchar(255) NOT NULL,
  `cust_nbr` varchar(255) NOT NULL,
  `merch_nbr` varchar(255) NOT NULL,
  `dba_nbr` varchar(255) NOT NULL,
  `terminal_nbr` varchar(255) NOT NULL,
  `mac` varchar(255) NOT NULL,
  `enterdate` datetime NOT NULL,
  `lastmod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logo` varchar(255) DEFAULT NULL,
  `processor_info` varchar(255) DEFAULT NULL,
  `processor_type` varchar(255) DEFAULT NULL,
  `taxrate` decimal(4,2) DEFAULT NULL,
  `onboarding_status` varchar(255) DEFAULT NULL,
  `reward_status` varchar(255) DEFAULT NULL,
  `fiserv_merch_id` varchar(255) DEFAULT NULL,
  `merchid_ach` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `batches`
--

CREATE TABLE `batches` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `processing_plan_id` bigint UNSIGNED NOT NULL,
  `status` enum('pending','processing','completed','failed','canceled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `scheduled_time` timestamp NOT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `total_items` int UNSIGNED NOT NULL DEFAULT '0',
  `processed_items` int UNSIGNED NOT NULL DEFAULT '0',
  `success_items` int UNSIGNED NOT NULL DEFAULT '0',
  `failed_items` int UNSIGNED NOT NULL DEFAULT '0',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `batch_items`
--

CREATE TABLE `batch_items` (
  `id` bigint UNSIGNED NOT NULL,
  `batch_id` bigint UNSIGNED NOT NULL,
  `subscription_id` int UNSIGNED NOT NULL,
  `status` enum('pending','processing','success','failed','skipped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  `retry_count` int UNSIGNED NOT NULL DEFAULT '0',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `result_data` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_address`
--

CREATE TABLE `billing_address` (
  `id` int UNSIGNED NOT NULL,
  `vendors_id` int DEFAULT NULL,
  `street` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_token`
--

CREATE TABLE `billing_token` (
  `id` int UNSIGNED NOT NULL,
  `billing_address_id` int UNSIGNED NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiries` timestamp NULL DEFAULT NULL,
  `card_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` int NOT NULL,
  `vendors_id` int NOT NULL,
  `account_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `id` int NOT NULL,
  `vendors_id` int NOT NULL,
  `first_name` text NOT NULL,
  `last_name` text NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `email` text NOT NULL,
  `street` text NOT NULL,
  `city` text NOT NULL,
  `zip` text NOT NULL,
  `dob` date NOT NULL,
  `point` int NOT NULL,
  `membership` tinyint(1) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `gender` tinyint(1) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastmod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `note` text NOT NULL,
  `fivserv_security_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fiserv_security_token` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `order_uuid` varchar(255) DEFAULT NULL,
  `subscription_id` varchar(255) DEFAULT NULL,
  `billing_token_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `debug_json_updates`
--

CREATE TABLE `debug_json_updates` (
  `order_id` int DEFAULT NULL,
  `order_uuid` varchar(255) DEFAULT NULL,
  `json_content` text,
  `json_tips` varchar(50) DEFAULT NULL,
  `json_total` varchar(50) DEFAULT NULL,
  `error_info` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `id` int NOT NULL,
  `agents_id` int NOT NULL,
  `vendors_id` int NOT NULL,
  `terminals_id` int NOT NULL,
  `groups_id` int NOT NULL,
  `description` varchar(255) NOT NULL,
  `groupType` varchar(255) NOT NULL,
  `notes` varchar(255) NOT NULL,
  `enterdate` datetime NOT NULL,
  `lastmod` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `group_menu`
--

CREATE TABLE `group_menu` (
  `id` int UNSIGNED NOT NULL,
  `first_column` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_column` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastmod` int DEFAULT NULL,
  `enterdate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventories`
--

CREATE TABLE `inventories` (
  `id` int NOT NULL,
  `vendors_id` int NOT NULL,
  `sku` varchar(255) NOT NULL,
  `upc` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `enterdate` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventoryLogs`
--

CREATE TABLE `inventoryLogs` (
  `id` int NOT NULL,
  `inventoryId` int NOT NULL,
  `quantity` int NOT NULL,
  `reason` varchar(255) NOT NULL,
  `orderUuid` varchar(36) DEFAULT NULL,
  `enterdate` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice`
--

CREATE TABLE `invoice` (
  `id` int UNSIGNED NOT NULL,
  `ref` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `profile_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` float NOT NULL,
  `status` smallint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int NOT NULL,
  `agents_id` int NOT NULL DEFAULT '-1',
  `vendors_id` int NOT NULL,
  `terminals_id` int NOT NULL DEFAULT '-1',
  `items_id` int NOT NULL DEFAULT '-1',
  `desc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `sku` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `cost` int NOT NULL,
  `price` int NOT NULL,
  `notes` varchar(255) NOT NULL,
  `upc` varchar(255) NOT NULL,
  `taxable` int NOT NULL,
  `taxrate` float NOT NULL,
  `group` int DEFAULT NULL,
  `amount_on_hand` int NOT NULL,
  `enterdate` datetime NOT NULL,
  `lastmod` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_vi_0900_ai_ci DEFAULT NULL,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_currency` varchar(255) DEFAULT 'USD',
  `status` tinyint DEFAULT NULL,
  `type_display` tinyint DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `group_belong_type` smallint UNSIGNED DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `print_type` int DEFAULT '0' COMMENT 'From online order',
  `is_ebt` tinyint(1) DEFAULT '0' COMMENT 'From online order',
  `is_manual_price` tinyint(1) DEFAULT '0' COMMENT 'From online order',
  `is_weighted` tinyint(1) DEFAULT '0' COMMENT 'From online order',
  `crv` json DEFAULT NULL COMMENT 'From online order',
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `item_group`
--

CREATE TABLE `item_group` (
  `id` int UNSIGNED NOT NULL,
  `first_column` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_column` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastmod` int DEFAULT NULL,
  `enterdate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `json`
--

CREATE TABLE `json` (
  `id` int NOT NULL,
  `serial` varchar(255) NOT NULL,
  `content` mediumtext NOT NULL,
  `lastmod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jsonOlaPay`
--

CREATE TABLE `jsonOlaPay` (
  `id` int NOT NULL,
  `serial` varchar(255) NOT NULL,
  `content` mediumtext NOT NULL,
  `lastmod` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

CREATE TABLE `menus` (
  `id` int UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enterdate` datetime DEFAULT NULL,
  `lastmod` int DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `num_stores` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_store`
--

CREATE TABLE `menu_store` (
  `id` int UNSIGNED NOT NULL,
  `first_column` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_column` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastmod` int DEFAULT NULL,
  `enterdate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of notification (e.g., email, sms, webhook)',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Status of notification (e.g., pending, sent, failed)',
  `vendor_id` bigint NOT NULL,
  `store_uuid` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium' COMMENT 'Priority (e.g., low, medium, high)',
  `payload` blob NOT NULL COMMENT 'Notification content/data',
  `sent_at` timestamp NULL DEFAULT NULL,
  `retry_count` int UNSIGNED DEFAULT '0',
  `max_retries` int UNSIGNED DEFAULT '3',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ol_orders`
--

CREATE TABLE `ol_orders` (
  `id` int NOT NULL,
  `vendor_id` int NOT NULL,
  `store_uuid` char(36) NOT NULL,
  `terminal_id` int NOT NULL,
  `employee_id` int DEFAULT '0',
  `order_total` decimal(10,2) NOT NULL,
  `sub_total` decimal(10,2) NOT NULL,
  `tip` decimal(10,2) DEFAULT '0.00',
  `tax` decimal(10,2) DEFAULT '0.00',
  `tech_fee` decimal(10,2) DEFAULT '0.00',
  `delivery_fee` decimal(10,2) DEFAULT '0.00',
  `delivery_type` tinyint NOT NULL,
  `enterdate` datetime DEFAULT NULL,
  `lastmod` int DEFAULT NULL,
  `delivery_info` json DEFAULT NULL,
  `customer_info` json DEFAULT NULL,
  `payment_info` json DEFAULT NULL,
  `order_items` json DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `uuid` char(36) DEFAULT NULL,
  `delivery_status` varchar(255) DEFAULT NULL,
  `delivery_service_update_payload` json DEFAULT NULL,
  `prep_time` int DEFAULT '30',
  `pending_order_id` int DEFAULT NULL,
  `new_order_timestamp` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ol_pending_orders`
--

CREATE TABLE `ol_pending_orders` (
  `id` int NOT NULL,
  `content` json NOT NULL,
  `lastmod` bigint DEFAULT NULL,
  `merchant_id` bigint DEFAULT NULL,
  `terminal_serial` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `online_order_groups`
--

CREATE TABLE `online_order_groups` (
  `id` int UNSIGNED NOT NULL,
  `vendor_id` int DEFAULT NULL,
  `group_type` smallint UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` smallint DEFAULT NULL,
  `type_display` smallint UNSIGNED DEFAULT NULL,
  `lastmod` int UNSIGNED DEFAULT NULL,
  `enterdate` datetime(3) DEFAULT NULL,
  `metadata` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orderItems`
--

CREATE TABLE `orderItems` (
  `id` int NOT NULL,
  `agents_id` int NOT NULL,
  `vendors_id` int NOT NULL,
  `terminals_id` int NOT NULL,
  `group_name` varchar(255) DEFAULT NULL,
  `orders_id` int NOT NULL,
  `items_id` int NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cost` float NOT NULL,
  `price` float NOT NULL,
  `notes` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `taxable` int NOT NULL,
  `taxamount` float NOT NULL,
  `group_id` int DEFAULT NULL,
  `itemid` int NOT NULL,
  `discount` float NOT NULL,
  `orderReference` int NOT NULL,
  `itemsAddedDateTime` int NOT NULL,
  `qty` int NOT NULL,
  `lastMod` int NOT NULL,
  `status` int NOT NULL,
  `itemUuid` varchar(255) DEFAULT NULL,
  `orderUuid` varchar(255) DEFAULT NULL,
  `ebt` int DEFAULT NULL,
  `crv` float DEFAULT NULL,
  `crv_taxable` int DEFAULT NULL,
  `labelPrint` int DEFAULT NULL,
  `kitchenPrint` int DEFAULT NULL,
  `weight` float DEFAULT NULL,
  `itemDiscount` float NOT NULL DEFAULT '0',
  `tech_fee_rate` decimal(5,4) DEFAULT NULL,
  `secondary_tax_list` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `orderReference` int NOT NULL,
  `agents_id` int NOT NULL,
  `vendors_id` int NOT NULL,
  `terminals_id` int NOT NULL,
  `subTotal` float NOT NULL,
  `tax` float NOT NULL,
  `total` float NOT NULL,
  `notes` text NOT NULL,
  `orderName` varchar(255) NOT NULL,
  `order_type` varchar(255) DEFAULT NULL,
  `payment_link` varchar(255) DEFAULT NULL,
  `payment_attempt` int DEFAULT NULL,
  `subscription_id` int DEFAULT NULL,
  `employee_id` int NOT NULL,
  `OrderDate` datetime DEFAULT NULL,
  `delivery_type` tinyint NOT NULL,
  `delivery_fee` float NOT NULL DEFAULT '0',
  `status` int NOT NULL,
  `lastMod` int NOT NULL,
  `uuid` varchar(255) DEFAULT NULL,
  `employee_pin` varchar(255) DEFAULT NULL,
  `store_uuid` varchar(36) DEFAULT NULL,
  `terminal_id` int DEFAULT NULL,
  `tip` decimal(10,2) DEFAULT '0.00',
  `tech_fee` decimal(10,2) DEFAULT '0.00',
  `delivery_info` json DEFAULT NULL,
  `customer_info` json DEFAULT NULL,
  `payment_info` json DEFAULT NULL,
  `order_items` json DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `delivery_status` varchar(255) DEFAULT NULL,
  `delivery_service_update_payload` json DEFAULT NULL,
  `prep_time` int DEFAULT '30',
  `pending_order_id` int DEFAULT NULL,
  `new_order_timestamp` int DEFAULT NULL,
  `onlineorder_id` varchar(255) DEFAULT '',
  `onlinetrans_id` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `s3_key` varchar(255) DEFAULT NULL,
  `secondary_tax_list` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `ordersPayments`
--

CREATE TABLE `ordersPayments` (
  `id` int NOT NULL,
  `agents_id` int NOT NULL,
  `vendors_id` int NOT NULL,
  `terminals_id` int NOT NULL,
  `orderReference` int NOT NULL,
  `orderId` varchar(255) NOT NULL,
  `total` float NOT NULL,
  `amtPaid` float NOT NULL,
  `payDate` int NOT NULL,
  `employee_id` int NOT NULL,
  `refund` float NOT NULL,
  `tips` float NOT NULL,
  `techFee` float NOT NULL,
  `refNumber` varchar(255) NOT NULL,
  `status` int NOT NULL,
  `lastMod` int NOT NULL,
  `paymentUuid` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `orderUuid` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `olapayApprovalId` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `employee_pin` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'NONE',
  `originalTotal` float DEFAULT NULL,
  `editTerminalSerial` varchar(255) DEFAULT NULL,
  `editEmployeeId` int DEFAULT NULL,
  `editEmployeePIN` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastmod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `enterdate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pending_orders`
--

CREATE TABLE `pending_orders` (
  `id` int UNSIGNED NOT NULL,
  `content` json NOT NULL,
  `lastmod` bigint DEFAULT NULL,
  `merchant_id` bigint DEFAULT NULL,
  `terminal_serial` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `phinxlog`
--

CREATE TABLE `phinxlog` (
  `version` bigint NOT NULL,
  `migration_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `processing_plans`
--

CREATE TABLE `processing_plans` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `frequency` enum('daily','weekly','monthly','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `processing_window_start` time DEFAULT NULL,
  `processing_window_end` time DEFAULT NULL,
  `max_batch_size` int UNSIGNED NOT NULL DEFAULT '1000',
  `retry_strategy` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quickbooks_export_queue`
--

CREATE TABLE `quickbooks_export_queue` (
  `id` int NOT NULL,
  `vendor_id` int NOT NULL,
  `batchCount` int NOT NULL,
  `payload` text NOT NULL,
  `status` int NOT NULL,
  `lastmod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quickbooks_token_cred`
--

CREATE TABLE `quickbooks_token_cred` (
  `id` int NOT NULL,
  `vendor_id` int UNSIGNED NOT NULL,
  `token_key` text NOT NULL,
  `token_type` text NOT NULL,
  `refresh_token` text NOT NULL,
  `token_expire` bigint UNSIGNED NOT NULL,
  `f5_token_expire` bigint UNSIGNED NOT NULL,
  `token_valid_duration` int UNSIGNED NOT NULL,
  `f5_token_valid_duration` int UNSIGNED NOT NULL,
  `realm_id` text NOT NULL,
  `base_url` text,
  `lastmod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staggered_store_hours`
--

CREATE TABLE `staggered_store_hours` (
  `id` int UNSIGNED NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Business Hour',
  `enterdate` date DEFAULT NULL,
  `lastmod` int UNSIGNED DEFAULT NULL,
  `monday` json DEFAULT NULL,
  `tuesday` json DEFAULT NULL,
  `wednesday` json DEFAULT NULL,
  `thursday` json DEFAULT NULL,
  `friday` json DEFAULT NULL,
  `saturday` json DEFAULT NULL,
  `sunday` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `id` int UNSIGNED NOT NULL,
  `uuid` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  `active_menu_id` int DEFAULT NULL,
  `active_storehour_id` int DEFAULT NULL,
  `enterdate` date DEFAULT NULL,
  `lastmod` int UNSIGNED NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prepare_time` int UNSIGNED DEFAULT '30',
  `timezone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `close_at_once` json DEFAULT NULL,
  `banner` json DEFAULT NULL,
  `techfee_rate` decimal(10,4) DEFAULT NULL,
  `logo` json DEFAULT NULL,
  `delivery_feature` json DEFAULT NULL COMMENT 'From online order'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_hours`
--

CREATE TABLE `store_hours` (
  `id` int UNSIGNED NOT NULL,
  `vendor_id` int DEFAULT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enterdate` date DEFAULT NULL,
  `lastmod` int UNSIGNED NOT NULL,
  `monday_open` time DEFAULT NULL,
  `monday_close` time DEFAULT NULL,
  `tuesday_open` time DEFAULT NULL,
  `tuesday_close` time DEFAULT NULL,
  `wednesday_open` time DEFAULT NULL,
  `wednesday_close` time DEFAULT NULL,
  `thursday_open` time DEFAULT NULL,
  `thursday_close` time DEFAULT NULL,
  `friday_open` time DEFAULT NULL,
  `friday_close` time DEFAULT NULL,
  `saturday_open` time DEFAULT NULL,
  `saturday_close` time DEFAULT NULL,
  `sunday_open` time DEFAULT NULL,
  `sunday_close` time DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Business Hour'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int UNSIGNED NOT NULL,
  `uuid` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int UNSIGNED NOT NULL,
  `vendor_id` int UNSIGNED NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `start_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `next_billing_date` timestamp NULL DEFAULT NULL,
  `end_date` timestamp NULL DEFAULT NULL,
  `payment_method_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_token_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_billing_date` timestamp NULL DEFAULT NULL,
  `billing_period_start` timestamp NOT NULL,
  `billing_period_end` timestamp NULL DEFAULT NULL,
  `cancellation_date` timestamp NULL DEFAULT NULL,
  `cancellation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '1',
  `current_price` decimal(10,2) NOT NULL,
  `billing_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `metadata` json DEFAULT NULL,
  `billing_cycle` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_prorated_first_payment` tinyint(1) NOT NULL DEFAULT '0',
  `has_processed_first_payment` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_payments`
--

CREATE TABLE `subscription_payments` (
  `id` int UNSIGNED NOT NULL,
  `uuid` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscription_id` int UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_payment_methods`
--

CREATE TABLE `subscription_payment_methods` (
  `id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `payment_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_four` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address_id` bigint UNSIGNED DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int UNSIGNED NOT NULL,
  `uuid` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `billing_interval` enum('daily','weekly','monthly','quarterly','annually') COLLATE utf8mb4_unicode_ci NOT NULL,
  `trial_days` int UNSIGNED NOT NULL DEFAULT '0',
  `features` json DEFAULT NULL,
  `interval_count` smallint DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terminals`
--

CREATE TABLE `terminals` (
  `id` int NOT NULL,
  `vendors_id` int NOT NULL,
  `serial` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `onlinestorename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `tech_fee` float NOT NULL DEFAULT '0',
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `enterdate` datetime NOT NULL,
  `lastmod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `lan_ip` varchar(255) DEFAULT NULL,
  `store_uuid` varchar(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terminals_amounts`
--

CREATE TABLE `terminals_amounts` (
  `id` int NOT NULL,
  `terminals_id` int NOT NULL,
  `vendors_id` int NOT NULL,
  `agents_id` int NOT NULL,
  `paydate` int NOT NULL,
  `amount` float NOT NULL,
  `terminals_orders_id` int NOT NULL,
  `lastmod` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terminal_payment_methods`
--

CREATE TABLE `terminal_payment_methods` (
  `id` int UNSIGNED NOT NULL,
  `terminal_id` int DEFAULT NULL,
  `payment_method_id` int UNSIGNED DEFAULT NULL,
  `lastmod` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `enterdate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test`
--

CREATE TABLE `test` (
  `sku` varchar(6) DEFAULT NULL,
  `quantity` varchar(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `tmp_orders_backup`
--

CREATE TABLE `tmp_orders_backup` (
  `id` int NOT NULL,
  `orderReference` int NOT NULL,
  `agents_id` int NOT NULL,
  `vendors_id` int NOT NULL,
  `terminals_id` int NOT NULL,
  `subTotal` float NOT NULL,
  `tax` float NOT NULL,
  `total` float NOT NULL,
  `notes` text NOT NULL,
  `orderName` varchar(255) NOT NULL,
  `order_type` varchar(255) DEFAULT NULL,
  `payment_link` varchar(255) DEFAULT NULL,
  `employee_id` int NOT NULL,
  `OrderDate` datetime DEFAULT NULL,
  `delivery_type` tinyint NOT NULL,
  `delivery_fee` float NOT NULL DEFAULT '0',
  `status` int NOT NULL,
  `lastMod` int NOT NULL,
  `uuid` varchar(255) DEFAULT NULL,
  `employee_pin` varchar(255) DEFAULT NULL,
  `store_uuid` varchar(36) DEFAULT NULL,
  `terminal_id` int DEFAULT NULL,
  `tip` decimal(10,2) DEFAULT '0.00',
  `tech_fee` decimal(10,2) DEFAULT '0.00',
  `delivery_info` json DEFAULT NULL,
  `customer_info` json DEFAULT NULL,
  `payment_info` json DEFAULT NULL,
  `order_items` json DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `delivery_status` varchar(255) DEFAULT NULL,
  `delivery_service_update_payload` json DEFAULT NULL,
  `prep_time` int DEFAULT '30',
  `pending_order_id` int DEFAULT NULL,
  `new_order_timestamp` int DEFAULT NULL,
  `onlineorder_id` varchar(255) DEFAULT '',
  `onlinetrans_id` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED DEFAULT NULL,
  `uuid` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscription_id` int UNSIGNED NOT NULL,
  `batch_item_id` bigint UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `status` enum('pending','processing','success','failed','refunded','partially_refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `transaction_type` enum('charge','refund','credit') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'charge',
  `payment_processor` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `processor_transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processor_response_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processor_response_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `unique_olapay_transactions`
--

CREATE TABLE `unique_olapay_transactions` (
  `id` int UNSIGNED NOT NULL,
  `serial` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `lastmod` bigint NOT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `trans_date` varchar(50) DEFAULT NULL,
  `trans_id` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `trans_type` varchar(50) GENERATED ALWAYS AS (json_unquote(json_extract(`content`,_utf8mb4'$.trans_type'))) STORED,
  `amount` decimal(10,2) GENERATED ALWAYS AS (cast(json_unquote(json_extract(`content`,_utf8mb4'$.amount')) as decimal(10,2))) STORED,
  `status` varchar(50) GENERATED ALWAYS AS (json_unquote(json_extract(`content`,_utf8mb4'$.Status'))) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_customer_subscription_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_customer_subscription_summary` (
`active_subscriptions` decimal(23,0)
,`customer_id` int
,`email` text
,`first_name` text
,`last_name` text
,`total_subscriptions` bigint
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_subscriptions_due_next_24h`
-- (See below for the actual view)
--
CREATE TABLE `vw_subscriptions_due_next_24h` (
);

-- --------------------------------------------------------

--
-- Structure for view `vw_customer_subscription_summary`
--
DROP TABLE IF EXISTS `vw_customer_subscription_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`api2`@`%` SQL SECURITY DEFINER VIEW `vw_customer_subscription_summary`  AS SELECT `c`.`id` AS `customer_id`, `c`.`email` AS `email`, `c`.`first_name` AS `first_name`, `c`.`last_name` AS `last_name`, count(`s`.`id`) AS `total_subscriptions`, sum((case when (`s`.`status` = 'active') then 1 else 0 end)) AS `active_subscriptions` FROM (`customer` `c` left join `subscriptions` `s` on((`c`.`id` = `s`.`customer_id`))) GROUP BY `c`.`id`, `c`.`email`, `c`.`first_name`, `c`.`last_name` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_subscriptions_due_next_24h`
--
DROP TABLE IF EXISTS `vw_subscriptions_due_next_24h`;

CREATE ALGORITHM=UNDEFINED DEFINER=`api2`@`%` SQL SECURITY DEFINER VIEW `vw_subscriptions_due_next_24h`  AS SELECT `s`.`id` AS `id`, `s`.`uuid` AS `uuid`, `s`.`customer_id` AS `customer_id`, `s`.`subscription_plan_id` AS `subscription_plan_id`, `s`.`status` AS `status`, `s`.`started_at` AS `started_at`, `s`.`next_billing_date` AS `next_billing_date`, `s`.`ended_at` AS `ended_at`, `s`.`payment_method_id` AS `payment_method_id`, `s`.`created_at` AS `created_at`, `s`.`updated_at` AS `updated_at` FROM `subscriptions` AS `s` WHERE ((`s`.`status` = 'active') AND (`s`.`next_billing_date` between now() and (now() + interval 24 hour))) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_accounts_id` (`id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `batches`
--
ALTER TABLE `batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_batch_uuid` (`uuid`),
  ADD KEY `idx_batch_status` (`status`),
  ADD KEY `idx_batch_scheduled` (`scheduled_time`),
  ADD KEY `idx_batch_processing_plan` (`processing_plan_id`);

--
-- Indexes for table `batch_items`
--
ALTER TABLE `batch_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_batch_subscription` (`batch_id`,`subscription_id`),
  ADD KEY `idx_batch_item_batch` (`batch_id`),
  ADD KEY `idx_batch_item_subscription` (`subscription_id`),
  ADD KEY `idx_batch_item_status` (`status`),
  ADD KEY `idx_batch_item_composite` (`batch_id`,`status`,`subscription_id`);

--
-- Indexes for table `billing_address`
--
ALTER TABLE `billing_address`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `billing_token`
--
ALTER TABLE `billing_token`
  ADD PRIMARY KEY (`id`),
  ADD KEY `billing_address_id` (`billing_address_id`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `group_menu`
--
ALTER TABLE `group_menu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_group_menu_mapping` (`second_column`,`first_column`);

--
-- Indexes for table `inventories`
--
ALTER TABLE `inventories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vendors_id_2` (`vendors_id`,`sku`),
  ADD KEY `vendors_id` (`vendors_id`);

--
-- Indexes for table `inventoryLogs`
--
ALTER TABLE `inventoryLogs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventoryId` (`inventoryId`);

--
-- Indexes for table `invoice`
--
ALTER TABLE `invoice`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_items_vendor_uuid` (`vendors_id`,`uuid`);

--
-- Indexes for table `item_group`
--
ALTER TABLE `item_group`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_group_mapping` (`first_column`,`second_column`);

--
-- Indexes for table `json`
--
ALTER TABLE `json`
  ADD PRIMARY KEY (`id`),
  ADD KEY `serial` (`serial`),
  ADD KEY `idx_lastmod_id` (`lastmod`,`id`);

--
-- Indexes for table `jsonOlaPay`
--
ALTER TABLE `jsonOlaPay`
  ADD PRIMARY KEY (`id`),
  ADD KEY `serial` (`serial`),
  ADD KEY `idx_jsonOlaPay_serial_lastmod` (`serial`,`lastmod`);

--
-- Indexes for table `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `menu_store`
--
ALTER TABLE `menu_store`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_notifications_uuid` (`uuid`),
  ADD KEY `idx_notifications_vendor` (`vendor_id`),
  ADD KEY `idx_notifications_store` (`store_uuid`),
  ADD KEY `idx_notifications_type` (`type`),
  ADD KEY `idx_notifications_status` (`status`),
  ADD KEY `idx_notifications_priority` (`priority`),
  ADD KEY `idx_notifications_sent_at` (`sent_at`),
  ADD KEY `idx_notifications_created_at` (`created_at`);

--
-- Indexes for table `ol_orders`
--
ALTER TABLE `ol_orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ol_pending_orders`
--
ALTER TABLE `ol_pending_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_id` (`id`);

--
-- Indexes for table `online_order_groups`
--
ALTER TABLE `online_order_groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orderItems`
--
ALTER TABLE `orderItems`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agents_id` (`agents_id`,`vendors_id`,`terminals_id`,`orders_id`,`items_id`,`group_id`),
  ADD KEY `description` (`description`),
  ADD KEY `notes` (`notes`),
  ADD KEY `idx_orderitems_ordersid` (`orders_id`),
  ADD KEY `idx_orderitems_orderuuid` (`orderUuid`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orders_lastmod_vendors` (`lastMod`,`vendors_id`),
  ADD KEY `idx_orders_lastmod_agents` (`lastMod`,`agents_id`),
  ADD KEY `idx_orders_lastmod_terminals` (`lastMod`,`terminals_id`),
  ADD KEY `idx_orders_uuid` (`uuid`),
  ADD KEY `idx_orders_lastmod` (`lastMod`),
  ADD KEY `idx_orders_uuid_lastmod` (`uuid`,`lastMod` DESC);

--
-- Indexes for table `ordersPayments`
--
ALTER TABLE `ordersPayments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agents_id` (`agents_id`,`vendors_id`,`terminals_id`,`orderReference`,`orderId`),
  ADD KEY `refNumber` (`refNumber`),
  ADD KEY `idx_orderspayments_orderref` (`orderReference`),
  ADD KEY `idx_orderspayments_olapay_approval_id` (`olapayApprovalId`),
  ADD KEY `idx_orderspayments_terminals_olapay` (`terminals_id`,`olapayApprovalId`),
  ADD KEY `idx_orderspayments_orderref_uuid_lastmod` (`orderReference`,`paymentUuid`,`lastMod` DESC),
  ADD KEY `idx_op_approval_lastmod` (`olapayApprovalId`,`lastMod`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `pending_orders`
--
ALTER TABLE `pending_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_id` (`id`);

--
-- Indexes for table `phinxlog`
--
ALTER TABLE `phinxlog`
  ADD PRIMARY KEY (`version`);

--
-- Indexes for table `processing_plans`
--
ALTER TABLE `processing_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_processing_active` (`is_active`);

--
-- Indexes for table `quickbooks_export_queue`
--
ALTER TABLE `quickbooks_export_queue`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `quickbooks_token_cred`
--
ALTER TABLE `quickbooks_token_cred`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `staggered_store_hours`
--
ALTER TABLE `staggered_store_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_id` (`id`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_id` (`id`);

--
-- Indexes for table `store_hours`
--
ALTER TABLE `store_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_id` (`id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD KEY `idx_subscriptions_customer_id` (`customer_id`),
  ADD KEY `idx_subscriptions_status` (`status`),
  ADD KEY `idx_subscription_date_range` (`next_billing_date`,`status`),
  ADD KEY `idx_subscription_updated` (`updated_at`),
  ADD KEY `billing_token_id` (`billing_token_id`);

--
-- Indexes for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD KEY `idx_subscription_payments_subscription_id` (`subscription_id`),
  ADD KEY `idx_subscription_payments_status` (`status`),
  ADD KEY `idx_subscription_payments_date` (`payment_date`);

--
-- Indexes for table `subscription_payment_methods`
--
ALTER TABLE `subscription_payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_spm_customer_id` (`customer_id`),
  ADD KEY `idx_spm_billing_address_id` (`billing_address_id`),
  ADD KEY `idx_spm_payment_type` (`payment_type`),
  ADD KEY `idx_spm_is_default` (`is_default`),
  ADD KEY `idx_spm_status` (`status`),
  ADD KEY `idx_spm_created_at` (`created_at`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`);

--
-- Indexes for table `terminals`
--
ALTER TABLE `terminals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_terminals_vendors_id_serial` (`vendors_id`,`serial`,`onlinestorename`),
  ADD KEY `idx_terminals_serial` (`serial`),
  ADD KEY `idx_terminals_serial_id` (`serial`,`id`),
  ADD KEY `idx_terminals_id` (`id`),
  ADD KEY `idx_terminals_vendors_serial` (`vendors_id`,`serial`),
  ADD KEY `idx_terminals_serial_description` (`serial`,`description`);

--
-- Indexes for table `terminals_amounts`
--
ALTER TABLE `terminals_amounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `terminal_payment_methods`
--
ALTER TABLE `terminal_payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `terminal_id` (`terminal_id`,`payment_method_id`),
  ADD KEY `system_id` (`payment_method_id`);

--
-- Indexes for table `tmp_orders_backup`
--
ALTER TABLE `tmp_orders_backup`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_transaction_uuid` (`uuid`),
  ADD KEY `idx_transaction_subscription` (`subscription_id`),
  ADD KEY `idx_transaction_batch_item` (`batch_item_id`),
  ADD KEY `idx_transaction_status` (`status`),
  ADD KEY `idx_transaction_created` (`created_at`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_transaction_date_amount` (`created_at`,`amount`,`status`),
  ADD KEY `idx_transaction_order` (`order_id`);

--
-- Indexes for table `unique_olapay_transactions`
--
ALTER TABLE `unique_olapay_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_transaction` (`serial`,`order_id`,`trans_date`,`trans_id`),
  ADD KEY `idx_serial` (`serial`),
  ADD KEY `idx_lastmod` (`lastmod`),
  ADD KEY `idx_trans_id` (`trans_id`),
  ADD KEY `idx_uot_trans_type` (`trans_type`),
  ADD KEY `idx_uot_amount` (`amount`),
  ADD KEY `idx_uot_status` (`status`),
  ADD KEY `idx_uot_lastmod_amount` (`lastmod`,`amount`),
  ADD KEY `idx_uot_serial_lastmod_status_type` (`serial`,`lastmod`,`status`,`trans_type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `batches`
--
ALTER TABLE `batches`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `batch_items`
--
ALTER TABLE `batch_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_address`
--
ALTER TABLE `billing_address`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_token`
--
ALTER TABLE `billing_token`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_menu`
--
ALTER TABLE `group_menu`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventories`
--
ALTER TABLE `inventories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventoryLogs`
--
ALTER TABLE `inventoryLogs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice`
--
ALTER TABLE `invoice`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_group`
--
ALTER TABLE `item_group`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `json`
--
ALTER TABLE `json`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jsonOlaPay`
--
ALTER TABLE `jsonOlaPay`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menus`
--
ALTER TABLE `menus`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_store`
--
ALTER TABLE `menu_store`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ol_orders`
--
ALTER TABLE `ol_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ol_pending_orders`
--
ALTER TABLE `ol_pending_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `online_order_groups`
--
ALTER TABLE `online_order_groups`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orderItems`
--
ALTER TABLE `orderItems`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ordersPayments`
--
ALTER TABLE `ordersPayments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pending_orders`
--
ALTER TABLE `pending_orders`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `processing_plans`
--
ALTER TABLE `processing_plans`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quickbooks_export_queue`
--
ALTER TABLE `quickbooks_export_queue`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quickbooks_token_cred`
--
ALTER TABLE `quickbooks_token_cred`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staggered_store_hours`
--
ALTER TABLE `staggered_store_hours`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_hours`
--
ALTER TABLE `store_hours`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_payment_methods`
--
ALTER TABLE `subscription_payment_methods`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terminals`
--
ALTER TABLE `terminals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terminals_amounts`
--
ALTER TABLE `terminals_amounts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terminal_payment_methods`
--
ALTER TABLE `terminal_payment_methods`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tmp_orders_backup`
--
ALTER TABLE `tmp_orders_backup`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `unique_olapay_transactions`
--
ALTER TABLE `unique_olapay_transactions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `batches`
--
ALTER TABLE `batches`
  ADD CONSTRAINT `batches_ibfk_1` FOREIGN KEY (`processing_plan_id`) REFERENCES `processing_plans` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `batch_items`
--
ALTER TABLE `batch_items`
  ADD CONSTRAINT `batch_items_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `batch_items_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `billing_token`
--
ALTER TABLE `billing_token`
  ADD CONSTRAINT `billing_token_ibfk_1` FOREIGN KEY (`billing_address_id`) REFERENCES `billing_address` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD CONSTRAINT `subscription_payments_ibfk_1` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `terminal_payment_methods`
--
ALTER TABLE `terminal_payment_methods`
  ADD CONSTRAINT `terminal_payment_methods_ibfk_1` FOREIGN KEY (`terminal_id`) REFERENCES `terminals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `terminal_payment_methods_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`batch_item_id`) REFERENCES `batch_items` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
