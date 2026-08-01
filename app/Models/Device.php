<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Device extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceFactory> */
    use HasFactory;

    public const ONLINE_THRESHOLD_SECONDS = 120;

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

    public function isOnline(?Carbon $now = null): bool
    {
        if ($this->last_seen_at === null) {
            return false;
        }

        $now ??= now();

        return $this->last_seen_at->greaterThan(
            $now->copy()->subSeconds(self::ONLINE_THRESHOLD_SECONDS)
        );
    }

    /**
     * online | offline | never_seen
     */
    public function connectivityLabel(): string
    {
        if ($this->last_seen_at === null) {
            return 'never_seen';
        }

        return $this->isOnline() ? 'online' : 'offline';
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
