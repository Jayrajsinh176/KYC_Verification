<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'retailer_id',
        'type',
        'amount',
        'balance_after',
        'ref',
        'mode',
        'note',
        'status',
        'by',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'balance_after' => 'float',
        'created_at' => 'datetime',
    ];

    public function retailer()
    {
        return $this->belongsTo(Retailer::class, 'retailer_id', 'id');
    }
}

