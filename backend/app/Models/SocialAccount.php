<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model {
    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'avatar',
        'token',
        'refresh_token',
    ];

    // Never serialize OAuth tokens in API responses
    protected $hidden = ['token', 'refresh_token'];

    protected $casts = [
        'token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
