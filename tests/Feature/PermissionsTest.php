<?php

namespace Tests\Feature;

use App\Filament\Clusters\Crm;
use App\Filament\Clusters\Crm\Resources\QuoteResource;
use App\Filament\Clusters\DataSets;
use App\Filament\Resources\DeclarationResource;
use App\Filament\Widgets\TreasuryOverviewWidget;
use App\Models\User;
use CharlesStOlive\FilamentPermissionManager\Support\SuperAdmin;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Le contrôle des accès de filbreeze (filament-permission-manager) : noms des permissions, rôles du seeder, entrée du
 * panel, listes, rubriques et widget de trésorerie selon le rôle.
 */
class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    public function test_the_seeder_uses_the_current_names_and_gives_everything_to_the_super_admin(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['panels.admin.access', 'crm.*', 'administration.user.viewany', 'administration.user.view'],
            Role::findByName('crm_manager', 'web')->permissions->pluck('name')->all(),
        );
        $this->assertSame(Permission::count(), Role::findByName(SuperAdmin::role(), 'web')->permissions()->count());
        $this->assertFalse(Permission::query()->whereIn('name', ['crm.companies.view', 'products.*', 'users.view', 'dashboard.view'])->exists());
    }

    public function test_legacy_names_are_renamed_for_roles(): void
    {
        $role = Role::findOrCreate('product_manager', 'web');
        foreach (['dashboard.view', 'products.*', 'crm.companies.view', 'crm.contacts.view'] as $name) {
            $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }

        (require database_path('migrations/2026_09_30_000002_rename_legacy_permissions.php'))->up();

        // panels.knowledge-base.access : donné aux rôles de l'admin par 2026_10_09_000001 (le centre d'aide).
        $this->assertEqualsCanonicalizing(
            ['datasets.product.*', 'crm.company.viewany', 'crm.company.view', 'crm.contact.viewany', 'crm.contact.view', 'panels.admin.access', 'panels.knowledge-base.access'],
            $role->fresh()->permissions->pluck('name')->all(),
        );
        $this->assertFalse(Permission::query()->where('name', 'crm.companies.view')->exists());
    }

    public function test_the_panel_needs_its_permission(): void
    {
        $this->assertFalse(User::factory()->create()->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue(User::factory()->withPermissions('panels.admin.access')->create()->fresh()->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_what_a_crm_manager_sees(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->actingAs($this->member('crm_manager'));

        $this->assertTrue(QuoteResource::canViewAny());
        $this->assertTrue(Crm::canAccess());
        $this->assertFalse(DeclarationResource::canViewAny());
        // Plus besoin du joker de toute la rubrique : Catalogues se ferme faute de liste ouverte, et le reste ne s'ouvre pas.
        $this->assertFalse(DataSets::canAccess());
        $this->assertFalse(TreasuryOverviewWidget::canView());

        $this->actingAs($this->member(SuperAdmin::role()));

        $this->assertTrue(DeclarationResource::canViewAny());
        $this->assertTrue(TreasuryOverviewWidget::canView());
    }

    public function test_no_list_is_left_without_control(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->artisan('permissions:audit', ['--panel' => ['admin']])
            ->doesntExpectOutputToContain('| AUCUN CONTRÔLE')
            ->doesntExpectOutputToContain('modèle partagé')
            ->assertSuccessful();
    }
}
