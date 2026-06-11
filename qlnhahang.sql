-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th5 22, 2026 lúc 06:12 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `qlnhahang`
--

DELIMITER $$
--
-- Thủ tục
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bao_cao_doanh_thu` (IN `p_start_date` DATE, IN `p_end_date` DATE)   BEGIN
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
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_tao_don_hang` (IN `p_table_id` INT, IN `p_customer_id` INT, IN `p_waiter_id` INT, IN `p_guest_count` INT, OUT `p_order_id` INT, OUT `p_status` VARCHAR(20), OUT `p_message` VARCHAR(255))   BEGIN
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
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_xu_ly_thanh_toan` (IN `p_order_id` INT, IN `p_cashier_id` INT, IN `p_payment_method` VARCHAR(20), IN `p_amount_paid` DECIMAL(12,2), OUT `p_payment_id` INT, OUT `p_status` VARCHAR(20), OUT `p_message` VARCHAR(255))   BEGIN
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
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'MonChinh', 'Cac mon an chinh'),
(2, 'DoUong', 'Nuoc giai khat'),
(3, 'TrangMieng', 'Mon trang mieng');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `customers`
--

INSERT INTO `customers` (`customer_id`, `customer_name`, `phone`, `email`, `created_by`, `created_at`, `user_id`) VALUES
(1, 'Trương Minh Khôi', '0918123456', 'minhkhoi@gmail.com', 5, '2026-03-01 10:00:00', 7),
(2, 'Phan Thị Diệu', '0918234567', 'thidieu@gmail.com', 5, '2026-03-05 11:00:00', 8),
(3, 'Vũ Hữu Nghĩa', '0918345678', 'huunghia@gmail.com', 5, '2026-03-10 14:00:00', 9),
(4, 'Ngô Thảo My', '0918456789', 'thaomy@gmail.com', 5, '2026-04-01 09:00:00', 10),
(5, 'Lê Hoàng Phúc', '0938765432', 'hoangphuc@outlook.com', 5, '2026-05-10 16:20:00', NULL),
(6, 'Phùng Khánh Linh', '02838224567', 'phungkhanhlinh@gmail.com', 2, '2026-05-12 09:00:00', NULL),
(7, 'Trần Huyền Trân', '0358874187', NULL, NULL, '2026-05-22 23:10:36', 11);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `data_operation_logs`
--

CREATE TABLE `data_operation_logs` (
  `log_id` int(11) NOT NULL,
  `action_type` enum('backup','health_check','manual_log','delete_orders','delete_reservations','reset_all','restore','clean_old_data') NOT NULL,
  `deleted_count` int(11) DEFAULT 0,
  `performed_by` int(11) NOT NULL,
  `performed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `data_operation_logs`
--

INSERT INTO `data_operation_logs` (`log_id`, `action_type`, `deleted_count`, `performed_by`, `performed_at`, `details`) VALUES
(1, 'backup', 0, 1, '2026-05-01 07:00:00', 'Sao lưu định kỳ đầu tháng'),
(2, 'health_check', 0, 1, '2026-05-22 08:00:00', 'Kiểm tra hệ thống: OK'),
(3, 'backup', 0, 1, '2026-05-22 22:14:09', 'File: backup_qlnhahang_2026-05-22_22-14-08.sql (0.05MB) - Lưu tại: C:\\xampp\\htdocs\\quanlynhahang\\admin\\api/../../backups');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `floors`
--

CREATE TABLE `floors` (
  `floor_id` int(11) NOT NULL,
  `floor_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `max_tables` int(11) DEFAULT NULL COMMENT 'Số bàn tối đa cho phép trong khu vực'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `floors`
--

INSERT INTO `floors` (`floor_id`, `floor_name`, `description`, `max_tables`) VALUES
(1, 'Tầng 1', 'Khu vực tầng 1', 20),
(2, 'Tầng 2', 'Khu vực tầng 2', 15);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `menu_items`
--

CREATE TABLE `menu_items` (
  `item_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `status` enum('con_hang','het_hang','ngung_ban') NOT NULL DEFAULT 'con_hang'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `menu_items`
--

INSERT INTO `menu_items` (`item_id`, `category_id`, `item_name`, `price`, `description`, `image_url`, `status`) VALUES
(1, 1, 'Lẩu bò tươi (nửa kg)', 299000.00, 'Thịt bò Úc, rau lẩu, nấm, mì và nước dùng đậm vị', '/quanlynhahang/assets/images/menu/17758094146724_featured-2.jpg', 'con_hang'),
(2, 1, 'Nước lẩu Đài Loan', 99000.00, 'Nước lẩm cay nhẹ, phù hợp 2–4 người', '/quanlynhahang/assets/images/menu/17758093916015_hero-slide-3.jpg', 'con_hang'),
(3, 1, 'Set combo gia đình', 399000.00, 'Lẩu nhỏ + 2 món phụ + 2 nước', '/quanlynhahang/assets/images/menu/17758095160418_combo.jpg', 'con_hang'),
(4, 1, 'Cơm sườn nướng', 65000.00, 'Sườn heo nướng, dưa chua, canh rong', '/quanlynhahang/assets/images/menu/17758095536862_com_suon.jpg', 'con_hang'),
(5, 1, 'Bún bò Huế', 55000.00, 'Bún bò chuẩn vị Huế, chả cua', '/quanlynhahang/assets/images/menu/17758093237164_bun_bo.jpg', 'con_hang'),
(6, 2, 'Trà đào cam sả', 35000.00, 'Ly lớn, ít đá', '/quanlynhahang/assets/images/menu/17758092792503_tra_dao.jpg', 'con_hang'),
(7, 2, 'Nước ép dưa hấu', 45000.00, 'Ép tươi, không thêm đường', '/quanlynhahang/assets/images/menu/17758091775395_nuoc_ep_dua-hau.jpg', 'con_hang'),
(8, 3, 'Bánh flan caramel', 25000.00, 'Làm tại bếp, phục vụ lạnh', '/quanlynhahang/assets/images/menu/1775809232082_banh-flan.jpg', 'con_hang'),
(9, 1, 'Bún nem nướng', 88000.00, 'Nem nướng Nha Trang, rau sống, nước mắm pha', '/quanlynhahang/assets/images/menu/17758093764921_bun.jpg', 'con_hang'),
(10, 1, 'Gà rang muối', 189000.00, 'Gà ta xốc muối ớt, ăn kèm lá chanh', '/quanlynhahang/assets/images/menu/17794571537295_canhgachien.jpg', 'con_hang');

--
-- Bẫy `menu_items`
--
DELIMITER $$
CREATE TRIGGER `trg_menu_items_before_delete` BEFORE DELETE ON `menu_items` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
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
  `number_of_people` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `orders`
--

INSERT INTO `orders` (`order_id`, `table_id`, `customer_id`, `reservation_id`, `waiter_id`, `order_time`, `status`, `total_amount`, `paid_amount`, `start_time`, `end_time`, `guest_count`, `number_of_people`) VALUES
(1, 1, 1, NULL, 5, '2026-05-18 17:45:00', 'da_thanh_toan', 384000.00, 384000.00, NULL, NULL, 2, 2),
(2, 3, NULL, NULL, 5, '2026-05-18 19:10:00', 'da_thanh_toan', 175000.00, 175000.00, NULL, NULL, 4, 4),
(3, 2, 1, 1, 5, '2026-05-18 18:35:00', 'da_thanh_toan', 469000.00, 469000.00, NULL, NULL, 4, 4),
(4, 4, 2, 2, 6, '2026-05-19 12:05:00', 'da_thanh_toan', 798000.00, 798000.00, NULL, NULL, 6, 6),
(5, 1, 3, 3, 5, '2026-05-20 19:05:00', 'da_thanh_toan', 299000.00, 299000.00, NULL, NULL, 2, 2),
(6, 5, 5, NULL, 6, '2026-05-20 20:30:00', 'da_thanh_toan', 246000.00, 246000.00, NULL, NULL, 3, 3),
(7, 7, 6, NULL, 5, '2026-05-21 12:15:00', 'da_thanh_toan', 608000.00, 608000.00, NULL, NULL, 8, 8),
(8, 1, 4, 4, NULL, '2026-05-21 11:35:00', 'da_huy', 275000.00, 0.00, NULL, NULL, 5, 5),
(9, 3, 1, 5, NULL, '2026-05-22 10:35:00', 'da_thanh_toan', 398000.00, 199000.00, NULL, NULL, 3, 3),
(10, 6, NULL, NULL, 5, '2026-05-22 18:20:00', 'da_thanh_toan', 268000.00, 268000.00, NULL, NULL, 2, 2),
(11, 1, 7, 7, NULL, '2026-05-22 23:11:16', 'da_thanh_toan', 299000.00, 149500.00, NULL, NULL, 3, 0);

--
-- Bẫy `orders`
--
DELIMITER $$
CREATE TRIGGER `trg_orders_after_update_status` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    -- Khi đơn chuyển sang đã thanh toán → trả bàn về trống
    IF NEW.status = 'da_thanh_toan' AND OLD.status != 'da_thanh_toan' THEN
        UPDATE tables 
        SET status = 'trong' 
        WHERE table_id = NEW.table_id
          AND status != 'bao_tri'; -- Không đổi nếu đang bảo trì
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_details`
--

CREATE TABLE `order_details` (
  `order_detail_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `item_status` enum('moi','dang_che_bien','hoan_thanh') DEFAULT 'moi'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `order_details`
--

INSERT INTO `order_details` (`order_detail_id`, `order_id`, `item_id`, `quantity`, `unit_price`, `note`, `item_status`) VALUES
(1, 1, 1, 1, 299000.00, NULL, 'hoan_thanh'),
(2, 1, 6, 1, 35000.00, NULL, 'hoan_thanh'),
(3, 1, 8, 2, 25000.00, NULL, 'hoan_thanh'),
(4, 2, 5, 2, 55000.00, NULL, 'hoan_thanh'),
(5, 2, 4, 1, 65000.00, NULL, 'hoan_thanh'),
(6, 3, 3, 1, 399000.00, 'Ít cay', 'hoan_thanh'),
(7, 3, 6, 2, 35000.00, NULL, 'hoan_thanh'),
(8, 4, 3, 2, 399000.00, NULL, 'hoan_thanh'),
(9, 5, 1, 1, 299000.00, NULL, 'hoan_thanh'),
(10, 6, 9, 2, 88000.00, NULL, 'hoan_thanh'),
(11, 6, 7, 1, 45000.00, NULL, 'hoan_thanh'),
(12, 6, 8, 1, 25000.00, NULL, 'hoan_thanh'),
(13, 7, 1, 1, 299000.00, NULL, 'hoan_thanh'),
(14, 7, 10, 1, 189000.00, NULL, 'hoan_thanh'),
(15, 7, 6, 2, 35000.00, NULL, 'hoan_thanh'),
(16, 7, 8, 2, 25000.00, NULL, 'hoan_thanh'),
(17, 8, 5, 5, 55000.00, NULL, 'moi'),
(18, 9, 1, 1, 299000.00, 'Thêm rau', 'moi'),
(19, 9, 2, 1, 99000.00, NULL, 'moi'),
(20, 10, 4, 1, 65000.00, NULL, 'hoan_thanh'),
(21, 10, 6, 1, 35000.00, NULL, 'hoan_thanh'),
(22, 10, 8, 1, 25000.00, NULL, 'hoan_thanh'),
(23, 10, 5, 1, 55000.00, '', 'moi'),
(24, 10, 9, 1, 88000.00, '', 'moi'),
(25, 11, 1, 1, 299000.00, NULL, 'moi');

--
-- Bẫy `order_details`
--
DELIMITER $$
CREATE TRIGGER `trg_order_details_after_delete` AFTER DELETE ON `order_details` FOR EACH ROW BEGIN
    -- Tính lại tổng tiền cho đơn hàng
    UPDATE orders 
    SET total_amount = (
        SELECT COALESCE(SUM(quantity * unit_price), 0)
        FROM order_details
        WHERE order_id = OLD.order_id
    )
    WHERE order_id = OLD.order_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_order_details_after_insert` AFTER INSERT ON `order_details` FOR EACH ROW BEGIN
    -- Tính lại tổng tiền cho đơn hàng
    UPDATE orders 
    SET total_amount = (
        SELECT COALESCE(SUM(quantity * unit_price), 0)
        FROM order_details
        WHERE order_id = NEW.order_id
    )
    WHERE order_id = NEW.order_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_order_details_after_update` AFTER UPDATE ON `order_details` FOR EACH ROW BEGIN
    -- Tính lại tổng tiền cho đơn hàng
    UPDATE orders 
    SET total_amount = (
        SELECT COALESCE(SUM(quantity * unit_price), 0)
        FROM order_details
        WHERE order_id = NEW.order_id
    )
    WHERE order_id = NEW.order_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_order_details_before_insert` BEFORE INSERT ON `order_details` FOR EACH ROW BEGIN
    -- Nếu chưa có unit_price hoặc unit_price = 0, lấy từ menu_items
    IF NEW.unit_price IS NULL OR NEW.unit_price = 0 THEN
        SET NEW.unit_price = (
            SELECT price 
            FROM menu_items 
            WHERE item_id = NEW.item_id
            LIMIT 1
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_order_details_validate` BEFORE INSERT ON `order_details` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `token_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `payment_method` enum('cash','bank_transfer','card','e_wallet','deposit_consumed') NOT NULL,
  `payment_type` varchar(50) DEFAULT 'order_payment',
  `amount_paid` decimal(12,2) NOT NULL,
  `payment_time` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('thanh_cong','that_bai','hoan_tien') NOT NULL DEFAULT 'thanh_cong'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `payments`
--

INSERT INTO `payments` (`payment_id`, `order_id`, `cashier_id`, `payment_method`, `payment_type`, `amount_paid`, `payment_time`, `payment_status`) VALUES
(1, 1, 3, 'cash', 'order_payment', 384000.00, '2026-05-18 18:50:00', 'thanh_cong'),
(2, 2, 3, 'cash', 'order_payment', 175000.00, '2026-05-18 19:45:00', 'thanh_cong'),
(3, 3, 3, 'bank_transfer', 'order_payment', 469000.00, '2026-05-18 20:15:00', 'thanh_cong'),
(4, 4, 3, 'bank_transfer', 'order_payment', 798000.00, '2026-05-19 13:40:00', 'thanh_cong'),
(5, 5, 3, 'deposit_consumed', 'deposit_consumed', 299000.00, '2026-05-20 20:10:00', 'thanh_cong'),
(6, 6, 3, 'card', 'order_payment', 246000.00, '2026-05-20 21:05:00', 'thanh_cong'),
(7, 7, 3, 'bank_transfer', 'order_payment', 608000.00, '2026-05-21 13:50:00', 'thanh_cong'),
(8, 9, 5, 'cash', 'order_payment', 199000.00, '2026-05-22 22:17:20', 'thanh_cong'),
(9, 10, 5, 'cash', 'order_payment', 268000.00, '2026-05-22 22:17:21', 'thanh_cong'),
(10, 11, 5, 'cash', 'order_payment', 149500.00, '2026-05-22 23:12:14', 'thanh_cong');

--
-- Bẫy `payments`
--
DELIMITER $$
CREATE TRIGGER `trg_payments_after_delete` AFTER DELETE ON `payments` FOR EACH ROW BEGIN
    -- Cập nhật lại paid_amount khi xóa payment
    UPDATE orders 
    SET paid_amount = (
        SELECT COALESCE(SUM(amount_paid), 0)
        FROM payments
        WHERE order_id = OLD.order_id 
          AND payment_status = 'thanh_cong'
    )
    WHERE order_id = OLD.order_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_payments_after_insert` AFTER INSERT ON `payments` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_payments_after_update` AFTER UPDATE ON `payments` FOR EACH ROW BEGIN
    -- Cập nhật lại paid_amount khi trạng thái thanh toán thay đổi
    UPDATE orders 
    SET paid_amount = (
        SELECT COALESCE(SUM(amount_paid), 0)
        FROM payments
        WHERE order_id = NEW.order_id 
          AND payment_status = 'thanh_cong'
    )
    WHERE order_id = NEW.order_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `reservations`
--

CREATE TABLE `reservations` (
  `reservation_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `table_id` int(11) NOT NULL,
  `reservation_time` datetime NOT NULL,
  `number_of_people` int(11) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `status` enum('cho_xac_nhan','da_xac_nhan','da_checkin','khong_den','da_huy','hoan_thanh') NOT NULL DEFAULT 'cho_xac_nhan',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `reservations`
--

INSERT INTO `reservations` (`reservation_id`, `user_id`, `table_id`, `reservation_time`, `number_of_people`, `note`, `status`, `created_at`, `start_time`, `end_time`) VALUES
(1, 7, 2, '2026-05-18 18:30:00', 4, 'Sinh nhật bạn gái, cần bàn yên tĩnh', 'hoan_thanh', '2026-05-17 14:20:00', '2026-05-18 18:30:00', '2026-05-18 20:30:00'),
(2, 8, 4, '2026-05-19 12:00:00', 6, 'Đặt trước set combo cho công ty', 'hoan_thanh', '2026-05-18 09:15:00', '2026-05-19 12:00:00', '2026-05-19 14:30:00'),
(3, 9, 1, '2026-05-20 19:00:00', 2, 'Hẹn hò, góc trong', 'hoan_thanh', '2026-05-19 20:40:00', '2026-05-20 19:00:00', '2026-05-20 20:30:00'),
(4, 10, 7, '2026-05-21 11:30:00', 5, 'Gia đình có 2 trẻ em', 'da_huy', '2026-05-20 08:00:00', '2026-05-21 11:30:00', '2026-05-21 13:30:00'),
(5, 7, 3, '2026-05-22 18:00:00', 3, 'Khách quen, thích lẩu bò', 'hoan_thanh', '2026-05-22 10:30:00', '2026-05-22 18:00:00', '2026-05-22 19:30:00'),
(6, 8, 5, '2026-05-23 19:30:00', 4, 'Tiếp đối tác', 'hoan_thanh', '2026-05-22 15:00:00', '2026-05-23 19:30:00', '2026-05-23 21:30:00'),
(7, 11, 1, '2026-05-22 23:36:00', 3, 'Sinh nhật', 'hoan_thanh', '2026-05-22 23:11:16', '2026-05-22 23:36:00', '2026-05-23 01:36:00');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `reservation_items`
--

CREATE TABLE `reservation_items` (
  `reservation_item_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `reservation_items`
--

INSERT INTO `reservation_items` (`reservation_item_id`, `reservation_id`, `item_id`, `quantity`, `unit_price`, `note`) VALUES
(1, 1, 3, 1, 399000.00, 'Ít cay'),
(2, 1, 6, 2, 35000.00, NULL),
(3, 2, 3, 2, 399000.00, 'Mang về hộp riêng'),
(4, 3, 1, 1, 299000.00, NULL),
(5, 4, 5, 5, 55000.00, NULL),
(6, 5, 1, 1, 299000.00, 'Thêm rau'),
(7, 5, 2, 1, 99000.00, NULL),
(8, 6, 10, 1, 189000.00, NULL),
(9, 7, 1, 1, 299000.00, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `reservation_payments`
--

CREATE TABLE `reservation_payments` (
  `reservation_payment_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `payment_type` varchar(20) NOT NULL,
  `payment_percent` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_time` datetime NOT NULL,
  `payment_status` enum('cho_xu_ly','thanh_cong','that_bai','hoan_tien') NOT NULL DEFAULT 'cho_xu_ly',
  `deposit_status` enum('giu_lai','da_su_dung','hoan_tien') NOT NULL DEFAULT 'giu_lai',
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `reservation_payments`
--

INSERT INTO `reservation_payments` (`reservation_payment_id`, `reservation_id`, `cashier_id`, `payment_type`, `payment_percent`, `amount`, `payment_method`, `payment_time`, `payment_status`, `deposit_status`, `note`) VALUES
(1, 1, 7, 'deposit', 50, 234500.00, 'bank_transfer', '2026-05-17 14:21:00', 'thanh_cong', 'da_su_dung', 'Cọc 50% đặt bàn online'),
(2, 2, 8, 'deposit', 50, 399000.00, 'bank_transfer', '2026-05-18 09:16:00', 'thanh_cong', 'da_su_dung', 'Cọc 50% set combo x2'),
(3, 3, 9, 'deposit', 50, 149500.00, 'bank_transfer', '2026-05-19 20:41:00', 'thanh_cong', 'da_su_dung', 'Cọc 50% lẩu bò'),
(4, 4, 10, 'deposit', 50, 137500.00, 'bank_transfer', '2026-05-20 08:01:00', 'thanh_cong', 'hoan_tien', 'Khách hủy vì trễ chuyến bay'),
(5, 5, 7, 'deposit', 50, 199000.00, 'bank_transfer', '2026-05-22 10:31:00', 'thanh_cong', 'da_su_dung', 'Cọc 50% lẩu tối nay'),
(6, 6, 8, 'deposit', 50, 94500.00, 'bank_transfer', '2026-05-22 15:01:00', 'cho_xu_ly', 'giu_lai', 'Chờ xác nhận chuyển khoản'),
(7, 7, 11, 'deposit', 50, 149500.00, 'bank_transfer', '2026-05-22 23:11:16', 'thanh_cong', 'da_su_dung', 'Đặt từ website | Sinh nhật | Mức thanh toán trước: 50% | Noi dung CK: DATBAN 7 0358874187 | Tong mon dat truoc: 299000');

--
-- Bẫy `reservation_payments`
--
DELIMITER $$
CREATE TRIGGER `trg_reservation_payments_after_insert` AFTER INSERT ON `reservation_payments` FOR EACH ROW BEGIN
    -- Khi thanh toán cọc thành công → chuyển reservation sang đã xác nhận
    IF NEW.payment_status = 'thanh_cong' THEN
        UPDATE reservations 
        SET status = 'da_xac_nhan'
        WHERE reservation_id = NEW.reservation_id
          AND status = 'cho_xac_nhan'; -- Chỉ update nếu đang chờ xác nhận
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(1, 'Admin'),
(4, 'Bep'),
(6, 'KhachHang'),
(5, 'PhucVu'),
(2, 'QuanLy'),
(3, 'ThuNgan');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `role_change_logs`
--

CREATE TABLE `role_change_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `old_role_id` int(11) NOT NULL,
  `new_role_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `role_change_logs`
--

INSERT INTO `role_change_logs` (`log_id`, `user_id`, `old_role_id`, `new_role_id`, `changed_by`, `changed_at`, `note`) VALUES
(1, 6, 4, 5, 2, '2026-02-01 08:00:00', 'Chuyển từ bếp sang phục vụ ca tối');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `staff_days_off`
--

CREATE TABLE `staff_days_off` (
  `day_off_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('cho_duyet','da_duyet','tu_choi') NOT NULL DEFAULT 'cho_duyet',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `staff_days_off`
--

INSERT INTO `staff_days_off` (`day_off_id`, `user_id`, `start_date`, `end_date`, `reason`, `status`, `created_at`) VALUES
(1, 4, '2026-05-01', '2026-05-01', 'Nghỉ lễ 30/4', 'da_duyet', '2026-04-25 09:00:00');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `staff_shifts`
--

CREATE TABLE `staff_shifts` (
  `shift_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `week_start_date` date DEFAULT NULL COMMENT 'Ngày đầu tuần (Thứ 2)',
  `day_of_week` enum('mon','tue','wed','thu','fri','sat','sun') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `staff_shifts`
--

INSERT INTO `staff_shifts` (`shift_id`, `user_id`, `week_start_date`, `day_of_week`, `start_time`, `end_time`, `created_at`, `updated_at`) VALUES
(1, 4, NULL, 'mon', '09:00:00', '17:00:00', '2026-01-15 08:00:00', '2026-01-15 08:00:00'),
(2, 4, NULL, 'wed', '09:00:00', '17:00:00', '2026-01-15 08:00:00', '2026-01-15 08:00:00'),
(3, 4, NULL, 'fri', '11:00:00', '21:00:00', '2026-01-15 08:00:00', '2026-01-15 08:00:00'),
(4, 5, NULL, 'tue', '10:00:00', '20:00:00', '2026-01-15 08:00:00', '2026-01-15 08:00:00'),
(5, 5, NULL, 'thu', '10:00:00', '20:00:00', '2026-01-15 08:00:00', '2026-01-15 08:00:00'),
(6, 5, NULL, 'sat', '10:00:00', '22:00:00', '2026-01-15 08:00:00', '2026-01-15 08:00:00'),
(7, 6, NULL, 'sun', '10:00:00', '20:00:00', '2026-02-01 08:00:00', '2026-02-01 08:00:00'),
(8, 3, NULL, 'mon', '10:00:00', '22:00:00', '2026-01-15 08:00:00', '2026-01-15 08:00:00'),
(9, 3, NULL, 'fri', '10:00:00', '22:00:00', '2026-01-15 08:00:00', '2026-01-15 08:00:00');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `system_config`
--

CREATE TABLE `system_config` (
  `config_id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_by` int(11) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `system_config`
--

INSERT INTO `system_config` (`config_id`, `config_key`, `config_value`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'opening_time', '10:00', 'Giờ mở cửa nhà hàng', 1, '2026-01-10 08:00:00'),
(2, 'closing_time', '22:00', 'Giờ đóng cửa nhà hàng', 1, '2026-01-10 08:00:00'),
(3, 'minimum_reservation_duration', '60', 'Thời gian đặt bàn tối thiểu (phút)', 1, '2026-01-10 08:00:00'),
(4, 'buffer_time', '15', 'Thời gian buffer giữa các lượt đặt (phút)', 1, '2026-01-10 08:00:00'),
(5, 'prevent_overlap', 'true', 'Không cho phép đặt trùng giờ', 1, '2026-01-10 08:00:00'),
(6, 'default_price_multiplier', '1.0', 'Hệ số nhân giá mặc định', 1, '2026-01-10 08:00:00'),
(7, 'tax_rate', '8', 'Tỷ lệ thuế (%)', 1, '2026-01-10 08:00:00');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tables`
--

CREATE TABLE `tables` (
  `table_id` int(11) NOT NULL,
  `floor_id` int(11) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `capacity` int(11) NOT NULL,
  `status` enum('trong','dang_su_dung','da_dat','bao_tri') NOT NULL DEFAULT 'trong'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `tables`
--

INSERT INTO `tables` (`table_id`, `floor_id`, `table_name`, `capacity`, `status`) VALUES
(1, 1, 'Bàn 1', 4, 'trong'),
(2, 1, 'Bàn 2', 4, 'trong'),
(3, 1, 'Bàn 3', 6, 'trong'),
(4, 1, 'Bàn VIP 1', 8, 'trong'),
(5, 2, 'Bàn 5', 4, 'trong'),
(6, 2, 'Bàn 6', 4, 'trong'),
(7, 2, 'Bàn 7', 6, 'trong'),
(8, 2, 'Bàn 8', 6, 'trong');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `ngay_sinh` date DEFAULT NULL,
  `gioi_tinh` enum('Nam','Nữ') DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `status` enum('hoat_dong','khoa') NOT NULL DEFAULT 'hoat_dong',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `phone`, `ngay_sinh`, `gioi_tinh`, `email`, `username`, `password`, `role_id`, `status`, `created_at`) VALUES
(1, 'Nguyễn Minh Quân', '0905123456', '1985-01-15', 'Nam', 'minhquan.admin@gmail.com', 'minhquan', '$2y$10$UflBjzzhpwmJSE3RAvTwjencXLa8fJbmEYBIdJPwnrI5gxLHkNND6', 1, 'hoat_dong', '2026-03-30 21:24:42'),
(2, 'Trần Quốc Huy', '0906234567', '1990-03-22', 'Nam', 'quochuy.manager@gmail.com', 'quochuy', '$2y$10$wb6zVYkbL0qPJtYESppFdOHhJdM3rJQyRIpuFEvk8UBHWYpArQTVi', 2, 'hoat_dong', '2026-03-30 21:24:42'),
(3, 'Lê Thị Ngọc Anh', '0912345678', '1995-07-10', 'Nữ', 'ngocanh.service@gmail.com', 'ngocanh', '$2y$10$V/SlPjAlP6J1EK9SrYPfZOxrDOB.4g3utNh7HtknmHlr6/G50Q2BG', 5, 'hoat_dong', '2026-03-30 21:24:42'),
(4, 'Phạm Văn Khánh', '0938456123', '1988-11-05', 'Nam', 'vankhanh.kitchen@gmail.com', 'vankhanh', '$2y$10$z2nNgamkgFY8xcplgPgZWeq2/a02wqEKVYlWci9tdjCjKyVktkwma', 4, 'hoat_dong', '2026-03-30 21:24:42'),
(5, 'Phương Mỹ Chi', '0789456123', '1997-06-18', 'Nữ', 'mychi.cashier@gmail.com', 'mychi', '$2y$10$CortGKKI0NiU1fLDFMnE7umN1dPGGpSAPntdc1cSUC01utEq/pxp.', 3, 'hoat_dong', '2026-05-18 13:26:21'),
(11, 'Trần Huyền Trân', '0358874187', '2003-06-21', 'Nữ', NULL, 'huyentran', '$2y$10$oRcots0EXW96joPNkoFM2eKI9YcpRg/dxndHnGnjsx.jNvHsA2bJ.', 6, 'hoat_dong', '2026-05-22 23:10:36');

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_bao_cao_doanh_thu`
-- (See below for the actual view)
--
CREATE TABLE `v_bao_cao_doanh_thu` (
`ngay` date
,`so_don_hang` bigint(21)
,`tong_doanh_thu` decimal(34,2)
,`doanh_thu_walk_in` decimal(34,2)
,`doanh_thu_online` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_lich_su_giao_dich`
-- (See below for the actual view)
--
CREATE TABLE `v_lich_su_giao_dich` (
`customer_id` int(11)
,`ten_khach_hang` varchar(100)
,`so_dien_thoai` varchar(15)
,`order_id` int(11)
,`thoi_gian` datetime
,`tong_tien` decimal(12,2)
,`trang_thai` enum('da_dat_coc','moi','dang_xu_ly','dang_che_bien','dang_phuc_vu','hoan_thanh','da_thanh_toan','da_huy')
,`loai_don` varchar(7)
);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_mon_ban_chay`
-- (See below for the actual view)
--
CREATE TABLE `v_mon_ban_chay` (
`item_id` int(11)
,`ten_mon` varchar(100)
,`danh_muc` varchar(100)
,`so_luong_ban` decimal(32,0)
,`doanh_thu` decimal(44,2)
,`gia_hien_tai` decimal(12,2)
);

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_bao_cao_doanh_thu`
--
DROP TABLE IF EXISTS `v_bao_cao_doanh_thu`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_bao_cao_doanh_thu`  AS SELECT cast(`o`.`order_time` as date) AS `ngay`, count(distinct `o`.`order_id`) AS `so_don_hang`, coalesce(sum(`o`.`total_amount`),0) AS `tong_doanh_thu`, coalesce(sum(case when `o`.`reservation_id` is null then `o`.`total_amount` else 0 end),0) AS `doanh_thu_walk_in`, coalesce(sum(case when `o`.`reservation_id` is not null then `o`.`total_amount` else 0 end),0) AS `doanh_thu_online` FROM `orders` AS `o` WHERE `o`.`status` = 'da_thanh_toan' GROUP BY cast(`o`.`order_time` as date) ;

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_lich_su_giao_dich`
--
DROP TABLE IF EXISTS `v_lich_su_giao_dich`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_lich_su_giao_dich`  AS SELECT `c`.`customer_id` AS `customer_id`, `c`.`customer_name` AS `ten_khach_hang`, `c`.`phone` AS `so_dien_thoai`, `o`.`order_id` AS `order_id`, `o`.`order_time` AS `thoi_gian`, `o`.`total_amount` AS `tong_tien`, `o`.`status` AS `trang_thai`, CASE WHEN `o`.`reservation_id` is null THEN 'Walk-in' ELSE 'Online' END AS `loai_don` FROM (`customers` `c` left join `orders` `o` on(`c`.`customer_id` = `o`.`customer_id`)) WHERE `o`.`order_id` is not null ;

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_mon_ban_chay`
--
DROP TABLE IF EXISTS `v_mon_ban_chay`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_mon_ban_chay`  AS SELECT `m`.`item_id` AS `item_id`, `m`.`item_name` AS `ten_mon`, `c`.`category_name` AS `danh_muc`, coalesce(sum(`od`.`quantity`),0) AS `so_luong_ban`, coalesce(sum(`od`.`quantity` * `od`.`unit_price`),0) AS `doanh_thu`, `m`.`price` AS `gia_hien_tai` FROM (((`order_details` `od` join `menu_items` `m` on(`od`.`item_id` = `m`.`item_id`)) join `categories` `c` on(`m`.`category_id` = `c`.`category_id`)) join `orders` `o` on(`od`.`order_id` = `o`.`order_id`)) WHERE `o`.`status` = 'da_thanh_toan' GROUP BY `m`.`item_id`, `m`.`item_name`, `c`.`category_name`, `m`.`price` ;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uq_categories_category_name` (`category_name`);

--
-- Chỉ mục cho bảng `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `uq_customers_phone` (`phone`),
  ADD UNIQUE KEY `uq_customers_email` (`email`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_customers_created_by` (`created_by`);

--
-- Chỉ mục cho bảng `data_operation_logs`
--
ALTER TABLE `data_operation_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `idx_performed_at` (`performed_at`),
  ADD KEY `idx_action_type` (`action_type`);

--
-- Chỉ mục cho bảng `floors`
--
ALTER TABLE `floors`
  ADD PRIMARY KEY (`floor_id`),
  ADD UNIQUE KEY `uq_floors_floor_name` (`floor_name`);

--
-- Chỉ mục cho bảng `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `uq_menu_items_item_name` (`item_name`),
  ADD KEY `idx_menu_items_category_id` (`category_id`);

--
-- Chỉ mục cho bảng `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `idx_orders_table_id` (`table_id`),
  ADD KEY `idx_orders_customer_id` (`customer_id`),
  ADD KEY `idx_orders_reservation_id` (`reservation_id`),
  ADD KEY `idx_orders_waiter_id` (`waiter_id`),
  ADD KEY `idx_orders_order_time` (`order_time`),
  ADD KEY `idx_orders_table_status` (`table_id`,`status`),
  ADD KEY `idx_orders_time_status` (`order_time`,`status`);

--
-- Chỉ mục cho bảng `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`order_detail_id`),
  ADD KEY `idx_order_details_order_id` (`order_id`),
  ADD KEY `idx_order_details_item_id` (`item_id`),
  ADD KEY `idx_order_details_status` (`item_status`);

--
-- Chỉ mục cho bảng `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `idx_password_reset_user` (`user_id`),
  ADD KEY `idx_password_reset_expires` (`expires_at`);

--
-- Chỉ mục cho bảng `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `idx_payments_order_id` (`order_id`),
  ADD KEY `idx_payments_cashier_id` (`cashier_id`),
  ADD KEY `idx_payments_payment_time` (`payment_time`),
  ADD KEY `idx_payments_time_status` (`payment_time`,`payment_status`);

--
-- Chỉ mục cho bảng `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `idx_reservations_table_id` (`table_id`),
  ADD KEY `idx_reservations_time` (`reservation_time`),
  ADD KEY `fk_reservations_user` (`user_id`),
  ADD KEY `idx_reservations_table_status` (`table_id`,`status`),
  ADD KEY `idx_reservations_time_status` (`reservation_time`,`status`);

--
-- Chỉ mục cho bảng `reservation_items`
--
ALTER TABLE `reservation_items`
  ADD PRIMARY KEY (`reservation_item_id`),
  ADD KEY `idx_reservation_items_reservation_id` (`reservation_id`),
  ADD KEY `idx_reservation_items_item_id` (`item_id`);

--
-- Chỉ mục cho bảng `reservation_payments`
--
ALTER TABLE `reservation_payments`
  ADD PRIMARY KEY (`reservation_payment_id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `cashier_id` (`cashier_id`),
  ADD KEY `idx_reservation_payment_status` (`reservation_id`,`payment_status`),
  ADD KEY `idx_reservation_payment_time` (`payment_time`);

--
-- Chỉ mục cho bảng `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `uq_roles_role_name` (`role_name`);

--
-- Chỉ mục cho bảng `role_change_logs`
--
ALTER TABLE `role_change_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `old_role_id` (`old_role_id`),
  ADD KEY `new_role_id` (`new_role_id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_changed_at` (`changed_at`);

--
-- Chỉ mục cho bảng `staff_days_off`
--
ALTER TABLE `staff_days_off`
  ADD PRIMARY KEY (`day_off_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Chỉ mục cho bảng `staff_shifts`
--
ALTER TABLE `staff_shifts`
  ADD PRIMARY KEY (`shift_id`),
  ADD UNIQUE KEY `uq_user_day` (`user_id`,`day_of_week`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `week_start_date` (`week_start_date`);

--
-- Chỉ mục cho bảng `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`config_id`),
  ADD UNIQUE KEY `config_key` (`config_key`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_config_key` (`config_key`);

--
-- Chỉ mục cho bảng `tables`
--
ALTER TABLE `tables`
  ADD PRIMARY KEY (`table_id`),
  ADD UNIQUE KEY `uq_tables_floor_table_name` (`floor_id`,`table_name`),
  ADD KEY `idx_tables_floor_id` (`floor_id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_phone` (`phone`),
  ADD KEY `idx_users_role_id` (`role_id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `data_operation_logs`
--
ALTER TABLE `data_operation_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `floors`
--
ALTER TABLE `floors`
  MODIFY `floor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `order_details`
--
ALTER TABLE `order_details`
  MODIFY `order_detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT cho bảng `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `token_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `reservations`
--
ALTER TABLE `reservations`
  MODIFY `reservation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `reservation_items`
--
ALTER TABLE `reservation_items`
  MODIFY `reservation_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `reservation_payments`
--
ALTER TABLE `reservation_payments`
  MODIFY `reservation_payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `role_change_logs`
--
ALTER TABLE `role_change_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `staff_days_off`
--
ALTER TABLE `staff_days_off`
  MODIFY `day_off_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `staff_shifts`
--
ALTER TABLE `staff_shifts`
  MODIFY `shift_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `system_config`
--
ALTER TABLE `system_config`
  MODIFY `config_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `tables`
--
ALTER TABLE `tables`
  MODIFY `table_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `data_operation_logs`
--
ALTER TABLE `data_operation_logs`
  ADD CONSTRAINT `data_operation_logs_ibfk_1` FOREIGN KEY (`performed_by`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
