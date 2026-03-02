# User Guide - CRM Connector for WHMCS

## 1) Mục tiêu module

CRM Connector giúp đồng bộ dữ liệu khách hàng từ WHMCS sang CRM endpoint bên ngoài, đồng thời cung cấp các chức năng CRM nội bộ mức MVP trong admin area.

## 2) Yêu cầu trước khi dùng

- WHMCS 8.x+
- PHP 8.1+
- Bật cURL extension
- Có quyền admin trong WHMCS

## 3) Cài đặt module

1. Copy thư mục `modules/addons/crmconnector` vào WHMCS root.
2. Vào `System Settings > Addon Modules`.
3. Activate `CRM Connector`.
4. Configure các field:
   - `CRM Endpoint URL`
   - `API Key`
   - `Default CRM Tag`
   - `Auto Sync via Hook`
   - `Restrict Write Access` (tuỳ chọn)
   - `Write Admin IDs` (ví dụ `1,2,5`)
5. Gán quyền cho admin role cần truy cập module.

## 4) Truy cập module

- Vào `Addons > CRM Connector`.
- Dashboard hiển thị:
  - Sync controls
  - Retry queue
  - Sync logs + CSV export
  - CRM Notes
  - Leads, Deals, Follow-ups, Campaigns, Automation Rules
  - Basic Analytics

## 5) Hướng dẫn sử dụng tính năng

### 5.1 Đồng bộ khách hàng

- `Sync User`: nhập `WHMCS User ID` và đồng bộ một khách.
- `Sync All Clients`: đồng bộ toàn bộ `tblclients`.
- `Auto Sync via Hook`: nếu bật, module tự sync khi `ClientAdd`/`ClientEdit`.

### 5.2 Retry queue

- `Retry All Failed`: chạy lại toàn bộ bản ghi đang `failed`.
- `Retry Selected`: chọn từng user và retry theo danh sách.

### 5.3 Logs và export

- Bảng `Recent Sync Logs` hiển thị lịch sử xử lý gần nhất.
- `Export Logs CSV` tải file `crmconnector-logs.csv` để phân tích/audit.

### 5.4 Notes

- Nhập `WHMCS User ID` + nội dung note để lưu note nội bộ.
- Notes hiển thị admin tạo note và timestamp.

### 5.5 Leads / Deals / Follow-ups / Campaigns / Rules (MVP)

- Leads: thêm lead mới để theo dõi pipeline.
- Deals: tạo deal gắn lead, amount, stage.
- Follow-ups: tạo tác vụ follow-up cơ bản.
- Campaigns: tạo campaign và trạng thái.
- Rules: tạo rule automation cơ bản (trigger/action).

## 6) Phân quyền ghi (Write Access)

Nếu bật `Restrict Write Access`:

- Chỉ admin nằm trong `Write Admin IDs` được chạy action ghi dữ liệu.
- Admin khác vẫn xem được dữ liệu ở chế độ read-only.

## 7) Nâng cấp module

- Module có `crmconnector_upgrade()` để reconciliation schema khi nâng version.
- Key `schema_version` được lưu trong `tbladdonmodules`.

## 8) Đóng gói release

Chạy lệnh:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\package.ps1 -Version 1.2.0
```

Artifact tạo tại `dist/crmconnector-whmcs-v1.2.0.zip`.

## 9) Troubleshooting nhanh

- Không sync được: kiểm tra endpoint/API key/firewall.
- Export CSV không tải: kiểm tra token/session admin.
- Không ghi được dữ liệu: kiểm tra `Restrict Write Access` + danh sách admin ID.
- Hook không chạy: kiểm tra module đã activate/config đúng chưa.
