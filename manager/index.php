<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['admin', 'quanly']);

$user = $_SESSION['user'];
$module = $_GET['module'] ?? 'reports';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - Nhà Hàng</title>
    <link rel="stylesheet" href="/quanlynhahang/assets/css/app.css">
    <style>
        .loading {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }
        
        .loading::after {
            content: '...';
            animation: dots 1.5s steps(4, end) infinite;
        }
        
        @keyframes dots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60%, 100% { content: '...'; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="logo">MG</div>
                <div>
                    <h2>Quản lý</h2>
                    <p>Quản lý vận hành</p>
                </div>
            </div>
            
            <nav class="menu">
                <a href="#" data-module="reports" class="<?= $module === 'reports' ? 'active' : '' ?>">
                    Tổng quan
                </a>
                <a href="#" data-module="staff" class="<?= $module === 'staff' ? 'active' : '' ?>">
                    Nhân viên
                </a>
                <a href="#" data-module="menu" class="<?= $module === 'menu' ? 'active' : '' ?>">
                    Thực đơn
                </a>
                <a href="#" data-module="tables" class="<?= $module === 'tables' ? 'active' : '' ?>">
                    Bàn & Tầng
                </a>
                <a href="#" data-module="orders" class="<?= $module === 'orders' ? 'active' : '' ?>">
                    Đơn hàng
                </a>
                <a href="#" data-module="reservations" class="<?= $module === 'reservations' ? 'active' : '' ?>">
                    Đặt bàn
                </a>
                <a href="#" data-module="customers" class="<?= $module === 'customers' ? 'active' : '' ?>">
                    Khách hàng
                </a>
                <a href="#" data-module="maintenance" class="<?= $module === 'maintenance' ? 'active' : '' ?>">
                    Bảo trì hệ thống
                </a>
                <a href="/quanlynhahang/index.php" target="_blank" class="external-link">
                    Trang chủ
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <a href="/quanlynhahang/auth/logout.php" class="btn-logout">Đăng xuất</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main">
            <div class="topbar">
                <div>
                    <h1 id="page-title">Tổng quan</h1>
                    <p id="page-subtitle"></p>
                </div>
                <div class="userbox">
                    <div class="avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
                    <div>
                        <strong><?= e($user['full_name']) ?></strong>
                        <small><?= e($user['email'] ?? $user['username']) ?></small>
                    </div>
                </div>
            </div>
            
            <div id="content-area">
                <div class="loading">Đang tải</div>
            </div>
        </main>
    </div>

    
    <!-- Chart.js for reports -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    
    <!-- Main JavaScript -->
    <script>
        const BASE_URL = '/quanlynhahang';
        
        // Module configuration
        const modules = {
            reports: {
                title: 'Tổng quan',
                subtitle: 'Thống kê doanh thu và hoạt động hàng ngày',
                url: `${BASE_URL}/manager/modules/reports.php`
            },
            staff: {
                title: 'Quản lý nhân viên',
                subtitle: 'Phân ca và xem danh sách nhân viên',
                url: `${BASE_URL}/manager/modules/staff.php`
            },
            menu: {
                title: 'Quản lý thực đơn',
                subtitle: 'Thêm và sửa món ăn',
                url: `${BASE_URL}/manager/modules/menu.php`
            },
            tables: {
                title: 'Quản lý bàn',
                subtitle: 'Xem trạng thái và gán bàn cho khách',
                url: `${BASE_URL}/manager/modules/tables.php`
            },
            orders: {
                title: 'Quản lý tất cả đơn hàng',
                subtitle: '',
                url: `${BASE_URL}/manager/modules/orders.php`
            },
            reservations: {
                title: 'Đặt bàn',
                subtitle: 'Quản lý đặt bàn của khách hàng',
                url: `${BASE_URL}/manager/modules/reservations.php`
            },
            customers: {
                title: 'Quản lý khách hàng',
                subtitle: 'Thông tin và lịch sử khách hàng',
                url: `${BASE_URL}/manager/modules/customers.php`
            },
            maintenance: {
                title: 'Bảo trì hệ thống',
                subtitle: 'Cài đặt và bảo trì hệ thống',
                url: `${BASE_URL}/manager/modules/maintenance.php`
            }
        };
        
        // Load module without page reload
        function loadModule(moduleName) {
            const contentArea = document.getElementById('content-area');
            const moduleConfig = modules[moduleName];
            
            if (!moduleConfig) {
                contentArea.innerHTML = '<div class="alert alert-error">Module không tồn tại</div>';
                return;
            }
            
            // Update page title
            document.getElementById('page-title').textContent = moduleConfig.title;
            document.getElementById('page-subtitle').textContent = moduleConfig.subtitle;
            
            // Update active menu
            document.querySelectorAll('.menu a').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector(`[data-module="${moduleName}"]`).classList.add('active');
            
            // Show loading
            contentArea.innerHTML = '<div class="loading">Đang tải</div>';
            
            // Fetch module content
            fetch(moduleConfig.url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    contentArea.innerHTML = html;
                    
                    // Execute scripts in the loaded HTML
                    const scripts = contentArea.querySelectorAll('script');
                    scripts.forEach(oldScript => {
                        const newScript = document.createElement('script');
                        if (oldScript.src) {
                            newScript.src = oldScript.src;
                        } else {
                            newScript.textContent = oldScript.textContent;
                        }
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    });
                    
                    initModuleScripts(moduleName);
                })
                .catch(error => {
                    console.error('Error loading module:', error);
                    contentArea.innerHTML = '<div class="alert alert-error">Lỗi tải module. Vui lòng thử lại.</div>';
                });
        }
        
        // Initialize module-specific scripts
        function initModuleScripts(moduleName) {
            switch(moduleName) {
                case 'tables':
                    if (typeof initTablesModule === 'function') initTablesModule();
                    break;
                case 'orders':
                    if (typeof initOrdersModule === 'function') initOrdersModule();
                    break;
                case 'menu':
                    if (typeof initMenuModule === 'function') initMenuModule();
                    break;
                case 'customers':
                    if (typeof initCustomersModule === 'function') initCustomersModule();
                    break;
                case 'staff':
                    if (typeof initStaffModule === 'function') initStaffModule();
                    break;
                case 'reports':
                    if (typeof initReportsModule === 'function') initReportsModule();
                    break;
            }
        }
        
        // Utility: Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // Utility: Format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: 'VND'
            }).format(amount);
        }
        
        // Utility: Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type}`;
            toast.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            // Setup menu click handlers
            document.querySelectorAll('.menu a').forEach(link => {
                link.addEventListener('click', (e) => {
                    if (link.classList.contains('external-link')) return; // bỏ qua link ngoài
                    e.preventDefault();
                    const module = link.dataset.module;
                    loadModule(module);
                    
                    // Update URL without reload
                    const url = new URL(window.location);
                    url.searchParams.set('module', module);
                    window.history.pushState({}, '', url);
                });
            });
            
            // Intercept form submissions in modules
            document.addEventListener('submit', (e) => {
                const form = e.target;
                const contentArea = document.getElementById('content-area');
                
                // Check if form is inside content-area (module form)
                if (contentArea.contains(form) && form.method.toLowerCase() === 'get' && !form.getAttribute('onsubmit')) {
                    e.preventDefault();
                    
                    // Get form data
                    const formData = new FormData(form);
                    const params = new URLSearchParams(formData);
                    
                    // Get current module from active menu
                    const activeMenu = document.querySelector('.menu a.active');
                    const currentModule = activeMenu ? activeMenu.dataset.module : 'reports';
                    const moduleConfig = modules[currentModule];
                    
                    // Build URL with form parameters
                    const url = `${moduleConfig.url}?${params.toString()}`;
                    
                    // Show loading
                    contentArea.innerHTML = '<div class="loading">Đang tải</div>';
                    
                    // Fetch with parameters
                    fetch(url)
                        .then(response => response.text())
                        .then(html => {
                            contentArea.innerHTML = html;
                            
                            // Execute scripts
                            const scripts = contentArea.querySelectorAll('script');
                            scripts.forEach(oldScript => {
                                const newScript = document.createElement('script');
                                if (oldScript.src) {
                                    newScript.src = oldScript.src;
                                } else {
                                    newScript.textContent = oldScript.textContent;
                                }
                                oldScript.parentNode.replaceChild(newScript, oldScript);
                            });
                            
                            initModuleScripts(currentModule);
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            contentArea.innerHTML = '<div class="alert alert-error">Lỗi tải dữ liệu</div>';
                        });
                }
            });
            
            // Load initial module
            const urlParams = new URLSearchParams(window.location.search);
            const initialModule = urlParams.get('module') || 'reports';
            loadModule(initialModule);
        });
    </script>
</body>
</html>