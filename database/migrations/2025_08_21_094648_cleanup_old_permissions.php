<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Supprimer les anciennes permissions qui pourraient exister
        $oldPermissions = [
            // Anciennes permissions de filament-breezy
            'page_MyProfile',
            'page_ApiTokens',
            'page_TwoFactor',
            'view_my_profile',
            'update_my_profile',
            'manage_api_tokens',
            'manage_two_factor',
            // Anciennes permissions filament-spatie-roles-permissions
            'view-any Role',
            'view Role',
            'create Role',
            'update Role',
            'delete Role',
            'delete-any Role',
            'view-any Permission',
            'view Permission',
            'create Permission',
            'update Permission',
            'delete Permission',
            'delete-any Permission',
            // Autres anciennes permissions non-wildcard
            'view_user',
            'create_user',
            'update_user',
            'delete_user',
            'view_role',
            'create_role',
            'update_role',
            'delete_role',
            'view_permission',
            'create_permission',
            'update_permission',
            'delete_permission',
        ];

        foreach ($oldPermissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission) {
                // Détacher des rôles avant de supprimer
                $permission->roles()->detach();
                $permission->delete();
                echo "Supprimé: $permissionName\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas de rollback pour cette migration de nettoyage
    }
};
