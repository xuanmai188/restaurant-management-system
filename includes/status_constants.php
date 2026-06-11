<?php
/**
 * Status Constants - Đồng nhất trên toàn hệ thống
 * Sử dụng trong tất cả các trang: admin, waiter, kitchen, cashier, manager
 */

// Order Status Labels
define('ORDER_STATUS_LABELS', [
    'moi' => 'Mới',
    'dang_xu_ly' => 'Đang xử lý',
    'dang_che_bien' => 'Đang nấu',
    'dang_phuc_vu' => 'Đang phục vụ',
    'hoan_thanh' => 'Hoàn thành món',
    'da_thanh_toan' => 'Đã thanh toán',
    'da_huy' => 'Đã hủy',
    'da_dat_coc' => 'Đã cọc',
    'da_coc' => 'Đã cọc', // Alias for da_dat_coc (backward compatibility)
]);

// Order Status Badge Classes
define('ORDER_STATUS_BADGES', [
    'moi' => 'badge-role',
    'dang_xu_ly' => 'badge-role',
    'dang_che_bien' => 'badge-role',
    'dang_phuc_vu' => 'badge-role',
    'hoan_thanh' => 'badge-active',
    'da_thanh_toan' => 'badge-active',
    'da_huy' => 'badge-inactive',
    'da_dat_coc' => 'badge-role',
    'da_coc' => 'badge-role', // Alias for da_dat_coc (backward compatibility)
]);

// Table Status Labels
define('TABLE_STATUS_LABELS', [
    'trong' => 'Trống',
    'dang_su_dung' => 'Có khách',
    'da_dat' => 'Đã đặt',
    'bao_tri' => 'Bảo trì',
]);

// Table Status Colors
define('TABLE_STATUS_COLORS', [
    'trong' => '#16a34a',
    'dang_su_dung' => '#dc2626',
    'da_dat' => '#d97706',
    'bao_tri' => '#6b7280',
]);

// Thời gian chờ trước khi đánh dấu khách không đến (phút) — dùng chung sync + cron
define('NO_SHOW_THRESHOLD_MINUTES', 30);

// Trạng thái reservation hợp lệ khi cập nhật thủ công
define('ALLOWED_RESERVATION_STATUSES', [
    'cho_xac_nhan',
    'da_xac_nhan',
    'da_checkin',
    'khong_den',
    'da_huy',
    'hoan_thanh',
]);

// Reservation Status Labels
define('RESERVATION_STATUS_LABELS', [
    'cho_xac_nhan' => 'Chờ xác nhận',
    'da_xac_nhan' => 'Đã xác nhận',
    'da_checkin' => 'Đã check-in',
    'khong_den' => 'Không đến',
    'da_huy' => 'Đã hủy',
    'hoan_thanh' => 'Hoàn thành',
]);

// Helper functions
function get_order_status_label($status) {
    return ORDER_STATUS_LABELS[$status] ?? $status;
}

function get_order_status_badge($status) {
    return ORDER_STATUS_BADGES[$status] ?? 'badge-role';
}

function get_table_status_label($status) {
    return TABLE_STATUS_LABELS[$status] ?? $status;
}

function get_table_status_color($status) {
    return TABLE_STATUS_COLORS[$status] ?? '#6b7280';
}

function get_reservation_status_label($status) {
    return RESERVATION_STATUS_LABELS[$status] ?? $status;
}
