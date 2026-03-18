# OlaPortal Legacy API — Project Documentation

> **Type:** Legacy PHP REST API  |  **Status:** Production (Active)

---

## Executive Summary _(For Non-Technical Readers)_

This is the **original brain of the OlaPortal platform** — the server-side software that stores, processes, and serves all business data. Written in PHP, it has been running in production since the platform launched and handles everything from recording a sale at a store terminal to generating the revenue charts you see on the dashboard.

Think of it as the **engine room**: merchants and agents never see it directly, but every click in the web portal triggers this system to either fetch or save data.

**Current status:** Fully operational. A newer, faster version (the Go API) is being built alongside it and will gradually take over its responsibilities.

---

## How the Platform Is Organized

```
┌─────────────────────────────────────────────────────────────────────┐
│                        OLAPAY PLATFORM                              │
│                                                                     │
│   ┌──────────────┐      manages      ┌──────────────────────────┐  │
│   │    ADMIN     │ ──────────────── ▶│         AGENTS           │  │
│   │  (OlaPay Co.)│                   │  (Sales reps / resellers) │  │
│   └──────────────┘                   └────────────┬─────────────┘  │
│                                                   │ manages        │
│                                      ┌────────────▼─────────────┐  │
│                                      │  VENDORS / MERCHANTS     │  │
│                                      │  (Store owners)          │  │
│                                      └────────────┬─────────────┘  │
│                                                   │ owns           │
│                                      ┌────────────▼─────────────┐  │
│                                      │      TERMINALS           │  │
│                                      │  (POS devices in stores) │  │
│                                      └──────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘

  Each level can only see data that belongs to them or their children.
  An agent sees all their merchants. A merchant sees only their own store.
```

---

## How a Sale Gets Recorded — End-to-End Workflow

This is the most important process in the system. Every sale at a store terminal goes through this exact journey before it appears on a dashboard.

```
STEP 1 — Customer Pays at Store
─────────────────────────────────────────────────────────────────
  Customer
    │ taps card / pays cash
    ▼
  POS Terminal (physical device in store)
    │ sends raw transaction data (JSON format)
    ▼
  This API  ──saves──▶  Database (raw_json table)
                        "We received it. Not yet processed."


STEP 2 — Background Reconciliation (runs every hour, automatically)
─────────────────────────────────────────────────────────────────
  Scheduled Job (reconcile_orders.php)
    │
    ├── reads raw JSON from database
    ├── parses and normalizes the data
    │   (handles different terminal firmware versions)
    ├── removes duplicates
    └── saves clean records into:
          ┌─────────────────┐
          │    orders        │  ← the sale itself
          │    orderItems    │  ← what was bought
          │    ordersPayments│  ← how it was paid
          └─────────────────┘


STEP 3 — Portal Shows the Data
─────────────────────────────────────────────────────────────────
  Merchant logs in to portal
    │
    ▼
  Portal asks this API for orders
    │
    ▼
  API reads clean records → returns formatted data
    │
    ▼
  Dashboard shows revenue, transactions, best sellers
```

> **Why the 2-step approach?**
> Terminals send data in slightly different formats depending on their firmware version. The reconciliation step normalizes everything into one consistent format before it enters the clean database tables. This prevents duplicate entries and data corruption.

---

## How OlaPay Card Payments Are Handled

OlaPay transactions (card payments via OlaPay terminals) follow a parallel but separate path:

```
  OlaPay Terminal
       │ sends payment payload
       ▼
  /jsonOlaPay.php  ──────▶  jsonOlaPay table (raw)
       │                    (rate-limited: 30 req/sec via Redis)
       │
       ▼ (background sync job)
  unique_olapay_transactions  ← deduplicated, clean records
       │
       ▼
  Portal → olapayTerminalRecord.php → shows in Transactions tab
```

---

## How a User Logs In

```
  User enters email + password on the portal
       │
       ▼
  /login.php  checks:
       ├── Does this email exist?
       └── Does the password hash match?
              │
        YES ──┘
              │
              ▼
        Issues a "security token" (JWT)
        valid for 30 days
        contains: role, account ID, company name
              │
              ▼
        Portal stores token, uses it for all future requests
        API validates token on every request — no token = access denied
```

---

## What Data This API Manages

```
┌──────────────────────────────────────────────────────────────────┐
│                        DATABASE OVERVIEW                         │
│                                                                  │
│  TRANSACTIONS           ACCOUNTS              PRODUCTS           │
│  ┌─────────────┐       ┌─────────────┐       ┌─────────────┐   │
│  │ orders      │       │ accounts    │       │ items       │   │
│  │ orderItems  │       │ terminals   │       │ menus       │   │
│  │ ordersPaymt │       │ stores      │       │ inventories │   │
│  │ jsonOlaPay  │       │ payment_    │       │ inventoryLg │   │
│  │ json (raw)  │       │  methods    │       └─────────────┘   │
│  └─────────────┘       └─────────────┘                         │
│                                                                  │
│  CUSTOMERS              BILLING               INTEGRATIONS       │
│  ┌─────────────┐       ┌─────────────┐       ┌─────────────┐   │
│  │ customer    │       │ subscript-  │       │ quickbooks_ │   │
│  │ (+ loyalty) │       │  ions       │       │  export_q   │   │
│  └─────────────┘       │ sub_plans   │       │ qb_token_   │   │
│                        │ sub_paymnts │       │  cred       │   │
│                        └─────────────┘       └─────────────┘   │
└──────────────────────────────────────────────────────────────────┘
  48 tables total. Managed with 77 database migrations over time.
```

---

## How QuickBooks Export Works

For merchants who use QuickBooks for their accounting:

```
  Transactions settle in the database
       │
       ▼
  Merchant clicks "Export to QuickBooks" in portal
       │
       ▼
  API queues the export  ──▶  quickbooks_export_queue table
       │
       ▼
  QuickBooks OAuth2 connection (merchant authorizes once)
       │
       ▼
  API pushes transactions to QuickBooks Online via their API
       │
       ▼
  Merchant's QuickBooks automatically updated ✓
```

---

## Dashboard Data — How Analytics Are Calculated

```
  Merchant opens Dashboard
       │
       ▼
  Portal calls multiple endpoints simultaneously:
       │
       ├──▶ /revenue.php
       │         └── SUM of orders by payment type + date range
       │
       ├──▶ /dashboardbestselling.php
       │         └── TOP items ranked by total revenue
       │
       ├──▶ /dashboardtopmerchants.php
       │         └── Highest revenue merchants this period
       │
       └──▶ /dashboardtopmerchantsolapay.php
                 └── Same but for OlaPay card transactions only
       │
       ▼
  Portal combines results → renders charts and numbers
```

---

## Key Features Summary

| Feature | What It Does | Business Value |
|---------|-------------|---------------|
| **Order Recording** | Stores every sale from every terminal | Complete transaction history |
| **Payment Reconciliation** | Cleans and normalizes raw terminal data every hour | Accurate, duplicate-free reporting |
| **OlaPay Processing** | Handles card payment data with deduplication | No double-counted revenue |
| **Dashboard Analytics** | Pre-calculated top items, merchants, revenue | Instant insights for operators |
| **QuickBooks Export** | Syncs transactions to accounting software | Reduces manual bookkeeping |
| **Terminal Management** | Track and configure all POS devices | Control over the device fleet |
| **Customer Management** | Store profiles and loyalty points | Foundation for retention programs |
| **Subscription Billing** | Recurring payment schema | SaaS/membership revenue support |
| **Role-Based Access** | Admin / Agent / Vendor permission layers | Secure multi-tenant operations |

---

## Technology Snapshot _(For Technical Reference)_

| Component | Technology | Purpose |
|-----------|-----------|---------|
| Language | PHP 8.0+ | Server-side scripting |
| Database | MySQL | Primary data store |
| Auth | JWT tokens | Secure, stateless login sessions |
| Rate Limiting | Redis | Protect API from overload (30 req/sec) |
| Migrations | Phinx (77 files) | Controlled database schema changes |
| Accounting | QuickBooks v3 SDK | Third-party accounting integration |
| Testing | PHPUnit + Faker | Automated test suite |

---

## Known Risks & Technical Debt

These are existing issues the engineering team is aware of:

| Risk | Impact | Mitigation |
|------|--------|-----------|
| Hardcoded database credentials | Security vulnerability if code is exposed | Move to environment variables |
| Weak password hashing (SHA1) | Passwords breakable if DB is stolen | Upgrade to bcrypt in new Go API |
| No API versioning | Breaking changes affect all clients simultaneously | Solved in new Go API (v1/v2 prefixes) |
| Manual file backups in root | Confusing codebase, harder to maintain | Use proper git branches |
| Hourly reconciliation lag | Dashboard data can be up to 1 hour behind | Go API addresses with real-time sync |

---

## How This Fits Into the Bigger Picture

```
┌─────────────────────────────────────────────────────────────────┐
│                    OLAPORTAL ECOSYSTEM                          │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              olaportal (Web Dashboard)                  │   │
│  │         What merchants & agents see in browser          │   │
│  └──────────────────────┬──────────────────────────────────┘   │
│                         │ reads / writes data via HTTP         │
│           ┌─────────────┴─────────────┐                        │
│           ▼                           ▼                        │
│  ┌────────────────┐         ┌──────────────────────┐          │
│  │ THIS API (PHP) │         │  New Go API (v1)     │          │
│  │  Legacy engine │         │  Modern replacement  │          │
│  │  ~100 endpoints│         │  ~80+ endpoints      │          │
│  └────────┬───────┘         └──────────┬───────────┘          │
│           └──────────┬──────────────────┘                      │
│                      ▼                                          │
│           ┌──────────────────────┐                             │
│           │    MySQL Database    │  ← shared by both APIs      │
│           └──────────────────────┘                             │
│                                                                 │
│  External inputs:                                               │
│  POS Terminals ──▶  /json.php                                  │
│  OlaPay Terminals ─▶ /jsonOlaPay.php                           │
│  QuickBooks ◀──────  /quickbook.php (export)                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Architecture & Request Flow _(Technical Detail)_

```
Incoming HTTP Request
  │
  ▼
CORS check  (allow browser cross-origin requests)
  │
  ▼
JWT validation  (is the token valid and not expired?)
  │
  ├── FAIL → 401 Unauthorized
  │
  └── PASS
        │
        ▼
   Endpoint PHP file  (e.g. orders2.php)
        │
        ▼
   MySQL query via PDO
        │
        ▼
   JSON response → browser
```

### Deployment

| Environment | Script | URL |
|-------------|--------|-----|
| Production | `deploy_portal.sh` | `portal.olapay.us/api` |
| Staging | `deploy_staging.sh` | `portalstg.olapay.us/api` |
