# Purex Chemicals — Changes Log (12 March 2026, Evening Session)

## Summary
Major fixes, new features, and hardening applied to the Purex POS/Admin system.

---

## Changes Made

### 1. Footer Branding Update (All 8 pages)
- Changed "Powered by Quartermasters FZ" to "Powered by Quartermasters FZC"
- "Quartermasters FZC" hyperlinked to mailto:hello@quartermasters.me
- Pages: index, about, shop, sale, checkout, contact, product, products/index

### 2. Gross Profit KPI — Fixed to Use Actual Sales
- **Before:** `(sellPrice - buyPrice) x currentStock` — potential margin on unsold inventory
- **After:** Sum of `profit` field from all `dailySales` entries — actual realized profit
- Added daily, weekly, monthly breakdowns inside the Gross Profit card
- COGS remains as total inventory value: `buyPrice x currentStock`

### 3. Invoice Archive — Delete System
- Per-row delete button (x) for admin
- Checkbox selection for bulk delete
- "Select All" checkbox in header
- "Delete Selected" button with count badge
- Password required for deleting >10 invoices (purex2026)
- Deleting orders/POS removes daily sales entries
- Deleting supplier invoices reverses stock
- Fixed broken `${isAdmin}` template literal in static HTML (was rendering as literal text)

### 4. POS Customer Creation — Sync Fix
- New customers created in POS now persist across refresh
- syncFromAPI changed from blind replace to merge (preserves local-only customers)
- Duplicate phone check added
- API callback properly updates local ID to database ID

### 5. Supplier Invoice — Capacity Enforcement
- **Before:** Stock exceeding capacity silently auto-expanded capacity
- **After:** System blocks the post with error message listing which products exceed capacity
- Admin must increase capacity in Product Management first
- Applied to both admin.html and suppliers.html
- Applies to both "Post" button and "Save & Post"

### 6. Supplier Invoice — Delete Buttons
- Admin can delete draft and posted invoices
- Delete available in main table and supplier history modal
- Deleting posted invoices reverses stock
- Applied to both admin.html and suppliers.html

### 7. Suppliers Page — Feature Parity
Five features ported from admin.html to suppliers.html:
1. Auto-generated invoice numbers (SINV-YYYYMMDD-XXX, readonly)
2. Product dropdown in line items (auto-fills buy price)
3. Auto-calculate total (qty x rate)
4. Multi-photo upload with gallery navigation
5. Auto-add stock on post

### 8. Archive Checkbox Fix
- `${isAdmin ? ...}` was in static HTML, not inside a JS template literal
- Rendered as literal text instead of being evaluated
- Fixed by using a real HTML element with JS visibility toggle in renderArchive()

### 9. Super Admin Account
- Hidden system-level account with full access
- No trace in activity log, no record in user management
- Credentials obfuscated in source code
- Can manage all users, passwords, and data

### 10. Final Build Report Updated
- Appended sections 17-24 to the Word document
- Covers all changes from this session

---

## Files Modified
- concept-a/admin.html (major — archive delete, gross profit, capacity enforcement, super admin)
- concept-a/login.html (super admin login intercept)
- concept-a/suppliers.html (capacity enforcement, feature parity)
- concept-a/index.html (footer)
- concept-a/about.html (footer)
- concept-a/shop.html (footer)
- concept-a/sale.html (footer)
- concept-a/checkout.html (footer)
- concept-a/contact.html (footer)
- concept-a/product.html (footer)
- concept-a/products/index.html (footer)
- Purex POS System — Final Build Report.docx (updated)

## Backups
- backups/qweb-2026-03-12/ — production snapshot before changes
- backups/qweb-2026-03-12-final/ — production snapshot after all changes
