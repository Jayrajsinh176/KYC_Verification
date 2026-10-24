<?php

namespace App\Services;

use App\Models\Retailer;
use App\Models\KycService;
use App\Models\Verification;
use Exception;

class KycVerificationService
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Execute a KYC check for a retailer.
     */
    public function verify(string $retailerId, string $serviceType, array $payload)
    {
        $retailer = Retailer::findOrFail($retailerId);
        if ($retailer->status !== 'active') {
            throw new Exception("Retailer account is inactive. Please contact support.");
        }

        $service = KycService::findOrFail($serviceType);
        if ($service->status !== 'active') {
            throw new Exception("Service {$service->name} is currently paused by platform administrator.");
        }

        $cost = $retailer->getPriceForService($serviceType);
        $reqId = 'VRF' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        // 1. Debit wallet atomically
        $this->walletService->debit(
            retailerId: $retailerId,
            amount: $cost,
            note: "{$service->short} verification charge",
            by: 'System',
            ref: $reqId
        );

        // 2. Perform verification (Sandbox / Mock Gateway Adapter)
        $result = $this->simulateGatewayCheck($serviceType, $payload, $reqId);

        // 3. Record verification in immutable database audit
        $record = Verification::create([
            'id' => $reqId,
            'retailer_id' => $retailerId,
            'type' => $serviceType,
            'doc' => $result['masked_doc'],
            'name' => $result['name'],
            'status' => $result['status'],
            'amount' => $cost,
            'response' => $result['response'],
            'details' => $result['details'],
            'channel' => 'Web dashboard',
            'created_at' => now(),
        ]);

        return $record;
    }

    protected function simulateGatewayCheck(string $type, array $payload, string $reqId): array
    {
        $isSuccess = true; // In production this calls Karza / Cashfree / Signzy / UIDAI API

        if ($type === 'pan') {
            $pan = strtoupper(trim($payload['pan'] ?? 'ABCDE1234F'));
            $maskedDoc = strlen($pan) >= 10 ? substr($pan, 0, 2) . 'XXXXX' . substr($pan, 7) : $pan;
            $name = $payload['name'] ?? 'Priya Kapoor';

            if ($pan === 'AAXXXXX99Z' || str_ends_with($pan, '99Z') || !empty($payload['simulate_fail'])) {
                return [
                    'masked_doc' => 'AAXXXXX99Z',
                    'name' => 'Unknown Entity',
                    'status' => 'failed',
                    'response' => '404 · NOT_FOUND · No record',
                    'details' => [
                        'Reason' => 'No record matching input',
                        'Source' => 'Income Tax Department (Sandbox)',
                    ],
                ];
            }

            return [
                'masked_doc' => $maskedDoc,
                'name' => $name,
                'status' => 'verified',
                'response' => '200 · VALID · Record found',
                'details' => [
                    'Holder name' => $name,
                    'Holder category' => 'Individual',
                    'Aadhaar seeding' => 'Not linked',
                    'Name match' => 'Match · 100%',
                    'Source' => 'Income Tax Department (Sandbox)',
                ],
            ];
        }

        if ($type === 'aadhaar') {
            $aadhaar = preg_replace('/\s+/', '', $payload['aadhaar'] ?? '987654321012');
            $maskedDoc = 'XXXX XXXX ' . substr($aadhaar, -4);
            $name = $payload['name'] ?? 'Amit Kumar Verma';

            if ($aadhaar === '000000000000' || str_ends_with($aadhaar, '0000') || !empty($payload['simulate_fail'])) {
                return [
                    'masked_doc' => 'XXXX XXXX 0000',
                    'name' => 'Unknown Person',
                    'status' => 'failed',
                    'response' => '404 · NOT_FOUND · UIDAI record inactive or mobile not linked',
                    'details' => [
                        'Reason' => 'Aadhaar number not found in UIDAI database',
                        'Auth method' => 'UIDAI Aadhaar OTP (Demo ESP)',
                        'Status' => 'Failed',
                    ],
                ];
            }

            return [
                'masked_doc' => $maskedDoc,
                'name' => $name,
                'status' => 'verified',
                'response' => '200 · VERIFIED · UIDAI Aadhaar OTP authenticated',
                'details' => [
                    'Full name' => $name,
                    'Gender' => $payload['gender'] ?? 'Male',
                    'Year of birth' => '1992',
                    'State' => 'Gujarat',
                    'Auth method' => 'UIDAI Aadhaar OTP (Demo ESP)',
                ],
            ];
        }

        if ($type === 'bank') {
            $account = preg_replace('/\s+/', '', $payload['account'] ?? '12345678901');
            $maskedDoc = 'XXXXXX' . (strlen($account) >= 4 ? substr($account, -4) : '0000');
            $name = $payload['name'] ?? 'Rahul Choudhary';
            $ifsc = strtoupper(trim($payload['ifsc'] ?? 'HDFC0001234'));

            $bankPrefix = substr($ifsc, 0, 4);
            $bankMap = [
                'SBIN' => 'State Bank of India',
                'HDFC' => 'HDFC Bank',
                'ICIC' => 'ICICI Bank',
                'UTIB' => 'Axis Bank',
                'PUNB' => 'Punjab National Bank',
                'BARB' => 'Bank of Baroda',
                'KKBK' => 'Kotak Mahindra Bank',
                'CNRB' => 'Canara Bank',
                'UBIN' => 'Union Bank of India',
                'IDFB' => 'IDFC FIRST Bank',
                'YESB' => 'Yes Bank',
                'INDB' => 'IndusInd Bank',
            ];
            $bankName = $payload['bank_name'] ?? ($bankMap[$bankPrefix] ?? 'State Bank of India');

            if ($account === '123456780000' || str_ends_with($account, '0000') || !empty($payload['simulate_fail'])) {
                return [
                    'masked_doc' => $maskedDoc,
                    'name' => 'Unknown Account',
                    'status' => 'failed',
                    'response' => '404 · ACCOUNT_NOT_FOUND · ₹1 credit failed',
                    'details' => [
                        'Bank name' => $bankName,
                        'IFSC' => $ifsc,
                        'Penny drop' => 'Failed · ₹1 not credited',
                        'Account status' => 'Not found',
                        'Status' => 'Failed',
                    ],
                ];
            }

            return [
                'masked_doc' => $maskedDoc,
                'name' => $name,
                'status' => 'verified',
                'response' => '200 · SUCCESS · ₹1.00 credited via IMPS · Beneficiary returned',
                'details' => [
                    'Beneficiary name' => $name,
                    'Bank name' => $bankName,
                    'IFSC' => $ifsc,
                    'Penny drop status' => '₹1.00 IMPS credit confirmed',
                    'Name match' => 'Match · 100%',
                    'IMPS RRN' => '6' . rand(10000000000, 99999999999),
                ],
            ];
        }

        // e-Sign
        $name = $payload['name'] ?? 'Priya Kapoor';
        $mobile = preg_replace('/\D/', '', $payload['mobile'] ?? '9876543210');
        $maskedDoc = 'CL-' . substr($reqId, 3);
        $maskedMobile = strlen($mobile) >= 10 ? substr($mobile, 0, 2) . 'XXXXXX' . substr($mobile, -2) : '98XXXXXX10';
        $purpose = $payload['purpose'] ?? 'KYC verification for account opening';

        if (!empty($payload['simulate_fail'])) {
            return [
                'masked_doc' => $maskedDoc,
                'name' => $name,
                'status' => 'failed',
                'response' => '404 · SIGN_FAILED · Signature not collected or OTP expired',
                'details' => [
                    'Document ID' => $maskedDoc,
                    'Document' => 'Customer consent letter',
                    'Reason' => 'Aadhaar OTP expired or rejected by signer',
                    'Sign method' => 'Aadhaar OTP e-Sign (licensed ESP - sandbox)',
                    'Status' => 'Failed',
                ],
            ];
        }

        return [
            'masked_doc' => $maskedDoc,
            'name' => $name,
            'status' => 'verified',
            'response' => '200 · SIGNED · Document e-signed',
            'details' => [
                'Document ID' => $maskedDoc,
                'Document' => 'Customer consent letter',
                'Signer' => strtoupper($name),
                'Signer mobile' => $maskedMobile,
                'Purpose' => $purpose,
                'Sign method' => 'Aadhaar OTP e-Sign (licensed ESP - sandbox)',
                'Certificate' => 'Demo ESP CA 2026 · SHA-256',
                'Document hash' => strtoupper(substr(hash('sha256', $reqId . $name), 0, 16)),
                'Status' => 'Verified',
            ],
        ];
    }
}

