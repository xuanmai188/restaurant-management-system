-- =============================================================================
-- DỮ LIỆU MẪU — chỉ INSERT (import sau khi đã có cấu trúc bảng)
-- Mật khẩu đăng nhập tất cả tài khoản: 123456
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `payments`;
TRUNCATE TABLE `order_details`;
TRUNCATE TABLE `orders`;
TRUNCATE TABLE `reservation_payments`;
TRUNCATE TABLE `reservation_items`;
TRUNCATE TABLE `reservations`;
TRUNCATE TABLE `role_change_logs`;
TRUNCATE TABLE `staff_days_off`;
TRUNCATE TABLE `staff_shifts`;
TRUNCATE TABLE `data_operation_logs`;
TRUNCATE TABLE `customers`;
TRUNCATE TABLE `menu_items`;
TRUNCATE TABLE `categories`;
TRUNCATE TABLE `tables`;
TRUNCATE TABLE `floors`;
TRUNCATE TABLE `system_config`;
TRUNCATE TABLE `users`;
TRUNCATE TABLE `roles`;

SET FOREIGN_KEY_CHECKS = 1;

-- Mật khẩu bcrypt: 123456
SET @pwd = '$2y$10$UflBjzzhpwmJSE3RAvTwjencXLa8fJbmEYBIdJPwnrI5gxLHkNND6';

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(1, 'Admin'),
(2, 'QuanLy'),
(3, 'ThuNgan'),
(4, 'Bep'),
(5, 'PhucVu'),
(6, 'KhachHang');

INSERT INTO `users` (`user_id`, `full_name`, `phone`, `ngay_sinh`, `gioi_tinh`, `email`, `username`, `password`, `role_id`, `status`, `created_at`) VALUES
(1, 'Nguyễn Văn An', '0903123456', '1985-03-12', 'Nam', 'nguyenvanan@nhahang.vn', 'nguyenvanan', @pwd, 1, 'hoat_dong', '2026-01-10 08:00:00'),
(2, 'Trần Thị Hương', '0903234567', '1990-07-22', 'Nữ', 'tranthihuong@nhahang.vn', 'tranthihuong', @pwd, 2, 'hoat_dong', '2026-01-10 08:00:00'),
(3, 'Lê Minh Tuấn', '0903345678', '1992-11-05', 'Nam', 'leminhtuan@nhahang.vn', 'leminhtuan', @pwd, 3, 'hoat_dong', '2026-01-10 08:00:00'),
(4, 'Phạm Văn Đức', '0903456789', '1988-05-18', 'Nam', 'phamvanduc@nhahang.vn', 'phamvanduc', @pwd, 4, 'hoat_dong', '2026-01-10 08:00:00'),
(5, 'Đỗ Thị Lan', '0903567890', '1996-09-30', 'Nữ', 'dothilan@nhahang.vn', 'dothilan', @pwd, 5, 'hoat_dong', '2026-01-10 08:00:00'),
(6, 'Hoàng Quốc Bình', '0903678901', '1994-12-08', 'Nam', 'hoangquocbinh@nhahang.vn', 'hoangquocbinh', @pwd, 5, 'hoat_dong', '2026-02-01 08:00:00'),
(7, 'Trương Minh Khôi', '0918123456', '1998-04-15', 'Nam', 'minhkhoi@gmail.com', 'minhkhoi', @pwd, 6, 'hoat_dong', '2026-03-01 10:00:00'),
(8, 'Phan Thị Diệu', '0918234567', '1999-08-20', 'Nữ', 'thidieu@gmail.com', 'thidieu', @pwd, 6, 'hoat_dong', '2026-03-05 11:00:00'),
(9, 'Vũ Hữu Nghĩa', '0918345678', '1995-01-25', 'Nam', 'huunghia@gmail.com', 'huunghia', @pwd, 6, 'hoat_dong', '2026-03-10 14:00:00'),
(10, 'Ngô Thảo My', '0918456789', '2000-06-10', 'Nữ', 'thaomy@gmail.com', 'thaomy', @pwd, 6, 'hoat_dong', '2026-04-01 09:00:00');

INSERT INTO `floors` (`floor_id`, `floor_name`, `description`, `max_tables`) VALUES
(1, 'Tầng trệt', 'Khu chính, gần quầy thu ngân', 12),
(2, 'Tầng lầu', 'Khu riêng tư, view cửa sổ', 10);

INSERT INTO `tables` (`table_id`, `floor_id`, `table_name`, `capacity`, `status`) VALUES
(1, 1, 'Bàn 1', 4, 'trong'),
(2, 1, 'Bàn 2', 4, 'trong'),
(3, 1, 'Bàn 3', 6, 'trong'),
(4, 1, 'Bàn VIP 1', 8, 'trong'),
(5, 2, 'Bàn 5', 4, 'trong'),
(6, 2, 'Bàn 6', 4, 'trong'),
(7, 2, 'Bàn 7', 6, 'trong'),
(8, 2, 'Bàn 8', 6, 'trong');

INSERT INTO `categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'Món chính', 'Lẩu, cơm, bún và món nóng'),
(2, 'Đồ uống', 'Trà, nước ép, soft drink'),
(3, 'Tráng miệng', 'Bánh và chè');

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

INSERT INTO `customers` (`customer_id`, `customer_name`, `phone`, `email`, `created_by`, `created_at`, `user_id`) VALUES
(1, 'Trương Minh Khôi', '0918123456', 'minhkhoi@gmail.com', 5, '2026-03-01 10:00:00', 7),
(2, 'Phan Thị Diệu', '0918234567', 'thidieu@gmail.com', 5, '2026-03-05 11:00:00', 8),
(3, 'Vũ Hữu Nghĩa', '0918345678', 'huunghia@gmail.com', 5, '2026-03-10 14:00:00', 9),
(4, 'Ngô Thảo My', '0918456789', 'thaomy@gmail.com', 5, '2026-04-01 09:00:00', 10),
(5, 'Lê Hoàng Phúc', '0938765432', 'hoangphuc@outlook.com', 5, '2026-05-10 16:20:00', NULL),
(6, 'Công ty TNHH Việt Food', '02838224567', 'datban@vietfood.vn', 2, '2026-05-12 09:00:00', NULL);

INSERT INTO `system_config` (`config_id`, `config_key`, `config_value`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'opening_time', '10:00', 'Giờ mở cửa nhà hàng', 1, '2026-01-10 08:00:00'),
(2, 'closing_time', '22:00', 'Giờ đóng cửa nhà hàng', 1, '2026-01-10 08:00:00'),
(3, 'minimum_reservation_duration', '60', 'Thời gian đặt bàn tối thiểu (phút)', 1, '2026-01-10 08:00:00'),
(4, 'buffer_time', '15', 'Thời gian buffer giữa các lượt đặt (phút)', 1, '2026-01-10 08:00:00'),
(5, 'prevent_overlap', 'true', 'Không cho phép đặt trùng giờ', 1, '2026-01-10 08:00:00'),
(6, 'default_price_multiplier', '1.0', 'Hệ số nhân giá mặc định', 1, '2026-01-10 08:00:00'),
(7, 'tax_rate', '8', 'Tỷ lệ thuế (%)', 1, '2026-01-10 08:00:00');

INSERT INTO `reservations` (`reservation_id`, `user_id`, `table_id`, `reservation_time`, `number_of_people`, `note`, `status`, `created_at`, `start_time`, `end_time`) VALUES
(1, 7, 2, '2026-05-18 18:30:00', 4, 'Sinh nhật bạn gái, cần bàn yên tĩnh', 'hoan_thanh', '2026-05-17 14:20:00', '2026-05-18 18:30:00', '2026-05-18 20:30:00'),
(2, 8, 4, '2026-05-19 12:00:00', 6, 'Đặt trước set combo cho công ty', 'hoan_thanh', '2026-05-18 09:15:00', '2026-05-19 12:00:00', '2026-05-19 14:30:00'),
(3, 9, 1, '2026-05-20 19:00:00', 2, 'Hẹn hò, góc trong', 'hoan_thanh', '2026-05-19 20:40:00', '2026-05-20 19:00:00', '2026-05-20 20:30:00'),
(4, 10, 7, '2026-05-21 11:30:00', 5, 'Gia đình có 2 trẻ em', 'da_huy', '2026-05-20 08:00:00', '2026-05-21 11:30:00', '2026-05-21 13:30:00'),
(5, 7, 3, '2026-05-22 18:00:00', 3, 'Khách quen, thích lẩu bò', 'da_xac_nhan', '2026-05-22 10:30:00', '2026-05-22 18:00:00', '2026-05-22 19:30:00'),
(6, 8, 5, '2026-05-23 19:30:00', 4, 'Tiếp đối tác', 'cho_xac_nhan', '2026-05-22 15:00:00', '2026-05-23 19:30:00', '2026-05-23 21:30:00');

INSERT INTO `reservation_items` (`reservation_item_id`, `reservation_id`, `item_id`, `quantity`, `unit_price`, `note`) VALUES
(1, 1, 3, 1, 399000.00, 'Ít cay'),
(2, 1, 6, 2, 35000.00, NULL),
(3, 2, 3, 2, 399000.00, 'Mang về hộp riêng'),
(4, 3, 1, 1, 299000.00, NULL),
(5, 4, 5, 5, 55000.00, NULL),
(6, 5, 1, 1, 299000.00, 'Thêm rau'),
(7, 5, 2, 1, 99000.00, NULL),
(8, 6, 10, 1, 189000.00, NULL);

INSERT INTO `reservation_payments` (`reservation_payment_id`, `reservation_id`, `cashier_id`, `payment_type`, `payment_percent`, `amount`, `payment_method`, `payment_time`, `payment_status`, `deposit_status`, `note`) VALUES
(1, 1, 7, 'deposit', 50, 234500.00, 'bank_transfer', '2026-05-17 14:21:00', 'thanh_cong', 'da_su_dung', 'Cọc 50% đặt bàn online'),
(2, 2, 8, 'deposit', 50, 399000.00, 'bank_transfer', '2026-05-18 09:16:00', 'thanh_cong', 'da_su_dung', 'Cọc 50% set combo x2'),
(3, 3, 9, 'deposit', 50, 149500.00, 'bank_transfer', '2026-05-19 20:41:00', 'thanh_cong', 'da_su_dung', 'Cọc 50% lẩu bò'),
(4, 4, 10, 'deposit', 50, 137500.00, 'bank_transfer', '2026-05-20 08:01:00', 'thanh_cong', 'hoan_tien', 'Khách hủy vì trễ chuyến bay'),
(5, 5, 7, 'deposit', 50, 199000.00, 'bank_transfer', '2026-05-22 10:31:00', 'thanh_cong', 'giu_lai', 'Cọc 50% lẩu tối nay'),
(6, 6, 8, 'deposit', 50, 94500.00, 'bank_transfer', '2026-05-22 15:01:00', 'cho_xu_ly', 'giu_lai', 'Chờ xác nhận chuyển khoản');

INSERT INTO `orders` (`order_id`, `table_id`, `customer_id`, `reservation_id`, `waiter_id`, `order_time`, `status`, `total_amount`, `paid_amount`, `start_time`, `end_time`, `guest_count`, `number_of_people`) VALUES
(1, 1, 1, NULL, 5, '2026-05-18 17:45:00', 'da_thanh_toan', 384000.00, 384000.00, NULL, NULL, 2, 2),
(2, 3, NULL, NULL, 5, '2026-05-18 19:10:00', 'da_thanh_toan', 175000.00, 175000.00, NULL, NULL, 4, 4),
(3, 2, 1, 1, 5, '2026-05-18 18:35:00', 'da_thanh_toan', 469000.00, 469000.00, NULL, NULL, 4, 4),
(4, 4, 2, 2, 6, '2026-05-19 12:05:00', 'da_thanh_toan', 798000.00, 798000.00, NULL, NULL, 6, 6),
(5, 1, 3, 3, 5, '2026-05-20 19:05:00', 'da_thanh_toan', 299000.00, 299000.00, NULL, NULL, 2, 2),
(6, 5, 5, NULL, 6, '2026-05-20 20:30:00', 'da_thanh_toan', 246000.00, 246000.00, NULL, NULL, 3, 3),
(7, 7, 6, NULL, 5, '2026-05-21 12:15:00', 'da_thanh_toan', 608000.00, 608000.00, NULL, NULL, 8, 8),
(8, 1, 4, 4, NULL, '2026-05-21 11:35:00', 'da_huy', 275000.00, 0.00, NULL, NULL, 5, 5),
(9, 3, 1, 5, NULL, '2026-05-22 10:35:00', 'da_dat_coc', 398000.00, 0.00, NULL, NULL, 3, 3),
(10, 6, NULL, NULL, 5, '2026-05-22 18:20:00', 'hoan_thanh', 125000.00, 0.00, NULL, NULL, 2, 2);

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
(22, 10, 8, 1, 25000.00, NULL, 'hoan_thanh');

INSERT INTO `payments` (`payment_id`, `order_id`, `cashier_id`, `payment_method`, `payment_type`, `amount_paid`, `payment_time`, `payment_status`) VALUES
(1, 1, 3, 'cash', 'order_payment', 384000.00, '2026-05-18 18:50:00', 'thanh_cong'),
(2, 2, 3, 'cash', 'order_payment', 175000.00, '2026-05-18 19:45:00', 'thanh_cong'),
(3, 3, 3, 'bank_transfer', 'order_payment', 469000.00, '2026-05-18 20:15:00', 'thanh_cong'),
(4, 4, 3, 'bank_transfer', 'order_payment', 798000.00, '2026-05-19 13:40:00', 'thanh_cong'),
(5, 5, 3, 'deposit_consumed', 'deposit_consumed', 299000.00, '2026-05-20 20:10:00', 'thanh_cong'),
(6, 6, 3, 'card', 'order_payment', 246000.00, '2026-05-20 21:05:00', 'thanh_cong'),
(7, 7, 3, 'bank_transfer', 'order_payment', 608000.00, '2026-05-21 13:50:00', 'thanh_cong');

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

INSERT INTO `staff_days_off` (`day_off_id`, `user_id`, `start_date`, `end_date`, `reason`, `status`, `created_at`) VALUES
(1, 4, '2026-05-01', '2026-05-01', 'Nghỉ lễ 30/4', 'da_duyet', '2026-04-25 09:00:00');

INSERT INTO `role_change_logs` (`log_id`, `user_id`, `old_role_id`, `new_role_id`, `changed_by`, `changed_at`, `note`) VALUES
(1, 6, 4, 5, 2, '2026-02-01 08:00:00', 'Chuyển từ bếp sang phục vụ ca tối');

INSERT INTO `data_operation_logs` (`log_id`, `action_type`, `deleted_count`, `performed_by`, `performed_at`, `details`) VALUES
(1, 'backup', 0, 1, '2026-05-01 07:00:00', 'Sao lưu định kỳ đầu tháng'),
(2, 'health_check', 0, 1, '2026-05-22 08:00:00', 'Kiểm tra hệ thống: OK');
