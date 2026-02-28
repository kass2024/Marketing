<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /*
    |--------------------------------------------------------------------------
    | ROLE CONSTANTS
    |--------------------------------------------------------------------------
    */

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN       = 'admin';
    public const ROLE_CLIENT      = 'client';
    public const ROLE_SUPPORT     = 'support';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN,
        self::ROLE_CLIENT,
        self::ROLE_SUPPORT,
    ];

    /*
    |--------------------------------------------------------------------------
    | STATUS CONSTANTS
    |--------------------------------------------------------------------------
    */

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
    ];

    /*
    |--------------------------------------------------------------------------
    | Mass Assignable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    /*
    |--------------------------------------------------------------------------
    | Hidden Attributes
    |--------------------------------------------------------------------------
    */

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Boot Logic (Auto Defaults)
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::creating(function ($user) {

            if (!$user->role) {
                $user->role = self::ROLE_CLIENT;
            }

            if (!$user->status) {
                $user->status = self::STATUS_ACTIVE;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeAdmins($query)
    {
        return $query->whereIn('role', [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN
        ]);
    }

    public function scopeClients($query)
    {
        return $query->where('role', self::ROLE_CLIENT);
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE HELPERS
    |--------------------------------------------------------------------------
    */

    public function hasRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN
        ]);
    }

    public function isClient(): bool
    {
        return $this->role === self::ROLE_CLIENT;
    }

    public function isSupport(): bool
    {
        return $this->role === self::ROLE_SUPPORT;
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS HELPERS
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESS CONTROL
    |--------------------------------------------------------------------------
    */

    public function canAccessSystem(): bool
    {
        return $this->isActive();
    }

    public function canAccessAdmin(): bool
    {
        return $this->isAdmin() && $this->isActive();
    }

    public function canAccessClient(): bool
    {
        return $this->isClient() && $this->isActive();
    }
}