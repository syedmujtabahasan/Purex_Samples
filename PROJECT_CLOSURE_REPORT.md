# PUREX CHEMICALS — Project Closure & Handover Report

**Date:** 2026-03-16
**Prepared by:** Quartermasters FZC
**Client:** PUREX CHEMICALS (Salman Raza, Numan Arshad)
**Project:** E-commerce website + POS + Inventory Management System

---

## 1. What Was Built

A complete web application for Purex Chemicals consisting of:

- **16 HTML pages** — Landing, Shop, Sale, Checkout, Product Detail, About, Contact, Admin Dashboard, Login, Audit & Reports, Suppliers, Customers, Users, Activity Log, Debug
- **12 PHP API routes** — Auth, Products, Orders, Sales, Transactions, Customers, Suppliers, Invoices, Users, Activity, Contacts, Uploads
- **12 MySQL database tables** — Full schema for products (31 SKUs), orders, POS transactions, customers, suppliers, purchase invoices, users with RBAC, activity logging
- **POS System** — Multi-item point-of-sale with transaction numbering (TXN-YYYYMMDD-###), customer linking, profit tracking
- **Inventory System** — Stock management with capacity limits, supplier invoice posting, automatic stock deduction on sales
- **Invoice PDF Generation** — jsPDF branded invoices with GST breakdown, NTN/STRN fields, itemized tables
- **WhatsApp Checkout** — Order placement via WhatsApp with formatted message builder
- **Admin Settings** — Configurable GST rate, delivery fees, company info, NTN/STRN, order prefix, currency

---

## 2. How It Was Secured

Six rounds of security hardening were applied:

**Round 1 — Backend & Server Config**
- Database credentials and JWT secret moved from hardcoded PHP to `.env` file
- CORS locked to production domain only (no wildcard `*`)
- Rate limiting added: login (5 attempts/15 min), contact form (3/10 min), global API (120/min)
- 7 security HTTP headers added to every response

**Round 2 — SQL Injection, JWT, XSS, CSRF**
- Every database query converted to PDO prepared statements with parameter binding
- All URL `$id` parameters cast to integer before any query
- JWT moved from sessionStorage (stealable by XSS) to HttpOnly Secure SameSite=Strict cookie
- Output escaping (`escape_output()`) applied to all API JSON responses
- CSRF double-submit cookie pattern implemented for all state-changing requests

**Round 3 — File Upload Protection**
- 7-layer upload validation: auth check, type whitelist, error handling, size limit, finfo MIME verification, GD image integrity check, GD re-encoding (destroys embedded payloads)
- Uploaded files get cryptographic random filenames (no user input in filename)
- `uploads/.htaccess` kills PHP engine, removes script handlers, blocks non-image files

**Round 4 — Apache .htaccess Hardening**
- `Options -Indexes` on all directories (no directory browsing)
- `.git/`, `backups/`, `database/`, all sensitive files blocked with 403
- HTTPS forced with Hostinger proxy header support
- Content-Security-Policy on HTML pages
- Only `api/index.php` can execute; all other PHP files blocked
- 7 `.htaccess` files total forming a defense grid

**Round 5 — Open Redirect Prevention**
- All 24 `window.location.href` calls across 11 files replaced with `safeRedirect()` (validates against allowlist)
- All `window.open()` calls replaced with `safeExternalOpen()` (validates domain against allowlist)
- PHP `safe_redirect()` function added for server-side redirect safety

**Round 6 — Invoice Fix**
- Black rectangle in PDF invoices fixed by stripping embedded raster images from SVG letterhead before rendering

---

## 3. How It Was Cleaned

The GitHub repository (`github.com/syedmujtabahasan/Purex_Samples`) was cleaned:

**Removed from repo:**
- `gemini-bridge/` — old AI bridge files (4 files)
- `products/` — old raw product photos from early development (33 files)
- `index.html` — old root redirect stub

**Excluded via .gitignore (never committed):**
- `api/.env` — database passwords, JWT secret
- `SESSION_CONTEXT.md` — FTP passwords, admin credentials
- `conversation_extract.txt` — internal chat logs
- `backups/` — old snapshots with hardcoded credentials
- `*.docx`, `*.pdf` — Word docs, reports
- `BACKEND_PLAN.md`, `DEPLOYMENT_GUIDE.md`, `PRODUCTION_PLAN.md`, `SECURITY_REPORT.md` — internal docs with architecture details
- `gen_*.py`, `backup_local.sh`, `migration_scanner.php` — utility scripts

**What IS in the repo (clean, deployable code only):**
- `concept-a/` — all 16 HTML pages + `js/api-client.js`
- `api/` — PHP router, 12 routes, middleware, helpers, config
- `api/.env.example` — template showing what env vars are needed (no real values)
- `assets/` — logos, product images, co-founder photo
- `database/schema.sql` — database structure
- `uploads/` — directory structure with security `.htaccess` files
- `.htaccess`, `.user.ini` — server config
- `.gitignore`

---

## 4. How It Was Backed Up

| Backup | Location | Size | Contains |
|--------|----------|------|----------|
| **Local Backup #2** | `C:\Users\Mujtaba Hasan\Downloads\Purex_Local_Backup_2.zip` | 136 MB | Everything — code, docs, backups, secrets, internal files |
| **GitHub** | `github.com/syedmujtabahasan/Purex_Samples` (main branch) | ~12 MB | Clean deployable code only — no secrets, no internal docs |
| **Local folder** | `C:\Users\Mujtaba Hasan\Downloads\Purex\` | ~170 MB | Working directory with everything |

The local backup zip contains files that GitHub does not (internal docs, `.env`, backups with old credentials, Word documents, migration scripts). This is intentional — GitHub has only what's safe to be in a repository.

---

## 5. How the Staging Server Was Torn Down

The testing domain `qwebtesting.tech` was completely wiped on 2026-03-16:
- All 15 root-level HTML files deleted
- All directories deleted (`concept-a/`, `api/`, `assets/`, `uploads/`, `database/`, `products/`)
- Config files deleted (`.htaccess`, `.user.ini`)
- Server is now empty and ready for the next project

---

## 6. How to Deploy to a New Domain

When the client provides a domain (e.g., `purexchemicals.com`) and hosting:

**Step 1:** Open `Purex_Local_Backup_2.zip` or the local folder

**Step 2:** On the new Hostinger account, create a MySQL database and note:
- Database name
- Database username
- Database password

**Step 3:** Import `database/schema.sql` via phpMyAdmin

**Step 4:** Create `api/.env` on the server (copy from `api/.env.example`) and fill in:
```
DB_NAME=<new database name>
DB_USER=<new database user>
DB_PASS=<new strong password>
JWT_SECRET=<run: openssl rand -hex 32>
ALLOWED_ORIGINS=https://newdomain.com,https://www.newdomain.com
```

**Step 5:** Upload all files to the server via FTP

**Step 6:** Visit `https://newdomain.com/api/seed.php` once to create admin user + 31 products

**Step 7:** Delete `api/seed.php` from the server

**Step 8:** Activate SSL certificate in Hostinger panel

**Step 9:** Log in at `/login.html`, change admin password

**Step 10:** Verify everything works

For the complete 17-step checklist with SQL cleanup queries and localStorage clearing scripts, read `PRE_DEPLOYMENT_PACKAGE.md`.

---

## 7. Files a Developer Should Read

If a developer needs to pick up this project in the future, read these files in this order:

| Priority | File | What it tells you |
|----------|------|-------------------|
| 1 | **This file** (`PROJECT_CLOSURE_REPORT.md`) | Overall picture — what was built, how it's secured, how to deploy |
| 2 | **`PRE_DEPLOYMENT_PACKAGE.md`** | Complete system map (all 16 pages, 12 API routes, core systems explained), plus the step-by-step deployment checklist |
| 3 | **`api/.env.example`** | What environment variables the backend needs |
| 4 | **`database/schema.sql`** | Full database structure (12 tables) |
| 5 | **`HANDOVER_CHECKLIST.md`** | Database cleanup SQL, localStorage keys to clear, files to delete before client handover |
| 6 | **`SECURITY_REPORT.md`** | Detailed breakdown of every security measure and what it protects against |
| 7 | **`migration_scanner.php`** | Run with `php migration_scanner.php` before any deployment — flags hardcoded staging references |

All of these files are in the local folder. They are NOT on GitHub (intentionally excluded to keep the repo clean).

---

## 8. Key Credentials & Access

**These are stored locally only — never in GitHub:**

| What | Where to find it |
|------|-----------------|
| Database credentials | `api/.env` (local copy) |
| FTP credentials | Hostinger panel for the new domain |
| Admin login | Default: `admin@purex.com` / `purex2026` — **must be changed on first login** |
| JWT secret | `api/.env` — **must be generated fresh per deployment** |

---

*Project completed and archived. Ready for deployment whenever the client provides hosting.*
