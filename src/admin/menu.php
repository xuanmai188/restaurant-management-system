<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['admin']);

$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);

$menu_items = $conn->query("
    SELECT m.item_id, m.item_name, m.price, m.description, m.status, m.image_url, c.category_name
    FROM menu_items m
    LEFT JOIN categories c ON c.category_id = m.category_id
    ORDER BY c.category_name, m.item_name
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Quản lý thực đơn';
$activeMenu = 'menu_items'; $sidebarRole = 'admin';
if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout.php'; }
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; padding-bottom:16px; border-bottom:2px solid #e5e7eb;">
    <h2 style="margin:0; font-size:28px; font-weight:700; color:#111827;">Quản lý menu</h2>
    <button class="btn btn-primary" onclick="openAddModal()">Thêm món mới</button>
</div>

<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:24px;">
    <?php foreach ($menu_items as $item): ?>
    <div style="background:white; border:1px solid #e5e7eb; border-radius:16px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.04); transition:all 0.3s ease;">
        <div style="display:flex; justify-content:space-between; align-items:start; padding:18px; background:linear-gradient(135deg,#f9fafb,#f3f4f6); border-bottom:1px solid #e5e7eb;">
            <h4 style="margin:0; font-size:18px; font-weight:700; color:#111827;"><?= e($item['item_name']) ?></h4>
            <span style="padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600; <?= $item['status']==='con_hang' ? 'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;' : 'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;' ?>">
                <?= $item['status']==='con_hang' ? 'Còn món' : 'Hết món' ?>
            </span>
        </div>
        <div style="padding:18px;">
            <p style="font-size:13px; color:#6b7280; font-weight:600; margin:0 0 10px; text-transform:uppercase; letter-spacing:0.5px;"><?= e($item['category_name']) ?></p>
            <p style="font-size:14px; color:#374151; margin:0 0 14px; line-height:1.6; min-height:42px;"><?= e($item['description'] ?? 'Chưa có mô tả') ?></p>
            <p style="font-size:22px; font-weight:800; color:#ef4444; margin:0;"><?= format_currency($item['price']) ?></p>
        </div>
        <div style="display:flex; gap:8px; padding:14px 18px; border-top:1px solid #f3f4f6; background:#fafafa;">
            <button onclick="editItem(<?= $item['item_id'] ?>)" style="flex:1; padding:10px; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; background:linear-gradient(135deg,#3b82f6,#2563eb); color:white;">Sửa</button>
            <button onclick="toggleStatus(<?= $item['item_id'] ?>, '<?= $item['status'] ?>')" style="flex:1; padding:10px; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; background:linear-gradient(135deg,#6b7280,#4b5563); color:white;">
                <?= $item['status']==='con_hang' ? 'Đánh dấu hết' : 'Đánh dấu còn' ?>
            </button>
            <button onclick="deleteItem(<?= $item['item_id'] ?>)" style="flex:1; padding:10px; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; background:linear-gradient(135deg,#ef4444,#dc2626); color:white;">Xóa</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal -->
<div id="menu-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:white; border-radius:16px; width:90%; max-width:600px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3); position:relative; z-index:10000;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:24px; border-bottom:2px solid #e5e7eb; background:linear-gradient(135deg,#f9fafb,#f3f4f6);">
            <h3 id="modal-title" style="margin:0; font-size:22px; font-weight:700; color:#111827;">Thêm món mới</h3>
            <button onclick="closeModal()" style="background:none; border:none; font-size:32px; cursor:pointer; color:#9ca3af; line-height:1;">&times;</button>
        </div>
        <form id="menu-form" style="padding:24px;">
            <input type="hidden" id="item-id">
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px; color:#374151;">Tên món ăn</label>
                <input type="text" id="item-name" style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; box-sizing:border-box;" required>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px; color:#374151;">Danh mục</label>
                <select id="item-category" style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; box-sizing:border-box;" required>
                    <option value="">Chọn danh mục</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>"><?= e($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px; color:#374151;">Giá (VNĐ)</label>
                <input type="number" id="item-price" style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; box-sizing:border-box;" min="0" step="1000" required>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px; color:#374151;">Mô tả</label>
                <textarea id="item-description" style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; box-sizing:border-box; resize:vertical; min-height:80px;" rows="3"></textarea>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px; color:#374151;">Hình ảnh</label>
                <input type="file" id="item-image" accept="image/*" onchange="previewImage(event)" style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; box-sizing:border-box;">
                <div id="image-preview" style="display:none; margin-top:12px; padding:12px; background:#f9fafb; border-radius:10px; border:1px solid #e5e7eb;">
                    <img id="preview-img" src="" alt="Preview" style="max-width:100%; max-height:200px; border-radius:10px; display:block; margin:0 auto;">
                    <button type="button" onclick="removeImage()" style="display:block; margin:12px auto 0; padding:8px 16px; background:linear-gradient(135deg,#ef4444,#dc2626); color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px;">Xóa ảnh</button>
                </div>
                <input type="hidden" id="current-image" value="">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; padding-top:20px; border-top:2px solid #e5e7eb;">
                <button type="button" onclick="closeModal()" style="padding:12px 24px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; background:linear-gradient(135deg,#6b7280,#4b5563); color:white;">Hủy</button>
                <button type="submit" style="padding:12px 24px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; background:linear-gradient(135deg,#3b82f6,#2563eb); color:white;">Lưu</button>
            </div>
        </form>
    </div>
</div>

<script>
const API_URL = '/quanlynhahang/manager/api/menu.php';

function previewImage(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('preview-img').src = e.target.result;
            document.getElementById('image-preview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

function removeImage() {
    document.getElementById('item-image').value = '';
    document.getElementById('preview-img').src = '';
    document.getElementById('image-preview').style.display = 'none';
    document.getElementById('current-image').value = '';
}

function openAddModal() {
    document.getElementById('modal-title').textContent = 'Thêm món mới';
    document.getElementById('menu-form').reset();
    document.getElementById('item-id').value = '';
    document.getElementById('current-image').value = '';
    document.getElementById('image-preview').style.display = 'none';
    document.getElementById('menu-modal').style.display = 'flex';
}

function editItem(itemId) {
    document.getElementById('modal-title').textContent = 'Sửa món ăn';
    fetch(`${API_URL}?action=get&item_id=${itemId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const item = data.data;
                document.getElementById('item-id').value = item.item_id;
                document.getElementById('item-name').value = item.item_name;
                document.getElementById('item-category').value = item.category_id;
                document.getElementById('item-price').value = item.price;
                document.getElementById('item-description').value = item.description || '';
                document.getElementById('current-image').value = item.image_url || '';
                if (item.image_url) {
                    document.getElementById('preview-img').src = item.image_url;
                    document.getElementById('image-preview').style.display = 'block';
                } else {
                    document.getElementById('image-preview').style.display = 'none';
                }
                document.getElementById('menu-modal').style.display = 'flex';
            }
        });
}

function toggleStatus(itemId, currentStatus) {
    const newStatus = currentStatus === 'con_hang' ? 'het_hang' : 'con_hang';
    fetch(`${API_URL}?action=toggleStatus`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({item_id: itemId, status: newStatus})
    }).then(r => r.json()).then(data => { if (data.success) location.reload(); });
}

function deleteItem(itemId) {
    if (!confirm('Bạn có chắc muốn xóa món này?')) return;
    fetch(`${API_URL}?action=delete`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({item_id: itemId})
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
        else alert(data.message);
    });
}

function closeModal() {
    document.getElementById('menu-modal').style.display = 'none';
}

document.getElementById('menu-form').addEventListener('submit', function(e) {
    e.preventDefault();
    console.log('Form submitted');
    
    const itemId = document.getElementById('item-id').value;
    const formData = new FormData();
    formData.append('action', itemId ? 'update' : 'create');
    if (itemId) formData.append('item_id', itemId);
    formData.append('item_name', document.getElementById('item-name').value);
    formData.append('category_id', document.getElementById('item-category').value);
    formData.append('price', document.getElementById('item-price').value);
    formData.append('description', document.getElementById('item-description').value);
    formData.append('current_image', document.getElementById('current-image').value);
    const imageFile = document.getElementById('item-image').files[0];
    if (imageFile) formData.append('image', imageFile);
    
    console.log('Sending request to:', API_URL);
    
    fetch(API_URL, {method: 'POST', body: formData})
        .then(r => {
            console.log('Response status:', r.status);
            return r.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) { 
                closeModal(); 
                location.reload(); 
            } else {
                alert(data.message || 'Có lỗi xảy ra');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Lỗi kết nối: ' + err.message);
        });
});
</script>

<?php if (!defined('ADMIN_EMBEDDED')) { include __DIR__ . '/../includes/layout_end.php'; } ?>


