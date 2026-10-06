-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 06, 2026 at 01:16 AM
-- Server version: 9.1.0
-- PHP Version: 8.4.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bistro_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `log_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_date` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `entity_type`, `entity_id`, `created_at`) VALUES
(3, 4, 'Mencipta akaun kakitangan dengan role Cashier', 'users', 8, '2026-10-04 02:23:47'),
(4, 4, 'Mencipta akaun kakitangan dengan role Kitchen', 'users', 9, '2026-10-04 02:24:22'),
(5, 8, 'Kemaskini status bayaran: Belum Dibayar -> Berjaya', 'payments', 6, '2026-10-04 11:54:12'),
(6, 8, 'Mengeluarkan resit BAB-R-9CAF8E080301', 'receipts', 1, '2026-10-04 11:54:12'),
(7, 9, 'Kemaskini status pesanan: Menunggu -> Sedang Disediakan', 'orders', 6, '2026-10-05 12:07:48'),
(8, 9, 'Kemaskini status pesanan: Menunggu -> Sedang Disediakan', 'orders', 19, '2026-10-05 12:59:46'),
(9, 9, 'Kemaskini status pesanan: Sedang Disediakan -> Sedia Diambil', 'orders', 19, '2026-10-05 13:00:11'),
(10, 8, 'Kemaskini status bayaran: Menunggu Pengesahan -> Berjaya', 'payments', 19, '2026-10-05 13:04:16'),
(11, 8, 'Mengeluarkan resit BAB-R-3D0F45B9B565', 'receipts', 13, '2026-10-05 13:04:16'),
(12, 9, 'Kemaskini status pesanan: Sedang Disediakan -> Sedia Diambil', 'orders', 6, '2026-10-05 15:08:16'),
(13, 9, 'Kemaskini status pesanan: Menunggu -> Sedang Disediakan', 'orders', 20, '2026-10-06 01:05:42'),
(14, 9, 'Kemaskini status pesanan: Sedang Disediakan -> Sedia Diambil', 'orders', 20, '2026-10-06 08:15:12'),
(15, NULL, 'Pelayan menandakan pesanan sebagai Diserahkan', 'orders', 19, '2026-10-06 08:27:06'),
(16, NULL, 'Pelayan menandakan pesanan sebagai Diserahkan', 'orders', 20, '2026-10-06 08:27:10'),
(17, NULL, 'Pelayan menandakan pesanan sebagai Diserahkan', 'orders', 6, '2026-10-06 08:27:46'),
(18, 9, 'Kemaskini status pesanan: Menunggu -> Sedang Disediakan', 'orders', 22, '2026-10-06 08:32:37'),
(19, 9, 'Kemaskini status pesanan: Sedang Disediakan -> Sedia Diambil', 'orders', 22, '2026-10-06 08:35:20'),
(20, NULL, 'Pelayan menandakan pesanan sebagai Diserahkan', 'orders', 22, '2026-10-06 08:36:05'),
(21, 8, 'Kemaskini status pesanan: Diserahkan -> Selesai', 'orders', 22, '2026-10-06 08:36:17'),
(22, 8, 'Kemaskini status pesanan: Diserahkan -> Selesai', 'orders', 6, '2026-10-06 08:36:39'),
(23, 8, 'Kemaskini status pesanan: Diserahkan -> Selesai', 'orders', 19, '2026-10-06 08:36:47'),
(24, 8, 'Tandakan meja sebagai kosong', 'restaurant_tables', 1, '2026-10-06 08:38:22'),
(25, 8, 'Tandakan meja sebagai kosong', 'restaurant_tables', 3, '2026-10-06 08:38:23');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uk_categories_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `is_active`, `created_at`) VALUES
(1, 'Set', 1, '2026-10-04 01:08:30'),
(2, 'Western', 1, '2026-10-04 01:08:30'),
(3, 'Mee', 1, '2026-10-04 01:08:30'),
(4, 'Minuman', 1, '2026-10-04 01:08:30'),
(5, 'Dessert', 1, '2026-10-04 01:08:30'),
(6, 'Snek', 1, '2026-10-04 01:08:30');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE IF NOT EXISTS `expenses` (
  `expense_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `expense_category_id` int UNSIGNED NOT NULL,
  `recorded_by` int UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expense_date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`expense_id`),
  KEY `idx_expense_category` (`expense_category_id`),
  KEY `idx_expense_date` (`expense_date`),
  KEY `fk_expenses_user` (`recorded_by`)
) ;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`expense_id`, `expense_category_id`, `recorded_by`, `amount`, `description`, `expense_date`, `created_at`) VALUES
(1, 1, 8, 485.60, 'Pembelian stok ayam, beras dan sayur · INV-260903-1842', '2026-09-03', '2026-09-03 09:15:00'),
(2, 2, 8, 318.75, 'Bil elektrik premis · INV-TNB-260915', '2026-09-15', '2026-09-15 09:15:00'),
(3, 3, 8, 165.00, 'Servis berkala peti sejuk dapur · WO-260927', '2026-09-27', '2026-09-27 09:15:00');

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `expense_category_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`expense_category_id`),
  UNIQUE KEY `uk_expense_category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`expense_category_id`, `category_name`) VALUES
(1, 'Bahan Mentah'),
(4, 'Gaji'),
(5, 'Lain-lain'),
(3, 'Penyelenggaraan'),
(2, 'Utiliti');

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

DROP TABLE IF EXISTS `menu_items`;
CREATE TABLE IF NOT EXISTS `menu_items` (
  `item_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` int UNSIGNED NOT NULL,
  `item_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`),
  KEY `fk_menu_category` (`category_id`)
) ;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`item_id`, `category_id`, `item_name`, `description`, `price`, `image_path`, `is_available`, `created_at`, `updated_at`) VALUES
(1, 1, 'Nasi Ayam', 'Ayam goreng rangup dihidangkan bersama nasi putih, sambal dan timun segar.', 12.00, 'images/foods/set/Nasi Ayam.png', 1, '2026-10-04 01:08:30', '2026-10-04 01:23:10'),
(2, 1, 'Nasi Lemak Ayam Goreng Berempah', 'Nasi lemak beras basmati beraroma santan dan daun pandan, dihidangkan bersama sambal tumis pedas manis, ayam goreng berempah, telur rebus, kacang, dan ikan bilis.', 22.00, 'images/foods/set/Nasi Lemak Ayam Goreng Berempah.png', 1, '2026-10-04 01:08:30', '2026-10-04 01:23:10'),
(3, 1, 'Nasi Daging Harimau Menangis', 'Nasi putih lembut dihidangkan bersama daging lembu bakar empuk, air asam utara yang padu, ulam-ulaman segar, dan sup kosong.', 28.00, 'images/foods/set/Nasi Daging Harimau Menangis.png', 1, '2026-10-04 01:08:30', '2026-10-04 01:23:10'),
(4, 1, 'Chicken Chop Crispy Sos Lada Hitam', 'Kepingan paha ayam digoreng rangup, disiram sos lada hitam pekat, dihidangkan bersama kentang goreng dan coleslaw.', 24.00, 'images/foods/set/Chicken Chop Crispy Sos Lada Hitam.png', 1, '2026-10-04 01:08:30', '2026-10-04 01:23:10'),
(5, 4, 'Iced Sparkling Berry Lemonade', 'Minuman soda berkarbonat segar digabungkan dengan pati beri dan perahan jus lemon segar.', 16.00, 'images/foods/drinks/Iced Sparkling Berry Lemonade.png', 1, '2026-10-04 01:23:10', '2026-10-04 01:23:10'),
(6, 4, 'Matcha Espresso Latte', 'Gabungan matcha Jepun premium, susu segar, dan satu shot kopi espresso.', 18.00, 'images/foods/drinks/Matcha Espresso Latte.png', 1, '2026-10-04 01:23:10', '2026-10-04 01:23:10'),
(7, 4, 'Classic Iced Peach Tea', 'Teh ais segar dengan rasa buah pic manis dan hirisan buah segar.', 14.00, 'images/foods/drinks/Classic Iced Peach Tea.png', 1, '2026-10-04 01:23:10', '2026-10-04 01:23:10'),
(8, 4, 'B@B Signature Chocolate', 'Minuman coklat pekat panas atau ais yang dibuat daripada coklat artisanal premium.', 16.00, 'images/foods/drinks/B@B Signature Chocolate.png', 1, '2026-10-04 01:23:10', '2026-10-04 01:23:10'),
(9, 5, 'Classic Crème Brûlée', 'Kastard vanila lembut dengan lapisan gula karamel rangup yang dibakar di bahagian atas.', 24.00, 'images/foods/desserts/Classic Crème Brûlée.png', 1, '2026-10-04 01:23:10', '2026-10-04 01:23:10'),
(10, 5, 'Warm Apple Tarte Tatin', 'Pai epal karamel gaya Perancis dengan pastri rangup, dihidangkan bersama gelato vanila.', 26.00, 'images/foods/desserts/Warm Apple Tarte Tatin.png', 1, '2026-10-04 01:23:10', '2026-10-04 01:23:10'),
(11, 5, 'Dark Chocolate Mousse', 'Mousse coklat gelap 70 peratus yang kaya dan gebu, ditaburi garam laut dan krim chantilly.', 22.00, 'images/foods/desserts/Dark Chocolate Mousse.png', 1, '2026-10-04 01:23:10', '2026-10-04 01:23:10'),
(12, 5, 'Churros Sos Coklat', 'Churros goreng panas dan rangup ditabur gula kayu manis, dihidangkan bersama sos celupan coklat pekat.', 18.00, 'images/foods/desserts/Churros Sos Coklat.png', 1, '2026-10-04 01:23:10', '2026-10-04 01:23:10'),
(13, 6, 'French Onion Soup', 'Sup bawang Perancis kaya rasa, dihidangkan bersama roti bakar dan keju Gruyère leleh.', 14.00, 'images/foods/snack/French Onion Soup.png', 1, '2026-10-04 01:23:10', '2026-10-04 01:23:10'),
(14, 6, 'Whipped Ricotta Sourdough', 'Keju ricotta gebu disiram madu dan minyak zaitun, dihidangkan bersama roti sourdough bakar.', 32.00, 'images/foods/snack/Whipped Ricotta Sourdough.png', 1, '2026-10-04 01:23:10', '2026-10-04 01:23:10'),
(15, 6, 'Escargots à la Bourguignonne', 'Siput escargot dimasak dalam mentega bawang putih dan herba segar, dihidangkan bersama roti Perancis bakar.', 38.00, 'images/foods/snack/Escargots à la Bourguignonne.png', 1, '2026-10-04 01:23:10', '2026-10-04 01:23:10'),
(16, 6, 'Crispy Truffle Fries', 'Kentang goreng rangup ditaburi minyak truffle, keju parmesan, dan herba parsley.', 22.00, 'images/foods/snack/Crispy Truffle Fries.png', 1, '2026-10-04 01:23:10', '2026-10-04 01:23:10');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_id` bigint UNSIGNED NOT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `order_status` enum('Menunggu','Sedang Disediakan','Sedia Diambil','Diserahkan','Selesai','Dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Menunggu',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `uk_orders_number` (`order_number`),
  KEY `idx_orders_session` (`session_id`),
  KEY `idx_orders_status` (`order_status`),
  KEY `idx_orders_created` (`created_at`),
  KEY `fk_orders_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `order_number`, `session_id`, `created_by`, `order_status`, `notes`, `created_at`, `completed_at`) VALUES
(6, 'BAB-CC500A5D81B0CDC4', 6, NULL, 'Selesai', '', '2026-10-04 11:46:56', '2026-10-06 08:36:39'),
(7, 'BAB-6F8A1C203D5E7B91', 7, NULL, 'Selesai', NULL, '2026-09-03 12:18:00', '2026-09-03 12:46:00'),
(8, 'BAB-2D7C9A416E03B852', 8, NULL, 'Selesai', NULL, '2026-09-09 18:42:00', '2026-09-09 19:10:00'),
(9, 'BAB-A1B2C3D4E5F60718', 9, NULL, 'Selesai', NULL, '2026-09-17 13:07:00', '2026-09-17 13:35:00'),
(10, 'BAB-5E9F0A3C7B2D6148', 10, NULL, 'Selesai', NULL, '2026-09-28 20:11:00', '2026-09-28 20:39:00'),
(11, 'BAB-8A10C405D7E29F31', 11, NULL, 'Selesai', NULL, '2026-10-05 12:14:00', '2026-10-05 12:42:00'),
(12, 'BAB-3D7F20A6C94B185E', 12, NULL, 'Selesai', NULL, '2026-10-06 19:08:00', '2026-10-06 19:36:00'),
(13, 'BAB-61E84A0D3B7295C6', 13, NULL, 'Selesai', NULL, '2026-10-07 13:22:00', '2026-10-07 13:50:00'),
(14, 'BAB-A56C20D14E738B92', 14, NULL, 'Selesai', NULL, '2026-10-08 20:02:00', '2026-10-08 20:30:00'),
(15, 'BAB-0C74B9A63E1528DF', 15, NULL, 'Selesai', NULL, '2026-10-09 12:45:00', '2026-10-09 13:13:00'),
(16, 'BAB-F19D360A72C48E51', 16, NULL, 'Selesai', NULL, '2026-10-10 18:34:00', '2026-10-10 19:02:00'),
(17, 'BAB-4E8A17C2D6093F5B', 17, NULL, 'Selesai', NULL, '2026-10-11 13:05:00', '2026-10-11 13:33:00'),
(18, 'BAB-92B6D0A45E731C8F', 18, NULL, 'Selesai', NULL, '2026-10-12 19:16:00', '2026-10-12 19:44:00'),
(19, 'BAB-20868981BD968DF4', 6, NULL, 'Selesai', '', '2026-10-05 12:58:24', '2026-10-06 08:36:47'),
(20, 'BAB-F1015094E7990AF4', 19, NULL, 'Diserahkan', '', '2026-10-06 00:59:07', NULL),
(22, 'BAB-694386AD2D7AAA57', 20, NULL, 'Selesai', 'Extra pedas', '2026-10-06 08:30:44', '2026-10-06 08:36:17');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` bigint UNSIGNED NOT NULL,
  `item_id` int UNSIGNED NOT NULL,
  `item_name_snapshot` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `quantity` int UNSIGNED NOT NULL,
  `item_status` enum('Menunggu','Sedang Disediakan','Sedia Diambil','Diserahkan','Dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Menunggu',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_item_id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_item` (`item_id`),
  KEY `idx_order_items_status` (`item_status`)
) ;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `item_id`, `item_name_snapshot`, `unit_price`, `quantity`, `item_status`, `notes`, `created_at`, `updated_at`) VALUES
(6, 6, 1, 'Nasi Ayam', 12.00, 1, 'Diserahkan', NULL, '2026-10-04 11:46:56', '2026-10-06 08:27:46'),
(7, 7, 1, 'Nasi Ayam', 12.00, 2, 'Diserahkan', NULL, '2026-09-03 12:18:00', '2026-09-03 12:46:00'),
(8, 8, 2, 'Nasi Lemak Ayam Goreng Berempah', 22.00, 2, 'Diserahkan', NULL, '2026-09-09 18:42:00', '2026-09-09 19:10:00'),
(9, 9, 3, 'Nasi Daging Harimau Menangis', 28.00, 1, 'Diserahkan', NULL, '2026-09-17 13:07:00', '2026-09-17 13:35:00'),
(10, 10, 1, 'Nasi Ayam', 12.00, 3, 'Diserahkan', NULL, '2026-09-28 20:11:00', '2026-09-28 20:39:00'),
(11, 11, 1, 'Nasi Ayam', 12.00, 2, 'Diserahkan', NULL, '2026-10-05 12:14:00', '2026-10-05 12:42:00'),
(12, 12, 2, 'Nasi Lemak Ayam Goreng Berempah', 22.00, 2, 'Diserahkan', NULL, '2026-10-06 19:08:00', '2026-10-06 19:36:00'),
(13, 13, 3, 'Nasi Daging Harimau Menangis', 28.00, 1, 'Diserahkan', NULL, '2026-10-07 13:22:00', '2026-10-07 13:50:00'),
(14, 14, 1, 'Nasi Ayam', 12.00, 3, 'Diserahkan', NULL, '2026-10-08 20:02:00', '2026-10-08 20:30:00'),
(15, 15, 2, 'Nasi Lemak Ayam Goreng Berempah', 22.00, 1, 'Diserahkan', NULL, '2026-10-09 12:45:00', '2026-10-09 13:13:00'),
(16, 16, 3, 'Nasi Daging Harimau Menangis', 28.00, 2, 'Diserahkan', NULL, '2026-10-10 18:34:00', '2026-10-10 19:02:00'),
(17, 17, 1, 'Nasi Ayam', 12.00, 1, 'Diserahkan', NULL, '2026-10-11 13:05:00', '2026-10-11 13:33:00'),
(18, 18, 2, 'Nasi Lemak Ayam Goreng Berempah', 22.00, 3, 'Diserahkan', NULL, '2026-10-12 19:16:00', '2026-10-12 19:44:00'),
(19, 19, 1, 'Nasi Ayam', 12.00, 1, 'Diserahkan', NULL, '2026-10-05 12:58:24', '2026-10-06 08:27:06'),
(20, 19, 5, 'Iced Sparkling Berry Lemonade', 16.00, 1, 'Diserahkan', NULL, '2026-10-05 12:58:24', '2026-10-06 08:27:06'),
(21, 19, 12, 'Churros Sos Coklat', 18.00, 1, 'Diserahkan', NULL, '2026-10-05 12:58:24', '2026-10-06 08:27:06'),
(22, 19, 16, 'Crispy Truffle Fries', 22.00, 1, 'Diserahkan', NULL, '2026-10-05 12:58:24', '2026-10-06 08:27:06'),
(23, 20, 1, 'Nasi Ayam', 12.00, 1, 'Diserahkan', NULL, '2026-10-06 00:59:07', '2026-10-06 08:27:10'),
(24, 20, 8, 'B@B Signature Chocolate', 16.00, 1, 'Diserahkan', NULL, '2026-10-06 00:59:07', '2026-10-06 08:27:10'),
(25, 20, 11, 'Dark Chocolate Mousse', 22.00, 1, 'Diserahkan', NULL, '2026-10-06 00:59:08', '2026-10-06 08:27:10'),
(26, 20, 14, 'Whipped Ricotta Sourdough', 32.00, 1, 'Diserahkan', NULL, '2026-10-06 00:59:08', '2026-10-06 08:27:10'),
(28, 22, 3, 'Nasi Daging Harimau Menangis', 28.00, 1, 'Diserahkan', NULL, '2026-10-06 08:30:44', '2026-10-06 08:36:05'),
(29, 22, 5, 'Iced Sparkling Berry Lemonade', 16.00, 1, 'Diserahkan', NULL, '2026-10-06 08:30:44', '2026-10-06 08:36:05'),
(30, 22, 10, 'Warm Apple Tarte Tatin', 26.00, 1, 'Diserahkan', NULL, '2026-10-06 08:30:44', '2026-10-06 08:36:05'),
(31, 22, 13, 'French Onion Soup', 14.00, 1, 'Diserahkan', NULL, '2026-10-06 08:30:44', '2026-10-06 08:36:05');

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

DROP TABLE IF EXISTS `order_status_history`;
CREATE TABLE IF NOT EXISTS `order_status_history` (
  `history_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `old_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_status_history_order` (`order_id`),
  KEY `idx_status_history_user` (`user_id`),
  KEY `idx_status_history_date` (`changed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_status_history`
--

INSERT INTO `order_status_history` (`history_id`, `order_id`, `user_id`, `old_status`, `new_status`, `changed_at`) VALUES
(9, 6, NULL, NULL, 'Menunggu', '2026-10-04 11:46:56'),
(10, 6, 9, 'Menunggu', 'Sedang Disediakan', '2026-10-05 12:07:48'),
(11, 19, NULL, NULL, 'Menunggu', '2026-10-05 12:58:24'),
(12, 19, 9, 'Menunggu', 'Sedang Disediakan', '2026-10-05 12:59:46'),
(13, 19, 9, 'Sedang Disediakan', 'Sedia Diambil', '2026-10-05 13:00:11'),
(14, 6, 9, 'Sedang Disediakan', 'Sedia Diambil', '2026-10-05 15:08:16'),
(15, 20, NULL, NULL, 'Menunggu', '2026-10-06 00:59:08'),
(16, 20, 9, 'Menunggu', 'Sedang Disediakan', '2026-10-06 01:05:42'),
(18, 20, 9, 'Sedang Disediakan', 'Sedia Diambil', '2026-10-06 08:15:12'),
(19, 19, NULL, 'Sedia Diambil', 'Diserahkan', '2026-10-06 08:27:06'),
(20, 20, NULL, 'Sedia Diambil', 'Diserahkan', '2026-10-06 08:27:10'),
(21, 6, NULL, 'Sedia Diambil', 'Diserahkan', '2026-10-06 08:27:46'),
(22, 22, NULL, NULL, 'Menunggu', '2026-10-06 08:30:44'),
(23, 22, 9, 'Menunggu', 'Sedang Disediakan', '2026-10-06 08:32:37'),
(24, 22, 9, 'Sedang Disediakan', 'Sedia Diambil', '2026-10-06 08:35:20'),
(25, 22, NULL, 'Sedia Diambil', 'Diserahkan', '2026-10-06 08:36:05'),
(26, 22, 8, 'Diserahkan', 'Selesai', '2026-10-06 08:36:17'),
(27, 6, 8, 'Diserahkan', 'Selesai', '2026-10-06 08:36:39'),
(28, 19, 8, 'Diserahkan', 'Selesai', '2026-10-06 08:36:47');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` bigint UNSIGNED NOT NULL,
  `recorded_by` int UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Tunai','FPX','Online Banking','TNG eWallet','Boost','ShopeePay') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_status` enum('Belum Dibayar','Menunggu Pengesahan','Berjaya','Gagal','Dipulangkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Belum Dibayar',
  `transaction_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `idx_payment_order` (`order_id`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_payment_date` (`created_at`),
  KEY `fk_payments_user` (`recorded_by`)
) ;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `order_id`, `recorded_by`, `amount`, `payment_method`, `payment_status`, `transaction_reference`, `paid_at`, `created_at`) VALUES
(6, 6, 8, 12.00, 'Tunai', 'Berjaya', NULL, '2026-10-04 11:54:12', '2026-10-04 11:46:56'),
(7, 7, 8, 24.00, 'Tunai', 'Berjaya', NULL, '2026-09-03 12:49:00', '2026-09-03 12:49:00'),
(8, 8, 8, 44.00, 'Tunai', 'Dipulangkan', NULL, '2026-09-09 19:13:00', '2026-09-09 19:13:00'),
(9, 9, 8, 28.00, 'FPX', 'Menunggu Pengesahan', 'FPX20260917130748261', NULL, '2026-09-17 13:38:00'),
(10, 10, 8, 36.00, 'Online Banking', 'Berjaya', 'OB20260928201139674', '2026-09-28 20:42:00', '2026-09-28 20:42:00'),
(11, 11, 8, 24.00, 'Tunai', 'Berjaya', NULL, '2026-10-05 12:45:00', '2026-10-05 12:45:00'),
(12, 12, 8, 44.00, 'Online Banking', 'Berjaya', 'OB20261006190842715', '2026-10-06 19:39:00', '2026-10-06 19:39:00'),
(13, 13, 8, 28.00, 'FPX', 'Berjaya', 'FPX20261007132258104', '2026-10-07 13:53:00', '2026-10-07 13:53:00'),
(14, 14, 8, 36.00, 'Tunai', 'Berjaya', NULL, '2026-10-08 20:33:00', '2026-10-08 20:33:00'),
(15, 15, 8, 22.00, 'Online Banking', 'Berjaya', 'OB20261009124530762', '2026-10-09 13:16:00', '2026-10-09 13:16:00'),
(16, 16, 8, 56.00, 'FPX', 'Berjaya', 'FPX20261010183462409', '2026-10-10 19:05:00', '2026-10-10 19:05:00'),
(17, 17, 8, 12.00, 'Tunai', 'Berjaya', NULL, '2026-10-11 13:36:00', '2026-10-11 13:36:00'),
(18, 18, 8, 66.00, 'Online Banking', 'Berjaya', 'OB20261012191684530', '2026-10-12 19:47:00', '2026-10-12 19:47:00'),
(19, 19, 8, 68.00, 'Online Banking', 'Berjaya', NULL, '2026-10-05 13:04:16', '2026-10-05 12:58:24'),
(20, 20, NULL, 82.00, 'Online Banking', 'Menunggu Pengesahan', NULL, NULL, '2026-10-06 00:59:08'),
(22, 22, NULL, 84.00, 'Boost', 'Berjaya', NULL, '2026-10-06 08:30:44', '2026-10-06 08:30:44');

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

DROP TABLE IF EXISTS `receipts`;
CREATE TABLE IF NOT EXISTS `receipts` (
  `receipt_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` bigint UNSIGNED NOT NULL,
  `receipt_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issued_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`receipt_id`),
  UNIQUE KEY `uk_receipt_payment` (`payment_id`),
  UNIQUE KEY `uk_receipt_number` (`receipt_number`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `receipts`
--

INSERT INTO `receipts` (`receipt_id`, `payment_id`, `receipt_number`, `issued_at`) VALUES
(1, 6, 'BAB-R-9CAF8E080301', '2026-10-04 11:54:12'),
(2, 7, 'BAB-R-3F8A1C20B5D7', '2026-09-03 12:49:00'),
(3, 8, 'BAB-R-2D7C9A416E03', '2026-09-09 19:13:00'),
(4, 10, 'BAB-R-5E9F0A3C7B2D', '2026-09-28 20:42:00'),
(5, 11, 'BAB-R-8A10C405D7E2', '2026-10-05 12:45:00'),
(6, 12, 'BAB-R-3D7F20A6C94B', '2026-10-06 19:39:00'),
(7, 13, 'BAB-R-61E84A0D3B72', '2026-10-07 13:53:00'),
(8, 14, 'BAB-R-A56C20D14E73', '2026-10-08 20:33:00'),
(9, 15, 'BAB-R-0C74B9A63E15', '2026-10-09 13:16:00'),
(10, 16, 'BAB-R-F19D360A72C4', '2026-10-10 19:05:00'),
(11, 17, 'BAB-R-4E8A17C2D609', '2026-10-11 13:36:00'),
(12, 18, 'BAB-R-92B6D0A45E73', '2026-10-12 19:47:00'),
(13, 19, 'BAB-R-3D0F45B9B565', '2026-10-05 13:04:16'),
(15, 22, 'BAB-R-5A1EC1BFE068', '2026-10-06 08:30:44');

-- --------------------------------------------------------

--
-- Table structure for table `report_test_expenses`
--

DROP TABLE IF EXISTS `report_test_expenses`;
CREATE TABLE IF NOT EXISTS `report_test_expenses` (
  `sample_key` varchar(40) NOT NULL,
  `expense_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`sample_key`),
  UNIQUE KEY `expense_id` (`expense_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `report_test_expenses`
--

INSERT INTO `report_test_expenses` (`sample_key`, `expense_id`) VALUES
('expense-a', 1),
('expense-b', 2),
('expense-c', 3);

-- --------------------------------------------------------

--
-- Table structure for table `report_test_orders`
--

DROP TABLE IF EXISTS `report_test_orders`;
CREATE TABLE IF NOT EXISTS `report_test_orders` (
  `sample_key` varchar(40) NOT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`sample_key`),
  UNIQUE KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `report_test_orders`
--

INSERT INTO `report_test_orders` (`sample_key`, `order_id`) VALUES
('sale-a', 7),
('sale-b', 8),
('sale-c', 9),
('sale-d', 10),
('oct-sale-20261005', 11),
('oct-sale-20261006', 12),
('oct-sale-20261007', 13),
('oct-sale-20261008', 14),
('oct-sale-20261009', 15),
('oct-sale-20261010', 16),
('oct-sale-20261011', 17),
('oct-sale-20261012', 18);

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_tables`
--

DROP TABLE IF EXISTS `restaurant_tables`;
CREATE TABLE IF NOT EXISTS `restaurant_tables` (
  `table_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_number` int UNSIGNED NOT NULL,
  `qr_token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_status` enum('Kosong','Diduduki','Menunggu Pembersihan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Kosong',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`table_id`),
  UNIQUE KEY `uk_table_number` (`table_number`),
  UNIQUE KEY `uk_table_qr_token` (`qr_token`)
) ;

--
-- Dumping data for table `restaurant_tables`
--

INSERT INTO `restaurant_tables` (`table_id`, `table_number`, `qr_token`, `table_status`, `created_at`) VALUES
(1, 1, 'BAB-TABLE-001', 'Kosong', '2026-10-04 01:08:30'),
(2, 2, 'BAB-TABLE-002', 'Kosong', '2026-10-04 01:08:30'),
(3, 3, 'BAB-TABLE-003', 'Kosong', '2026-10-04 01:08:30'),
(4, 4, 'BAB-TABLE-004', 'Kosong', '2026-10-04 01:08:30'),
(5, 5, 'BAB-TABLE-005', 'Diduduki', '2026-10-04 01:08:30');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uk_roles_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(1, 'Admin'),
(3, 'Cashier'),
(2, 'Kitchen');

-- --------------------------------------------------------

--
-- Table structure for table `table_sessions`
--

DROP TABLE IF EXISTS `table_sessions`;
CREATE TABLE IF NOT EXISTS `table_sessions` (
  `session_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_id` int UNSIGNED NOT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` datetime DEFAULT NULL,
  `session_status` enum('Aktif','Selesai','Dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Aktif',
  PRIMARY KEY (`session_id`),
  KEY `idx_session_table` (`table_id`),
  KEY `idx_session_status` (`session_status`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `table_sessions`
--

INSERT INTO `table_sessions` (`session_id`, `table_id`, `started_at`, `ended_at`, `session_status`) VALUES
(6, 1, '2026-10-04 11:46:56', '2026-10-06 08:36:47', 'Selesai'),
(7, 5, '2026-09-03 12:06:00', '2026-09-03 13:00:00', 'Selesai'),
(8, 5, '2026-09-09 18:30:00', '2026-09-09 19:24:00', 'Selesai'),
(9, 5, '2026-09-17 12:55:00', '2026-09-17 13:49:00', 'Selesai'),
(10, 5, '2026-09-28 19:59:00', '2026-09-28 20:53:00', 'Selesai'),
(11, 5, '2026-10-05 12:02:00', '2026-10-05 12:56:00', 'Selesai'),
(12, 5, '2026-10-06 18:56:00', '2026-10-06 19:50:00', 'Selesai'),
(13, 5, '2026-10-07 13:10:00', '2026-10-07 14:04:00', 'Selesai'),
(14, 5, '2026-10-08 19:50:00', '2026-10-08 20:44:00', 'Selesai'),
(15, 5, '2026-10-09 12:33:00', '2026-10-09 13:27:00', 'Selesai'),
(16, 5, '2026-10-10 18:22:00', '2026-10-10 19:16:00', 'Selesai'),
(17, 5, '2026-10-11 12:53:00', '2026-10-11 13:47:00', 'Selesai'),
(18, 5, '2026-10-12 19:04:00', '2026-10-12 19:58:00', 'Selesai'),
(19, 5, '2026-10-06 00:59:07', NULL, 'Aktif'),
(20, 3, '2026-10-06 08:30:44', '2026-10-06 08:36:17', 'Selesai');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` int UNSIGNED NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_users_username` (`username`),
  KEY `fk_users_role` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role_id`, `full_name`, `username`, `password_hash`, `is_active`, `created_at`, `updated_at`) VALUES
(4, 1, 'MUHAMMAD AIMAN ASYRAAF BIN MOHD NUUR NAZRI', 'Admin', '$2y$10$Q1nKSJ4w8ITBby3lCVGpGuHu823hfkPsy.JzSUYFULe1bHvSk5h6e', 1, '2026-10-04 01:39:02', '2026-10-04 01:41:46'),
(8, 3, 'ALICE', 'Cashier', '$2y$12$Kzs8p75FIxdokKE.HMUGVe2Xp2N3hGw1CqhoHkWzQmX4g3xqdCz1m', 1, '2026-10-04 02:23:47', '2026-10-04 02:23:47'),
(9, 2, 'BOB', 'Kitchen', '$2y$12$Wli/U8s1VxCfjxFzOduhJu61DWNu77hEEI/.Ad.cYduYiyMzvRRPW', 1, '2026-10-04 02:24:22', '2026-10-04 02:24:22');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_expenses_category` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`expense_category_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_expenses_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_session` FOREIGN KEY (`session_id`) REFERENCES `table_sessions` (`session_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_menu` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `fk_status_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_status_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payments_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `receipts`
--
ALTER TABLE `receipts`
  ADD CONSTRAINT `fk_receipts_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `report_test_expenses`
--
ALTER TABLE `report_test_expenses`
  ADD CONSTRAINT `fk_report_test_expenses_expense` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`expense_id`) ON DELETE CASCADE;

--
-- Constraints for table `report_test_orders`
--
ALTER TABLE `report_test_orders`
  ADD CONSTRAINT `fk_report_test_orders_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `table_sessions`
--
ALTER TABLE `table_sessions`
  ADD CONSTRAINT `fk_session_table` FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables` (`table_id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
