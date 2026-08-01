<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'mqtt_topic',
        'status',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'device_user_permissions')
            ->withPivot(['allowed_actions'])
            ->withTimestamps()
            ->using(DeviceUserPermission::class);
    }

    /**
     * @return HasMany<DeviceUserPermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(DeviceUserPermission::class);
    }

    /**
     * @return HasMany<DisplayLog, $this>
     */
    public function displayLogs(): HasMany
    {
        return $this->hasMany(DisplayLog::class);
    }
}
