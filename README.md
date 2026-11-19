# Pixelforge Studio — PromptEdit Senior Product Engineer Assessment

A **Laravel 13 + Vue 3 + Inertia.js v3 + TypeScript 6 + Tailwind CSS v4 + PostgreSQL** web application integrating with the Pixelforge AI Image-Generation API, built around exact sub-cent financial accounting, resilient webhook & concurrency handling, user credit balances, and live UTC usage reconciliation.

---

## Assessment Coverage Checklist

| Requirement | Status | Implementation Highlights |
|---|---|---|
| **Generating** | Implemented | Prompt, size, quality, optional reference image URLs (omitted when empty), and real-time pre-submit token & cost estimator (`BigInt` picodollars). |
| **Pricing (+25% markup)** | Implemented | Exact integer picodollars (`10^-12 USD`, zero floats), Unicode code-point token counting, snapshotted `rates`, actual post-completion settlement, `$0.00` on failed/rejected jobs. |
| **History** | Implemented | Live status (`submitting`, `queued_locally`, `queued`, `processing`, `succeeded`, `failed`), estimated vs actual cost, token breakdown popover, local timestamps with UTC hover tooltips, and rendered output images. |
| **Webhooks** | Implemented | Raw-body HMAC-SHA256 signature verification (`hash_equals`, 300s replay window), atomic `evt_...` deduplication (`insertOrIgnore`), early-webhook matching (`metadata.local_id`), monotonic state machine, plus 3s polling fallback. |
| **Stretch: Admin Report** | Implemented | `/admin/reports` (RBAC-protected via `EnsureUserIsAdmin`) showing credit spend, provider costs, and profit by UTC `completed_at` day and per user, reconciled live to 9 decimal places against `GET /v1/usage`. |
| **Stretch: Credit Balances** | Implemented | Atomic `SELECT ... FOR UPDATE` credit reservations before submit, idempotent settlement (`settled_at`), full hold release on failure/rejection, simulated **Buy Credits** top-up, and overdraft blocking. |

---

## Core Architecture & Design Choices

### 1. Exact Money Precision (Picodollars, Zero Floats)
- Pixelforge reports USD amounts to **9 decimal places** (nanodollars, `$0.000000001`). Applying a **25% markup** (`× 1.25`) extends precision up to 11 decimal places.
- All database columns (`BIGINT`) and backend/frontend calculations (`MoneyFormatter` with `bcmath` on PHP; `BigInt` in `resources/js/lib/pixelforgePricing.ts` on TypeScript) store and compute exact integer **picodollars** (`1 USD = 10^12 picodollars`).
- Prompt tokens are counted by **Unicode code points** (`mb_strlen(..., 'UTF-8')` in PHP and `[...prompt].length` in TypeScript), matching the Pixelforge API specification.
- Provider costs format identically to Pixelforge's 9-decimal strings (`$0.373875000`), and each generation snapshots its `rates` at creation time.
- Failed or synchronously rejected generations cost `$0.00`.

### 2. Webhook Security, Deduplication & Race-Condition Safety
- **Signature Verification (`PixelforgeSignatureVerifier`):** Validates `Pixelforge-Signature` (`t=<unix>,v1=<hmac_sha256>`) against the **raw HTTP body** using constant-time `hash_equals()` and a 300-second replay window. The `/webhooks/pixelforge` route is explicitly excluded from CSRF protection.
- **Event Deduplication (`ProcessPixelforgeWebhookAction`):** Claims each `evt_...` atomically via `insertOrIgnore` on `pixelforge_webhook_events.event_id` (`UNIQUE`). Duplicate deliveries (`[duplicate]`) return `204 No Content` with zero double-charging.
- **Early Webhook & Out-of-Order Protection (`SyncGenerationStateAction`):**
  - Pre-generates a local UUID `id` and reserves credits **before** calling `POST /v1/generations`, sending `metadata.local_id`.
  - If a webhook arrives before the HTTP `POST` returns, it matches via `pixelforge_id` **or** `metadata.local_id`.
  - Enforces a **monotonic state machine** (`submitting`/`queued_locally` → `queued` → `processing` → `succeeded`/`failed`) under `DB::transaction` + `lockForUpdate()`, so out-of-order `processing` webhooks never overwrite terminal states.
- **Polling Fallback (`PollInFlightGenerationsAction`):** Live 3-second polling (`GET /generations/status`) includes a 5-second per-item cooldown sync against `GET /v1/generations/{id}` when webhooks are delayed or unreachable.

### 3. Rate Limits, Concurrency & Synchronous Rejection Semantics
- **`Idempotency-Key`:** Every generation generates a stable UUID `idempotency_key` sent on `POST /v1/generations` and reused across retries.
- **Synchronous Rejections (`[blocked]`, `422`, `[overloaded]`):** Transient `503 overloaded` responses retry up to 2 times honoring `Retry-After` with the same `Idempotency-Key`. If rejected permanently (`400 content_policy_violation`, persistent `503`), the `submitting` placeholder and credit hold are completely rolled back (*"nothing created or billed"*).
- **Concurrency Limit (`429 concurrency_limit_exceeded`):** Transitions the request to `queued_locally` and dispatches `DispatchQueuedGenerationJob` with a `Retry-After` delay so bursts exceeding 3 concurrent jobs submit once a slot opens.

### 4. Stretch Goals Implemented
1. **User Credit Balances (`app/Actions/Credits/`):**
   - `PurchaseCreditsAction` (simulated top-ups + reviewer `"Set $0.00"` helper), `ReserveCreditsAction` (`SELECT ... FOR UPDATE` hold of max estimated cost before submission), `SettleGenerationCreditsAction` (idempotent settlement guarded by `settled_at`), and `ReleaseReservedCreditsAction`.
   - Full append-only audit ledger (`credit_transactions`) displayed on `/credits`.
2. **Admin Report & `GET /v1/usage` Reconciliation (`/admin/reports`):**
   - Aggregates succeeded generations strictly by the **UTC calendar date of `pixelforge_completed_at`** (matching Pixelforge's billing day boundary, with PostgreSQL session timezone forced to `UTC`) and compares local totals line-by-line against `GET /v1/usage`.

### 5. Security, RBAC, Rate Limiting & Resilient UX
- **Role-Based Access Control:** `/admin/reports` is protected server-side by `EnsureUserIsAdmin` (`403 Forbidden` for non-admins). Public `/register` always creates standard users (`is_admin = false`). Ownership checks prevent IDOR on generation sync endpoints.
- **Hardening Headers (`AddSecurityHeaders`):** `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Content-Security-Policy: frame-ancestors 'none'`, `Cross-Origin-Opener-Policy: same-origin`, `Referrer-Policy: strict-origin-when-cross-origin`, and `Permissions-Policy`.
- **Application Rate Limiting:** Named limiters for `auth`, `generations`, `generation-sync`, `credits`, and `webhooks`.
- **Custom Error Pages (`ErrorPageRenderer` + `Errors/ErrorPage.vue` + `errors::minimal`):** Branded dark-mode error pages for `401`, `403`, `404`, `419`, `429`, `500`, and `503` with session-aware CTA redirects (`Back to Studio` / `View Credits` when signed in via `Route::fallback`, or `Go to Sign In` / `Create Account` for guests), while preserving JSON responses for API/webhook routes.
- **Modular Frontend Architecture:** Global primitives in `resources/js/Components/{Layout,UI}/` and co-located view-scoped components in `resources/js/Pages/<Module>/Components/` (excluded from Inertia page resolution in `resources/js/app.ts`). Timestamps are stored in UTC and formatted in the viewer's local timezone with full UTC hover tooltips.

---

## Local Setup & Running

### Prerequisites
- PHP 8.4+ (`bcmath`, `pdo_pgsql`, `mbstring`, `openssl`, `curl`)
- Composer 2, Node.js 22+, npm, PostgreSQL 18 (or 15+)

### Installation
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure `.env` with your PostgreSQL credentials and Pixelforge keys:
```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pixelforge
DB_USERNAME=postgres
DB_PASSWORD=
DB_TIMEZONE=UTC

PIXELFORGE_BASE_URL=https://pixelforge.promptedit.dev/v1
PIXELFORGE_API_KEY=pf_...
PIXELFORGE_WEBHOOK_SECRET=whsec_...
```

Run migrations, seed the Demo Admin (`reviewer@promptedit.test`, `$5.00`) and Demo User (`user@promptedit.test`, `$2.00`) accounts, and build frontend assets:
```bash
php artisan migrate --seed
npm run build
```

Run the server and queue worker:
```bash
php artisan serve
php artisan queue:work --tries=1
```

For local webhook testing, expose port 8000 (e.g., `cloudflared tunnel --url http://127.0.0.1:8000`) and set `https://<your-tunnel>/webhooks/pixelforge` on your local Pixelforge API key.

### Deployment Notes
- Use a **separate Pixelforge API key** for your deployed environment so its webhook URL (`https://<deployed-domain>/webhooks/pixelforge`), signing secret, and `GET /v1/usage` ledger remain isolated from local development and reconcile 1:1.
- Ensure a queue worker (`php artisan queue:work --tries=1`) is running in production to process `429` concurrency-delayed jobs (`DispatchQueuedGenerationJob`).
- Set `APP_ENV=production`, `APP_DEBUG=false`, and `DB_TIMEZONE=UTC`.

---

## Reviewer Guide
1. Open the app URL and use the quick-access buttons on the Login page: **Demo Admin** (`reviewer@promptedit.test`, `is_admin = true`, `$5.00` credit) for full access including `/admin/reports`, or **Demo User** (`user@promptedit.test`, `is_admin = false`, `$2.00` credit) for standard user access. Accounts created via `/register` are always regular users (`is_admin = false`) and receive `403 Forbidden` on `/admin/reports`.
2. **Generate Studio (`/generations`):** Type a prompt (or click quick test keywords `[succeed]`, `[fail]`, `[slow]`, `[duplicate]`, `[blocked]`, `[overloaded]`), inspect the live token & cost estimator (`+25%` markup), and submit.
3. **Credits & Ledger (`/credits`):** Click **"+ Buy Credits"** in the navbar to add simulated funds or click **"Set $0.00 (Test Block)"** to verify overdraft blocking.
4. **Admin Report (`/admin/reports`):** View daily UTC breakdowns, per-user spend/profit, and live 9-decimal reconciliation against Pixelforge `GET /v1/usage`.

---

## Automated Verification
```bash
php artisan test        # 47 PHPUnit unit & feature tests (322 assertions, PostgreSQL)
npm test                # 5 Vitest unit tests (BigInt pricing, Unicode counts, date formatting)
npm run typecheck       # vue-tsc --noEmit
./vendor/bin/pint --test
```
