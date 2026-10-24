<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Retailer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'shop',
        'owner',
        'city',
        'state',
        'mobile',
        'email',
        'password',
        'address',
        'pan',
        'gst',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'retailer_id', 'id');
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class, 'retailer_id', 'id');
    }

    public function verifications()
    {
        return $this->hasMany(Verification::class, 'retailer_id', 'id');
    }

    public function pricingOverrides()
    {
        return $this->hasMany(RetailerPricing::class, 'retailer_id', 'id');
    }

    public function fundRequests()
    {
        return $this->hasMany(FundRequest::class, 'retailer_id', 'id');
    }

    public function getPriceForService(string $serviceId): float
    {
        $override = $this->pricingOverrides()->where('service_id', $serviceId)->first();
        if ($override) {
            return (float) $override->price;
        }

        $service = KycService::find($serviceId);
        return $service ? (float) $service->price : 20.00;
    }
}

