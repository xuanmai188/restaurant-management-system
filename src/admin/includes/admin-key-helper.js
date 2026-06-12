// Admin Key Helper - Tự động thêm key parameter vào tất cả links và forms
(function() {
    // Lấy key từ URL hiện tại
    const urlParams = new URLSearchParams(window.location.search);
    const adminKey = urlParams.get('key') || '';
    
    if (!adminKey) return; // Không có key thì không làm gì
    
    // Hàm thêm key vào URL
    function addKeyToUrl(url) {
        if (!url || url.startsWith('#') || url.startsWith('javascript:')) return url;
        const separator = url.includes('?') ? '&' : '?';
        return url + separator + 'key=' + adminKey;
    }
    
    // Khi DOM load xong
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Thêm key vào tất cả các link <a> trong trang (trừ logout và external links)
        document.querySelectorAll('a[href]').forEach(link => {
            const href = link.getAttribute('href');
            if (href && !href.includes('logout.php') && !href.startsWith('http') && !href.startsWith('#') && !href.includes('key=')) {
                link.href = addKeyToUrl(href);
            }
        });
        
        // 2. Thêm hidden input vào tất cả các form GET
        document.querySelectorAll('form[method="GET"], form:not([method])').forEach(form => {
            // Kiểm tra xem đã có input key chưa
            if (!form.querySelector('input[name="key"]')) {
                const keyInput = document.createElement('input');
                keyInput.type = 'hidden';
                keyInput.name = 'key';
                keyInput.value = adminKey;
                form.appendChild(keyInput);
            }
        });
        
        // 3. Xử lý các location.href trong onclick
        document.querySelectorAll('[onclick*="location.href"]').forEach(el => {
            const onclick = el.getAttribute('onclick');
            if (onclick && !onclick.includes('key=')) {
                // Thay thế location.href='...' bằng location.href='...&key=...'
                const newOnclick = onclick.replace(/location\.href\s*=\s*['"]([^'"]+)['"]/g, function(match, url) {
                    return `location.href='${addKeyToUrl(url)}'`;
                });
                el.setAttribute('onclick', newOnclick);
            }
        });
        
        // 4. Xử lý các select với onchange redirect
        document.querySelectorAll('select[onchange*="location"]').forEach(select => {
            const onchange = select.getAttribute('onchange');
            if (onchange && !onchange.includes('key=')) {
                const newOnchange = onchange.replace(/location\.href\s*=\s*['"]([^'"]+)['"]/g, function(match, url) {
                    return `location.href='${addKeyToUrl(url)}'`;
                });
                select.setAttribute('onchange', newOnchange);
            }
        });
    });
    
    // 5. Intercept window.location assignments
    const originalLocation = window.location;
    Object.defineProperty(window, 'location', {
        get: function() {
            return originalLocation;
        },
        set: function(url) {
            if (typeof url === 'string' && !url.includes('logout.php') && !url.includes('key=')) {
                originalLocation.href = addKeyToUrl(url);
            } else {
                originalLocation.href = url;
            }
        }
    });
})();
