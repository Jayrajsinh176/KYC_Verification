# VerifyHub Backend — Laravel 12 REST API

The robust financial and KYC verification backend for the VerifyHub B2B platform, built with **Laravel 12**, **PHP 8.2**, **Laravel Sanctum**, and a concurrency-safe double-entry wallet engine.

---

## 🚀 Quick Start

From the `backend/` directory:

```powershell
cd backend
php artisan serve --port=8000
```

The API will be available at: **`http://localhost:8000/api`**

---

## 🗄️ Database & Models

* **Database Engine**: SQLite (`database/database.sqlite`), zero configuration needed.
* **Migrations**: Run with `php artisan migrate`
* **Seeders**: Seed initial demo retailers, wallets, and KYC services with:
  ```powershell
  php artisan db:seed
  ```

### Models & Schema:
1. **`User`**: Super Administrators with Sanctum token authentication.
2. **`Retailer`**: Merchant service points (shop title, owner, mobile, email, PAN, GSTIN).
3. **`KycService`**: Verification switchboard (Aadhaar, PAN, Bank Penny Drop, e-Sign) with base costs and default retail pricing.
4. **`RetailerPricing`**: Custom per-retailer pricing override matrices.
5. **`Wallet`**: Prepaid balance with double-entry accounting.
6. **`WalletTransaction`**: Immutable audit ledger recording credits and debits with `balance_after`.
7. **`FundRequest`**: Retailer wallet top-up requests (UPI, IMPS, NEFT) with admin approve/reject actions.
8. **`Verification`**: Immutable verification log storing masked identifiers, status, and returned gateway metadata.
9. **`AuditLog`**: System and user action trails.

---

## 🔒 Financial Wallet Engine (`App\Services\WalletService`)

The wallet engine executes atomic transactions with database row locking (`lockForUpdate()`):
* **Credit**: Automatically increments balance, assigns reference ID, and writes a ledger transaction.
* **Debit**: Checks that `balance >= amount`, rejects with an `InsufficientBalanceException` if low, and deducts atomically.

---

## 📡 Registered API Endpoints

### Authentication
* `POST /api/auth/admin/login`: Super Admin login (returns token)
* `POST /api/auth/retailer/login`: Retailer outlet login (returns token)
* `POST /api/auth/logout`: Invalidate current token
* `GET  /api/auth/me`: Authenticated user profile

### KYC Services & Pricing Switchboard
* `GET  /api/services`: List all services with pricing & costs
* `PUT  /api/services/{id}`: Toggle status (active/paused) or adjust prices
* `GET  /api/pricing`: Matrix of all custom pricing overrides
* `POST /api/pricing/override`: Set custom per-retailer pricing
* `DELETE /api/pricing/override/{retailerId}`: Reset overrides to default

### Retailers Management
* `GET  /api/retailers`: List all retailers with live wallet balances
* `POST /api/retailers`: Onboard a new retailer with zero-balance wallet
* `PUT  /api/retailers/{id}/status`: Toggle retailer active/inactive

### Wallet Operations
* `GET  /api/wallet/transactions`: Filterable transaction ledger
* `POST /api/wallet/adjust`: Super Admin manual credit/debit
* `GET  /api/wallet/fund-requests`: List pending/approved top-up requests
* `POST /api/wallet/fund-requests`: Retailer requests fund addition
* `PUT  /api/wallet/fund-requests/{id}/approve`: Super Admin approves & credits wallet
* `PUT  /api/wallet/fund-requests/{id}/reject`: Super Admin rejects request

### KYC Verification Engine
* `POST /api/verifications/verify`: Executes balance check, debits wallet, runs check, and stores record
* `GET  /api/verifications/records`: Filterable verification records audit
