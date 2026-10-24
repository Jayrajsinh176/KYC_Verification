<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'audience',
        'type',
        'title',
        'text',
        'read',
        'link',
        'created_at',
    ];

    protected $casts = [
        'read' => 'boolean',
        'created_at' => 'datetime',
    ];
}

