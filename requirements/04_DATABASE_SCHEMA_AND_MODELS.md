# GRM Inventory Management System — Database Schema & Data Models

## 1. Database Architecture Overview

The database is built on **MariaDB 10.4+ / MySQL 8.0+** using the `InnoDB` storage engine and `utf8mb4_unicode_ci` collation. The canonical DDL in [`database/schema.sql`](../database/schema.sql) currently defines **112 tables**. That file is authoritative for complete column definitions, indexes, and constraints; the inventory below lists every table in the current baseline.

```text
allocation_items, allocation_outwards, asset_audit_items, asset_audits,
asset_categories, asset_maintenance_logs, asset_transfers, assets,
audit_logs, backups, bank_deposits, brands, cash_expenses, cash_registers,
cash_transactions, categories, channel_damages, colors, customers,
cutting_batch_rolls, cutting_batches, cutting_items, deliveries,
exchange_items, exchanges, expense_categories, expenses, hsn_codes,
label_batches, label_items, locations, notifications, payments, permissions,
product_price_logs, product_variants, production_batch_items, production_batches,
production_bill_item_tags, production_bill_items, production_bills,
production_deleted_label_codes, production_expense_items, production_expenses,
production_outward_batches, production_outwards, production_program_items,
production_program_rolls, production_programs, production_purchase_items,
production_purchases, production_return_items, production_returns,
production_stock_request_items, production_stock_requests, production_units,
production_warehouse_reworks, products, purchase_inward_items, purchase_inwards,
purchase_items, purchase_orders, qc_batches, qc_items, return_items, returns,
role_permissions, roles, roll_damage_reasons, roll_damages, sale_items, sales,
sales_channels, seller_roll_deleted_labels, seller_rolls, sellers, settings,
shop_damages, shop_returned_products, shop_stock_adjustments, shops, sizes,
stitching_batches, stitching_items, stitching_wastage_sales, stock_adjustments,
stock_balances, stock_transactions, sub_subcategories, subcategories,
transfer_items, units, user_permission_overrides, user_permissions, users,
warehouse_allocations, warehouse_categories, warehouse_damages,
warehouse_deleted_label_codes, warehouse_qr_codes, warehouse_return_items,
warehouse_returns, warehouse_sub_subcategories, warehouse_subcategories,
warehouse_suppliers, warehouse_transfers, warehouses, wastage_sales,
wh_category_attributes, wh_category_default_products, whatsapp_returned_products,
whatsapp_stock_adjustments
```

`product_price_logs` records warehouse catalog price revisions and is included in both the canonical schema and the additive migration for existing databases.

---

## 2. Core Relational Schemas & Entity Definitions

### 2.1 Central Inventory Ledger (`stock_balances` & `stock_transactions`)

#### Table: `stock_balances` (Snapshot of Real-Time Stock)
| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `INT UNSIGNED` | `PRIMARY KEY, AUTO_INCREMENT` | Unique balance identifier |
| `variant_id` | `INT UNSIGNED` | `NOT NULL, FK -> product_variants.id` | Specific SKU variant |
| `location_type` | `ENUM` | `NOT NULL, ('PRODUCTION','WAREHOUSE','SHOP','CHANNEL')` | Physical or logical site |
| `location_id` | `INT UNSIGNED` | `NOT NULL` | ID of unit, warehouse, shop, or channel |
| `location_code` | `VARCHAR(50)` | `NULL` | Fast code alias |
| `stock_pool` | `ENUM` | `NOT NULL DEFAULT 'AVAILABLE'` | `'AVAILABLE'`, `'RESERVED'`, `'DAMAGED'`, `'IN_PRODUCTION'` |
| `quantity` | `DECIMAL(10,2)` | `NOT NULL DEFAULT 0.00` | Current on-hand quantity |
| `rack_location` | `VARCHAR(100)` | `NULL` | Bin / Shelf coordinates |
| `updated_at` | `DATETIME` | `NOT NULL` | Timestamp of last stock balance change |

#### Table: `stock_transactions` (Immutable Audit Log)
| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | `PRIMARY KEY, AUTO_INCREMENT` | Unique transaction ID |
| `transaction_number` | `VARCHAR(60)` | `NOT NULL, UNIQUE` | Formatted identifier (`TXN-YYYYMMDD-XXXX`) |
| `variant_id` | `INT UNSIGNED` | `NOT NULL, FK -> product_variants.id` | SKU reference |
| `transaction_type` | `VARCHAR(50)` | `NOT NULL` | `'INWARD'`, `'OUTWARD'`, `'RESERVATION'`, `'TRANSFER'`, `'SALE'`, `'RETURN'`, `'ADJUSTMENT'` |
| `source_type` | `VARCHAR(50)` | `NULL` | Source location type |
| `source_id` | `INT UNSIGNED` | `NULL` | Source location ID |
| `dest_type` | `VARCHAR(50)` | `NULL` | Destination location type |
| `dest_id` | `INT UNSIGNED` | `NULL` | Destination location ID |
| `quantity` | `DECIMAL(10,2)` | `NOT NULL` | Transacted quantity |
| `previous_balance` | `DECIMAL(10,2)` | `NOT NULL` | Balance before transaction |
| `new_balance` | `DECIMAL(10,2)` | `NOT NULL` | Balance after transaction |
| `reference_type` | `VARCHAR(50)` | `NOT NULL` | `'SALE'`, `'PO'`, `'ALLOCATION'`, `'PRODUCTION_BATCH'`, `'RETURN'` |
| `reference_id` | `INT UNSIGNED` | `NOT NULL` | ID in reference table |
| `created_by` | `INT UNSIGNED` | `NOT NULL, FK -> users.id` | User who authorized movement |
| `created_at` | `DATETIME` | `NOT NULL` | Timestamp |

---

### 2.2 Operational Expenses & Petty Cash (`expenses` & `expense_categories`)

#### Table: `expenses` (Enterprise Operational & Channel Expense Registry)
| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `INT UNSIGNED` | `PRIMARY KEY, AUTO_INCREMENT` | Unique expense record ID |
| `expense_number` | `VARCHAR(50)` | `NOT NULL, UNIQUE` | E.g. `EXP-202609-0001` |
| `location_type` | `ENUM` | `NOT NULL` | `'PRODUCTION'`, `'WAREHOUSE'`, `'SHOP'`, `'CHANNEL'`, `'OFFICE'`, `'WHATSAPP'`, `'WEBSITE'` |
| `location_id` | `INT UNSIGNED` | `NULL` | Specific Unit / Warehouse / Shop ID |
| `category` | `VARCHAR(100)` | `NOT NULL` | E.g. `'Freight'`, `'Packaging'`, `'Electricity'` |
| `item_name` | `VARCHAR(255)` | `NOT NULL` | Expense item / line title |
| `quantity` | `DECIMAL(10,2)` | `NOT NULL DEFAULT 1.00` | Quantity purchased |
| `unit_cost` | `DECIMAL(12,2)` | `NOT NULL DEFAULT 0.00` | Cost per unit |
| `total_cost` | `DECIMAL(12,2)` | `NOT NULL` | Total expense amount |
| `paid_amount` | `DECIMAL(12,2)` | `NOT NULL` | Amount settled |
| `payment_status` | `ENUM` | `NOT NULL` | `'PAID'`, `'PENDING'`, `'PARTIAL'`, `'APPROVED'`, `'CANCELLED'` |
| `payment_method` | `ENUM` | `NOT NULL` | `'CASH'`, `'UPI'`, `'BANK_TRANSFER'`, `'CARD'`, `'PETTY_CASH'`, `'CHEQUE'`, `'CREDIT'` |
| `paid_to` | `VARCHAR(200)` | `NOT NULL` | Vendor / Payee name |
| `expense_date` | `DATE` | `NOT NULL` | Effective date of expense |
| `receipt_url` | `VARCHAR(500)` | `NULL` | Uploaded receipt image URL |
| `created_by` | `INT UNSIGNED` | `FK -> users.id` | Staff who submitted expense |
| `approved_by` | `INT UNSIGNED` | `FK -> users.id` | Manager who approved expense |

---

### 2.3 Product Price History (`product_price_logs`)

The warehouse catalog price-edit flow records the prior and new selling price and MRP, cost, calculated percentages, revision note, actor snapshot, and timestamp. The composite index on (`variant_id`, `created_at`) supports the stock-detail history query.

| Column | Type | Constraints / behavior |
| :--- | :--- | :--- |
| `variant_id` | `INT UNSIGNED` | Nullable FK to `product_variants.id`; `ON DELETE SET NULL` preserves the history row |
| `product_id` | `INT UNSIGNED` | Nullable FK to `products.id`; `ON DELETE SET NULL` preserves the history row |
| `sku` | `VARCHAR(100)` | Non-null SKU snapshot |
| `old_selling_price`, `new_selling_price`, `old_mrp`, `new_mrp`, `cost_price` | `DECIMAL(10,2)` | Non-null price snapshots |
| `markup_percentage` | `DECIMAL(10,4)` | Nullable when cost is zero |
| `discount_percentage` | `DECIMAL(10,4)` | Non-null |
| `revision_notes` | `TEXT` | Non-null reason / note |
| `user_id` | `INT UNSIGNED` | Nullable indexed actor ID; no FK, so missing actor metadata cannot prevent the log insert |
| `user_name` | `VARCHAR(200)` | Non-null actor-name snapshot |
| `created_at` | `DATETIME` | Non-null, defaults to current timestamp |

### 2.4 Returns & Customer Exchanges (`returns` & `exchanges`)

#### Table: `returns` (Customer & Inward Returns)
| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `INT UNSIGNED` | `PRIMARY KEY, AUTO_INCREMENT` | Return transaction ID |
| `return_number` | `VARCHAR(50)` | `NOT NULL, UNIQUE` | E.g. `RET-202609-0001` |
| `source_type` | `ENUM` | `NOT NULL DEFAULT 'SHOP'` | `'SHOP'`, `'WAREHOUSE_PURCHASE'`, `'WHATSAPP'`, `'WEBSITE'` |
| `sale_id` | `INT UNSIGNED` | `NULL, FK -> sales.id` | Original retail / channel sale |
| `purchase_order_id` | `INT UNSIGNED` | `NULL, FK -> purchase_orders.id` | Original direct purchase |
| `customer_id` | `INT UNSIGNED` | `NULL, FK -> customers.id` | Customer record |
| `total_amount` | `DECIMAL(12,2)` | `NOT NULL` | Refund / credit value |
| `refund_status` | `ENUM` | `NOT NULL` | `'COMPLETED'`, `'PENDING'`, `'REJECTED'` |
| `refund_method` | `VARCHAR(50)` | `NOT NULL` | `'CASH'`, `'UPI'`, `'EXCHANGE'`, `'CREDIT_NOTE'` |
| `created_at` | `DATETIME` | `NOT NULL` | Date of return |

#### Table: `exchanges` (Replacement & Size/Color Swaps)
| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `INT UNSIGNED` | `PRIMARY KEY, AUTO_INCREMENT` | Exchange record ID |
| `exchange_number` | `VARCHAR(50)` | `NOT NULL, UNIQUE` | E.g. `EXC-202609-0001` |
| `return_id` | `INT UNSIGNED` | `NOT NULL, FK -> returns.id` | Linked return transaction |
| `sale_id` | `INT UNSIGNED` | `NULL, FK -> sales.id` | Original sale |
| `purchase_order_id` | `INT UNSIGNED` | `NULL, FK -> purchase_orders.id` | Direct purchase |
| `price_difference` | `DECIMAL(12,2)` | `NOT NULL DEFAULT 0.00` | `+ve` (Customer Pays) / `-ve` (Store Refunds) |
| `settlement_mode` | `VARCHAR(50)` | `NOT NULL DEFAULT 'CASH'` | Cash / UPI / Card settlement |
| `created_at` | `DATETIME` | `NOT NULL` | Date of exchange |
