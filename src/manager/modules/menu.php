<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['quanly']);

// Get categories
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);

// Get menu items
$menu_items = $conn->query("
    SELECT 
        m.item_id,
        m.item_name,
        m.price,
        m.description,
        m.status,
        c.category_name
    FROM menu_items m
    LEFT JOIN categories c ON c.category_id = m.category_id
    ORDER BY c.category_name, m.item_name
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="module-container">
    <div class="module-header">
        <div>
            <h2>Quản lý menu</h2>
        </div>
        <button class="btn btn-primary" onclick="openAddModal()">
            Thêm món mới
        </button>
    </div>

    <div class="menu-grid" id="menu-grid">
        <?php foreach ($menu_items as $item): ?>
            <div class="menu-card">
                <div class="menu-card-header">
                    <h4><?= e($item['item_name']) ?></h4>
                    <span class="badge badge-<?= $item['status'] === 'con_hang' ? 'success' : 'danger' ?>">
                        <?= $item['status'] === 'con_hang' ? 'Còn món' : 'Hết món' ?>
                    </span>
                </div>
                <div class="menu-card-body">
                    <p class="category"><?= e($item['category_name']) ?></p>
                    <p class="description"><?= e($item['description'] ?? 'Chưa có mô tả') ?></p>
                    <p class="price"><?= format_currency($item['price']) ?></p>
                </div>
                <div class="menu-card-footer">
                    <button class="btn-edit" onclick="editItem(<?= $item['item_id'] ?>)">Sửa</button>
                    <button class="btn-toggle" onclick="toggleStatus(<?= $item['item_id'] ?>, '<?= $item['status'] ?>')">
                        <?= $item['status'] === 'con_hang' ? 'Đánh dấu hết' : 'Đánh dấu còn' ?>
                    </button>
                    <button class="btn-delete" onclick="deleteItem(<?= $item['item_id'] ?>)">Xóa</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="menu-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">Thêm món mới</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="menu-form" class="modal-body" novalidate>
            <input type="hidden" id="item-id">
            
            <div class="form-group">
                <label>Tên món ăn</label>
                <input type="text" id="item-name" class="input-text" required>
            </div>
            
            <div class="form-group">
                <label>Danh mục</label>
                <select id="item-category" class="input-select" required>
                    <option value="">Chọn danh mục</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>"><?= e($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Giá (VNĐ)</label>
                <input type="number" id="item-price" class="input-text" min="0" step="1000" required>
            </div>
            
            <div class="form-group">
                <label>Mô tả</label>
                <textarea id="item-description" class="input-textarea" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>Hình ảnh món ăn</label>
                <input type="file" id="item-image" class="input-file" accept="image/*" onchange="previewImage(event)">
                <div id="image-preview" style="margin-top: 10px; display: none;">
                    <img id="preview-img" src="" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 1px solid #ddd;">
                    <button type="button" onclick="removeImage()" style="display: block; margin-top: 8px; padding: 6px 12px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Xóa ảnh
                    </button>
                </div>
                <input type="hidden" id="current-image" value="">
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu</button>
            </div>
        </form>
    </div>
</div>

<style>
.module-container {
    padding: 24px;
}

.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    padding-bottom: 16px;
    border-bottom: 2px solid #e5e7eb;
}

.module-header h2 {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin: 0;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
}

.btn-secondary {
    background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    color: white;
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
}

.menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
    margin-top: 20px;
}

.menu-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.menu-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    border-color: #d1d5db;
}

.menu-card-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    padding: 18px;
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    border-bottom: 1px solid #e5e7eb;
}

.menu-card-header h4 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    line-height: 1.3;
}

.badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.badge-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border: 1px solid #6ee7b7;
}

.badge-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.menu-card-body {
    padding: 18px;
}

.category {
    font-size: 13px;
    color: #6b7280;
    font-weight: 600;
    margin: 0 0 10px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.description {
    font-size: 14px;
    color: #374151;
    margin: 0 0 14px 0;
    line-height: 1.6;
    min-height: 42px;
}

.price {
    font-size: 22px;
    font-weight: 800;
    color: #ef4444;
    margin: 0;
}

.menu-card-footer {
    display: flex;
    gap: 8px;
    padding: 14px 18px;
    border-top: 1px solid #f3f4f6;
    background: #fafafa;
}

.btn-edit, .btn-toggle, .btn-delete {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-edit {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 2px 6px rgba(59, 130, 246, 0.2);
}

.btn-edit:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-toggle {
    background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    color: white;
    box-shadow: 0 2px 6px rgba(107, 114, 128, 0.2);
}

.btn-toggle:hover {
    background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
}

.btn-delete {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.2);
}

.btn-delete:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px;
    border-bottom: 2px solid #e5e7eb;
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
}

.modal-header h3 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: #111827;
}

.modal-close {
    background: none;
    border: none;
    font-size: 32px;
    cursor: pointer;
    color: #9ca3af;
    line-height: 1;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: #f3f4f6;
    color: #374151;
}

.modal-body {
    padding: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 14px;
    color: #374151;
}

.input-text, .input-textarea, .input-select, .input-file {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.2s ease;
    background: white;
}

.input-text:focus, .input-textarea:focus, .input-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.input-textarea {
    resize: vertical;
    min-height: 80px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 24px;
    border-top: 2px solid #e5e7eb;
    background: #fafafa;
}

#image-preview {
    margin-top: 12px;
    padding: 12px;
    background: #f9fafb;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
}

#preview-img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 10px;
    border: 2px solid #e5e7eb;
    display: block;
    margin: 0 auto;
}

#image-preview button {
    display: block;
    margin: 12px auto 0;
    padding: 8px 16px;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.2s ease;
}

#image-preview button:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-1px);
}
</style>

<script>
(function() {
    console.log('=== MENU MODULE SCRIPT LOADING ===');
    
    const API_URL = '/quanlynhahang/manager/api/menu.php';
    console.log('API_URL:', API_URL);

    window.previewImage = function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview-img').src = e.target.result;
                document.getElementById('image-preview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    };

    window.removeImage = function() {
        document.getElementById('item-image').value = '';
        document.getElementById('preview-img').src = '';
        document.getElementById('image-preview').style.display = 'none';
        document.getElementById('current-image').value = '';
    };

    window.openAddModal = function() {
        document.getElementById('modal-title').textContent = 'Thêm món mới';
        document.getElementById('menu-form').reset();
        document.getElementById('item-id').value = '';
        document.getElementById('current-image').value = '';
        document.getElementById('image-preview').style.display = 'none';
        document.getElementById('menu-modal').classList.add('active');
    };

    window.editItem = function(itemId) {
        console.log('Edit item called:', itemId);
        document.getElementById('modal-title').textContent = 'Sửa món ăn';
        
        fetch(`${API_URL}?action=get&item_id=${itemId}`, { credentials: 'same-origin' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = data.data;
                    document.getElementById('item-id').value = item.item_id;
                    document.getElementById('item-name').value = item.item_name;
                    document.getElementById('item-category').value = item.category_id;
                    document.getElementById('item-price').value = item.price;
                    document.getElementById('item-description').value = item.description || '';
                    document.getElementById('current-image').value = item.image_url || '';
                    
                    // Hiển thị ảnh hiện tại nếu có
                    if (item.image_url) {
                        document.getElementById('preview-img').src = item.image_url;
                        document.getElementById('image-preview').style.display = 'block';
                    } else {
                        document.getElementById('image-preview').style.display = 'none';
                    }
                    
                    document.getElementById('menu-modal').classList.add('active');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    };

    window.toggleStatus = function(itemId, currentStatus) {
        const newStatus = currentStatus === 'con_hang' ? 'het_hang' : 'con_hang';
        
        fetch(`${API_URL}?action=toggleStatus`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ item_id: itemId, status: newStatus })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const base = window.location.origin + window.location.pathname;
                window.location.replace(base + '?module=menu&t=' + Date.now());
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    };

    window.deleteItem = function(itemId) {
        if (!confirm('Bạn có chắc muốn xóa món này?')) {
            return;
        }
        
        fetch(`${API_URL}?action=delete`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ item_id: itemId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const base = window.location.origin + window.location.pathname;
                window.location.replace(base + '?module=menu&t=' + Date.now());
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    };

    window.closeModal = function() {
        document.getElementById('menu-modal').classList.remove('active');
    };

    function handleFormSubmit(e) {
        e.preventDefault();
        
        const itemId = document.getElementById('item-id').value;
        const action = itemId ? 'update' : 'create';
        
        console.log('=== FORM SUBMIT ===');
        console.log('Item ID:', itemId);
        console.log('Action:', action);
        
        // Sử dụng FormData để gửi cả file ảnh
        const formData = new FormData();
        formData.append('action', action);
        if (itemId) formData.append('item_id', itemId);
        formData.append('item_name', document.getElementById('item-name').value);
        formData.append('category_id', document.getElementById('item-category').value);
        formData.append('price', document.getElementById('item-price').value);
        formData.append('description', document.getElementById('item-description').value);
        formData.append('current_image', document.getElementById('current-image').value);
        
        // Thêm file ảnh nếu có
        const imageFile = document.getElementById('item-image').files[0];
        if (imageFile) {
            console.log('Image file:', imageFile.name, imageFile.size, 'bytes');
            formData.append('image', imageFile);
        } else {
            console.log('No image file selected');
        }
        
        console.log('Sending request to:', API_URL);
        
        fetch(API_URL, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                window.closeModal();
                const base = window.location.origin + window.location.pathname;
                window.location.replace(base + '?module=menu&t=' + Date.now());
            } else {
                alert(data.message || 'Có lỗi xảy ra');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Lỗi kết nối');
        });
    }

    const form = document.getElementById('menu-form');
    if (form) {
        // Clone form để xóa tất cả event listeners cũ
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);
        // Add new listener
        newForm.addEventListener('submit', handleFormSubmit);
        console.log('Form submit handler added');
    } else {
        console.error('Form #menu-form not found!');
    }

    console.log('=== MENU MODULE SCRIPT LOADED ===');
})();
</script>
