<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Migration 2026_09_30_000001 : le rôle `admin` devient « Super Admin » (le même nom dans toutes les applications),
 * avec ses utilisateurs et ses permissions.
 */
class SuperAdminRoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migrate(): void
    {
        (require database_path('migrations/2026_09_30_000001_rename_admin_role_to_super_admin.php'))->up();
    }

    public function test_admin_is_renamed_with_its_users_and_permissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::query()->where('name', 'Super Admin')->delete();
        $admin = Role::findOrCreate('admin', 'web');
        $admin->givePermissionTo(Permission::findOrCreate('crm.quote.viewany', 'web'));
        $user = User::factory()->create();
        $user->assignRole($admin);

        $this->migrate();

        $this->assertFalse(Role::query()->where('name', 'admin')->exists());
        $this->assertTrue($user->fresh()->hasRole('Super Admin'));
        $this->assertTrue(Role::findByName('Super Admin', 'web')->hasPermissionTo('crm.quote.viewany'));
    }

    public function test_admin_joins_an_existing_super_admin_role(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $superAdmin = Role::findOrCreate('Super Admin', 'web');
        $admin = Role::findOrCreate('admin', 'web');
        $admin->givePermissionTo(Permission::findOrCreate('crm.invoice.viewany', 'web'));
        $user = User::factory()->create();
        $user->assignRole($admin);

        $this->migrate();

        $this->assertFalse(Role::query()->where('name', 'admin')->exists());
        $this->assertTrue($user->fresh()->hasRole('Super Admin'));
        $this->assertTrue($superAdmin->fresh()->hasPermissionTo('crm.invoice.viewany'));
    }
}
