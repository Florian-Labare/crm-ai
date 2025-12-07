<?php

namespace App\Enums;

enum TeamRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';
    case VIEWER = 'viewer';

    /**
     * Get human-readable label for the role
     */
    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Propriétaire',
            self::ADMIN => 'Administrateur',
            self::MEMBER => 'Membre',
            self::VIEWER => 'Observateur',
        };
    }

    /**
     * Get permissions associated with this role
     */
    public function permissions(): array
    {
        return match ($this) {
            self::OWNER => ['*'], // All permissions
            self::ADMIN => ['manage-team', 'manage-members', 'crud-resources'],
            self::MEMBER => ['create-resources', 'update-own-resources', 'view-resources'],
            self::VIEWER => ['view-resources'],
        };
    }

    /**
     * Check if this role can manage team settings
     */
    public function canManageTeam(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    /**
     * Check if this role can manage team members
     */
    public function canManageMembers(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    /**
     * Check if this role can create/update resources
     */
    public function canManageResources(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::MEMBER]);
    }

    /**
     * Check if this role can delete resources
     */
    public function canDeleteResources(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    /**
     * Get all available roles
     */
    public static function all(): array
    {
        return [
            self::OWNER,
            self::ADMIN,
            self::MEMBER,
            self::VIEWER,
        ];
    }

    /**
     * Get all role values as strings
     */
    public static function values(): array
    {
        return array_map(fn($role) => $role->value, self::all());
    }
}
