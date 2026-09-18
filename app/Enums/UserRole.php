<?php

namespace App\Enums;

enum UserRole: string
{
    case Superadmin = 'superadmin';
    // 'productor' es el valor por defecto de la columna users.role, así que este
    // caso debe existir o el cast del modelo revienta con cualquier usuario normal.
    case Productor = 'productor';
    // case Tecnico = 'tecnico';

    /**
     * Roles que están obligados a usar doble factor (RF003).
     */
    public function requiresTwoFactor(): bool
    {
        return $this === self::Superadmin;
    }

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::Productor => 'Productor',
            // self::Tecnico => 'Técnico',
        };
    }
}
