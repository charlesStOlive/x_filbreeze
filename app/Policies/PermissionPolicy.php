<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;

class PermissionPolicy
{
    /**
     * Determine if the given user can view permissions.
     */
    public function viewAny(User $user)
    {
        // Vérifiez si l'utilisateur a une permission spécifique
        return $user->hasRole('User*');
    }
}
