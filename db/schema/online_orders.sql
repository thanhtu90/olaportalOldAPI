-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 21, 2025 at 04:26 AM
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
-- Database: `test_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `online_orders`
--

CREATE TABLE `online_orders` (
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

--
-- Indexes for dumped tables
--

--
-- Indexes for table `online_orders`
--
ALTER TABLE `online_orders`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `online_orders`
--
ALTER TABLE `online_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
