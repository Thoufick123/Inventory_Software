# Production Billing Chat Summary

## Project

- Repository: Inventory
- Workspace: `C:\xampp\htdocs\testing`
- Module: Production Billing

## Features implemented

- Buyer selection between **Company** and **Customer**, with fields that switch based on the selection.
- Billing from available QC-passed Production Inventory using QR scanning/search.
- Bill create, list, view, edit, delete, and print flows.
- Per-item GST options: **SGST**, **IGST**, **CGST**, **Both (CGST and SGST)**, and **Exempted**.
- Per-item tax input modes: **Percentage** and **Fixed amount per piece**.
- Per-item GST rates, tax amounts, totals, and invoice displays.
- Fixed the edit form submission so changed price, GST type, and tax input type are sent when saving.

## Key files

- `app/Controllers/ProductionBillingController.php` — billing logic and persistence.
- `views/production/billing/form.php` — create/edit form and GST controls.
- `views/production/billing/index.php` — bill list.
- `views/production/billing/view.php` — bill details.
- `views/production/billing/print.php` — printable invoice.
- `database/migrations/create_production_billing.php` — billing tables and per-item GST migration.
- `database/schema.sql` — canonical schema definitions.
- `routes/web.php` — billing routes.
- `views/layouts/main.php` — Production Billing navigation.
- `database/seed_permissions.php` — billing permissions.

## Database setup

The migration has **not** been run against the database. Run:

```powershell
php database\migrations\create_production_billing.php
```

This migration creates the billing tables and adds missing billing and per-item GST columns. It also updates existing item GST defaults.

## Validation completed

- PHP syntax checks passed for the billing controller, views, and migration.
- Billing form JavaScript syntax check passed.
- GST calculation examples and SQL placeholder counts passed.
- `git diff --check` passed.
