<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User; // Added this line
use Illuminate\Support\Facades\Storage;

class Team extends Model
{
    protected $fillable = ['user_id', 'name', 'logo_path', 'personal_team'];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) return null;
        return Storage::disk('s3')->url($this->logo_path);
    }

    /**
     * The owner of the team.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * All of the users that belong to the team.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Invitations for this team.
     */
    public function invitations()
    {
        return $this->hasMany(TeamInvitation::class);
    }
}
