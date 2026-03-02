# CRM Connector Product Roadmap (All Phases)

Updated: 2026-03-02

## Why license/IP risk exists when "copying"

You can copy **ideas and feature behavior**, but must not copy:

- Proprietary source code
- Vendor-specific database schemas copied verbatim
- UI assets/screenshots/text content
- Decompiled/obfuscated logic

Safe route: implement original code that achieves similar outcomes (feature parity).

## Current Delivery Status

- Phase 1 implemented in this repository (v1.1.0 scope):
  - CRM notes (basic)
  - Retry queue UI (failed sync retry all/selected)
  - CSV export for logs
- MVP baseline added for Phases 2-6 in module dashboard (Leads/Deals/Follow-ups/Campaigns/Rules/Basic Analytics)
- Expanded MVP added:
  - Contact Types and Labels management
  - Webform definitions + public intake endpoint
  - Leads CSV import/export
  - Daily cron processing for due follow-ups and automation rule checks
  - Campaign filter by lead status
  - Label board with drag-drop lead movement

## All-Phase Plan

### Phase 1 - Connector Operations (Done)

- Sync single/all clients
- Auto sync hooks
- Failed sync retry queue
- Sync audit logs + CSV export
- Basic notes

### Phase 2 - Core CRM Data Model

- `leads` entity and lifecycle statuses
- `deals` entity with stages
- Kanban board for stage movement
- Search, filters, and sorting
- Role-based permissions for CRM actions

### Phase 3 - Activities and Follow-ups

- Follow-up/task scheduler
- Reminder channels (email/in-app)
- Contact timeline (notes + activity)
- Ticket and quote relationship links

### Phase 4 - Campaigns and Intake

- Campaign model and assignment rules
- Public web form intake endpoint
- UTM/source tracking and campaign attribution
- Bulk actions and segmented contact lists

### Phase 5 - Automation Engine

- Rule conditions and actions
- Trigger events (invoice/order/service changes)
- Scheduled worker/cron execution
- Action logs and replay/retry mechanics

### Phase 6 - Analytics and Reporting

- KPI dashboard (pipeline, conversion, response time)
- CSV exports (contacts/deals/activities)
- Date-range and owner filters

### Phase 7 - Integration and Hardening

- Google Calendar sync (optional)
- Webhook security (signature/IP allow list)
- Data retention controls and purge jobs
- Migration scripts and versioned upgrades

### Phase 8 - Marketplace Readiness

- UX polish and screenshots
- Full admin permissions map
- Compatibility matrix (WHMCS/PHP)
- Changelog and support policy completion

## Delivery Method

- Build each phase in milestone branches
- Release semantic versions (`v1.x`, `v2.x`)
- No reuse of proprietary competitor code
- Validate by behavior and integration tests
