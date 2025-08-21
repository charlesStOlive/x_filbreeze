<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

class ResetPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:reset {--force : Force reset without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset complet du système de permissions et rôles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->warn('⚠️  ATTENTION: Cette commande va SUPPRIMER toutes les permissions et rôles !');

        if (!$this->option('force') && !$this->confirm('Êtes-vous sûr de vouloir continuer ?')) {
            $this->info('❌ Reset annulé');
            return;
        }

        $this->info('🗑️  Suppression de toutes les permissions et rôles...');

        // 1. Détacher tous les utilisateurs de leurs rôles
        $this->info('📤 Détachement des rôles des utilisateurs...');
        $users = User::with('roles')->get();
        foreach ($users as $user) {
            $user->roles()->detach();
        }

        // 2. Supprimer toutes les permissions
        $this->info('🔐 Suppression des permissions...');
        $permissionCount = Permission::count();
        Permission::query()->delete();

        // 3. Supprimer tous les rôles
        $this->info('👥 Suppression des rôles...');
        $roleCount = Role::count();
        Role::query()->delete();

        $this->info("✅ Reset terminé !");
        $this->info("   - {$permissionCount} permission(s) supprimée(s)");
        $this->info("   - {$roleCount} rôle(s) supprimé(s)");
        $this->info("   - Tous les utilisateurs détachés de leurs rôles");

        // 4. Optionnel: Recréer le système de base
        if ($this->confirm('Voulez-vous recréer le système de permissions de base ?')) {
            $this->call('permissions:sync', ['--force' => true]);

            // Recréer l'utilisateur admin
            $adminEmail = $this->ask('Email de l\'administrateur', 'charles@notilac.fr');
            $admin = User::where('email', $adminEmail)->first();

            if ($admin) {
                $adminRole = Role::where('name', 'admin')->first();
                if ($adminRole) {
                    $admin->assignRole($adminRole);
                    $this->info("✅ Utilisateur {$adminEmail} assigné au rôle admin");
                }
            } else {
                $this->warn("⚠️  Utilisateur {$adminEmail} non trouvé");
            }
        }

        $this->info('🎉 Reset complet terminé !');
    }
}
