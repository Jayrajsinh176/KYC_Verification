# VerifyHub B2B KYC Platform — Completed API Reference Report

> **Official Integration Guide & API Reference for Frontend Developers**  
> **Platform:** VerifyHub B2B KYC Verification System  
> **Base URL:** `http://127.0.0.1:8000/api` (Local) or your deployed staging URL / Ngrok tunnel  
> **Default Content Headers for All Requests:**
> ```http
> Accept: application/json
> Content-Type: application/json
> ```
> **Protected Endpoints:** Pass the Sanctum Bearer token received during login:
> ```http
> Authorization: Bearer <token>
> ```

---

## Complete API Route Index (54 Registered Endpoints)

| # | Group | Method | Endpoint Route | Used in Frontend Screen | Description |
|:---:|:---|:---:|:---|:---|:---|
| **1** | Auth | `POST` | `/api/auth/admin/login` | Admin Login Screen | Super Admin authentication |
| **2** | Auth | `POST` | `/api/auth/retailer/login` | Retailer Login Screen | Retailer login via Mobile/Email |
| **3** | Auth | `GET` | `/api/auth/me` | App Bootstrap / Header | Retrieve active session & live balance |
| **4** | Auth | `POST` | `/api/auth/logout` | Header / User Menu | Revoke current session token |
| **5** | KYC Engine | `POST` | `/api/verifications/verify` | Verification Modals | Run PAN / Aadhaar / Bank / e-Sign KYC |
| **6** | KYC Engine | `GET` | `/api/verifications/records` | Audit Trail / History | Search & filter verification logs |
| **7** | Retailer | `GET` | `/api/retailer/overview` | Retailer Dashboard | KPIs, balance float & recent transactions |
| **8** | Retailer | `GET` | `/api/retailer/profile` | Retailer Settings | View profile details |
| **9** | Retailer | `PUT` | `/api/retailer/profile` | Retailer Settings | Update profile details |
| **10** | Retailer | `PUT` | `/api/retailer/password` | Retailer Settings | Change retailer account password |
| **11** | Retailer | `POST` | `/api/retailer/logout-all` | Retailer Security | Revoke all active session tokens |
| **12** | Retailer | `POST` | `/api/retailer/fund-requests` | Retailer Wallet | Submit top-up request with UTR |
| **13** | Retailer | `GET` | `/api/retailer/records` | Retailer Verifications | Paginated verification history |
| **14** | Retailer | `GET` | `/api/retailer/verifications` | Retailer Verifications | Alias to verification records |
| **15** | Retailer | `GET` | `/api/retailer/history` | Retailer History | Alias to verification records |
| **16** | Wallet | `GET` | `/api/wallet/transactions` | Wallet Passbook | Paginated ledger credits & debits |
| **17** | Wallet | `GET` | `/api/wallet/fund-requests` | Wallet Top-Ups | List submitted fund requests |
| **18** | Wallet | `POST` | `/api/wallet/fund-requests` | Wallet Top-Ups | Submit new wallet top-up request |
| **19** | Wallet | `POST` | `/api/wallet/adjust` | Admin Wallet Panel | Manual credit / debit adjustment |
| **20** | Wallet | `PUT` | `/api/wallet/fund-requests/{id}/approve` | Admin Wallet | Approve fund request & credit float |
| **21** | Wallet | `PUT` | `/api/wallet/fund-requests/{id}/reject` | Admin Wallet | Reject fund request with reason |
| **22** | Admin | `GET` | `/api/admin/overview` | Admin Dashboard | Platform KPIs, success rates & charts |
| **23** | Admin | `GET` | `/api/admin/retailers` | Retailer Directory | List merchants with balance & usage |
| **24** | Admin | `POST` | `/api/admin/retailers` | Onboard Retailer Modal | Register new retailer account |
| **25** | Admin | `PUT` | `/api/admin/retailers/{id}` | Edit Retailer Modal | Update merchant profile & address |
| **26** | Admin | `PUT` | `/api/admin/retailers/{id}/status` | Retailer Row Actions | Toggle active / inactive status |
| **27** | Admin | `GET` | `/api/admin/services` | Service Switchboard | List KYC services, costs & status |
| **28** | Admin | `PUT` | `/api/admin/services/{id}` | Edit Service Modal | Update base price, cost, or pause |
| **29** | Admin | `GET` | `/api/admin/pricing` | Pricing Matrix | Rate cards, margins & overrides |
| **30** | Admin | `POST` | `/api/admin/pricing/defaults` | Pricing Settings | Save platform default rate card |
| **31** | Admin | `POST` | `/api/admin/pricing/override` | Rate Card Editor | Assign custom pricing to retailer |
| **32** | Admin | `DELETE` | `/api/admin/pricing/override/{id}` | Rate Card Editor | Reset retailer to platform default |
| **33** | Admin | `GET` | `/api/admin/wallet/overview` | Admin Wallet Overview | Total float, monthly volume & requests |
| **34** | Admin | `GET` | `/api/admin/wallet/transactions` | Admin Ledger View | Platform-wide financial ledger |
| **35** | Admin | `GET` | `/api/admin/wallet/fund-requests` | Admin Approvals | List pending merchant top-up proofs |
| **36** | Admin | `PUT` | `/api/admin/wallet/fund-requests/{id}/approve` | Admin Approvals | Approve fund request & credit float |
| **37** | Admin | `PUT` | `/api/admin/wallet/fund-requests/{id}/reject` | Admin Approvals | Reject invalid UTR submission |
| **38** | Admin | `POST` | `/api/admin/wallet/adjust` | Manual Adjustment Drawer | Direct balance credit / debit |
| **39** | Admin | `GET` | `/api/admin/records` | Admin Audit Trail | Platform-wide verification history |
| **40** | Admin | `GET` | `/api/admin/reports` | Analytics & Reports | Verification trends, margin & metrics |
| **41** | Admin | `GET` | `/api/admin/settings` | Platform Settings | Company info, system thresholds, ESPs |
| **42** | Admin | `PUT` | `/api/admin/settings/{section}` | Platform Settings | Update company, notification, or ESP |
| **43** | Admin | `PUT` | `/api/admin/profile` | Admin Profile | Update admin name, designation & phone |
| **44** | Admin | `PUT` | `/api/admin/password` | Admin Security | Change administrator password |
| **45** | Admin | `GET` | `/api/admin/audit-logs` | Security Logs | System audit log of admin actions |
| **46** | Admin | `POST` | `/api/admin/demo-data/reset` | Demo Reset Bar | Re-seed database to default state |
| **47** | Notifications | `GET` | `/api/notifications` | Top Navbar Bell | Unread notifications stream |
| **48** | Notifications | `PUT` | `/api/notifications/{id}/read` | Notification Dropdown | Mark individual alert as read |
| **49** | Notifications | `POST` | `/api/notifications/mark-all-read` | Notification Dropdown | Mark all alerts as read |
| **50** | Shared | `GET` | `/api/services` | Service Catalog | Public/shared list of active services |
| **51** | Shared | `GET` | `/api/pricing` | Shared Pricing | Public/shared pricing matrix |
| **52** | Shared | `GET` | `/api/retailers` | Merchant Fallback | Shared retailer directory |
| **53** | Shared | `POST` | `/api/retailers` | Merchant Fallback | Direct retailer registration |
| **54** | Shared | `PUT` | `/api/retailers/{id}/status` | Merchant Fallback | Direct status toggle |

---

## 1. Authentication & Session APIs

### 1. Super Admin Login
* **Where Used in Frontend:** Admin Login Screen (`/admin/login`)
* **Endpoint:** `POST /api/auth/admin/login`
* **Why used:** Authenticates administrator, checks admin role capabilities, records security audit trail, and returns Sanctum Bearer token.
* **Request format:**
```json
{
  "email": "admin@verifyhub.in",
  "password": "password123"
}
```
* **Response format (200 OK):**
```json
{
  "success": true,
  "message": "Super Admin authenticated successfully.",
  "data": {
    "token_type": "Bearer",
    "access_token": "1|adminAccessTokenExampleXYZ123456",
    "role": "admin",
    "user": {
      "id": 1,
      "name": "Super Administrator",
      "email": "admin@verifyhub.in",
      "role": "admin",
      "status": "active"
    }
  }
}
```

---

### 2. Retailer Merchant Login
* **Where Used in Frontend:** Retailer Login Screen (`/retailer/login`)
* **Endpoint:** `POST /api/auth/retailer/login`
* **Why used:** Authenticates retailer using registered **10-digit mobile number** OR **email address**.
* **Request format:**
```json
{
  "identifier": "9876543210",
  "password": "password123"
}
```
* **Response format (200 OK):**
```json
{
  "success": true,
  "message": "Retailer authenticated successfully.",
  "data": {
    "token_type": "Bearer",
    "access_token": "2|retailerTokenExampleABC987654",
    "role": "retailer",
    "user": {
      "id": "RT1001",
      "name": "Rajesh Sharma",
      "shop": "Sharma Communication",
      "email": "rajesh@sharmakyc.in",
      "mobile": "9876543210",
      "city": "Vadodara",
      "state": "Gujarat",
      "pan": "ABCDE1234F",
      "gst": "24ABCDE1234F1Z5",
      "status": "active",
      "wallet": 4850.00
    }
  }
}
```

---

### 3. Get Authenticated User Profile (`/me`)
* **Where Used in Frontend:** App Bootstrap / Reload / Header User Badge
* **Endpoint:** `GET /api/auth/me`
* **Why used:** Validates stored session token on page load, fetches updated role, permissions, and live wallet balance.
* **Headers:** `Authorization: Bearer <token>`
* **Response format (200 OK):**
```json
{
  "success": true,
  "message": "User profile retrieved successfully.",
  "data": {
    "id": "RT1001",
    "name": "Rajesh Sharma",
    "shop": "Sharma Communication",
    "email": "rajesh@sharmakyc.in",
    "mobile": "9876543210",
    "role": "retailer",
    "status": "active",
    "wallet": 4850.00
  }
}
```

---

### 4. Logout
* **Where Used in Frontend:** Navbar / Sidebar "Sign Out"
* **Endpoint:** `POST /api/auth/logout`
* **Why used:** Destroys active Sanctum Bearer token and records audit log.
* **Headers:** `Authorization: Bearer <token>`
* **Response format (200 OK):**
```json
{
  "success": true,
  "message": "Successfully logged out.",
  "data": null
}
```

---

## 2. KYC Verification Engine

### 5. Execute Instant Verification
* **Where Used in Frontend:** Retailer Portal → Verification Form Modal (PAN, Aadhaar, Bank, e-Sign)
* **Endpoint:** `POST /api/verifications/verify`
* **Why used:** Core verification engine. Automatically checks wallet balance, debits service cost, runs the check, and logs an immutable audit record.
* **Headers:** `Authorization: Bearer <token>`

#### Payload A: PAN Card Verification
```json
{
  "retailer_id": "RT1001",
  "service_type": "pan",
  "payload": {
    "pan": "ABCDE1234F",
    "name": "Priya Kapoor"
  }
}
```

#### Payload B: Aadhaar OTP Verification
```json
{
  "retailer_id": "RT1001",
  "service_type": "aadhaar",
  "payload": {
    "aadhaar": "987654321012",
    "name": "Amit Kumar Verma",
    "gender": "Male"
  }
}
```

#### Payload C: Bank Account Penny Drop (IMPS)
```json
{
  "retailer_id": "RT1001",
  "service_type": "bank",
  "payload": {
    "account": "12345678901",
    "ifsc": "HDFC0001234",
    "name": "Rahul Choudhary"
  }
}
```

#### Payload D: Consent Letter with e-Sign
```json
{
  "retailer_id": "RT1001",
  "service_type": "esign",
  "payload": {
    "name": "Priya Kapoor",
    "mobile": "9876543210",
    "purpose": "KYC verification for account opening"
  }
}
```

* **Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Verification processed successfully.",
  "data": {
    "id": "VRF261024A1B2C",
    "retailer_id": "RT1001",
    "type": "pan",
    "doc": "ABXXXXX34F",
    "name": "Priya Kapoor",
    "status": "verified",
    "amount": 2.25,
    "response": "200 · VALID · Record found",
    "details": {
      "Holder name": "Priya Kapoor",
      "Holder category": "Individual",
      "Aadhaar seeding": "Linked",
      "Name match": "Match · 100%",
      "Source": "Income Tax Department"
    },
    "channel": "Web dashboard",
    "created_at": "2026-10-24T10:15:00.000000Z"
  }
}
```

---

### 6. Filter & List Verification Records (Audit Trail)
* **Where Used in Frontend:** Admin Audit Trail (`/admin/records`) & Retailer History (`/retailer/history`)
* **Endpoint:** `GET /api/verifications/records`
* **Query Parameters:** `page`, `per_page`, `retailer_id`, `type`, `status`, `search`, `start_date`, `end_date`
* **Response format (200 OK):**
```json
{
  "success": true,
  "message": "Verification records retrieved successfully.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "VRF261024A1B2C",
        "retailer_id": "RT1001",
        "type": "pan",
        "doc": "ABXXXXX34F",
        "name": "Priya Kapoor",
        "status": "verified",
        "amount": "2.25",
        "response": "200 · VALID · Record found",
        "channel": "Web dashboard",
        "created_at": "2026-10-24T10:15:00.000000Z",
        "retailer": {
          "id": "RT1001",
          "shop": "Sharma Communication",
          "owner": "Rajesh Sharma"
        }
      }
    ],
    "total": 142,
    "per_page": 10,
    "last_page": 15,
    "stats": {
      "total": 142,
      "verified": 136,
      "failed": 6,
      "deducted": 354.50
    }
  }
}
```

---

## 3. Retailer Portal & Account Security

### 7. Retailer Overview
* **Where Used in Frontend:** Retailer Home Screen (`/retailer/dashboard`)
* **Endpoint:** `GET /api/retailer/overview`
* **Why used:** Provides wallet float, today's verifications count, success rate %, active service rate cards, and recent records.

### 8. Retailer Profile Management
* **Where Used in Frontend:** Retailer Settings (`/retailer/settings`)
* **Endpoints:**
  * Get Profile: `GET /api/retailer/profile`
  * Update Profile: `PUT /api/retailer/profile`
  * Change Password: `PUT /api/retailer/password`
  * Terminate All Sessions: `POST /api/retailer/logout-all`

---

## 4. Wallet, Fund Requests & Approvals

### 9. Retailer Top-Up Fund Request
* **Where Used in Frontend:** Retailer Wallet → "Request Wallet Top-Up" Modal
* **Endpoint:** `POST /api/wallet/fund-requests`
* **Request format:**
```json
{
  "amount": 2500,
  "mode": "UPI",
  "utr": "UTR61024889912",
  "transfer_date": "2026-10-24",
  "remarks": "GooglePay transfer for weekend verifications"
}
```

### 10. Admin Approve / Reject Fund Request
* **Where Used in Frontend:** Admin Wallet Approvals (`/admin/wallet`)
* **Endpoints:**
  * Approve: `PUT /api/admin/wallet/fund-requests/{id}/approve`
  * Reject: `PUT /api/admin/wallet/fund-requests/{id}/reject`
* **Reject Request Body:**
```json
{
  "reason": "UTR number does not match bank statement"
}
```

### 11. Admin Manual Wallet Adjustment
* **Where Used in Frontend:** Admin Portal → Manual Adjustment Drawer
* **Endpoint:** `POST /api/admin/wallet/adjust`
* **Request format:**
```json
{
  "retailer_id": "RT1001",
  "type": "credit",
  "amount": 1000,
  "mode": "NEFT",
  "ref": "BANK-REF-9921",
  "note": "Promotional bonus credit"
}
```

---

## 5. Super Admin Suite & Analytics

### 12. Admin Reports & Financial Analytics *(Newly Cataloged)*
* **Where Used in Frontend:** Admin Portal → Analytics & Reports (`/admin/reports`)
* **Endpoint:** `GET /api/admin/reports`
* **Query Parameters:** `start_date` (`YYYY-MM-DD`), `end_date` (`YYYY-MM-DD`)
* **Why used:** Comprehensive analytics showing date-range verification count, success vs. failure rates, provider costs, gross margins, and daily volume breakdown.
* **Response format (200 OK):**
```json
{
  "success": true,
  "message": "Reports analytics retrieved successfully.",
  "data": {
    "kpis": {
      "total_verifications": 1240,
      "verified_count": 1205,
      "failed_count": 35,
      "success_rate": 97.2,
      "billed_amount": 3840.50,
      "provider_cost": 2150.00,
      "gross_margin": 1690.50,
      "margin_pct": 44.0,
      "active_retailers": 24
    },
    "daily_records": [],
    "service_breakdown": [],
    "retailer_leaderboard": []
  }
}
```

---

### 13. Platform Settings & Configurations *(Newly Cataloged)*
* **Where Used in Frontend:** Admin Portal → System Settings (`/admin/settings`)
* **Endpoints:**
  * Get Settings: `GET /api/admin/settings`
  * Update Settings Section: `PUT /api/admin/settings/{section}` (`company`, `notifications`, `system`, or `providers`)
* **Update Section Request format:**
```json
{
  "data": {
    "low_balance_threshold": 250,
    "min_topup": 500,
    "session_timeout": 30,
    "masking_level": "strict",
    "maintenance_mode": false
  }
}
```

---

### 14. Admin Profile, Password & Audit Logs *(Newly Cataloged)*
* **Where Used in Frontend:** Admin Settings → Profile & Security Tab
* **Endpoints:**
  * Update Profile: `PUT /api/admin/profile`
    ```json
    {
      "name": "Super Administrator",
      "designation": "Head of Compliance",
      "mobile": "9876500001"
    }
    ```
  * Change Password: `PUT /api/admin/password`
    ```json
    {
      "current_password": "OldAdminPassword123",
      "new_password": "NewSecurePassword456",
      "confirm_password": "NewSecurePassword456"
    }
    ```
  * System Audit Logs: `GET /api/admin/audit-logs?page=1&search=LOGIN`
  * Reset Demo Database: `POST /api/admin/demo-data/reset`
