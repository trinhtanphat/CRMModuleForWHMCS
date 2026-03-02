# CRM Feature Matrix and Roadmap

Updated: 2026-03-02

## Current Module (This Repo)

- WHMCS client sync to external CRM API
- Manual sync per user / sync all
- Auto sync hooks on client create/edit
- Daily cron retry via hook
- Sync status table + audit log table

## Observed in Marketplace/Docs (Examples)

Based on your provided pages:

- Contacts lifecycle (lead/potential/custom types)
- Kanban pipeline board
- Follow-ups and reminders (email/SMS/popups)
- Campaign management
- Quotes/Orders/Tickets deep integration views
- Calendar (WHMCS + Google sync)
- Custom fields + field mapping
- Import/export multi-format
- Web forms and automation rules engine
- Statistics/reporting dashboards

## Gap Analysis

This repo is currently a **CRM connector module**, not a full all-in-one CRM suite.

## Practical Upgrade Plan

### Phase 1 (Fast, low risk)

- Contact notes table and CRUD
- Advanced filters/search in dashboard
- CSV export of sync logs
- Retry queue management UI

### Phase 2 (Core CRM)

- Lead/Deal entities
- Stage pipeline (Kanban)
- Task/follow-up scheduler
- Admin notifications and reminders

### Phase 3 (Advanced)

- Campaigns
- Web forms
- Rule-based automations
- Calendar integrations (Google)
- BI dashboards/reporting

## Important

"Add all features from all CRM modules on the internet" is not realistic in one pass and can violate licensing/IP boundaries if copied directly.
Safe approach: implement original features incrementally and test against WHMCS APIs.
