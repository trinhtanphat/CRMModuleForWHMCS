# CRM Connector for WHMCS

WHMCS addon module to synchronize client data to an external CRM API endpoint.

## Features

- Admin dashboard in WHMCS Addon Modules
- Manual sync by client ID
- Bulk sync all clients
- Auto sync via WHMCS hooks (`ClientAdd`, `ClientEdit`)
- Retry failed/pending records in `DailyCronJob`
- Local sync status tracking table (`mod_crmconnector_contacts`)
- Sync audit logs (`mod_crmconnector_logs`)
- Retry queue UI (retry all / retry selected)
- CRM notes (basic)
- CSV export for sync logs
- Module/schema version tracking
- Upgrade handler (`crmconnector_upgrade`) for migration compatibility
- Optional write-access restriction by admin ID list

## Project Structure

```text
modules/
  addons/
    crmconnector/
      crmconnector.php
      hooks.php
      lib/
        CrmClient.php
```

## Requirements

- WHMCS 8.x+
- PHP 8.1+ (recommended)
- cURL extension enabled

## Installation (Local or Production)

1. Copy folder `modules/addons/crmconnector` into your WHMCS root.
2. Login to WHMCS Admin.
3. Go to **System Settings > Addon Modules**.
4. Find **CRM Connector** and click **Activate**.
5. Click **Configure** and set:
   - `CRM Endpoint URL`
   - `API Key`
   - `Default CRM Tag`
   - `Auto Sync via Hook`
6. Assign access permissions to admin role(s).
7. Open **Addons > CRM Connector** and test with **Sync User**.

## Packaging for Marketplace-style Delivery

Create a zip that keeps this exact relative path inside archive:

```text
modules/addons/crmconnector/*
```

### Quick package command (Windows PowerShell)

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\package.ps1 -Version 1.0.0
```

Artifact will be generated in `dist/crmconnector-whmcs-v1.0.0.zip`.

If you upload to marketplace, include:

- Product description
- Screenshots
- Changelog
- Support policy
- Privacy notes (what customer data is sent to CRM)

Policy templates included:

- `docs/PRIVACY_POLICY.md`
- `docs/DATA_PROCESSING_ADDENDUM.md`

Feature benchmark and roadmap:

- `FEATURE_MATRIX.md`
- `ROADMAP.md`

GitHub publishing guide:

- `docs/PUBLISH_GITHUB.md`

Release checklist:

- `docs/RELEASE_CHECKLIST.md`

User guide:

- `docs/USER_GUIDE.md`
- `docs/CRM_FULL_GUIDE.md`

API endpoint:

- `modules/addons/crmconnector/api.php`

API token management:

- Rotate/deactivate tokens in module dashboard (`API Management`)
- Configure TTL via `API Token TTL Days`

API production capabilities:

- Pagination/filter/sort list endpoints
- Outbound webhooks with HMAC signature
- Audit trail snapshots for API mutations
- API IP allowlist controls
- Token rotation policy with max-active auto-disable

API specs and tools:

- `docs/openapi.crmconnector.json`
- `docs/postman.crmconnector.collection.json`

Test scenarios:

- `docs/TEST_SCENARIOS.md`

## Permission Model

Addon access is still controlled by WHMCS admin role permissions. Additionally, this module can enforce write-action restrictions:

- `Restrict Write Access` = enabled
- `Write Admin IDs` = comma-separated whitelist of admin IDs

Admins outside this list can still view module data in read-only mode.

## Migration / Versioning

- Module version is declared in addon config.
- Schema version is stored in `tbladdonmodules` with key `schema_version`.
- `crmconnector_upgrade()` executes schema reconciliation for upgrades.

## Can WHMCS Block This?

Usually not blocked if your module follows technical and policy rules. Common rejection/block reasons:

- Uses obfuscation or suspicious remote code loading
- Sends personal data without disclosure
- No versioning/changelog/support details
- Security issues (missing CSRF checks, unsafe input handling)
- Conflicts with WHMCS licensing policy

This project already includes CSRF protection in admin forms and a conservative, transparent integration flow.

## Development

### Install tooling

```bash
composer install
```

### Lint PHP

```bash
composer run lint
```

### Static analysis

```bash
composer run analyse
```

## Notes about WHMCS Source Code

- WHMCS itself is commercial and closed source (not fully public).
- Module/plugin development is officially supported via public developer docs and hook/module APIs.
- You can still build and distribute addon modules without WHMCS core source.

## License

Private/proprietary by default. Add your own license before publishing.
