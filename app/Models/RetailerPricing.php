<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetailerPricing extends Model
{
    protected $table = 'retailer_pricing';

    protected $fillable = [
        'retailer_id',
        'service_id',
        'price',
    ];

    protected $casts = [
        'price' => 'float',
    ];

    public function retailer()
    {
        return $this->belongsTo(Retailer::class, 'retailer_id', 'id');
    }

    public function service()
    {
        return $this->belongsTo(KycService::class, 'service_id', 'id');
    }
}

