<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TeamInvitation extends Model {
    protected $fillable = [
        'team_id',
        'email',
        'role',
        'token',
        'invited_by',
        'accepted_at',
        'expires_at',
    ];

    protected function casts(): array {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function team() {
        return $this->belongsTo(Team::class);
    }

    public function inviter() {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool {
        return is_null($this->accepted_at) && ! $this->isExpired();
    }

    public function scopePending(Builder $query): Builder {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }
}
