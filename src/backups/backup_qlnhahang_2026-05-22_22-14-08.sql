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
  KEY `idx_customers_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'Trương Minh Khôi','0918123456','minhkhoi@gmail.com',5,'2026-03-01 10:00:00',7),(2,'Phan Thị Diệu','0918234567','thidieu@gmail.com',5,'2026-03-05 11:00:00',8),(3,'Vũ Hữu Nghĩa','0918345678','huunghia@gmail.com',5,'2026-03-10 14:00:00',9),(4,'Ngô Thảo My','0918456789','thaomy@gmail.com',5,'2026-04-01 09:00:00',10),(5,'Lê Hoàng Phúc','0938765432','hoangphuc@outlook.com',5,'2026-05-10 16:20:00',NULL),(6,'Phùng Khánh Linh','02838224567','phungkhanhlinh@gmail.com',2,'2026-05-12 09:00:00',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `data_operation_logs`
--

LOCK TABLES `data_operation_logs` WRITE;
/*!40000 ALTER TABLE `data_operation_logs` DISABLE KEYS */;
INSERT INTO `data_operation_logs` VALUES (1,'backup',0,1,'2026-05-01 07:00:00','Sao lưu định kỳ đầu tháng'),(2,'health_check',0,1,'2026-05-22 08:00:00','Kiểm tra hệ thống: OK');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  KEY `idx_menu_items_category_id` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_items`
--

LOCK TABLES `menu_items` WRITE;
/*!40000 ALTER TABLE `menu_items` DISABLE KEYS */;
INSERT INTO `menu_items` VALUES (1,1,'Lẩu bò tươi (nửa kg)',299000.00,'Thịt bò Úc, rau lẩu, nấm, mì và nước dùng đậm vị','/quanlynhahang/assets/images/menu/17758094146724_featured-2.jpg','con_hang'),(2,1,'Nước lẩu Đài Loan',99000.00,'Nước lẩm cay nhẹ, phù hợp 2–4 người','/quanlynhahang/assets/images/menu/17758093916015_hero-slide-3.jpg','con_hang'),(3,1,'Set combo gia đình',399000.00,'Lẩu nhỏ + 2 món phụ + 2 nước','/quanlynhahang/assets/images/menu/17758095160418_combo.jpg','con_hang'),(4,1,'Cơm sườn nướng',65000.00,'Sườn heo nướng, dưa chua, canh rong','/quanlynhahang/assets/images/menu/17758095536862_com_suon.jpg','con_hang'),(5,1,'Bún bò Huế',55000.00,'Bún bò chuẩn vị Huế, chả cua','/quanlynhahang/assets/images/menu/17758093237164_bun_bo.jpg','con_hang'),(6,2,'Trà đào cam sả',35000.00,'Ly lớn, ít đá','/quanlynhahang/assets/images/menu/17758092792503_tra_dao.jpg','con_hang'),(7,2,'Nước ép dưa hấu',45000.00,'Ép tươi, không thêm đường','/quanlynhahang/assets/images/menu/17758091775395_nuoc_ep_dua-hau.jpg','con_hang'),(8,3,'Bánh flan caramel',25000.00,'Làm tại bếp, phục vụ lạnh','/quanlynhahang/assets/images/menu/1775809232082_banh-flan.jpg','con_hang'),(9,1,'Bún nem nướng',88000.00,'Nem nướng Nha Trang, rau sống, nước mắm pha','/quanlynhahang/assets/images/menu/17758093764921_bun.jpg','con_hang'),(10,1,'Gà rang muối',189000.00,'Gà ta xốc muối ớt, ăn kèm lá chanh','/quanlynhahang/assets/images/menu/17794571537295_canhgachien.jpg','con_hang');
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
  KEY `idx_order_details_status` (`item_status`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_details`
--

LOCK TABLES `order_details` WRITE;
/*!40000 ALTER TABLE `order_details` DISABLE KEYS */;
INSERT INTO `order_details` VALUES (1,1,1,1,299000.00,NULL,'hoan_thanh'),(2,1,6,1,35000.00,NULL,'hoan_thanh'),(3,1,8,2,25000.00,NULL,'hoan_thanh'),(4,2,5,2,55000.00,NULL,'hoan_thanh'),(5,2,4,1,65000.00,NULL,'hoan_thanh'),(6,3,3,1,399000.00,'Ít cay','hoan_thanh'),(7,3,6,2,35000.00,NULL,'hoan_thanh'),(8,4,3,2,399000.00,NULL,'hoan_thanh'),(9,5,1,1,299000.00,NULL,'hoan_thanh'),(10,6,9,2,88000.00,NULL,'hoan_thanh'),(11,6,7,1,45000.00,NULL,'hoan_thanh'),(12,6,8,1,25000.00,NULL,'hoan_thanh'),(13,7,1,1,299000.00,NULL,'hoan_thanh'),(14,7,10,1,189000.00,NULL,'hoan_thanh'),(15,7,6,2,35000.00,NULL,'hoan_thanh'),(16,7,8,2,25000.00,NULL,'hoan_thanh'),(17,8,5,5,55000.00,NULL,'moi'),(18,9,1,1,299000.00,'Thêm rau','moi'),(19,9,2,1,99000.00,NULL,'moi'),(20,10,4,1,65000.00,NULL,'hoan_thanh'),(21,10,6,1,35000.00,NULL,'hoan_thanh'),(22,10,8,1,25000.00,NULL,'hoan_thanh');
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
  KEY `idx_orders_time_status` (`order_time`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,1,1,NULL,5,'2026-05-18 17:45:00','da_thanh_toan',384000.00,384000.00,NULL,NULL,2,2),(2,3,NULL,NULL,5,'2026-05-18 19:10:00','da_thanh_toan',175000.00,175000.00,NULL,NULL,4,4),(3,2,1,1,5,'2026-05-18 18:35:00','da_thanh_toan',469000.00,469000.00,NULL,NULL,4,4),(4,4,2,2,6,'2026-05-19 12:05:00','da_thanh_toan',798000.00,798000.00,NULL,NULL,6,6),(5,1,3,3,5,'2026-05-20 19:05:00','da_thanh_toan',299000.00,299000.00,NULL,NULL,2,2),(6,5,5,NULL,6,'2026-05-20 20:30:00','da_thanh_toan',246000.00,246000.00,NULL,NULL,3,3),(7,7,6,NULL,5,'2026-05-21 12:15:00','da_thanh_toan',608000.00,608000.00,NULL,NULL,8,8),(8,1,4,4,NULL,'2026-05-21 11:35:00','da_huy',275000.00,0.00,NULL,NULL,5,5),(9,3,1,5,NULL,'2026-05-22 10:35:00','hoan_thanh',398000.00,0.00,NULL,NULL,3,3),(10,6,NULL,NULL,5,'2026-05-22 18:20:00','hoan_thanh',125000.00,0.00,NULL,NULL,2,2);
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
  KEY `idx_password_reset_expires` (`expires_at`)
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
  KEY `idx_payments_time_status` (`payment_time`,`payment_status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,1,3,'cash','order_payment',384000.00,'2026-05-18 18:50:00','thanh_cong'),(2,2,3,'cash','order_payment',175000.00,'2026-05-18 19:45:00','thanh_cong'),(3,3,3,'bank_transfer','order_payment',469000.00,'2026-05-18 20:15:00','thanh_cong'),(4,4,3,'bank_transfer','order_payment',798000.00,'2026-05-19 13:40:00','thanh_cong'),(5,5,3,'deposit_consumed','deposit_consumed',299000.00,'2026-05-20 20:10:00','thanh_cong'),(6,6,3,'card','order_payment',246000.00,'2026-05-20 21:05:00','thanh_cong'),(7,7,3,'bank_transfer','order_payment',608000.00,'2026-05-21 13:50:00','thanh_cong');
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
  KEY `idx_reservation_items_item_id` (`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservation_items`
--

LOCK TABLES `reservation_items` WRITE;
/*!40000 ALTER TABLE `reservation_items` DISABLE KEYS */;
INSERT INTO `reservation_items` VALUES (1,1,3,1,399000.00,'Ít cay'),(2,1,6,2,35000.00,NULL),(3,2,3,2,399000.00,'Mang về hộp riêng'),(4,3,1,1,299000.00,NULL),(5,4,5,5,55000.00,NULL),(6,5,1,1,299000.00,'Thêm rau'),(7,5,2,1,99000.00,NULL),(8,6,10,1,189000.00,NULL);
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
  KEY `idx_reservation_payment_time` (`payment_time`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservation_payments`
--

LOCK TABLES `reservation_payments` WRITE;
/*!40000 ALTER TABLE `reservation_payments` DISABLE KEYS */;
INSERT INTO `reservation_payments` VALUES (1,1,7,'deposit',50,234500.00,'bank_transfer','2026-05-17 14:21:00','thanh_cong','da_su_dung','Cọc 50% đặt bàn online'),(2,2,8,'deposit',50,399000.00,'bank_transfer','2026-05-18 09:16:00','thanh_cong','da_su_dung','Cọc 50% set combo x2'),(3,3,9,'deposit',50,149500.00,'bank_transfer','2026-05-19 20:41:00','thanh_cong','da_su_dung','Cọc 50% lẩu bò'),(4,4,10,'deposit',50,137500.00,'bank_transfer','2026-05-20 08:01:00','thanh_cong','hoan_tien','Khách hủy vì trễ chuyến bay'),(5,5,7,'deposit',50,199000.00,'bank_transfer','2026-05-22 10:31:00','thanh_cong','giu_lai','Cọc 50% lẩu tối nay'),(6,6,8,'deposit',50,94500.00,'bank_transfer','2026-05-22 15:01:00','cho_xu_ly','giu_lai','Chờ xác nhận chuyển khoản');
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
  KEY `idx_reservations_time_status` (`reservation_time`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservations`
--

LOCK TABLES `reservations` WRITE;
/*!40000 ALTER TABLE `reservations` DISABLE KEYS */;
INSERT INTO `reservations` VALUES (1,7,2,'2026-05-18 18:30:00',4,'Sinh nhật bạn gái, cần bàn yên tĩnh','hoan_thanh','2026-05-17 14:20:00','2026-05-18 18:30:00','2026-05-18 20:30:00'),(2,8,4,'2026-05-19 12:00:00',6,'Đặt trước set combo cho công ty','hoan_thanh','2026-05-18 09:15:00','2026-05-19 12:00:00','2026-05-19 14:30:00'),(3,9,1,'2026-05-20 19:00:00',2,'Hẹn hò, góc trong','hoan_thanh','2026-05-19 20:40:00','2026-05-20 19:00:00','2026-05-20 20:30:00'),(4,10,7,'2026-05-21 11:30:00',5,'Gia đình có 2 trẻ em','da_huy','2026-05-20 08:00:00','2026-05-21 11:30:00','2026-05-21 13:30:00'),(5,7,3,'2026-05-22 18:00:00',3,'Khách quen, thích lẩu bò','hoan_thanh','2026-05-22 10:30:00','2026-05-22 18:00:00','2026-05-22 19:30:00'),(6,8,5,'2026-05-23 19:30:00',4,'Tiếp đối tác','hoan_thanh','2026-05-22 15:00:00','2026-05-23 19:30:00','2026-05-23 21:30:00');
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
  KEY `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_change_logs`
--

LOCK TABLES `role_change_logs` WRITE;
/*!40000 ALTER TABLE `role_change_logs` DISABLE KEYS */;
INSERT INTO `role_change_logs` VALUES (1,6,4,5,2,'2026-02-01 08:00:00','Chuyển từ bếp sang phục vụ ca tối');
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
  KEY `idx_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_days_off`
--

LOCK TABLES `staff_days_off` WRITE;
/*!40000 ALTER TABLE `staff_days_off` DISABLE KEYS */;
INSERT INTO `staff_days_off` VALUES (1,4,'2026-05-01','2026-05-01','Nghỉ lễ 30/4','da_duyet','2026-04-25 09:00:00');
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
  KEY `week_start_date` (`week_start_date`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_shifts`
--

LOCK TABLES `staff_shifts` WRITE;
/*!40000 ALTER TABLE `staff_shifts` DISABLE KEYS */;
INSERT INTO `staff_shifts` VALUES (1,4,NULL,'mon','09:00:00','17:00:00','2026-01-15 08:00:00','2026-01-15 08:00:00'),(2,4,NULL,'wed','09:00:00','17:00:00','2026-01-15 08:00:00','2026-01-15 08:00:00'),(3,4,NULL,'fri','11:00:00','21:00:00','2026-01-15 08:00:00','2026-01-15 08:00:00'),(4,5,NULL,'tue','10:00:00','20:00:00','2026-01-15 08:00:00','2026-01-15 08:00:00'),(5,5,NULL,'thu','10:00:00','20:00:00','2026-01-15 08:00:00','2026-01-15 08:00:00'),(6,5,NULL,'sat','10:00:00','22:00:00','2026-01-15 08:00:00','2026-01-15 08:00:00'),(7,6,NULL,'sun','10:00:00','20:00:00','2026-02-01 08:00:00','2026-02-01 08:00:00'),(8,3,NULL,'mon','10:00:00','22:00:00','2026-01-15 08:00:00','2026-01-15 08:00:00'),(9,3,NULL,'fri','10:00:00','22:00:00','2026-01-15 08:00:00','2026-01-15 08:00:00');
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
  KEY `idx_config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_config`
--

LOCK TABLES `system_config` WRITE;
/*!40000 ALTER TABLE `system_config` DISABLE KEYS */;
INSERT INTO `system_config` VALUES (1,'opening_time','10:00','Giờ mở cửa nhà hàng',1,'2026-01-10 08:00:00'),(2,'closing_time','22:00','Giờ đóng cửa nhà hàng',1,'2026-01-10 08:00:00'),(3,'minimum_reservation_duration','60','Thời gian đặt bàn tối thiểu (phút)',1,'2026-01-10 08:00:00'),(4,'buffer_time','15','Thời gian buffer giữa các lượt đặt (phút)',1,'2026-01-10 08:00:00'),(5,'prevent_overlap','true','Không cho phép đặt trùng giờ',1,'2026-01-10 08:00:00'),(6,'default_price_multiplier','1.0','Hệ số nhân giá mặc định',1,'2026-01-10 08:00:00'),(7,'tax_rate','8','Tỷ lệ thuế (%)',1,'2026-01-10 08:00:00');
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
  KEY `idx_tables_floor_id` (`floor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tables`
--

LOCK TABLES `tables` WRITE;
/*!40000 ALTER TABLE `tables` DISABLE KEYS */;
INSERT INTO `tables` VALUES (1,1,'Bàn 1',4,'trong'),(2,1,'Bàn 2',4,'trong'),(3,1,'Bàn 3',6,'trong'),(4,1,'Bàn VIP 1',8,'trong'),(5,2,'Bàn 5',4,'trong'),(6,2,'Bàn 6',4,'dang_su_dung'),(7,2,'Bàn 7',6,'trong'),(8,2,'Bàn 8',6,'trong');
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
  KEY `idx_users_role_id` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Nguyễn Minh Quân','0905123456','1985-01-15','Nam','minhquan.admin@gmail.com','minhquan','$2y$10$UflBjzzhpwmJSE3RAvTwjencXLa8fJbmEYBIdJPwnrI5gxLHkNND6',1,'hoat_dong','2026-03-30 21:24:42'),(2,'Trần Quốc Huy','0906234567','1990-03-22','Nam','quochuy.manager@gmail.com','quochuy','$2y$10$wb6zVYkbL0qPJtYESppFdOHhJdM3rJQyRIpuFEvk8UBHWYpArQTVi',2,'hoat_dong','2026-03-30 21:24:42'),(3,'Lê Thị Ngọc Anh','0912345678','1995-07-10','Nữ','ngocanh.service@gmail.com','ngocanh','$2y$10$V/SlPjAlP6J1EK9SrYPfZOxrDOB.4g3utNh7HtknmHlr6/G50Q2BG',5,'hoat_dong','2026-03-30 21:24:42'),(4,'Phạm Văn Khánh','0938456123','1988-11-05','Nam','vankhanh.kitchen@gmail.com','vankhanh','$2y$10$z2nNgamkgFY8xcplgPgZWeq2/a02wqEKVYlWci9tdjCjKyVktkwma',4,'hoat_dong','2026-03-30 21:24:42'),(5,'Phương Mỹ Chi','0789456123','1997-06-18','Nữ','mychi.cashier@gmail.com','mychi','$2y$10$CortGKKI0NiU1fLDFMnE7umN1dPGGpSAPntdc1cSUC01utEq/pxp.',3,'hoat_dong','2026-05-18 13:26:21');
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

-- Dump completed on 2026-05-22 22:14:09
