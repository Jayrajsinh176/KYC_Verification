<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycService extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'short',
        'desc',
        'icon',
        'provider_cost',
        'price',
        'status',
    ];

    protected $casts = [
        'provider_cost' => 'float',
        'price' => 'float',
    ];
}

