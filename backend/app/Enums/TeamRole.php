<?php

namespace App\Enums;

enum TeamRole: string {
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MIA = 'mia';
    case SECRETAIRE = 'secretaire';

    public function label(): string {
        return match ($this) {
            self::OWNER => 'Propriétaire',
            self::ADMIN => 'Admin',
            self::MIA => 'MIA',
            self::SECRETAIRE => 'Secrétaire',
        };
    }

    public function permissions(): array {
        return match ($this) {
            self::OWNER => ['*'],
            self::ADMIN => ['manage-team', 'manage-members', 'crud-resources'],
            self::MIA => ['create-resources', 'update-own-resources', 'view-resources'],
            self::SECRETAIRE => ['view-resources'],
        };
    }

    public function canManageTeam(): bool {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    public function canManageMembers(): bool {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    public function canManageResources(): bool {
        return in_array($this, [self::OWNER, self::ADMIN, self::MIA]);
    }

    public function canDeleteResources(): bool {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    public static function all(): array {
        return [self::OWNER, self::ADMIN, self::MIA, self::SECRETAIRE];
    }

    public static function values(): array {
        return array_map(fn ($role) => $role->value, self::all());
    }
}
