<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'actor',
        'action',
        'target',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Record a system/user audit event.
     */
    public static function record(string $actor, string $action, ?string $target = null, ?string $ip = null): self
    {
        return self::create([
            'id' => 'AUD' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
            'actor' => $actor,
            'action' => $action,
            'target' => $target,
            'ip' => $ip,
            'created_at' => now(),
        ]);
    }
}

