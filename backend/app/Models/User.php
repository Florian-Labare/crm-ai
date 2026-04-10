<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasTeams;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable {
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasTeams, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'firstname',
        'avatar_path',
        'email',
        'password',
        // 'is_super_admin' intentionally excluded — set only via direct DB update or SuperAdminController::toggleSuperAdmin
    ];

    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute(): ?string {
        if (! $this->avatar_path) {
            return null;
        }

        return Storage::disk('s3')->url($this->avatar_path);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_super_admin' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool {
        return (bool) $this->is_super_admin;
    }

    public function setEmailAttribute(string $value): void {
        $this->attributes['email'] = strtolower(trim($value));
    }

    public function socialAccounts() {
        return $this->hasMany(\App\Models\SocialAccount::class);
    }
}
