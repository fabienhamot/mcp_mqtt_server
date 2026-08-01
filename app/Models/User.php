<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements FilamentUser, OAuthenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * @return BelongsToMany<Device, $this>
     */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'device_user_permissions')
            ->withPivot(['allowed_actions'])
            ->withTimestamps()
            ->using(DeviceUserPermission::class);
    }

    /**
     * @return HasMany<DeviceUserPermission, $this>
     */
    public function devicePermissions(): HasMany
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
