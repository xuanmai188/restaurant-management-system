<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';


$db = null;
$dbType = '';

if (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
    $dbType = 'pdo';
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
    $dbType = 'mysqli';
} elseif (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
    $dbType = 'mysqli';
} else {
    die('Không tìm thấy biến kết nối CSDL. Hãy kiểm tra file config/database.php');
}

/*
|--------------------------------------------------------------------------
| Tự động hủy reservation quá 1 tiếng
|--------------------------------------------------------------------------
*/
if ($db && $dbType === 'mysqli') {
    auto_cancel_expired_reservations();
}

/*
|--------------------------------------------------------------------------
| Hàm lấy dữ liệu tương thích PDO / MySQLi
|--------------------------------------------------------------------------
*/
function db_fetch_all($db, string $dbType, string $sql, array $params = []): array
{
    if ($dbType === 'pdo') {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // MySQLi
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        die('Lỗi prepare SQL: ' . $db->error);
    }

    if (!empty($params)) {
        $types = '';
        $values = [];

        foreach ($params as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $value;
        }

        $stmt->bind_param($types, ...$values);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

/*
|--------------------------------------------------------------------------
| Nhận bộ lọc
|--------------------------------------------------------------------------
*/
$keyword = trim($_GET['keyword'] ?? '');
$categoryId = (int)($_GET['category_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Lấy danh mục
|--------------------------------------------------------------------------
*/
$categoriesSql = "SELECT category_id, category_name FROM categories ORDER BY category_name ASC";
$categories = db_fetch_all($db, $dbType, $categoriesSql);

/*
|--------------------------------------------------------------------------
| Lấy danh sách món
|--------------------------------------------------------------------------
*/
$sql = "SELECT 
            mi.item_id,
            mi.item_name,
            mi.price,
            mi.description,
            mi.image_url,
            mi.status,
            c.category_name
        FROM menu_items mi
        LEFT JOIN categories c ON c.category_id = mi.category_id
        WHERE mi.status = 'con_hang'";

$params = [];

if ($keyword !== '') {
    $sql .= " AND (mi.item_name LIKE ? OR mi.description LIKE ?)";
    $likeKeyword = '%' . $keyword . '%';
    $params[] = $likeKeyword;
    $params[] = $likeKeyword;
}

if ($categoryId > 0) {
    $sql .= " AND mi.category_id = ?";
    $params[] = $categoryId;
}

$sql .= " ORDER BY mi.item_id DESC";

$items = db_fetch_all($db, $dbType, $sql, $params);

/*
|--------------------------------------------------------------------------
| Hàm fallback nếu functions.php chưa có
|--------------------------------------------------------------------------
*/
if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_currency')) {
    function format_currency($amount)
    {
        return number_format((float)$amount, 0, ',', '.') . ' đ';
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="menu-page">
    <div class="container">
        <div class="menu-hero">
            <div class="menu-hero-content">
                <span class="menu-badge">Món Ăn Bình Dân</span>
                <h1>Xem món ăn đang phục vụ từ cơ sở dữ liệu</h1>                
            </div>
        </div>

        <div class="menu-toolbar">
            <form method="GET" class="menu-filter-form">
                <div class="filter-group">
                    <input
                        type="text"
                        class="filter-input"
                        name="keyword"
                        placeholder="Tìm tên món hoặc mô tả..."
                        value="<?= e($keyword) ?>"
                    >
                </div>

                <div class="filter-group">
                    <select class="filter-select" name="category_id">
                        <option value="0">Tất cả danh mục</option>
                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= (int)$category['category_id'] ?>"
                                <?= $categoryId === (int)$category['category_id'] ? 'selected' : '' ?>
                            >
                                <?= e($category['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Lọc dữ liệu</button>
            </form>
        </div>

        <div class="menu-grid">
            <?php if (empty($items)): ?>
                <div class="empty-box">
                    <h3>Không tìm thấy món ăn phù hợp</h3>
                    <p>Hãy thử từ khóa khác hoặc bỏ bộ lọc danh mục.</p>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <div class="menu-card">
                        <div class="menu-card-image">
                            <?php 
                            $imageUrl = $item['image_url'] ?? '/quanlynhahang/assets/images/featured-2.jpg';
                            if (empty($imageUrl)) {
                                $imageUrl = '/quanlynhahang/assets/images/featured-2.jpg';
                            }
                            ?>
                            <img src="<?= e($imageUrl) ?>" alt="<?= e($item['item_name']) ?>">
                        </div>

                        <div class="menu-card-body">
                            <div class="menu-card-top">
                                <h3><?= e($item['item_name']) ?></h3>
                                <span class="menu-price"><?= format_currency($item['price']) ?></span>
                            </div>

                            <p class="menu-description">
                                <?= e($item['description'] ?: 'Chưa có mô tả cho món ăn này.') ?>
                            </p>

                            <div class="menu-card-actions">
                                <a href="/quanlynhahang/customer/reservation.php?item_id=<?= (int)$item['item_id'] ?>#chon-mon-dat-truoc" class="btn btn-primary btn-sm">
                                        Đặt bàn ngay
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>