<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_role(['quanly']);
?>

<div class="module-container">
    <!-- Header + Date picker -->
    <div class="module-header" style="flex-wrap:wrap; gap:12px;">
        <div>
            <h2>Dashboard</h2>
            <p style="color:#6b7280; font-size:14px;">Thống kê doanh thu và đơn hàng</p>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <label style="font-size:14px; font-weight:600; color:#374151;">Ngày:</label>
            <input type="date" id="dash-date" value="<?= date('Y-m-d') ?>"
                   style="padding:8px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; cursor:pointer;">
            <button onclick="loadDashboard()" class="btn btn-primary" style="padding:8px 16px; font-size:14px;">
                Xem
            </button>
        </div>
    </div>

    <!-- Loading spinner -->
    <div id="dash-loading" style="display:none; text-align:center; padding:40px; color:#9ca3af;">
        <div style="font-size:32px; margin-bottom:8px;">⏳</div>
        <p>Đang tải dữ liệu...</p>
    </div>

    <!-- Stat Cards -->
    <div id="dash-stats" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; margin-bottom:24px;">
        <?php
        $cards = [
            ['id'=>'stat-total-orders',   'label'=>'Tổng đơn',          'color'=>'#1f2937', 'bg'=>'#f9fafb'],
            ['id'=>'stat-walkin-orders',  'label'=>'Walk-in',            'color'=>'#065f46', 'bg'=>'#ecfdf5'],
            ['id'=>'stat-online-orders',  'label'=>'Online',             'color'=>'#1e40af', 'bg'=>'#eff6ff'],
            ['id'=>'stat-total-revenue',  'label'=>'Tổng doanh thu',     'color'=>'#7c3aed', 'bg'=>'#f5f3ff'],
            ['id'=>'stat-walkin-revenue', 'label'=>'Doanh thu Walk-in',  'color'=>'#065f46', 'bg'=>'#ecfdf5'],
            ['id'=>'stat-online-revenue', 'label'=>'Doanh thu Online',   'color'=>'#1e40af', 'bg'=>'#eff6ff'],
            ['id'=>'stat-khong-den',      'label'=>'Cọc không đến',      'color'=>'#92400e', 'bg'=>'#fffbeb'],
        ];
        foreach ($cards as $c): ?>
            <div style="background:<?= $c['bg'] ?>; border-radius:14px; padding:20px; border:1px solid #e5e7eb;">
                <p style="font-size:13px; color:#6b7280; font-weight:600; margin-bottom:8px;"><?= $c['label'] ?></p>
                <h3 id="<?= $c['id'] ?>" style="font-size:22px; font-weight:800; color:<?= $c['color'] ?>;">—</h3>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">
        <div style="background:white; border-radius:14px; padding:20px; border:1px solid #e5e7eb;">
            <h4 style="font-size:15px; font-weight:700; margin-bottom:16px; color:#111827;">📊 Doanh thu theo giờ</h4>
            <canvas id="chart-revenue" style="max-height:260px;"></canvas>
        </div>
        <div style="background:white; border-radius:14px; padding:20px; border:1px solid #e5e7eb;">
            <h4 style="font-size:15px; font-weight:700; margin-bottom:16px; color:#111827;">📈 Số đơn theo giờ</h4>
            <canvas id="chart-orders" style="max-height:260px;"></canvas>
        </div>
    </div>

    <!-- Revenue breakdown -->
    <div style="background:white; border-radius:14px; padding:20px; border:1px solid #e5e7eb;">
        <h4 style="font-size:15px; font-weight:700; margin-bottom:16px; color:#111827;">💰 Phân tích doanh thu</h4>
        <div id="dash-breakdown" style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
            <div style="text-align:center; padding:16px; background:#ecfdf5; border-radius:10px;">
                <p style="font-size:12px; color:#6b7280; margin-bottom:6px;">Walk-in</p>
                <strong id="bd-walkin" style="font-size:18px; color:#065f46;">—</strong>
                <p id="bd-walkin-pct" style="font-size:12px; color:#9ca3af; margin-top:4px;">—%</p>
            </div>
            <div style="text-align:center; padding:16px; background:#eff6ff; border-radius:10px;">
                <p style="font-size:12px; color:#6b7280; margin-bottom:6px;">Online (đã ăn)</p>
                <strong id="bd-online" style="font-size:18px; color:#1e40af;">—</strong>
                <p id="bd-online-pct" style="font-size:12px; color:#9ca3af; margin-top:4px;">—%</p>
            </div>
            <div style="text-align:center; padding:16px; background:#fffbeb; border-radius:10px;">
                <p style="font-size:12px; color:#6b7280; margin-bottom:6px;">Cọc không đến</p>
                <strong id="bd-khong-den" style="font-size:18px; color:#92400e;">—</strong>
                <p id="bd-khong-den-pct" style="font-size:12px; color:#9ca3af; margin-top:4px;">—%</p>
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width: 768px) {
    #dash-stats { grid-template-columns: 1fr 1fr !important; }
    #dash-breakdown { grid-template-columns: 1fr !important; }
    div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
}
@media (max-width: 480px) {
    #dash-stats { grid-template-columns: 1fr !important; }
}
</style>

<script>
(function() {
    const API = '/quanlynhahang/manager/api/dashboard.php';
    let revenueChart = null;
    let ordersChart  = null;

    function fmt(n) {
        return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + ' đ';
    }

    function setEl(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function showLoading(show) {
        document.getElementById('dash-loading').style.display = show ? 'block' : 'none';
    }

    async function loadDashboard() {
        const date = document.getElementById('dash-date').value;
        if (!date) return;
        showLoading(true);

        try {
            const [today, hourly, breakdown] = await Promise.all([
                fetch(`${API}?action=today&date=${date}`).then(r => r.json()),
                fetch(`${API}?action=hourly&date=${date}`).then(r => r.json()),
                fetch(`${API}?action=breakdown&date=${date}`).then(r => r.json()),
            ]);

            if (today.success) {
                setEl('stat-total-orders',   today.total_orders);
                setEl('stat-walkin-orders',  today.walkin_orders);
                setEl('stat-online-orders',  today.online_orders);
                setEl('stat-total-revenue',  fmt(today.total_revenue));
                setEl('stat-walkin-revenue', fmt(today.revenue_walkin));
                setEl('stat-online-revenue', fmt(today.revenue_online));
                setEl('stat-khong-den',      fmt(today.revenue_khong_den));
            }

            if (hourly.success) renderCharts(hourly);

            if (breakdown.success) {
                setEl('bd-walkin',       fmt(breakdown.walkin));
                setEl('bd-walkin-pct',   breakdown.pct_walkin + '%');
                setEl('bd-online',       fmt(breakdown.online));
                setEl('bd-online-pct',   breakdown.pct_online + '%');
                setEl('bd-khong-den',    fmt(breakdown.khong_den));
                setEl('bd-khong-den-pct', breakdown.pct_khong_den + '%');
            }
        } catch (e) {
            console.error('Dashboard load error:', e);
        } finally {
            showLoading(false);
        }
    }

    function renderCharts(data) {
        const labels = data.hours.map(h => h + ':00');

        // Bar chart — doanh thu theo giờ
        const ctxRev = document.getElementById('chart-revenue').getContext('2d');
        if (revenueChart) revenueChart.destroy();
        revenueChart = new Chart(ctxRev, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Doanh thu',
                    data: data.revenue,
                    backgroundColor: 'rgba(124, 58, 237, 0.2)',
                    borderColor: '#7c3aed',
                    borderWidth: 2,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => fmt(ctx.raw) } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) + ' đ' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Line chart — số đơn theo giờ (walk-in vs online)
        const ctxOrd = document.getElementById('chart-orders').getContext('2d');
        if (ordersChart) ordersChart.destroy();
        ordersChart = new Chart(ctxOrd, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Walk-in',
                        data: data.walkin_count,
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22,163,74,0.1)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 4,
                    },
                    {
                        label: 'Online',
                        data: data.online_count,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,0.1)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + ctx.raw + ' đơn' } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Expose to global scope for button onclick
    window.loadDashboard = loadDashboard;

    // Auto-load on init
    loadDashboard();
})();
</script>
