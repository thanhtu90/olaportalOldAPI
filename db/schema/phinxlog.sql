-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 24, 2025 at 06:08 AM
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
-- Database: `api2`
--

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

--
-- Dumping data for table `phinxlog`
--

INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`) VALUES
(20240817050935, 'InitMigration', '2024-08-17 12:25:01', '2024-08-17 12:25:04', 0),
(20240817072044, 'OrderUuidColumnMigration', '2024-08-23 05:22:05', '2024-08-23 05:22:05', 0),
(20240818162541, 'OrderEmployeePinColumnMigration', '2024-08-23 05:22:05', '2024-08-23 05:22:05', 0),
(20240818163524, 'OrderItemUuidColumnMigration', '2024-08-23 05:22:05', '2024-08-23 05:22:05', 0),
(20240818173921, 'OrderItemsAddColumnEbtMigration', '2024-08-23 05:22:05', '2024-08-23 05:22:05', 0),
(20240818174204, 'OrderItemsAddColumnCrvMigration', '2024-08-23 05:22:05', '2024-08-23 05:22:05', 0),
(20240818174254, 'OrderItemsAddColumnCrvTaxableMigration', '2024-08-23 05:22:05', '2024-08-23 05:22:05', 0),
(20240818174353, 'OrderItemsAddColumnLabelPrintMigration', '2024-08-23 05:22:05', '2024-08-23 05:22:06', 0),
(20240818174438, 'OrderItemsAddColumnKitechnPrintMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240818174935, 'OrderItemsAddColumnWeightMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240821052355, 'MergeOnlineVendorToAccountMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240821052754, 'GroupMenuMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240821053429, 'InvoiceMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240821053859, 'OnlineItemsToItemsMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240821072120, 'ItemGroupMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240821072229, 'MenuMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240821072435, 'MenuStoreMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240821072538, 'OnlineOrderGroupMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240821073227, 'OrdersMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240821073746, 'PendingOrderMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240821073933, 'StaggeredStoreHoursMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240821074032, 'StoresMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240821074129, 'StoreHoursMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20240822030957, 'OrderPaymentUuidMigration', '2024-08-23 05:22:06', '2024-08-23 05:22:06', 0),
(20241217050633, 'TerminalPaymentMethodMigration', '2024-12-17 08:16:11', '2024-12-17 08:16:11', 0),
(20241217084326, 'TerminalAlterColumnSystemMigration', '2024-12-17 08:47:00', '2024-12-17 08:47:00', 0),
(20250109140405, 'ModifyExistingTablesForOnlineOrderMigration', '2025-01-23 11:46:08', '2025-01-23 11:46:10', 0),
(20250121123914, 'OnlineOrders', '2025-01-23 11:46:10', '2025-01-23 11:46:10', 0),
(20250121123915, 'OnlinePendingOrders', '2025-01-23 11:46:10', '2025-01-23 11:46:10', 0),
(20250121123916, 'AddVendorIdToOnlineOrderGroups', '2025-01-23 11:46:10', '2025-01-23 11:46:10', 0),
(20250121123917, 'AddSkuToItemsTable', '2025-01-23 11:46:10', '2025-01-23 11:46:10', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `phinxlog`
--
ALTER TABLE `phinxlog`
  ADD PRIMARY KEY (`version`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
