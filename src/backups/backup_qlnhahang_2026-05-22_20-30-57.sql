-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: qlnhahang
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
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_categories_category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'MonChinh','Cac mon an chinh'),(2,'DoUong','Nuoc giai khat'),(3,'TrangMieng','Mon trang mieng');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `uq_customers_phone` (`phone`),
  UNIQUE KEY `uq_customers_email` (`email`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_customers_created_by` (`created_by`),
  CONSTRAINT `fk_customers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'Nguyễn Thành Công','0912345678','an.nguyen@gmail.com',1,'2026-03-30 21:24:53',NULL),(5,'Trần Hải Yến','0965894123','xuanmai@gmail.com',NULL,'2026-04-05 14:41:31',NULL),(25,'Lê Hoài Nam','0911111113',NULL,NULL,'2026-04-23 23:29:05',7),(28,'Nguyễn Thu Hà','0911111114','thuha@gmail.com',5,'2026-04-24 19:40:05',NULL),(31,'Trần Mỹ Linh','0911111115',NULL,NULL,'2026-04-25 12:10:03',36),(34,'Võ Quốc Nam','0911111116','quocnam@gmail.com',NULL,'2026-05-08 09:05:21',39),(35,'Trương Gia Hân','0568940900',NULL,5,'2026-05-09 14:57:32',NULL),(36,'Phạm Ngọc Lan','0564985555',NULL,5,'2026-05-13 21:37:02',NULL),(37,'Lý Tuấn Kiệt','0358874999',NULL,5,'2026-05-13 21:49:02',NULL),(38,'Trần Huyền Trân','0973900959',NULL,NULL,'2026-05-22 19:11:47',43);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `data_operation_logs`
--

DROP TABLE IF EXISTS `data_operation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_operation_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `action_type` enum('backup','health_check','manual_log','delete_orders','delete_reservations','reset_all','restore','clean_old_data') NOT NULL,
  `deleted_count` int(11) DEFAULT 0,
  `performed_by` int(11) NOT NULL,
  `performed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `details` text DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `performed_by` (`performed_by`),
  KEY `idx_performed_at` (`performed_at`),
  KEY `idx_action_type` (`action_type`),
  CONSTRAINT `data_operation_logs_ibfk_1` FOREIGN KEY (`performed_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_operation_logs`
--

LOCK TABLES `data_operation_logs` WRITE;
/*!40000 ALTER TABLE `data_operation_logs` DISABLE KEYS */;
INSERT INTO `data_operation_logs` VALUES (1,'backup',0,1,'2026-05-04 16:13:16','File: backup_qlnhahang_2026-05-04_16-13-16.sql (0.05MB) - Lưu tại: E:\\saoluudulieu'),(2,'health_check',0,1,'2026-05-04 16:16:25','✓ Kết nối CSDL: OK, ✓ Số bàn: 4, ✓ Người dùng hoạt động: 9, ✓ Đơn hàng hôm nay: 0'),(3,'backup',0,1,'2026-05-04 16:16:48','File: backup_qlnhahang_2026-05-04_16-16-47.sql (0.05MB) - Lưu tại: E:\\Môn Cô Nam'),(4,'backup',0,2,'2026-05-08 12:05:01','File: backup_qlnhahang_2026-05-08_12-05-01.sql (0.06MB) - Lưu tại: D:/backups/');
/*!40000 ALTER TABLE `data_operation_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `floors`
--

DROP TABLE IF EXISTS `floors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `floors` (
  `floor_id` int(11) NOT NULL AUTO_INCREMENT,
  `floor_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `max_tables` int(11) DEFAULT NULL COMMENT 'Số bàn tối đa cho phép trong khu vực',
  PRIMARY KEY (`floor_id`),
  UNIQUE KEY `uq_floors_floor_name` (`floor_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `floors`
--

LOCK TABLES `floors` WRITE;
/*!40000 ALTER TABLE `floors` DISABLE KEYS */;
INSERT INTO `floors` VALUES (1,'Tầng 1','Khu vực tầng 1',20),(2,'Tầng 2','Khu vực tầng 2',15);
/*!40000 ALTER TABLE `floors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_items`
--

DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `status` enum('con_hang','het_hang','ngung_ban') NOT NULL DEFAULT 'con_hang',
  PRIMARY KEY (`item_id`),
  UNIQUE KEY `uq_menu_items_item_name` (`item_name`),
  KEY `idx_menu_items_category_id` (`category_id`),
  CONSTRAINT `fk_menu_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_items`
--

LOCK TABLES `menu_items` WRITE;
/*!40000 ALTER TABLE `menu_items` DISABLE KEYS */;
INSERT INTO `menu_items` VALUES (1,1,'Lẩu bò tươi',299000.00,'Lẩu bò tươi với rau, nấm và nước dùng đậm vị','/quanlynhahang/assets/images/menu/17758094146724_featured-2.jpg','con_hang'),(2,1,'Nước lẩu Đài Loan',99000.00,'Nước lẩu phong cách Đài Loan, vị đậm đà','/quanlynhahang/assets/images/menu/17758093916015_hero-slide-3.jpg','con_hang'),(3,1,'Set combo đặc biệt',399000.00,'Combo đặc biệt gồm nhiều món ăn kèm','/quanlynhahang/assets/images/menu/17758095160418_combo.jpg','con_hang'),(4,1,'Cơm sườn nướng',65000.00,'Cơm sườn nướng ăn kèm dưa chua và nước mắm chua ngọt','/quanlynhahang/assets/images/menu/17758095536862_com_suon.jpg','con_hang'),(5,1,'Bún bò Huế',55000.00,'Bún bò đậm vị với thịt bò, chả và rau sống','/quanlynhahang/assets/images/menu/17758093237164_bun_bo.jpg','con_hang'),(6,2,'Trà đào cam sả',35000.00,'Thức uống thanh mát với trà đào, cam tươi và sả','/quanlynhahang/assets/images/menu/17758092792503_tra_dao.jpg','con_hang'),(7,2,'Nước ép dưa hấu',300000.00,'Nước ép dưa hấu tươi mát, ít đá','/quanlynhahang/assets/images/menu/17758091775395_nuoc_ep_dua-hau.jpg','con_hang'),(8,3,'Bánh flan caramel',25000.00,'Bánh flan mềm mịn phủ lớp caramel thơm ngọt','/quanlynhahang/assets/images/menu/1775809232082_banh-flan.jpg','con_hang'),(9,1,'bún nem nướng',88000.00,'ngon lắm nha','/quanlynhahang/assets/images/menu/17758093764921_bun.jpg','con_hang'),(10,1,'Phật nhảy tường',808000.00,'Phật nhảy tường là một loại súp trong ẩm thực Phúc Kiến, nổi tiếng bởi sự bổ dưỡng với những loại nguyên liệu quý hiếm và đắt đỏ như Bào Ngư, Hải Sâm, Bong Bóng Cá được nấu cùng các vị thuốc gia truyền và được chế biến vô cùng công phu.','/quanlynhahang/assets/images/menu/17782079721933_phat-nhay-tuong-gia-bao-nhieu-1707073679.jpg','con_hang');
/*!40000 ALTER TABLE `menu_items` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_menu_items_before_delete` BEFORE DELETE ON `menu_items` FOR EACH ROW BEGIN
    DECLARE active_order_count INT;
    
    -- Đếm số đơn active có món này
    SELECT COUNT(*) INTO active_order_count
    FROM order_details od
    JOIN orders o ON o.order_id = od.order_id
    WHERE od.item_id = OLD.item_id
      AND o.status IN ('moi', 'dang_xu_ly', 'dang_che_bien', 'dang_phuc_vu', 'hoan_thanh');
    
    -- Nếu có đơn active → không cho xóa
    IF active_order_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Không thể xóa món đang có trong đơn hàng chưa hoàn thành';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `order_details`
--

DROP TABLE IF EXISTS `order_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_details` (
  `order_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `item_status` enum('moi','dang_che_bien','hoan_thanh') DEFAULT 'moi',
  PRIMARY KEY (`order_detail_id`),
  KEY `idx_order_details_order_id` (`order_id`),
  KEY `idx_order_details_item_id` (`item_id`),
  KEY `idx_order_details_status` (`item_status`),
  CONSTRAINT `fk_order_details_item` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_order_details_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_details`
--

LOCK TABLES `order_details` WRITE;
/*!40000 ALTER TABLE `order_details` DISABLE KEYS */;
INSERT INTO `order_details` VALUES (1,1,1,2,50000.00,NULL,'hoan_thanh'),(2,1,2,1,50000.00,NULL,'hoan_thanh'),(3,2,1,3,50000.00,NULL,'hoan_thanh'),(4,2,3,2,50000.00,NULL,'hoan_thanh'),(5,3,1,4,50000.00,NULL,'hoan_thanh'),(6,3,2,2,50000.00,NULL,'hoan_thanh'),(7,4,1,8,50000.00,NULL,'hoan_thanh'),(8,4,2,4,50000.00,NULL,'hoan_thanh'),(9,1,9,1,88000.00,'d','hoan_thanh'),(10,1,9,1,88000.00,'','hoan_thanh'),(11,2,5,1,55000.00,'','hoan_thanh'),(12,5,4,1,65000.00,'','hoan_thanh'),(13,6,5,4,55000.00,'','hoan_thanh'),(14,7,9,4,88000.00,NULL,'moi'),(15,8,3,1,399000.00,NULL,'moi'),(16,9,1,1,299000.00,'','hoan_thanh'),(17,10,8,6,25000.00,NULL,'moi'),(18,11,2,1,99000.00,NULL,'moi'),(19,11,3,1,399000.00,NULL,'moi'),(20,11,7,1,300000.00,NULL,'moi'),(21,12,8,1,25000.00,NULL,'moi'),(22,13,7,1,300000.00,NULL,'hoan_thanh'),(23,14,8,1,25000.00,NULL,'hoan_thanh'),(24,14,1,1,299000.00,'','hoan_thanh'),(25,15,5,1,55000.00,'','hoan_thanh'),(26,16,6,1,35000.00,NULL,'hoan_thanh'),(27,16,6,1,35000.00,NULL,'hoan_thanh'),(28,16,9,1,88000.00,'','hoan_thanh'),(29,17,9,1,88000.00,'','hoan_thanh'),(30,18,1,1,299000.00,NULL,'moi'),(31,19,5,1,55000.00,'','hoan_thanh'),(32,20,9,1,88000.00,'','hoan_thanh'),(33,21,8,1,25000.00,NULL,'moi'),(34,22,9,1,88000.00,NULL,'moi'),(35,23,3,1,399000.00,NULL,'moi'),(36,24,10,1,808000.00,NULL,'hoan_thanh'),(37,24,9,1,88000.00,'','hoan_thanh');
/*!40000 ALTER TABLE `order_details` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_order_details_before_insert` BEFORE INSERT ON `order_details` FOR EACH ROW BEGIN
    -- Nếu chưa có unit_price hoặc unit_price = 0, lấy từ menu_items
    IF NEW.unit_price IS NULL OR NEW.unit_price = 0 THEN
        SET NEW.unit_price = (
            SELECT price 
            FROM menu_items 
            WHERE item_id = NEW.item_id
            LIMIT 1
        );
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_order_details_validate` BEFORE INSERT ON `order_details` FOR EACH ROW BEGIN
    DECLARE item_status_val VARCHAR(20);
    
    -- Kiểm tra số lượng phải > 0
    IF NEW.quantity <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Số lượng món phải lớn hơn 0';
    END IF;
    
    -- Kiểm tra giá phải >= 0
    IF NEW.unit_price < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Giá món không được âm';
    END IF;
    
    -- Kiểm tra món còn hàng không
    SELECT status INTO item_status_val
    FROM menu_items
    WHERE item_id = NEW.item_id;
    
    IF item_status_val != 'con_hang' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Món này hiện đang hết hàng hoặc ngừng bán';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_order_details_after_insert` AFTER INSERT ON `order_details` FOR EACH ROW BEGIN
    -- Tính lại tổng tiền cho đơn hàng
    UPDATE orders 
    SET total_amount = (
        SELECT COALESCE(SUM(quantity * unit_price), 0)
        FROM order_details
        WHERE order_id = NEW.order_id
    )
    WHERE order_id = NEW.order_id;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_order_details_after_update` AFTER UPDATE ON `order_details` FOR EACH ROW BEGIN
    -- Tính lại tổng tiền cho đơn hàng
    UPDATE orders 
    SET total_amount = (
        SELECT COALESCE(SUM(quantity * unit_price), 0)
        FROM order_details
        WHERE order_id = NEW.order_id
    )
    WHERE order_id = NEW.order_id;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_order_details_after_delete` AFTER DELETE ON `order_details` FOR EACH ROW BEGIN
    -- Tính lại tổng tiền cho đơn hàng
    UPDATE orders 
    SET total_amount = (
        SELECT COALESCE(SUM(quantity * unit_price), 0)
        FROM order_details
        WHERE order_id = OLD.order_id
    )
    WHERE order_id = OLD.order_id;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `table_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `waiter_id` int(11) DEFAULT NULL,
  `order_time` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('da_dat_coc','moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh','da_thanh_toan','da_huy') NOT NULL DEFAULT 'moi',
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `guest_count` int(11) DEFAULT 1,
  `number_of_people` int(11) DEFAULT 0,
  PRIMARY KEY (`order_id`),
  KEY `idx_orders_table_id` (`table_id`),
  KEY `idx_orders_customer_id` (`customer_id`),
  KEY `idx_orders_reservation_id` (`reservation_id`),
  KEY `idx_orders_waiter_id` (`waiter_id`),
  KEY `idx_orders_order_time` (`order_time`),
  KEY `idx_orders_table_status` (`table_id`,`status`),
  KEY `idx_orders_time_status` (`order_time`,`status`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_table` FOREIGN KEY (`table_id`) REFERENCES `tables` (`table_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_waiter` FOREIGN KEY (`waiter_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,1,1,NULL,5,'2026-04-24 20:51:38','da_thanh_toan',326000.00,326000.00,NULL,NULL,2,0),(2,3,NULL,NULL,5,'2026-04-24 21:21:39','da_thanh_toan',305000.00,305000.00,NULL,NULL,4,0),(3,2,NULL,1,5,'2026-04-24 21:21:39','da_thanh_toan',300000.00,150000.00,NULL,NULL,2,0),(4,25,28,2,5,'2026-04-24 21:21:39','da_thanh_toan',600000.00,350000.00,NULL,NULL,4,0),(5,2,NULL,1,5,'2026-04-24 21:32:21','da_thanh_toan',65000.00,65000.00,NULL,NULL,1,0),(6,3,28,NULL,5,'2026-04-24 22:12:49','da_thanh_toan',220000.00,220000.00,NULL,NULL,1,0),(7,1,25,3,NULL,'2026-04-24 22:33:15','da_huy',352000.00,0.00,NULL,NULL,4,0),(8,1,31,4,NULL,'2026-04-25 12:22:29','da_huy',399000.00,0.00,NULL,NULL,2,0),(9,3,31,NULL,5,'2026-04-25 15:10:19','da_thanh_toan',299000.00,299000.00,NULL,NULL,1,0),(10,3,NULL,5,5,'2026-04-25 15:20:21','da_huy',150000.00,0.00,NULL,NULL,6,0),(11,1,34,6,NULL,'2026-05-08 09:22:20','da_huy',798000.00,0.00,NULL,NULL,2,0),(12,1,34,7,NULL,'2026-05-08 09:46:32','da_huy',25000.00,0.00,NULL,NULL,2,0),(13,2,34,8,5,'2026-05-08 10:20:19','da_thanh_toan',300000.00,150000.00,NULL,NULL,2,0),(14,3,34,9,5,'2026-05-08 10:22:51','da_thanh_toan',324000.00,311500.00,NULL,NULL,3,0),(15,25,1,NULL,5,'2026-05-08 10:30:49','da_thanh_toan',55000.00,55000.00,NULL,NULL,1,0),(16,1,34,10,5,'2026-05-08 21:56:10','da_thanh_toan',158000.00,123000.00,NULL,NULL,2,0),(17,3,25,NULL,5,'2026-05-09 13:58:52','da_thanh_toan',88000.00,88000.00,NULL,NULL,1,0),(18,3,28,11,NULL,'2026-05-09 14:55:54','da_thanh_toan',299000.00,0.00,NULL,NULL,2,0),(19,25,35,NULL,5,'2026-05-09 14:57:32','da_thanh_toan',55000.00,55000.00,NULL,NULL,1,0),(20,1,36,NULL,5,'2026-05-13 21:37:02','da_thanh_toan',88000.00,88000.00,NULL,NULL,1,0),(21,1,34,12,5,'2026-05-13 21:41:25','da_thanh_toan',25000.00,12500.00,NULL,NULL,2,0),(22,3,37,13,5,'2026-05-13 21:49:02','da_thanh_toan',88000.00,0.00,NULL,NULL,2,0),(23,1,38,14,NULL,'2026-05-22 19:50:52','da_dat_coc',399000.00,0.00,NULL,NULL,2,0),(24,2,38,15,3,'2026-05-22 19:51:29','da_thanh_toan',896000.00,492000.00,NULL,NULL,1,0);
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_orders_after_update_status` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    -- Khi đơn chuyển sang đã thanh toán → trả bàn về trống
    IF NEW.status = 'da_thanh_toan' AND OLD.status != 'da_thanh_toan' THEN
        UPDATE tables 
        SET status = 'trong' 
        WHERE table_id = NEW.table_id
          AND status != 'bao_tri'; -- Không đổi nếu đang bảo trì
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `token_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_password_reset_user` (`user_id`),
  KEY `idx_password_reset_expires` (`expires_at`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
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
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `payment_method` enum('cash','bank_transfer','card','e_wallet','deposit_consumed') NOT NULL,
  `payment_type` varchar(50) DEFAULT 'order_payment',
  `amount_paid` decimal(12,2) NOT NULL,
  `payment_time` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('thanh_cong','that_bai','hoan_tien') NOT NULL DEFAULT 'thanh_cong',
  PRIMARY KEY (`payment_id`),
  KEY `idx_payments_order_id` (`order_id`),
  KEY `idx_payments_cashier_id` (`cashier_id`),
  KEY `idx_payments_payment_time` (`payment_time`),
  KEY `idx_payments_time_status` (`payment_time`,`payment_status`),
  CONSTRAINT `fk_payments_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (11,5,3,'cash','order_payment',65000.00,'2026-04-24 21:33:33','thanh_cong'),(12,1,3,'cash','order_payment',326000.00,'2026-04-24 21:42:15','thanh_cong'),(13,2,3,'cash','order_payment',305000.00,'2026-04-24 21:42:16','thanh_cong'),(14,3,3,'cash','order_payment',150000.00,'2026-04-24 21:54:41','thanh_cong'),(15,6,3,'cash','order_payment',220000.00,'2026-04-24 22:13:26','thanh_cong'),(16,4,3,'cash','order_payment',350000.00,'2026-04-24 23:52:54','thanh_cong'),(17,9,3,'cash','order_payment',299000.00,'2026-04-25 15:11:53','thanh_cong'),(18,15,3,'cash','order_payment',55000.00,'2026-05-08 11:02:26','thanh_cong'),(19,14,3,'cash','order_payment',311500.00,'2026-05-08 11:02:31','thanh_cong'),(20,13,3,'bank_transfer','order_payment',150000.00,'2026-05-08 11:24:38','thanh_cong'),(21,16,3,'cash','order_payment',123000.00,'2026-05-08 23:18:23','thanh_cong'),(24,17,3,'cash','order_payment',88000.00,'2026-05-09 14:32:13','thanh_cong'),(25,19,3,'cash','order_payment',55000.00,'2026-05-09 15:02:06','thanh_cong'),(26,20,3,'cash','order_payment',88000.00,'2026-05-13 21:38:44','thanh_cong'),(27,21,3,'cash','order_payment',12500.00,'2026-05-13 21:41:59','thanh_cong'),(28,24,41,'bank_transfer','order_payment',492000.00,'2026-05-22 20:06:17','thanh_cong');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_payments_after_insert` AFTER INSERT ON `payments` FOR EACH ROW BEGIN
    -- Chỉ cập nhật nếu thanh toán thành công
    IF NEW.payment_status = 'thanh_cong' THEN
        UPDATE orders 
        SET paid_amount = (
            SELECT COALESCE(SUM(amount_paid), 0)
            FROM payments
            WHERE order_id = NEW.order_id 
              AND payment_status = 'thanh_cong'
        )
        WHERE order_id = NEW.order_id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_payments_after_update` AFTER UPDATE ON `payments` FOR EACH ROW BEGIN
    -- Cập nhật lại paid_amount khi trạng thái thanh toán thay đổi
    UPDATE orders 
    SET paid_amount = (
        SELECT COALESCE(SUM(amount_paid), 0)
        FROM payments
        WHERE order_id = NEW.order_id 
          AND payment_status = 'thanh_cong'
    )
    WHERE order_id = NEW.order_id;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_payments_after_delete` AFTER DELETE ON `payments` FOR EACH ROW BEGIN
    -- Cập nhật lại paid_amount khi xóa payment
    UPDATE orders 
    SET paid_amount = (
        SELECT COALESCE(SUM(amount_paid), 0)
        FROM payments
        WHERE order_id = OLD.order_id 
          AND payment_status = 'thanh_cong'
    )
    WHERE order_id = OLD.order_id;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `reservation_items`
--

DROP TABLE IF EXISTS `reservation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reservation_items` (
  `reservation_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`reservation_item_id`),
  KEY `idx_reservation_items_reservation_id` (`reservation_id`),
  KEY `idx_reservation_items_item_id` (`item_id`),
  CONSTRAINT `fk_ri_item` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ri_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservation_items`
--

LOCK TABLES `reservation_items` WRITE;
/*!40000 ALTER TABLE `reservation_items` DISABLE KEYS */;
INSERT INTO `reservation_items` VALUES (1,3,9,4,88000.00,NULL),(2,4,3,1,399000.00,NULL),(3,5,8,6,25000.00,NULL),(4,6,2,1,99000.00,NULL),(5,6,3,1,399000.00,NULL),(6,6,7,1,300000.00,NULL),(7,7,8,1,25000.00,NULL),(8,8,7,1,300000.00,NULL),(9,9,8,1,25000.00,NULL),(10,10,6,1,35000.00,NULL),(11,10,6,1,35000.00,NULL),(12,11,1,1,299000.00,NULL),(13,12,8,1,25000.00,NULL),(14,13,9,1,88000.00,NULL),(15,14,3,1,399000.00,NULL),(16,15,10,1,808000.00,NULL);
/*!40000 ALTER TABLE `reservation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reservation_payments`
--

DROP TABLE IF EXISTS `reservation_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reservation_payments` (
  `reservation_payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` int(11) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `payment_type` varchar(20) NOT NULL,
  `payment_percent` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_time` datetime NOT NULL,
  `payment_status` enum('cho_xu_ly','thanh_cong','that_bai','hoan_tien') NOT NULL DEFAULT 'cho_xu_ly',
  `deposit_status` enum('giu_lai','da_su_dung','hoan_tien') NOT NULL DEFAULT 'giu_lai',
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`reservation_payment_id`),
  KEY `reservation_id` (`reservation_id`),
  KEY `cashier_id` (`cashier_id`),
  KEY `idx_reservation_payment_status` (`reservation_id`,`payment_status`),
  KEY `idx_reservation_payment_time` (`payment_time`),
  CONSTRAINT `reservation_payments_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`),
  CONSTRAINT `reservation_payments_ibfk_2` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservation_payments`
--

LOCK TABLES `reservation_payments` WRITE;
/*!40000 ALTER TABLE `reservation_payments` DISABLE KEYS */;
INSERT INTO `reservation_payments` VALUES (1,1,6,'deposit',50,150000.00,'bank_transfer','2026-04-24 21:21:39','thanh_cong','da_su_dung','Đặt cọc 50%'),(2,2,6,'deposit',50,250000.00,'bank_transfer','2026-04-24 21:21:39','thanh_cong','da_su_dung','Đặt cọc 50%'),(3,3,7,'deposit',50,176000.00,'bank_transfer','2026-04-24 22:33:15','thanh_cong','giu_lai','Đặt từ website | 4 | Mức thanh toán trước: 50% | Noi dung CK: DATBAN 3 0911111113 | Tong mon dat truoc: 352000'),(4,4,36,'deposit',50,199500.00,'bank_transfer','2026-04-25 12:22:29','thanh_cong','giu_lai','Đặt từ website | luồng 1 là khách đặt bàn rồi đợi tới h ăn | Mức thanh toán trước: 50% | Noi dung CK: DATBAN 4 0911111115 | Tong mon dat truoc: 399000'),(5,5,5,'deposit',50,75000.00,'0','2026-04-25 15:20:21','','giu_lai',NULL),(6,6,39,'deposit',50,399000.00,'bank_transfer','2026-05-08 09:22:20','thanh_cong','giu_lai','Đặt từ website | đi hẹn hò | Mức thanh toán trước: 50% | Noi dung CK: DATBAN 6 0911111116 | Tong mon dat truoc: 798000'),(7,7,39,'deposit',50,12500.00,'bank_transfer','2026-05-08 09:46:32','thanh_cong','giu_lai','Đặt từ website | Mức thanh toán trước: 50% | Noi dung CK: DATBAN 7 0911111116 | Tong mon dat truoc: 25000'),(8,8,39,'deposit',50,150000.00,'bank_transfer','2026-05-08 10:20:19','thanh_cong','da_su_dung','Đặt từ website | đặt bàn test checkin | Mức thanh toán trước: 50% | Noi dung CK: DATBAN 8 0911111116 | Tong mon dat truoc: 300000'),(9,9,39,'deposit',50,12500.00,'bank_transfer','2026-05-08 10:22:51','thanh_cong','da_su_dung','Đặt từ website | Mức thanh toán trước: 50% | Noi dung CK: DATBAN 9 0911111116 | Tong mon dat truoc: 25000'),(10,10,39,'deposit',50,35000.00,'bank_transfer','2026-05-08 21:56:10','thanh_cong','da_su_dung','Đặt từ website | Mức thanh toán trước: 50% | Noi dung CK: DATBAN 10 0911111116 | Tong mon dat truoc: 70000'),(11,11,6,'deposit',50,149500.00,'bank_transfer','2026-05-09 14:55:54','thanh_cong','giu_lai','Đặt từ website | Mức thanh toán trước: 50% | Noi dung CK: DATBAN 11 0911111114 | Tong mon dat truoc: 299000'),(12,12,5,'deposit',50,12500.00,'cash','2026-05-13 21:41:25','thanh_cong','da_su_dung',NULL),(13,13,5,'deposit',50,44000.00,'cash','2026-05-13 21:49:02','thanh_cong','giu_lai',NULL),(14,14,43,'full_payment',100,399000.00,'bank_transfer','2026-05-22 19:50:52','thanh_cong','giu_lai','Đặt từ website | Sinh thần | Mức thanh toán trước: 100% | Noi dung CK: DATBAN 14 0973900959 | Tong mon dat truoc: 399000'),(15,15,43,'deposit',50,404000.00,'bank_transfer','2026-05-22 19:51:29','thanh_cong','da_su_dung','Đặt từ website | sinh nhât | Mức thanh toán trước: 50% | Noi dung CK: DATBAN 15 0973900959 | Tong mon dat truoc: 808000');
/*!40000 ALTER TABLE `reservation_payments` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_reservation_payments_after_insert` AFTER INSERT ON `reservation_payments` FOR EACH ROW BEGIN
    -- Khi thanh toán cọc thành công → chuyển reservation sang đã xác nhận
    IF NEW.payment_status = 'thanh_cong' THEN
        UPDATE reservations 
        SET status = 'da_xac_nhan'
        WHERE reservation_id = NEW.reservation_id
          AND status = 'cho_xac_nhan'; -- Chỉ update nếu đang chờ xác nhận
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `reservations`
--

DROP TABLE IF EXISTS `reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reservations` (
  `reservation_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `table_id` int(11) NOT NULL,
  `reservation_time` datetime NOT NULL,
  `number_of_people` int(11) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `status` enum('cho_xac_nhan','da_xac_nhan','da_checkin','khong_den','da_huy','hoan_thanh') NOT NULL DEFAULT 'cho_xac_nhan',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  PRIMARY KEY (`reservation_id`),
  KEY `idx_reservations_table_id` (`table_id`),
  KEY `idx_reservations_time` (`reservation_time`),
  KEY `fk_reservations_user` (`user_id`),
  KEY `idx_reservations_table_status` (`table_id`,`status`),
  KEY `idx_reservations_time_status` (`reservation_time`,`status`),
  CONSTRAINT `fk_reservations_table` FOREIGN KEY (`table_id`) REFERENCES `tables` (`table_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_reservations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservations`
--

LOCK TABLES `reservations` WRITE;
/*!40000 ALTER TABLE `reservations` DISABLE KEYS */;
INSERT INTO `reservations` VALUES (1,6,2,'2026-04-24 21:51:39',2,'Đặt bàn online','hoan_thanh','2026-04-24 21:21:39',NULL,'2026-04-24 23:21:39'),(2,6,25,'2026-04-24 22:51:39',4,'Sinh nhật','hoan_thanh','2026-04-24 21:21:39',NULL,'2026-04-25 00:21:39'),(3,7,1,'2026-04-24 23:33:00',4,'4','da_huy','2026-04-24 22:33:15',NULL,'2026-04-25 01:03:00'),(4,36,1,'2026-04-25 14:22:00',2,'luồng 1 là khách đặt bàn rồi đợi tới h ăn','da_huy','2026-04-25 12:22:29',NULL,'2026-04-25 15:52:00'),(5,5,3,'2026-04-25 17:20:00',6,'','khong_den','2026-04-25 15:20:21',NULL,'2026-04-25 18:50:00'),(6,39,1,'2026-05-08 13:25:00',2,'đi hẹn hò','khong_den','2026-05-08 09:22:20',NULL,'2026-05-08 14:55:00'),(7,39,1,'2026-05-08 16:46:00',2,'Đặt trước','khong_den','2026-05-08 09:46:32','2026-05-08 16:46:00','2026-05-08 18:16:00'),(8,39,2,'2026-05-08 10:33:00',2,'đặt bàn test checkin','hoan_thanh','2026-05-08 10:20:19','2026-05-08 10:33:00','2026-05-08 12:03:00'),(9,39,3,'2026-05-08 10:24:00',3,'Đặt trước','hoan_thanh','2026-05-08 10:22:51','2026-05-08 10:24:00','2026-05-08 12:24:00'),(10,39,1,'2026-05-08 22:30:00',2,'Đặt trước','hoan_thanh','2026-05-08 21:56:10','2026-05-08 22:30:00','2026-05-09 00:00:00'),(11,6,3,'2026-05-09 15:55:00',2,'Đặt trước','hoan_thanh','2026-05-09 14:55:54','2026-05-09 15:55:00','2026-05-09 17:25:00'),(12,5,1,'2026-05-13 22:17:00',2,'sinh nhật','hoan_thanh','2026-05-13 21:41:25',NULL,'2026-05-13 23:47:00'),(13,5,3,'2026-05-13 22:05:00',2,'sinh nhật','hoan_thanh','2026-05-13 21:49:02',NULL,'2026-05-13 23:35:00'),(14,43,1,'2026-05-22 20:34:00',2,'Sinh thần','da_xac_nhan','2026-05-22 19:50:52','2026-05-22 20:34:00','2026-05-22 22:04:00'),(15,43,2,'2026-05-22 20:20:00',1,'sinh nhât','hoan_thanh','2026-05-22 19:51:29','2026-05-22 20:20:00','2026-05-22 21:50:00');
/*!40000 ALTER TABLE `reservations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_change_logs`
--

DROP TABLE IF EXISTS `role_change_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_change_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `old_role_id` int(11) NOT NULL,
  `new_role_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `old_role_id` (`old_role_id`),
  KEY `new_role_id` (`new_role_id`),
  KEY `changed_by` (`changed_by`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_changed_at` (`changed_at`),
  CONSTRAINT `role_change_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `role_change_logs_ibfk_2` FOREIGN KEY (`old_role_id`) REFERENCES `roles` (`role_id`),
  CONSTRAINT `role_change_logs_ibfk_3` FOREIGN KEY (`new_role_id`) REFERENCES `roles` (`role_id`),
  CONSTRAINT `role_change_logs_ibfk_4` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_change_logs`
--

LOCK TABLES `role_change_logs` WRITE;
/*!40000 ALTER TABLE `role_change_logs` DISABLE KEYS */;
INSERT INTO `role_change_logs` VALUES (1,38,4,3,2,'2026-05-09 00:22:32','Cập nhật vai trò từ quản lý'),(2,4,4,5,2,'2026-05-18 13:26:32','Cập nhật vai trò từ quản lý'),(3,41,4,3,2,'2026-05-18 13:34:46','Cập nhật vai trò từ quản lý'),(4,5,5,4,2,'2026-05-18 13:35:29','Cập nhật vai trò từ quản lý'),(5,5,4,3,2,'2026-05-18 13:35:47','Cập nhật vai trò từ quản lý'),(6,42,3,4,2,'2026-05-18 13:40:32','Cập nhật vai trò từ quản lý'),(7,5,3,4,2,'2026-05-18 13:40:49','Cập nhật vai trò từ quản lý'),(8,3,3,5,2,'2026-05-18 13:43:14','Cập nhật vai trò từ quản lý'),(9,4,5,4,2,'2026-05-18 13:46:28','Cập nhật vai trò từ quản lý');
/*!40000 ALTER TABLE `role_change_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_roles_role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin'),(4,'Bep'),(6,'KhachHang'),(5,'PhucVu'),(2,'QuanLy'),(3,'ThuNgan');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_days_off`
--

DROP TABLE IF EXISTS `staff_days_off`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_days_off` (
  `day_off_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('cho_duyet','da_duyet','tu_choi') NOT NULL DEFAULT 'cho_duyet',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`day_off_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_dates` (`start_date`,`end_date`),
  CONSTRAINT `fk_staff_days_off_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_days_off`
--

LOCK TABLES `staff_days_off` WRITE;
/*!40000 ALTER TABLE `staff_days_off` DISABLE KEYS */;
INSERT INTO `staff_days_off` VALUES (1,4,'2026-04-30','2026-05-01','','da_duyet','2026-04-10 12:33:25');
/*!40000 ALTER TABLE `staff_days_off` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_shifts`
--

DROP TABLE IF EXISTS `staff_shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_shifts` (
  `shift_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `week_start_date` date DEFAULT NULL COMMENT 'Ngày đầu tuần (Thứ 2)',
  `day_of_week` enum('mon','tue','wed','thu','fri','sat','sun') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`shift_id`),
  UNIQUE KEY `uq_user_day` (`user_id`,`day_of_week`),
  KEY `idx_user_id` (`user_id`),
  KEY `week_start_date` (`week_start_date`),
  CONSTRAINT `fk_staff_shifts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_shifts`
--

LOCK TABLES `staff_shifts` WRITE;
/*!40000 ALTER TABLE `staff_shifts` DISABLE KEYS */;
INSERT INTO `staff_shifts` VALUES (3,4,NULL,'mon','08:00:00','17:00:00','2026-04-12 13:14:37','2026-04-12 13:14:37'),(4,4,NULL,'fri','08:00:00','17:00:00','2026-04-12 13:14:37','2026-04-12 13:14:37'),(5,5,NULL,'mon','08:00:00','17:00:00','2026-04-12 13:15:23','2026-04-12 13:15:23'),(6,5,NULL,'wed','08:00:00','17:00:00','2026-04-12 13:15:23','2026-04-12 13:15:23'),(7,5,NULL,'fri','08:00:00','17:00:00','2026-04-12 13:15:23','2026-04-12 13:15:23'),(8,3,NULL,'mon','08:00:00','17:00:00','2026-04-12 13:18:53','2026-04-12 13:18:53'),(9,3,NULL,'sun','08:00:00','17:00:00','2026-04-12 13:18:53','2026-04-12 13:18:53');
/*!40000 ALTER TABLE `staff_shifts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_config`
--

DROP TABLE IF EXISTS `system_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_config` (
  `config_id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_by` int(11) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`config_id`),
  UNIQUE KEY `config_key` (`config_key`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_config_key` (`config_key`),
  CONSTRAINT `system_config_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_config`
--

LOCK TABLES `system_config` WRITE;
/*!40000 ALTER TABLE `system_config` DISABLE KEYS */;
INSERT INTO `system_config` VALUES (1,'opening_time','10:00','Giờ mở cửa nhà hàng',1,'2026-04-09 21:58:45'),(2,'closing_time','22:00','Giờ đóng cửa nhà hàng',1,'2026-04-09 21:58:45'),(3,'minimum_reservation_duration','60','Thời gian đặt bàn tối thiểu (phút)',1,'2026-04-09 21:58:45'),(4,'buffer_time','15','Thời gian buffer giữa các lượt đặt (phút)',1,'2026-04-09 21:58:45'),(5,'prevent_overlap','true','Không cho phép đặt trùng giờ',1,'2026-04-09 21:58:45'),(6,'default_price_multiplier','1.0','Hệ số nhân giá mặc định',1,'2026-04-09 21:58:45'),(7,'tax_rate','10','Tỷ lệ thuế (%)',1,'2026-04-09 21:58:45');
/*!40000 ALTER TABLE `system_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tables`
--

DROP TABLE IF EXISTS `tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tables` (
  `table_id` int(11) NOT NULL AUTO_INCREMENT,
  `floor_id` int(11) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `capacity` int(11) NOT NULL,
  `status` enum('trong','dang_su_dung','da_dat','bao_tri') NOT NULL DEFAULT 'trong',
  PRIMARY KEY (`table_id`),
  UNIQUE KEY `uq_tables_floor_table_name` (`floor_id`,`table_name`),
  KEY `idx_tables_floor_id` (`floor_id`),
  CONSTRAINT `fk_tables_floor` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`floor_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tables`
--

LOCK TABLES `tables` WRITE;
/*!40000 ALTER TABLE `tables` DISABLE KEYS */;
INSERT INTO `tables` VALUES (1,1,'Bàn 1',4,'da_dat'),(2,1,'Bàn 2',4,'trong'),(3,2,'Bàn 3',6,'trong'),(25,2,'Bàn 4',6,'trong');
/*!40000 ALTER TABLE `tables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `ngay_sinh` date DEFAULT NULL,
  `gioi_tinh` enum('Nam','Nữ') DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `status` enum('hoat_dong','khoa') NOT NULL DEFAULT 'hoat_dong',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`),
  KEY `idx_users_role_id` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Nguyễn Minh Quân','0903456781','1985-01-15','Nam','minhquan@gmail.com','minhquan','$2y$10$UflBjzzhpwmJSE3RAvTwjencXLa8fJbmEYBIdJPwnrI5gxLHkNND6',1,'hoat_dong','2026-03-30 21:24:42'),(2,'Trần Quốc Huy','0903456782','1990-03-22','Nam','quochuy@gmail.com','quochuy','$2y$10$wb6zVYkbL0qPJtYESppFdOHhJdM3rJQyRIpuFEvk8UBHWYpArQTVi',2,'hoat_dong','2026-03-30 21:24:42'),(3,'Lê Thị Ngọc Anh','0903456783','1995-07-10','Nữ','ngocanh@gmail.com','ngocanh','$2y$10$V/SlPjAlP6J1EK9SrYPfZOxrDOB.4g3utNh7HtknmHlr6/G50Q2BG',5,'hoat_dong','2026-03-30 21:24:42'),(4,'Phạm Văn Khánh','0903456784','1988-11-05','Nam','vankhanh@gmail.com','vankhanh','$2y$10$z2nNgamkgFY8xcplgPgZWeq2/a02wqEKVYlWci9tdjCjKyVktkwma',4,'hoat_dong','2026-03-30 21:24:42'),(5,'Đỗ Thị Mai','0903456785','1998-06-18','Nữ','thimai@gmail.com','thimai','$2y$10$1j1GXYDONXyThyEd2w7cWunyCA1KozMWiWhS99Wrg/9sV2HLW5Zoi',4,'hoat_dong','2026-03-30 21:24:42'),(6,'Nguyễn Thu Hà','0911111114','1995-08-28','Nữ','thuha@gmail.com','thuha','$2y$10$wh7Sj/qUBUym1aOqoQJOq./Htw8dHe/jPLC3se32pi4YsAo3WDh5q',6,'hoat_dong','2026-04-05 11:51:38'),(7,'Hoàng Gia Bảo','0911111113','2004-04-24','Nữ',NULL,'giabao','$2y$10$3mCkENcvXKAoa3aWuzZsj.hrIpm38uLT0fz3iYUHaYiqdan76Nsau',6,'hoat_dong','2026-04-23 23:29:05'),(36,'Trần Mỹ Linh','0911111115','2005-10-20','Nữ',NULL,'mylinh','$2y$10$0B8zqjn6ohLuCWivockW0ODdeNczhHdpKXa8QtoEwMljSon/W60eC',6,'hoat_dong','2026-04-25 12:10:03'),(38,'Bùi Thành Đạt','0911111120','2003-10-04','Nam','thanhdat@gmail.com','thanhdat','$2y$10$AsmNdV/NX.qvb9Pg4lDrQOHCIOrSEpOLIeTeyfVZHGddyxeZY6beO',2,'hoat_dong','2026-05-04 15:33:48'),(39,'Võ Quốc Nam','0911111116','2005-05-05','Nam','quocnam@gmail.com','quocnam','$2y$10$roOli3oPaHTW0a.8jAt5RO/HYnpx4nr12XAusvboyfA6kdmGicQxW',6,'hoat_dong','2026-05-08 09:05:21'),(40,'Phùng Khánh Linh','0901234567','1998-06-18','Nữ','PhungKhanhLinh@gmail.com','khanhlinh','$2y$10$m9M02XjYryvurJ5jUcbKWONvZChlED7uzBWT/r/z7URWb/2nSCppi',5,'hoat_dong','2026-05-18 13:24:05'),(41,'Phương Mỹ Chi','0784567890','1997-06-18','Nữ','mychi@gmail.com','mychi','$2y$10$CortGKKI0NiU1fLDFMnE7umN1dPGGpSAPntdc1cSUC01utEq/pxp.',3,'hoat_dong','2026-05-18 13:26:21'),(42,'Trần Thị Thu Lan','0358874187','1997-10-18','Nữ','lan288005@gmail.com','thulan','$2y$10$O0XVrx10G0OMRdM.y4ftdOp8koNmF87fFJPVvIZueV4AjdPAJmoSi',4,'hoat_dong','2026-05-18 13:27:20'),(43,'Trần Huyền Trân','0973900959','1998-06-22','Nữ',NULL,'huyentran','$2y$10$ZY0xfUK1rX8Z7FpDaluqO.y1W7wrFgstv0j9kqKrpJULbB3BOPklq',6,'hoat_dong','2026-05-22 19:11:47');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `v_bao_cao_doanh_thu`
--

DROP TABLE IF EXISTS `v_bao_cao_doanh_thu`;
/*!50001 DROP VIEW IF EXISTS `v_bao_cao_doanh_thu`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_bao_cao_doanh_thu` AS SELECT
 1 AS `ngay`,
  1 AS `so_don_hang`,
  1 AS `tong_doanh_thu`,
  1 AS `doanh_thu_walk_in`,
  1 AS `doanh_thu_online` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_lich_su_giao_dich`
--

DROP TABLE IF EXISTS `v_lich_su_giao_dich`;
/*!50001 DROP VIEW IF EXISTS `v_lich_su_giao_dich`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_lich_su_giao_dich` AS SELECT
 1 AS `customer_id`,
  1 AS `ten_khach_hang`,
  1 AS `so_dien_thoai`,
  1 AS `order_id`,
  1 AS `thoi_gian`,
  1 AS `tong_tien`,
  1 AS `trang_thai`,
  1 AS `loai_don` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_mon_ban_chay`
--

DROP TABLE IF EXISTS `v_mon_ban_chay`;
/*!50001 DROP VIEW IF EXISTS `v_mon_ban_chay`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_mon_ban_chay` AS SELECT
 1 AS `item_id`,
  1 AS `ten_mon`,
  1 AS `danh_muc`,
  1 AS `so_luong_ban`,
  1 AS `doanh_thu`,
  1 AS `gia_hien_tai` */;
SET character_set_client = @saved_cs_client;

--
-- Dumping routines for database 'qlnhahang'
--
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_bao_cao_doanh_thu` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bao_cao_doanh_thu`(IN `p_start_date` DATE, IN `p_end_date` DATE)
BEGIN
    -- Nếu không truyền tham số, mặc định là tháng hiện tại
    IF p_start_date IS NULL THEN
        SET p_start_date = DATE_FORMAT(NOW(), '%Y-%m-01');
    END IF;
    
    IF p_end_date IS NULL THEN
        SET p_end_date = LAST_DAY(NOW());
    END IF;
    
    -- Trả về báo cáo doanh thu
    SELECT 
        DATE(o.order_time) AS ngay,
        COUNT(DISTINCT o.order_id) AS so_don_hang,
        COALESCE(SUM(o.total_amount), 0) AS tong_doanh_thu,
        COALESCE(SUM(CASE WHEN o.reservation_id IS NULL THEN o.total_amount ELSE 0 END), 0) AS doanh_thu_walk_in,
        COALESCE(SUM(CASE WHEN o.reservation_id IS NOT NULL THEN o.total_amount ELSE 0 END), 0) AS doanh_thu_online,
        COALESCE(ROUND(AVG(o.total_amount), 2), 0) AS gia_tri_trung_binh
    FROM orders o
    WHERE o.status = 'da_thanh_toan'
        AND DATE(o.order_time) BETWEEN p_start_date AND p_end_date
    GROUP BY DATE(o.order_time)
    ORDER BY ngay DESC;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_tao_don_hang` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_tao_don_hang`(IN `p_table_id` INT, IN `p_customer_id` INT, IN `p_waiter_id` INT, IN `p_guest_count` INT, OUT `p_order_id` INT, OUT `p_status` VARCHAR(20), OUT `p_message` VARCHAR(255))
BEGIN
    DECLARE v_table_status VARCHAR(20);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_status = 'ERROR';
        SET p_message = 'Lỗi khi tạo đơn hàng';
        SET p_order_id = NULL;
    END;
    
    START TRANSACTION;
    
    -- Kiểm tra bàn có tồn tại không
    SELECT status INTO v_table_status 
    FROM tables 
    WHERE table_id = p_table_id;
    
    -- Nếu bàn không tồn tại
    IF v_table_status IS NULL THEN
        SET p_status = 'ERROR';
        SET p_message = 'Bàn không tồn tại';
        SET p_order_id = NULL;
        ROLLBACK;
    -- Kiểm tra trạng thái bàn
    ELSEIF v_table_status NOT IN ('trong', 'da_dat') THEN
        SET p_status = 'ERROR';
        SET p_message = CONCAT('Bàn đang ', v_table_status, ', không thể tạo đơn');
        SET p_order_id = NULL;
        ROLLBACK;
    ELSE
        -- Tạo đơn hàng mới
        INSERT INTO orders (
            table_id, 
            customer_id, 
            waiter_id, 
            order_time, 
            status, 
            total_amount, 
            paid_amount,
            guest_count
        ) VALUES (
            p_table_id,
            p_customer_id,
            p_waiter_id,
            NOW(),
            'moi',
            0.00,
            0.00,
            p_guest_count
        );
        
        SET p_order_id = LAST_INSERT_ID();
        
        -- Cập nhật trạng thái bàn
        UPDATE tables 
        SET status = 'dang_su_dung' 
        WHERE table_id = p_table_id;
        
        SET p_status = 'SUCCESS';
        SET p_message = 'Tạo đơn hàng thành công';
        
        COMMIT;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_xu_ly_thanh_toan` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_xu_ly_thanh_toan`(IN `p_order_id` INT, IN `p_cashier_id` INT, IN `p_payment_method` VARCHAR(20), IN `p_amount_paid` DECIMAL(12,2), OUT `p_payment_id` INT, OUT `p_status` VARCHAR(20), OUT `p_message` VARCHAR(255))
BEGIN
    DECLARE v_order_status VARCHAR(20);
    DECLARE v_total_amount DECIMAL(12,2);
    DECLARE v_paid_amount DECIMAL(12,2);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_status = 'ERROR';
        SET p_message = 'Lỗi khi xử lý thanh toán';
        SET p_payment_id = NULL;
    END;
    
    START TRANSACTION;
    
    -- Kiểm tra đơn hàng có tồn tại không
    SELECT status, total_amount, paid_amount 
    INTO v_order_status, v_total_amount, v_paid_amount
    FROM orders 
    WHERE order_id = p_order_id;
    
    -- Nếu đơn hàng không tồn tại
    IF v_order_status IS NULL THEN
        SET p_status = 'ERROR';
        SET p_message = 'Đơn hàng không tồn tại';
        SET p_payment_id = NULL;
        ROLLBACK;
    -- Kiểm tra trạng thái đơn hàng
    ELSEIF v_order_status NOT IN ('hoan_thanh', 'dang_phuc_vu') THEN
        SET p_status = 'ERROR';
        SET p_message = 'Đơn hàng chưa hoàn thành, không thể thanh toán';
        SET p_payment_id = NULL;
        ROLLBACK;
    ELSEIF p_amount_paid <= 0 THEN
        SET p_status = 'ERROR';
        SET p_message = 'Số tiền thanh toán phải lớn hơn 0';
        SET p_payment_id = NULL;
        ROLLBACK;
    ELSE
        -- Tạo payment record
        INSERT INTO payments (
            order_id,
            cashier_id,
            payment_method,
            amount_paid,
            payment_time,
            payment_status
        ) VALUES (
            p_order_id,
            p_cashier_id,
            p_payment_method,
            p_amount_paid,
            NOW(),
            'thanh_cong'
        );
        
        SET p_payment_id = LAST_INSERT_ID();
        
        -- Trigger tự động cập nhật paid_amount
        -- Nếu đã trả đủ, cập nhật trạng thái đơn
        SELECT paid_amount INTO v_paid_amount
        FROM orders
        WHERE order_id = p_order_id;
        
        IF v_paid_amount >= v_total_amount THEN
            UPDATE orders 
            SET status = 'da_thanh_toan' 
            WHERE order_id = p_order_id;
            -- Trigger tự động trả bàn về 'trong'
        END IF;
        
        SET p_status = 'SUCCESS';
        SET p_message = 'Thanh toán thành công';
        
        COMMIT;
    END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `v_bao_cao_doanh_thu`
--

/*!50001 DROP VIEW IF EXISTS `v_bao_cao_doanh_thu`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_bao_cao_doanh_thu` AS select cast(`o`.`order_time` as date) AS `ngay`,count(distinct `o`.`order_id`) AS `so_don_hang`,coalesce(sum(`o`.`total_amount`),0) AS `tong_doanh_thu`,coalesce(sum(case when `o`.`reservation_id` is null then `o`.`total_amount` else 0 end),0) AS `doanh_thu_walk_in`,coalesce(sum(case when `o`.`reservation_id` is not null then `o`.`total_amount` else 0 end),0) AS `doanh_thu_online` from `orders` `o` where `o`.`status` = 'da_thanh_toan' group by cast(`o`.`order_time` as date) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_lich_su_giao_dich`
--

/*!50001 DROP VIEW IF EXISTS `v_lich_su_giao_dich`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_lich_su_giao_dich` AS select `c`.`customer_id` AS `customer_id`,`c`.`customer_name` AS `ten_khach_hang`,`c`.`phone` AS `so_dien_thoai`,`o`.`order_id` AS `order_id`,`o`.`order_time` AS `thoi_gian`,`o`.`total_amount` AS `tong_tien`,`o`.`status` AS `trang_thai`,case when `o`.`reservation_id` is null then 'Walk-in' else 'Online' end AS `loai_don` from (`customers` `c` left join `orders` `o` on(`c`.`customer_id` = `o`.`customer_id`)) where `o`.`order_id` is not null */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_mon_ban_chay`
--

/*!50001 DROP VIEW IF EXISTS `v_mon_ban_chay`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_mon_ban_chay` AS select `m`.`item_id` AS `item_id`,`m`.`item_name` AS `ten_mon`,`c`.`category_name` AS `danh_muc`,coalesce(sum(`od`.`quantity`),0) AS `so_luong_ban`,coalesce(sum(`od`.`quantity` * `od`.`unit_price`),0) AS `doanh_thu`,`m`.`price` AS `gia_hien_tai` from (((`order_details` `od` join `menu_items` `m` on(`od`.`item_id` = `m`.`item_id`)) join `categories` `c` on(`m`.`category_id` = `c`.`category_id`)) join `orders` `o` on(`od`.`order_id` = `o`.`order_id`)) where `o`.`status` = 'da_thanh_toan' group by `m`.`item_id`,`m`.`item_name`,`c`.`category_name`,`m`.`price` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-22 20:30:57
