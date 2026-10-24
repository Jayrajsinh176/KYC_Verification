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

## Table of Contents
1. [Authentication & Session APIs](#1-authentication--session-apis)
2. [KYC Verification Services (Core Engine)](#2-kyc-verification-services-core-engine)
3. [Retailer Portal & Dashboard APIs](#3-retailer-portal--dashboard-apis)
4. [Wallet & Fund Request APIs](#4-wallet--fund-request-apis)
5. [Super Admin Management APIs](#5-super-admin-management-apis)
6. [Notifications & Platform Settings APIs](#6-notifications--platform-settings-apis)

---

## 1. Authentication & Session APIs

### 1. Super Admin Login
* **Where Used in Frontend:** Admin Login Screen (`/login/admin` or Admin Login Tab)
* **Endpoint:** `POST /api/auth/admin/login`
* **Why used:** Authenticates platform administrators, verifies admin role privileges, and issues a Sanctum Bearer token.
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
* **Where Used in Frontend:** Retailer Portal Login Screen (`/login/retailer` or Retailer Login Tab)
* **Endpoint:** `POST /api/auth/retailer/login`
* **Why used:** Allows registered merchants/retailers to log in using either their registered **Email** or **10-digit Mobile number**.
* **Request format:**
```json
{
  "identifier": "9876543210", 
  "password": "password123"
}
```
*(Note: `identifier` accepts either mobile number e.g. `9876543210` or email e.g. `merchant@shop.com`)*
* **Response format (200 OK):**
```json
{
  "success": true,
  "message": "Retailer authenticated successfully.",
  "data": {
    "token_type": "Bearer",
    "access_token": "2|retailerTokenExampleABC987654",
    "token": "2|retailerTokenExampleABC987654",
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

### 3. Get Current Authenticated Profile (`/me`)
* **Where Used in Frontend:** App Startup / Auth Provider / Header User Badge
* **Endpoint:** `GET /api/auth/me`
* **Why used:** Verifies the stored Bearer token on app reload and returns the current user profile (Admin or Retailer) with live wallet balance.
* **Headers:** `Authorization: Bearer <token>`
* **Request format:** No body
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
* **Where Used in Frontend:** Header / Sidebar "Sign Out" button
* **Endpoint:** `POST /api/auth/logout`
* **Why used:** Destroys the current Sanctum access token and records an audit log.
* **Headers:** `Authorization: Bearer <token>`
* **Request format:** No body
* **Response format (200 OK):**
```json
{
  "success": true,
  "message": "Successfully logged out.",
  "data": null
}
```

---

## 2. KYC Verification Services (Core Engine)

### 5. Execute Instant Verification
* **Where Used in Frontend:** Retailer Dashboard → Verification Form Modal (PAN, Aadhaar OTP, Bank Penny Drop, e-Sign)
* **Endpoint:** `POST /api/verifications/verify`
* **Why used:** The primary engine that verifies customer identity. It atomically deducts the service fee from the retailer's wallet, runs the check, and logs an immutable audit record.
* **Headers:** `Authorization: Bearer <token>`

#### A. PAN Card Verification Request:
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

#### B. Aadhaar OTP Verification Request:
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

#### C. Bank Account Penny Drop Request:
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

#### D. Consent Letter with e-Sign Request:
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
* **Error Response when Insufficient Balance (422 Unprocessable):**
```json
{
  "success": false,
  "message": "Insufficient wallet balance. Please top up your wallet to proceed."
}
```

---

### 6. Filter & List Verification Records (Audit Trail)
* **Where Used in Frontend:** 
  * Admin Panel → Verification Audit Trail (`/admin/records`)
  * Retailer Portal → Verification History (`/retailer/history` or `/retailer/verifications`)
* **Endpoint:** `GET /api/verifications/records` (or `GET /api/admin/records` / `GET /api/retailer/records`)
* **Why used:** Displays paginated verification history with live search, date filters, status breakdown (`verified`, `failed`), and stats summary.
* **Query Parameters (All optional):**
  * `page` (integer, e.g. `1`)
  * `per_page` (integer, default: `12`)
  * `retailer_id` (e.g. `RT1001` or `all`)
  * `type` (`pan`, `aadhaar`, `bank`, `esign`, or `all`)
  * `status` (`verified`, `failed`, or `all`)
  * `search` (Search by Verification ID, Masked Doc, Customer Name, or Shop Name)
  * `start_date` (`YYYY-MM-DD`)
  * `end_date` (`YYYY-MM-DD`)
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
    "per_page": 12,
    "last_page": 12,
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

## 3. Retailer Portal & Dashboard APIs

### 7. Retailer Dashboard Overview
* **Where Used in Frontend:** Retailer Home Screen (`/retailer/dashboard`)
* **Endpoint:** `GET /api/retailer/overview`
* **Why used:** Fetches live wallet float, today's verification counts, weekly chart analytics, active services catalog with the retailer's custom pricing, and recent verification history.
* **Headers:** `Authorization: Bearer <token>`
* **Request format:** No body
* **Response format (200 OK):**
```json
{
  "success": true,
  "message": "Retailer overview retrieved successfully.",
  "data": {
    "retailer": {
      "id": "RT1001",
      "shop": "Sharma Communication",
      "owner": "Rajesh Sharma",
      "email": "rajesh@sharmakyc.in",
      "mobile": "9876543210",
      "city": "Vadodara",
      "status": "active"
    },
    "wallet": {
      "balance": 4850.00,
      "last_topup_amount": 2000.00,
      "pending_request_amount": 0.00,
      "pending_request_count": 0
    },
    "kpis": {
      "total_verifications": 142,
      "verified_count": 136,
      "failed_count": 6,
      "success_rate": 95.8,
      "todays_verifications": 14,
      "todays_spent": 38.50,
      "total_spent": 354.50
    },
    "services": [
      {
        "id": "aadhaar",
        "name": "Aadhaar Verification (OTP)",
        "price": 3.50,
        "status": "active"
      },
      {
        "id": "pan",
        "name": "PAN Card Verification",
        "price": 2.25,
        "status": "active"
      },
      {
        "id": "bank",
        "name": "Bank Penny Drop",
        "price": 2.50,
        "status": "active"
      },
      {
        "id": "esign",
        "name": "Consent e-Sign",
        "price": 6.00,
        "status": "active"
      }
    ],
    "recent_verifications": []
  }
}
```

---

### 8. Retailer Profile & Security Settings
* **Where Used in Frontend:** Retailer Settings & Profile Page (`/retailer/settings`)
* **Endpoints:**
  * Get Profile: `GET /api/retailer/profile`
  * Update Profile: `PUT /api/retailer/profile`
  * Change Password: `PUT /api/retailer/password`
* **Update Profile Request:**
```json
{
  "shop": "Sharma Communication & Cyber Cafe",
  "owner": "Rajesh K. Sharma",
  "address": "Shop 4, Near Station, Vadodara",
  "city": "Vadodara",
  "state": "Gujarat"
}
```
* **Change Password Request:**
```json
{
  "current_password": "oldPassword123",
  "new_password": "NewSecurePassword456",
  "new_password_confirmation": "NewSecurePassword456"
}
```

---

## 4. Wallet & Fund Request APIs

### 9. Retailer Top-Up Fund Request
* **Where Used in Frontend:** Retailer Wallet → "Request Wallet Top-Up" Modal
* **Endpoint:** `POST /api/wallet/fund-requests`
* **Why used:** Retailers upload payment proof (UTR / Transaction Reference number and mode) after transferring funds to the company bank account/UPI.
* **Headers:** `Authorization: Bearer <token>`
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
*(Valid modes: `UPI`, `NEFT`, `IMPS`, `RTGS`. Minimum amount: ₹500).*
* **Response format (201 Created):**
```json
{
  "success": true,
  "message": "Fund request of ₹2,500 submitted successfully.",
  "data": {
    "id": "FRQ261024K9L2P",
    "retailer_id": "RT1001",
    "amount": "2500.00",
    "mode": "UPI",
    "utr": "UTR61024889912",
    "status": "pending",
    "created_at": "2026-10-24T11:00:00.000000Z"
  }
}
```

---

### 10. Wallet Transactions (Ledger)
* **Where Used in Frontend:** 
  * Retailer Portal → Wallet Passbook (`/retailer/wallet`)
  * Admin Portal → Wallet Ledger (`/admin/wallet`)
* **Endpoint:** `GET /api/wallet/transactions`
* **Query Parameters:** `page`, `per_page`, `retailer_id`, `type` (`credit` or `debit`), `search`, `start_date`, `end_date`
* **Response format (200 OK):**
```json
{
  "success": true,
  "message": "Wallet transactions retrieved successfully.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "TXN261024001",
        "retailer_id": "RT1001",
        "type": "credit",
        "amount": "2500.00",
        "balance_before": "2350.00",
        "balance_after": "4850.00",
        "note": "Top-up approved by Super Admin",
        "ref": "FRQ261024K9L2P",
        "mode": "UPI",
        "created_at": "2026-10-24T11:05:00.000000Z"
      },
      {
        "id": "TXN261024002",
        "retailer_id": "RT1001",
        "type": "debit",
        "amount": "2.25",
        "balance_before": "4850.00",
        "balance_after": "4847.75",
        "note": "PAN verification charge",
        "ref": "VRF261024A1B2C",
        "mode": "Auto Debit",
        "created_at": "2026-10-24T11:10:00.000000Z"
      }
    ]
  }
}
```

---

### 11. Admin Approve / Reject Fund Request
* **Where Used in Frontend:** Admin Portal → Wallet Approvals Tab (`/admin/wallet`)
* **Endpoints:**
  * Approve: `PUT /api/admin/wallet/fund-requests/{id}/approve`
  * Reject: `PUT /api/admin/wallet/fund-requests/{id}/reject`
* **Headers:** `Authorization: Bearer <token>`
* **Approve Request format:** No body (or optional note)
* **Reject Request format:**
```json
{
  "reason": "UTR number does not match bank statement"
}
```

---

### 12. Admin Manual Wallet Adjustment
* **Where Used in Frontend:** Admin Portal → "Manual Credit / Debit" Drawer
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

## 5. Super Admin Management APIs

### 13. Admin Dashboard Overview & Analytics
* **Where Used in Frontend:** Admin Home Screen (`/admin/dashboard`)
* **Endpoint:** `GET /api/admin/overview`
* **Why used:** Provides real-time metrics across all merchants: Total retailers, verifications count, network success rate, total platform revenue, total floating wallet balance, and service-wise breakdown chart data.
* **Response format (200 OK):**
```json
{
  "success": true,
  "message": "Admin dashboard overview retrieved successfully.",
  "data": {
    "kpis": {
      "total_retailers": 28,
      "active_retailers": 26,
      "total_verifications": 4820,
      "success_rate": 97.4,
      "total_revenue": 14850.75,
      "total_floating_wallet": 62400.00,
      "todays_verifications": 182,
      "todays_billed": 560.25
    },
    "category_distribution": {
      "total": 4820,
      "items": [
        { "id": "aadhaar", "name": "Aadhaar OTP", "count": 2100, "percentage": 43.6, "color": "#3b82f6" },
        { "id": "pan", "name": "PAN Verification", "count": 1850, "percentage": 38.4, "color": "#10b981" },
        { "id": "bank", "name": "Bank Penny Drop", "count": 620, "percentage": 12.9, "color": "#06b6d4" },
        { "id": "esign", "name": "e-Sign Consent", "count": 250, "percentage": 5.1, "color": "#8b5cf6" }
      ]
    }
  }
}
```

---

### 14. Retailers Management (CRUD)
* **Where Used in Frontend:** Admin Portal → Retailers List (`/admin/retailers`)
* **Endpoints:**
  * **List Retailers:** `GET /api/admin/retailers`
  * **Create New Retailer:** `POST /api/admin/retailers`
  * **Update Retailer:** `PUT /api/admin/retailers/{id}`
  * **Toggle Active / Inactive:** `PUT /api/admin/retailers/{id}/status`
* **Create Retailer Request:**
```json
{
  "shop": "National Digital Services",
  "owner": "Vikram Mehta",
  "mobile": "9825012345",
  "email": "vikram@nationaldigital.in",
  "address": "101 City Center, Station Road",
  "city": "Ahmedabad",
  "state": "Gujarat",
  "pan": "ABCDE1234F",
  "gst": "24ABCDE1234F1Z5",
  "password": "TemporaryPassword123",
  "status": "active"
}
```

---

### 15. Pricing Switchboard & Custom Rate Cards
* **Where Used in Frontend:** Admin Portal → Pricing & Rates (`/admin/pricing`)
* **Endpoints:**
  * **Get Matrix:** `GET /api/admin/pricing` (shows standard pricing, margins, and custom retailer rate overrides)
  * **Update Default Platform Rates:** `POST /api/admin/pricing/defaults`
  * **Set Custom Pricing for Retailer:** `POST /api/admin/pricing/override`
  * **Reset Retailer to Defaults:** `DELETE /api/admin/pricing/override/{retailerId}`
* **Set Custom Retailer Price Request:**
```json
{
  "retailer_id": "RT1001",
  "pricing": {
    "pan": 2.00,
    "aadhaar": 3.00,
    "bank": 2.20,
    "esign": 5.50
  }
}
```

---

## 6. Notifications & Platform Settings APIs

### 16. In-App Notifications
* **Where Used in Frontend:** Top Navbar Notification Bell Icon
* **Endpoints:**
  * **Get Notifications:** `GET /api/notifications`
  * **Mark as Read:** `PUT /api/notifications/{id}/read`
  * **Mark All Read:** `POST /api/notifications/mark-all-read`
* **Response format (200 OK):**
```json
{
  "success": true,
  "data": {
    "unread_count": 2,
    "notifications": [
      {
        "id": "NTF_FRQ_102",
        "title": "New fund request",
        "text": "Sharma Communication requested a ₹2,500 wallet top-up.",
        "type": "info",
        "read": false,
        "link": "/admin/wallet",
        "created_at": "2026-10-24T11:00:00.000000Z"
      }
    ]
  }
}
```

---

## 7. Standard HTTP Error Codes

| Status Code | Reason | What Frontend Should Display |
| :--- | :--- | :--- |
| `200 OK` | Request succeeded | Show data / Success toast |
| `201 Created` | Resource created (e.g., Fund request, Retailer) | Show success modal / redirect |
| `401 Unauthorized` | Invalid password or expired Bearer token | Redirect to `/login` |
| `403 Forbidden` | Inactive account or unauthorized role | Show "Access Restricted" alert |
| `422 Unprocessable` | Validation failed or Insufficient wallet balance | Display validation errors under form fields |
| `500 Server Error` | Unexpected backend or third-party gateway error | Show "Service temporarily unavailable" |

