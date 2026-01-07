-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 09, 2025 at 04:12 AM
-- Server version: 8.0.40-0ubuntu0.22.04.1
-- PHP Version: 8.1.2-1ubuntu2.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `online_order_prod`
--

-- --------------------------------------------------------

--
-- Table structure for table `group_menu`
--

CREATE TABLE `group_menu` (
  `id` int NOT NULL,
  `first_column` varchar(36) DEFAULT NULL,
  `second_column` varchar(36) DEFAULT NULL,
  `lastmod` int UNSIGNED DEFAULT NULL,
  `enterdate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice`
--

CREATE TABLE `invoice` (
  `id` int NOT NULL,
  `ref` varchar(255) NOT NULL,
  `profile_id` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text,
  `uuid` varchar(36) DEFAULT NULL,
  `price_val` int DEFAULT NULL,
  `price_currency` varchar(3) DEFAULT 'USD',
  `is_active` tinyint(1) DEFAULT NULL,
  `type_display` tinyint UNSIGNED DEFAULT NULL,
  `enterdate` date DEFAULT NULL,
  `lastmod` int UNSIGNED DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `is_taxable` tinyint(1) DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  `group_belong_type` tinyint UNSIGNED DEFAULT NULL,
  `tax_rate` decimal(6,4) DEFAULT NULL,
  `available_amount` int DEFAULT '0',
  `upc` varchar(255) DEFAULT NULL,
  `image_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `print_type` int DEFAULT '0',
  `is_ebt` tinyint(1) DEFAULT '0',
  `is_manual_price` tinyint(1) DEFAULT '0',
  `is_weighted` tinyint(1) DEFAULT '0',
  `crv` json DEFAULT NULL,
  `sku` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_group`
--

CREATE TABLE `item_group` (
  `id` int NOT NULL,
  `first_column` varchar(36) DEFAULT NULL,
  `second_column` varchar(36) DEFAULT NULL,
  `lastmod` int UNSIGNED DEFAULT NULL,
  `enterdate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

CREATE TABLE `menus` (
  `id` int NOT NULL,
  `uuid` varchar(36) DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `enterdate` date DEFAULT NULL,
  `lastmod` int UNSIGNED DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `num_stores` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_store`
--

CREATE TABLE `menu_store` (
  `id` int UNSIGNED NOT NULL,
  `first_column` char(36) DEFAULT NULL,
  `second_column` char(36) DEFAULT NULL,
  `lastmod` int UNSIGNED DEFAULT NULL,
  `enterdate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `online_order_groups`
--

CREATE TABLE `online_order_groups` (
  `id` int NOT NULL,
  `group_type` tinyint UNSIGNED DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text,
  `uuid` varchar(36) NOT NULL,
  `is_active` tinyint(1) DEFAULT NULL,
  `type_display` tinyint UNSIGNED DEFAULT NULL,
  `enterdate` date DEFAULT NULL,
  `lastmod` int UNSIGNED DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  `metadata` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
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
-- Table structure for table `pending_orders`
--

CREATE TABLE `pending_orders` (
  `id` int NOT NULL,
  `content` json NOT NULL,
  `lastmod` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staggered_store_hours`
--

CREATE TABLE `staggered_store_hours` (
  `id` int NOT NULL,
  `uuid` char(36) DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `id` int NOT NULL,
  `uuid` varchar(36) DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  `active_menu_id` int DEFAULT NULL,
  `active_storehour_id` int DEFAULT NULL,
  `enterdate` date DEFAULT NULL,
  `lastmod` int UNSIGNED NOT NULL,
  `address` text,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `prepare_time` int UNSIGNED DEFAULT '30',
  `timezone` varchar(255) DEFAULT NULL,
  `close_at_once` json DEFAULT NULL,
  `banner` json DEFAULT NULL,
  `logo` json DEFAULT NULL,
  `delivery_feature` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `group_menu`
--
ALTER TABLE `group_menu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_group_menu_first_column` (`first_column`),
  ADD KEY `fk_group_menu_second_column` (`second_column`);

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
  ADD UNIQUE KEY `uuid` (`uuid`);

--
-- Indexes for table `item_group`
--
ALTER TABLE `item_group`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_item_group_first_column` (`first_column`),
  ADD KEY `fk_item_group_second_column` (`second_column`);

--
-- Indexes for table `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`);

--
-- Indexes for table `menu_store`
--
ALTER TABLE `menu_store`
  ADD PRIMARY KEY (`id`),
  ADD KEY `first_column` (`first_column`),
  ADD KEY `second_column` (`second_column`);

--
-- Indexes for table `online_order_groups`
--
ALTER TABLE `online_order_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pending_orders`
--
ALTER TABLE `pending_orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staggered_store_hours`
--
ALTER TABLE `staggered_store_hours`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `group_menu`
--
ALTER TABLE `group_menu`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice`
--
ALTER TABLE `invoice`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_group`
--
ALTER TABLE `item_group`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menus`
--
ALTER TABLE `menus`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_store`
--
ALTER TABLE `menu_store`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `online_order_groups`
--
ALTER TABLE `online_order_groups`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pending_orders`
--
ALTER TABLE `pending_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staggered_store_hours`
--
ALTER TABLE `staggered_store_hours`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `group_menu`
--
ALTER TABLE `group_menu`
  ADD CONSTRAINT `fk_group_menu_first_column` FOREIGN KEY (`first_column`) REFERENCES `online_order_groups` (`uuid`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_group_menu_second_column` FOREIGN KEY (`second_column`) REFERENCES `menus` (`uuid`) ON DELETE CASCADE;

--
-- Constraints for table `item_group`
--
ALTER TABLE `item_group`
  ADD CONSTRAINT `fk_item_group_first_column` FOREIGN KEY (`first_column`) REFERENCES `items` (`uuid`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_item_group_second_column` FOREIGN KEY (`second_column`) REFERENCES `online_order_groups` (`uuid`) ON DELETE CASCADE;

--
-- Constraints for table `menu_store`
--
ALTER TABLE `menu_store`
  ADD CONSTRAINT `menu_store_ibfk_1` FOREIGN KEY (`first_column`) REFERENCES `menus` (`uuid`) ON DELETE CASCADE,
  ADD CONSTRAINT `menu_store_ibfk_2` FOREIGN KEY (`second_column`) REFERENCES `stores` (`uuid`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
