<?php
/**
 * Auto Job: Tự động chuyển trạng thái "Không đến"
 * 
 * Chạy định kỳ mỗi 15 phút qua cron:
 *   (slash)(star)/15 (star) (star) (star) (star) php /path/to/scripts/auto_khong_den.php >> /var/log/auto_khong_den.log 2>&1
 * 
 * Logic (idempotent):
 * - Chỉ cập nhật Reservation có status = 'da_xac_nhan' (không bao giờ cập nhật lại)
 * - Điều kiện: quá NO_SHOW_THRESHOLD_MINUTES (cùng syncReservationStatus)
 * - Kết quả: Reservation.status = 'khong_den', Order.status = 'da_huy' (nếu có)
 */

require_once __DIR__ . '/../includes/status_constants.php';

$configPath = __DIR__ . '/../config/database.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ERROR: Không tìm thấy config/database.php' . PHP_EOL);
    exit(1);
}

require_once $configPath;

if (!isset($conn) || !$conn) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ERROR: Không kết nối được database' . PHP_EOL);
    exit(1);
}

$now = date('Y-m-d H:i:s');
$noShowMins = defined('NO_SHOW_THRESHOLD_MINUTES') ? (int)NO_SHOW_THRESHOLD_MINUTES : 30;
echo '[' . $now . '] Job auto_khong_den bắt đầu chạy (ngưỡng ' . $noShowMins . ' phút)...' . PHP_EOL;

// Cùng logic với syncReservationStatus() — idempotent
$stmt = $conn->prepare("
    SELECT r.reservation_id, r.table_id, o.order_id
    FROM reservations r
    LEFT JOIN orders o ON o.reservation_id = r.reservation_id
                      AND o.status IN ('da_dat_coc','da_coc')
    WHERE r.status IN ('cho_xac_nhan','da_xac_nhan')
      AND DATE_ADD(r.reservation_time, INTERVAL ? MINUTE) < ?
");

if (!$stmt) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ERROR: Prepare thất bại: ' . $conn->error . PHP_EOL);
    exit(1);
}

$stmt->bind_param('is', $noShowMins, $now);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$processed = 0;
$errors    = 0;

foreach ($rows as $row) {
    $res_id   = (int)$row['reservation_id'];
    $order_id = $row['order_id'] ? (int)$row['order_id'] : null;

    $conn->begin_transaction();
    try {
        // Cập nhật reservation — chỉ khi vẫn còn da_xac_nhan (idempotent guard)
        $upRes = $conn->prepare("UPDATE reservations SET status='khong_den' WHERE reservation_id=? AND status IN ('cho_xac_nhan','da_xac_nhan')");
        $upRes->bind_param('i', $res_id);
        $upRes->execute();
        $affected = $upRes->affected_rows;
        $upRes->close();

        if ($affected === 0) {
            // Đã được xử lý bởi lần chạy khác — bỏ qua
            $conn->rollback();
            continue;
        }

        // Cập nhật order liên kết nếu có
        if ($order_id) {
            $upOrd = $conn->prepare("UPDATE orders SET status='da_huy' WHERE order_id=? AND status IN ('da_dat_coc','da_coc')");
            $upOrd->bind_param('i', $order_id);
            $upOrd->execute();
            $upOrd->close();
        }

        $conn->commit();
        $processed++;
        echo '[' . date('Y-m-d H:i:s') . '] OK: reservation_id=' . $res_id
            . ($order_id ? ', order_id=' . $order_id : ', order=none')
            . ' → khong_den' . PHP_EOL;

    } catch (Exception $e) {
        $conn->rollback();
        $errors++;
        fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ERROR reservation_id=' . $res_id . ': ' . $e->getMessage() . PHP_EOL);
    }
}

echo '[' . date('Y-m-d H:i:s') . '] Hoàn thành: ' . $processed . ' cập nhật, ' . $errors . ' lỗi.' . PHP_EOL;
exit($errors > 0 ? 1 : 0);
