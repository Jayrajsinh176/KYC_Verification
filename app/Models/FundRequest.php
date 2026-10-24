<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundRequest extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'retailer_id',
        'amount',
        'mode',
        'utr',
        'remarks',
        'status',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function retailer()
    {
        return $this->belongsTo(Retailer::class, 'retailer_id', 'id');
    }
}

