# Dữ liệu mẫu CSDL

## Cách 1: Import lại toàn bộ (có cấu trúc + dữ liệu mới)

1. Mở phpMyAdmin hoặc MySQL CLI.
2. Import file `../qlnhahang.sql` (đã cập nhật INSERT, **không đổi** CREATE TABLE / trigger).

## Cách 2: Chỉ thay dữ liệu (giữ cấu trúc hiện tại)

Chạy file `sample_data_inserts.sql` — script sẽ TRUNCATE các bảng có dữ liệu mẫu rồi INSERT lại ID từ 1.

```bash
mysql -u root qlnhahang < database/sample_data_inserts.sql
```

## Tài khoản demo

Mật khẩu tất cả tài khoản: **123456**

| Username | Vai trò |
|----------|---------|
| nguyenvanan | Admin |
| tranthihuong | Quản lý |
| leminhtuan | Thu ngân |
| phamvanduc | Bếp |
| dothilan / hoangquocbinh | Phục vụ |
| minhkhoi / thidieu / huunghia / thaomy | Khách hàng |

## Nội dung mẫu

- 6 vai trò, 10 user, 8 bàn (ID 1–8), 10 món, 6 khách hàng
- 6 đặt bàn, 10 đơn hàng (tổng tiền khớp chi tiết món)
- Ghi chú đặt bàn / đơn hàng có ngữ cảnh thực tế (sinh nhật, công ty, hẹn hò…)
