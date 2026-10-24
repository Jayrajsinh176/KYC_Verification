<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Verification extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'retailer_id',
        'type',
        'doc',
        'name',
        'status',
        'amount',
        'response',
        'details',
        'channel',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function retailer()
    {
        return $this->belongsTo(Retailer::class, 'retailer_id', 'id');
    }
}

