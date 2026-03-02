# Test Scenarios - CRM Connector for WHMCS

## 1) Mục tiêu test

Xác nhận module hoạt động ổn định cho:

- Sync dữ liệu khách hàng
- Quyền truy cập ghi
- Retry queue/log export
- Chức năng CRM MVP (notes/leads/deals/follow-ups/campaigns/rules)
- Tính tương thích khi release/upgrade

## 2) Phạm vi

- In-scope: admin addon module workflow
- Out-of-scope: UI/feature parity 100% với module thương mại bên thứ ba

## 3) Dữ liệu test mẫu

- Admin A: ID thuộc whitelist write
- Admin B: ID không thuộc whitelist
- Clients:
  - Client #1: dữ liệu hợp lệ
  - Client #2: dữ liệu hợp lệ
  - Client #3: dùng để mô phỏng retry failed

## 4) Test cases chi tiết

### TC-01 Activate module

- Steps:
  1. Vào Addon Modules và Activate `CRM Connector`.
  2. Mở trang module.
- Expected:
  - Không lỗi activate.
  - Hiển thị module version + schema version.

### TC-02 Sync single thành công

- Steps:
  1. Nhập `WHMCS User ID` hợp lệ.
  2. Bấm `Sync User`.
- Expected:
  - Message thành công.
  - `mod_crmconnector_contacts` cập nhật status `synced`.
  - Có log `sync_user`.

### TC-03 Sync all

- Steps:
  1. Bấm `Sync All Clients`.
- Expected:
  - Có summary số lượng synced.
  - Có log `sync_all`.

### TC-04 Retry all failed

- Steps:
  1. Tạo ít nhất 1 bản ghi failed (bằng endpoint sai hoặc chặn endpoint).
  2. Bấm `Retry All Failed`.
- Expected:
  - Có summary retry.
  - Có log `retry_failed_all`.

### TC-05 Retry selected

- Steps:
  1. Tick một số failed user.
  2. Bấm `Retry Selected`.
- Expected:
  - Chỉ user được chọn được retry.
  - Có log `retry_selected`.

### TC-06 Export CSV logs

- Steps:
  1. Bấm `Export Logs CSV`.
- Expected:
  - Download file `crmconnector-logs.csv`.
  - Header CSV đúng: `id,created_at,userid,action,status,message`.

### TC-07 Add note

- Steps:
  1. Nhập `note_userid` hợp lệ và nội dung note.
  2. Submit `Add Note`.
- Expected:
  - Note xuất hiện trong bảng Notes.
  - Có log `add_note`.

### TC-08 Permission guard (read-only)

- Preconditions:
  - Bật `Restrict Write Access`.
  - Chỉ whitelist Admin A.
- Steps:
  1. Login bằng Admin B.
  2. Thử `Sync User` hoặc `Add Note`.
- Expected:
  - Bị chặn ghi với message permission denied.
  - Vẫn xem được dữ liệu dashboard.

### TC-09 Permission guard (write allowed)

- Steps:
  1. Login Admin A (trong whitelist).
  2. Thực hiện một action ghi bất kỳ.
- Expected:
  - Action chạy thành công.

### TC-10 Leads MVP

- Steps:
  1. Add lead với name/email/status.
- Expected:
  - Lead hiển thị đúng trong bảng.

### TC-11 Deals MVP

- Steps:
  1. Add deal (title/amount/stage).
- Expected:
  - Deal hiển thị đúng trong bảng.

### TC-12 Follow-ups MVP

- Steps:
  1. Add follow-up.
- Expected:
  - Follow-up hiển thị với status `pending`.

### TC-13 Campaign MVP

- Steps:
  1. Add campaign.
- Expected:
  - Campaign hiển thị đúng status/description.

### TC-14 Automation rule MVP

- Steps:
  1. Add rule.
- Expected:
  - Rule hiển thị với trigger/action đúng.

### TC-15 Upgrade path

- Steps:
  1. Nâng version module.
  2. Chạy upgrade (WHMCS module upgrade flow).
- Expected:
  - Không mất dữ liệu cũ.
  - Schema vẫn hợp lệ.
  - `schema_version` cập nhật.

### TC-16 Lead CSV import/export

- Steps:
  1. Export leads CSV.
  2. Chỉnh file và import lại qua form Import Leads CSV.
- Expected:
  - Export tải được.
  - Import tạo thêm leads hợp lệ.

### TC-17 Webform endpoint token

- Steps:
  1. Gửi POST đến `modules/addons/crmconnector/webform.php` với token sai.
  2. Gửi lại với token đúng.
- Expected:
  - Token sai trả 403.
  - Token đúng tạo lead thành công.

### TC-18 Daily cron expanded behavior

- Steps:
  1. Tạo follow-up `pending` có `due_at` trong quá khứ.
  2. Chạy cron Daily.
- Expected:
  - Follow-up đổi thành `done`.
  - Có log `daily_cron` và `followup_due`.

### TC-19 Kanban drag-drop deal stage

- Steps:
  1. Tạo deal ở stage `qualification`.
  2. Kéo thả card sang cột `proposal`.
- Expected:
  - Stage cập nhật thành `proposal`.
  - Có log `move_deal_stage`.

### TC-20 Lead-Campaign assignment

- Steps:
  1. Tạo lead và campaign.
  2. Gán lead vào campaign.
- Expected:
  - Bảng assignment hiển thị mapping đúng.
  - Có log `assign_lead_campaign`.

### TC-21 Action permissions matrix

- Steps:
  1. Tạo rule `Admin B + add_deal = no`.
  2. Login Admin B, thử tạo deal.
- Expected:
  - Action bị chặn với permission denied.
  - Admin B vẫn chạy được action khác không bị chặn.

### TC-22 Campaign filter by lead status

- Steps:
  1. Tạo campaign, gán nhiều lead với status khác nhau.
  2. Chọn campaign và status filter trên dashboard.
- Expected:
  - Danh sách chỉ hiển thị lead đúng campaign và status.

### TC-23 Label board drag-drop

- Steps:
  1. Tạo labels, gán lead vào label A.
  2. Kéo lead card từ cột label A sang label B.
- Expected:
  - Mapping lead-label được cập nhật.
  - Bảng assignment và board phản ánh label mới.

### TC-24 API auth and CRUD for leads

- Steps:
  1. Gọi `GET api.php?resource=leads` không token.
  2. Gọi lại với token đúng.
  3. Gọi POST tạo lead mới.
  4. Gọi PUT cập nhật lead.
  5. Gọi DELETE lead.
- Expected:
  - Không token -> 401.
  - Token đúng -> thao tác CRUD thành công.

### TC-25 API rate limit

- Steps:
  1. Cấu hình `API Rate Limit/Min` thấp (ví dụ 3).
  2. Gọi API cùng token/IP vượt ngưỡng.
- Expected:
  - Request vượt ngưỡng nhận 429.

### TC-26 OpenAPI/Postman interoperability

- Steps:
  1. Import `docs/openapi.crmconnector.json` vào API client.
  2. Import `docs/postman.crmconnector.collection.json` vào Postman.
  3. Chạy request mẫu Leads list/create.
- Expected:
  - Collection chạy được với token đúng.
  - Kết quả API khớp mô tả spec.

### TC-27 API token rotation and deactivation

- Steps:
  1. Trong dashboard, tạo token mới bằng `Rotate/New API Token`.
  2. Gọi API bằng token mới.
  3. Deactivate token vừa tạo.
  4. Gọi lại API bằng token đó.
- Expected:
  - Trước deactivate: gọi API thành công.
  - Sau deactivate: nhận 401.

### TC-28 API resource coverage (notes/followups/labels)

- Steps:
  1. POST tạo note/followup/label qua API.
  2. GET list từng resource.
  3. PUT update từng resource.
  4. DELETE resource test.
- Expected:
  - CRUD hoạt động đúng cho cả 3 resource mới.

### TC-29 API cleanup cron

- Steps:
  1. Tạo traffic API để sinh rate-limit rows.
  2. Chạy Daily cron hoặc bấm cleanup thủ công.
- Expected:
  - Bản ghi rate-limit cũ được dọn.
  - Có log `cleanup_api_limits`.

## 5) Smoke test trước release

Chạy tối thiểu:

- TC-01, TC-02, TC-06, TC-08, TC-09, TC-15

## 6) Regression test mỗi lần thay đổi lớn

- Re-run toàn bộ TC-01 đến TC-15
- Kiểm tra workflow GitHub:
  - `CI`
  - `Release Readiness`

## 7) Exit criteria

- 100% test critical pass (TC-01, 02, 06, 08, 09, 15)
- Không có lỗi blocker trong admin area
- Package zip build thành công và cài được trên môi trường test
