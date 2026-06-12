<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$topItems = [];

/*
|--------------------------------------------------------------------------
| Lấy 6 món ăn ưu tiên theo số lượng bán
|--------------------------------------------------------------------------
| Vì bảng order_details hiện đang trống, truy vấn vẫn chạy được nhưng total_sold = 0.
| Ta vẫn sắp xếp theo total_sold DESC, sau đó tới item_id DESC để có dữ liệu hiển thị.
*/
$sqlTopItems = "
    SELECT 
        mi.item_id,
        mi.item_name,
        mi.price,
        mi.description,
        mi.image_url,
        mi.status,
        c.category_name,
        COALESCE(SUM(od.quantity), 0) AS total_sold
    FROM menu_items mi
    LEFT JOIN categories c ON c.category_id = mi.category_id
    LEFT JOIN order_details od ON od.item_id = mi.item_id
    WHERE mi.status IN ('con_hang')
    GROUP BY 
        mi.item_id,
        mi.item_name,
        mi.price,
        mi.description,
        mi.status,
        c.category_name
    ORDER BY total_sold DESC, mi.item_id DESC
    LIMIT 6
";

$resultTopItems = $conn->query($sqlTopItems);

if ($resultTopItems && $resultTopItems->num_rows > 0) {
    while ($row = $resultTopItems->fetch_assoc()) {
        $topItems[] = $row;
    }
}

include __DIR__ . '/includes/header.php';
?>

<section class="hero-slider">
    <div class="slides">
        <div class="slide active" style="background-image: url('/quanlynhahang/assets/images/hero-slide-1.jpg');">
            <div class="slide-overlay"></div>
            <div class="slide-content container">
                <span class="hero-tag">Combo giá rẻ</span>
                <h1>Thưởng thức món ngon trong không gian ấm cúng</h1>
                <p>Khám phá thực đơn hấp dẫn, đặt bàn nhanh chóng và tận hưởng trải nghiệm tuyệt vời tại nhà hàng.</p>
                <div class="hero-buttons">
                    <a href="/quanlynhahang/customer/menu.php" class="btn btn-primary">Xem thực đơn</a>
                    <a href="/quanlynhahang/customer/reservation.php" class="btn btn-light">Đặt bàn ngay</a>
                </div>
            </div>
        </div>

        <div class="slide" style="background-image: url('/quanlynhahang/assets/images/hero-slide-2.jpg');">
            <div class="slide-overlay"></div>
            <div class="slide-content container">
                <span class="hero-tag">Món ăn nổi bật</span>
                <h1>Những hương vị được khách hàng yêu thích</h1>
                <p>Từ các món lẩu đặc trưng đến combo hấp dẫn, tất cả đều được chuẩn bị với nguyên liệu tươi ngon.</p>
                <div class="hero-buttons">
                    <a href="/quanlynhahang/customer/menu.php" class="btn btn-primary">Xem thực đơn</a>
                    <a href="/quanlynhahang/customer/reservation-history.php" class="btn btn-light">Lịch sử đặt bàn</a>
                </div>
            </div>
        </div>
    </div>

    <button class="slider-btn prev" onclick="changeSlide(-1)">&#10094;</button>
    <button class="slider-btn next" onclick="changeSlide(1)">&#10095;</button>

    <div class="slider-dots">
        <span class="dot active" onclick="currentSlide(0)"></span>
        <span class="dot" onclick="currentSlide(1)"></span>
    </div>
</section>

<?php if (!empty($topItems)): ?>
<section class="section-block">
    <div class="container">
        <div class="section-title">
            <h2>6 món được yêu thích nhất</h2>
            <p>Những món ăn được khách hàng gọi nhiều và quan tâm nhất tại nhà hàng</p>
        </div>

        <div class="menu-grid">
            <?php foreach ($topItems as $item): ?>
                <?php
                    $imageUrl = !empty($item['image_url']) ? $item['image_url'] : '/quanlynhahang/assets/images/featured-2.jpg';
                ?>
                <div class="menu-card">
                    <div class="menu-card-image">
                        <img src="<?= e($imageUrl) ?>" alt="<?= e($item['item_name']) ?>">
                    </div>
                    <div class="menu-card-body">
                        <div class="menu-card-top">
                            <h3><?= e($item['item_name']) ?></h3>
                            <span class="menu-price"><?= format_currency($item['price']) ?></span>
                        </div>
                        <p class="menu-description"><?= e($item['description'] ?: 'Chưa có mô tả.') ?></p>
                        <div class="menu-card-actions">
                            <a href="/quanlynhahang/customer/reservation.php?item_id=<?= (int)$item['item_id'] ?>#chon-mon-dat-truoc" class="btn btn-primary btn-sm">
                                Đặt bàn ngay
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section-block">
    <div class="container">
        <div class="cta-banner">
            <h2>Sẵn sàng trải nghiệm ẩm thực tại nhà hàng?</h2>
            <p>Đặt bàn ngay hôm nay hoặc xem thêm thực đơn hấp dẫn của chúng tôi</p>
            <div class="hero-buttons center-buttons">
                <a href="/quanlynhahang/customer/reservation.php" class="btn btn-light">Đặt bàn ngay</a>
                <a href="/quanlynhahang/customer/menu.php" class="btn btn-outline" style="border-color:#fff;color:#fff;">Xem thực đơn</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>