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
}