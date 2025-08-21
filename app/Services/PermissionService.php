<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class PermissionService
{
    /**
     * Vérifie si l'utilisateur connecté a une permission donnée ou ses wildcards
     */
    public static function can(string $permission): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Vérifier la permission exacte
        if ($user->can($permission)) {
            return true;
        }

        // Extraire la base de la permission (avant le dernier point)
        $parts = explode('.', $permission);

        if (count($parts) >= 2) {
            // Vérifier les wildcards
            $base = implode('.', array_slice($parts, 0, -1));

            // Vérifier base.*
            if ($user->can($base . '.*')) {
                return true;
            }

            // Vérifier les wildcards de niveau supérieur
            while (count($parts) > 1) {
                array_pop($parts);
                $parentBase = implode('.', $parts);
                if ($user->can($parentBase . '.*')) {
                    return true;
                }
            }
        }

        // Vérifier admin.*
        if ($user->can('admin.*')) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur a un accès administrateur complet
     */
    public static function isAdmin(): bool
    {
        return Auth::check() && Auth::user()->can('admin.*');
    }

    /**
     * Vérifie si l'utilisateur a accès au CRM
     */
    public static function canAccessCrm(): bool
    {
        return self::can('crm.view') || self::can('crm.*');
    }

    /**
     * Vérifie si l'utilisateur peut gérer les utilisateurs
     */
    public static function canManageUsers(): bool
    {
        return self::can('users.*') || self::isAdmin();
    }

    /**
     * Vérifie si l'utilisateur peut gérer les rôles
     */
    public static function canManageRoles(): bool
    {
        return self::can('roles.*') || self::isAdmin();
    }

    /**
     * Vérifie si l'utilisateur peut gérer les permissions
     */
    public static function canManagePermissions(): bool
    {
        return self::can('permissions.*') || self::isAdmin();
    }
}
