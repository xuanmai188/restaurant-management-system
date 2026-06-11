    <?php
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_login();
    require_role(['phucvu', 'admin', 'quanly']);

    $order_id  = (int)($_GET['id'] ?? 0);
    $msg       = urldecode($_GET['msg'] ?? '');
    $msgType   = 'alert-success';

    if (!$order_id) { redirect('/quanlynhahang/waiter/index.php'); }

    // Lấy thông tin đơn
    $oRes  = $conn->query("
        SELECT o.*, t.table_name, f.floor_name, c.customer_name
        FROM   orders o
        LEFT JOIN tables    t ON t.table_id    = o.table_id
        LEFT JOIN floors    f ON f.floor_id    = t.floor_id
        LEFT JOIN customers c ON c.customer_id = o.customer_id
        WHERE  o.order_id = $order_id LIMIT 1
    ");
    $order = $oRes ? $oRes->fetch_assoc() : null;
    if (!$order) { redirect('/quanlynhahang/waiter/index.php'); }

    // ── Thêm món ────────────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
        require_post_csrf();

        $item_id  = (int)$_POST['item_id'];
        $quantity = max(1, (int)$_POST['quantity']);
        $note     = $conn->real_escape_string(trim($_POST['note'] ?? ''));

        $iRes  = $conn->query("SELECT price FROM menu_items WHERE item_id=$item_id AND status='con_hang' LIMIT 1");
        $item  = $iRes ? $iRes->fetch_assoc() : null;

        if ($item) {
            $price = (float)$item['price'];
            // Nếu có ghi chú riêng → luôn thêm dòng mới để bếp thấy rõ yêu cầu
            // Nếu không có ghi chú → cộng vào dòng cũ (nếu đã có)
            $exist = $conn->query("SELECT order_detail_id, quantity FROM order_details WHERE order_id=$order_id AND item_id=$item_id AND (note='' OR note IS NULL) LIMIT 1");
            if (!$note && $exist && $exist->num_rows > 0) {
                $row = $exist->fetch_assoc();
                $newQty = $row['quantity'] + $quantity;
                $conn->query("UPDATE order_details SET quantity=$newQty, item_status='moi' WHERE order_detail_id={$row['order_detail_id']}");
            } else {
                // Thêm dòng mới (có ghi chú riêng hoặc chưa có dòng nào)
                $conn->query("INSERT INTO order_details (order_id,item_id,quantity,unit_price,note,item_status) VALUES ($order_id,$item_id,$quantity,$price,'$note','moi')");
            }
            // Cập nhật tổng tiền và status
            // Logic chuyển status:
            // - Nếu đơn 'moi' (chưa có món) → chuyển sang 'dang_phuc_vu' (khách bắt đầu ăn)
            // - Nếu đơn đã 'dang_phuc_vu' → giữ nguyên (khách đang ăn, thêm món mới)
            // - Các status khác → giữ nguyên
            $currentStatus = $order['status'];
            $newStatus = ($currentStatus === 'moi') ? 'dang_phuc_vu' : $currentStatus;
            $conn->query("UPDATE orders SET total_amount=(SELECT SUM(quantity*unit_price) FROM order_details WHERE order_id=$order_id), status='$newStatus' WHERE order_id=$order_id");
            $msg = 'Đã thêm món vào đơn. Bếp sẽ nhận được yêu cầu nấu món mới.';
            // Reload để tránh resubmit
            header("Location: order.php?id=$order_id&msg=" . urlencode($msg)); exit;
        } else {
            $msg = 'Món không tồn tại hoặc đã hết.'; $msgType = 'alert-error';
        }
    }

    // ── Xóa món ─────────────────────────────────────────────────────────────────
    if (isset($_GET['remove'])) {
        require_csrf($_GET['csrf_token'] ?? '');

        $did = (int)$_GET['remove'];
        $conn->query("DELETE FROM order_details WHERE order_detail_id=$did AND order_id=$order_id");
        $conn->query("UPDATE orders SET total_amount=IFNULL((SELECT SUM(quantity*unit_price) FROM order_details WHERE order_id=$order_id),0) WHERE order_id=$order_id");
        header("Location: order.php?id=$order_id&msg=Đã xóa món."); exit;
    }

    // ── Hoàn thành đơn ──────────────────────────────────────────────────────────
    if (isset($_GET['complete'])) {
        require_csrf($_GET['csrf_token'] ?? '');

        // Kiểm tra còn món nào bếp chưa nấu xong không
        $pendingItems = $conn->query("
            SELECT COUNT(*) AS cnt FROM order_details 
            WHERE order_id=$order_id AND item_status IN ('moi','dang_che_bien')
        ");
        $pendingCount = $pendingItems ? (int)$pendingItems->fetch_assoc()['cnt'] : 0;

        if ($pendingCount > 0) {
            $msg = "Còn $pendingCount món bếp chưa nấu xong. Vui lòng chờ bếp hoàn thành trước.";
            $msgType = 'alert-error';
        } else {
            $conn->query("UPDATE orders SET status='hoan_thanh' WHERE order_id=$order_id");
            header("Location: order.php?id=$order_id&msg=Đơn đã hoàn thành, chờ thu ngân thanh toán."); exit;
        }
    }

    // ── Dữ liệu ─────────────────────────────────────────────────────────────────
    $details = $conn->query("
        SELECT od.*, mi.item_name, c.category_name
        FROM   order_details od
        JOIN   menu_items mi ON mi.item_id = od.item_id
        LEFT JOIN categories c ON c.category_id = mi.category_id
        WHERE  od.order_id = $order_id
        ORDER  BY od.order_detail_id
    ");

    $categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
    $catFilter  = (int)($_GET['cat'] ?? 0);
    $menuWhere  = "status='con_hang'" . ($catFilter ? " AND category_id=$catFilter" : '');
    $menuItems  = $conn->query("SELECT mi.*, c.category_name FROM menu_items mi LEFT JOIN categories c ON c.category_id=mi.category_id WHERE $menuWhere ORDER BY mi.item_name");

    $statusLabel = ['moi'=>'Mới','dang_xu_ly'=>'Đang xử lý','dang_che_bien'=>'Đang nấu','dang_phuc_vu'=>'Đang phục vụ','hoan_thanh'=>'Hoàn thành món','da_thanh_toan'=>'Đã thanh toán','da_huy'=>'Đã hủy'];

    $pageTitle    = 'Đơn hàng #' . $order_id;
    $pageSubtitle = e($order['floor_name'] . ' – ' . $order['table_name']);
    $activeMenu   = 'waiter';
    $sidebarRole  = 'phucvu';
    include __DIR__ . '/../includes/layout.php';
    ?>

    <?php if ($msg): ?>
        <div class="alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns:1fr 380px; gap:20px; align-items:start;">

        <!-- Cột trái: Thực đơn -->
        <div>
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
                <h3 style="font-size:18px;">Chọn món</h3>
                <a href="index.php" class="btn btn-secondary" style="font-size:13px; padding:8px 14px;">← Quay lại</a>
            </div>

            <!-- Lọc danh mục -->
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
                <a href="order.php?id=<?= $order_id ?>" class="btn <?= !$catFilter?'btn-primary':'btn-secondary' ?>" style="padding:8px 14px; font-size:13px;">Tất cả</a>
                <?php $categories->data_seek(0); while ($c = $categories->fetch_assoc()): ?>
                    <a href="order.php?id=<?= $order_id ?>&cat=<?= $c['category_id'] ?>" class="btn <?= $catFilter==$c['category_id']?'btn-primary':'btn-secondary' ?>" style="padding:8px 14px; font-size:13px;">
                        <?= e($c['category_name']) ?>
                    </a>
                <?php endwhile; ?>
            </div>

            <!-- Grid món ăn -->
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px;">
                <?php if ($menuItems && $menuItems->num_rows > 0): ?>
                    <?php while ($m = $menuItems->fetch_assoc()): ?>
                        <div class="card" style="padding:16px;">
                            <p style="font-size:13px; color:var(--muted); margin-bottom:4px;"><?= e($m['category_name']) ?></p>
                            <strong style="font-size:15px; display:block; margin-bottom:6px;"><?= e($m['item_name']) ?></strong>
                            <p style="font-size:16px; font-weight:800; color:var(--primary); margin-bottom:12px;"><?= format_currency($m['price']) ?></p>
                            <form method="POST" style="display:flex; flex-direction:column; gap:6px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="item_id" value="<?= $m['item_id'] ?>">
                                <div style="display:flex; gap:6px;">
                                    <input type="number" name="quantity" value="1" min="1" max="99" class="input" style="width:60px; padding:8px; text-align:center;">
                                    <button type="submit" name="add_item" class="btn btn-primary" style="flex:1; padding:8px; font-size:13px;">+ Thêm</button>
                                </div>
                                <input type="text" name="note" class="input" style="padding:6px 8px; font-size:12px;" placeholder="Ghi chú">
                            </form>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">Không có món nào.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cột phải: Đơn hàng -->
        <div style="position:sticky; top:20px;">
            <div class="card" style="padding:20px;">
                <div style="margin-bottom:16px; padding-bottom:14px; border-bottom:1px solid var(--border);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="font-size:18px;">Đơn #<?= $order_id ?></h3>
                        <span class="badge badge-role"><?= $statusLabel[$order['status']] ?? $order['status'] ?></span>
                    </div>
                    <p style="font-size:13px; color:var(--muted); margin-top:4px;"><?= e($order['floor_name'].' – '.$order['table_name']) ?></p>
                    <p style="font-size:13px; color:var(--muted);"><?= e($order['customer_name'] ?? 'Khách vãng lai') ?></p>
                </div>

                <!-- Chi tiết món -->
                <?php if ($details && $details->num_rows > 0): ?>
                    <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:16px; max-height:320px; overflow-y:auto;">
                        <?php while ($d = $details->fetch_assoc()): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 12px; background:#f9fafb; border-radius:12px;">
                                <div>
                                    <strong style="font-size:14px;"><?= e($d['item_name']) ?></strong>
                                    <p style="font-size:12px; color:var(--muted);">x<?= $d['quantity'] ?> × <?= format_currency($d['unit_price']) ?></p>
                                    <?php if (!empty($d['note'])): ?>
                                        <p style="font-size:11px; color:#d97706; margin-top:2px;">📝 <?= e($d['note']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-weight:700; color:var(--primary); font-size:14px;"><?= format_currency($d['quantity'] * $d['unit_price']) ?></span>
                                    <?php if (!in_array($order['status'], ['hoan_thanh','da_thanh_toan'])): ?>
                                        <a href="?id=<?= $order_id ?>&remove=<?= $d['order_detail_id'] ?>&csrf_token=<?= urlencode(csrf_token()) ?>" style="color:#dc2626; font-size:18px; line-height:1;" onclick="return confirm('Xóa món này?')">×</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <div style="padding-top:14px; border-top:2px solid var(--border);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                            <strong style="font-size:16px;">Tổng cộng</strong>
                            <strong style="font-size:22px; color:var(--primary);"><?= format_currency($order['total_amount']) ?></strong>
                        </div>
                        <?php if (!in_array($order['status'], ['hoan_thanh','da_thanh_toan'])): ?>
                            <a href="?id=<?= $order_id ?>&complete=1&csrf_token=<?= urlencode(csrf_token()) ?>" class="btn btn-success" style="width:100%; height:48px; justify-content:center;" onclick="return confirm('Xác nhận hoàn thành đơn? Thu ngân sẽ thanh toán.')">
                                Hoàn thành đơn
                            </a>
                        <?php else: ?>
                            <div style="text-align:center; padding:12px; background:#ecfdf5; border-radius:12px; color:#166534; font-weight:700;">
                                Đơn đã hoàn thành - Chờ thu ngân
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding:24px 0;">Chưa có món nào trong đơn</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/layout_end.php'; ?>
