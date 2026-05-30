<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'avatar',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'avatar_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function getAvatarUrlAttribute(): ?string 
    {
        if (!$this->avatar) return null;
        return Storage::disk('public')->url($this->avatar);
    }

    public function isAdmin(): bool 
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool {
        return $this->role === 'manager';
    }

    public function isCashier(): bool {
        return $this->role === 'cashier';
    }

    public function isStaff(): bool {
        return in_array($this->role, ['admin', 'managar']);
    }

    public function getRoleLabelAttribute(): string 
    {
        return match ($this->role) {
            'owner'     => 'Owner',
            'admin'     => 'Admin',
            'manager'   => 'Manager',
            'cashier'   => 'Kasir',
            default     => 'Unknown',
        };
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'outlet_user')
                    ->withPivot('role', 'is_active')
                    ->withTimestamps();
    }

    public function isOwner(): bool 
    {
        return $this->role === 'owner';
    }

    public function activeOutletId(): ?int
    {
        if ($this->isOwner()) {
            return session('active_outlet_id')
                ?? $this->outlets()->first()?->id;
        }

        return $this->outlets()
                    ->wherePivot('is_active', true)
                    ->first()?->id;
    }

    public function roleInOutlet(int $outletId): string 
    {
        if ($this->isOwner()) return 'owner';

        $pivot = $this->outlets()
                      ->wherePivot('outlet_id', $outletId)
                      ->first()?->pivot;

        return $pivot?->role ?? 'cashier';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}