# CRM Connector - Full Admin Guide

## 1. Overview

CRM Connector là module CRM mở rộng trong WHMCS, gồm:

- Đồng bộ khách hàng WHMCS sang CRM endpoint
- Quản lý Leads / Deals / Follow-ups / Campaigns / Rules
- Labels và Contact Types cho quy trình sales
- Webform intake cho khách ngoài site
- Logging, retry queue, import/export CSV, analytics cơ bản

## 2. Installation & Activation

1. Upload `modules/addons/crmconnector` vào WHMCS root.
2. Activate tại `System Settings > Addon Modules`.
3. Configure endpoint/API key và các option bảo mật.
4. Gán quyền truy cập cho admin groups.

## 3. Global Configuration

- **CRM Endpoint URL**: địa chỉ API CRM ngoài.
- **API Key**: token xác thực.
- **Auto Sync via Hook**: auto sync khi ClientAdd/ClientEdit.
- **Restrict Write Access**: bật chế độ read-only cho admin không whitelist.
- **Write Admin IDs**: danh sách admin được ghi dữ liệu.
- **Webform Token**: token bảo vệ endpoint webform.

## 4. Dashboard Modules

### 4.1 Sync Center

- Sync từng user theo ID
- Sync toàn bộ khách hàng
- Bảng trạng thái sync gần nhất

### 4.2 Retry Queue

- Retry tất cả bản ghi failed
- Retry bản ghi được chọn

### 4.3 Logs & Export

- Log lịch sử tác vụ
- Export log CSV

### 4.4 CRM Notes

- Tạo note theo WHMCS User ID
- Truy vết admin tạo note

## 5. CRM Data Areas

### 5.1 Leads

- Tạo lead thủ công
- Import/Export CSV leads

### 5.2 Deals (Pipeline)

- Tạo deals theo stage
- Theo dõi amount và expected close

### 5.3 Follow-ups

- Tạo follow-up theo user/lead
- Có due datetime
- Cron tự xử lý follow-up đến hạn

### 5.4 Campaigns

- Tạo campaign với trạng thái
- Lưu mô tả chiến dịch

### 5.5 Automation Rules

- Tạo rules trigger/action
- Cron đánh dấu execution log theo rules enabled

### 5.6 Contact Types & Labels

- Tạo contact types tùy chỉnh
- Tạo labels phục vụ board/pipeline

## 6. Web Forms

Endpoint intake:

`modules/addons/crmconnector/webform.php`

Request `POST` mẫu:

- `token` (bắt buộc)
- `form_id` (optional)
- `name` (bắt buộc)
- `email` (optional)
- `source` (optional)

Phản hồi JSON `success/message`.

## 7. Cron Behavior

Trong `DailyCronJob` module sẽ:

1. Retry failed/pending sync (nếu auto sync bật)
2. Process follow-ups đến hạn (`pending` -> `done`)
3. Process automation rules enabled (log execution)

## 8. Security & Compliance

- CSRF cho admin actions
- Token cho webform endpoint
- Audit logs đầy đủ
- Có policy templates: privacy + DPA

## 9. Suggested Operations Workflow

1. Tạo contact types + labels
2. Cấu hình webform/token
3. Thu lead từ webform/import CSV
4. Tạo deals/follow-ups theo lead
5. Dùng campaign + automation rules
6. Theo dõi analytics/logs và export audit

## 10. Known MVP Gaps

Bản hiện tại là expanded MVP, chưa phải enterprise CRM hoàn chỉnh như các module thương mại lớn.

Các phần nên tiếp tục nâng cấp:

- Kanban drag & drop UI thực sự
- Rule engine điều kiện/phân nhánh nâng cao
- Mailbox 2 chiều + template engine sâu
- Dashboard chart trực quan
- Permission matrix theo từng action
