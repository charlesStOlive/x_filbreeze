<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Les rôles de filbreeze portaient d'anciens noms de permissions que plus aucun écran ne vérifie (`crm.companies.view`,
 * `products.*`, `users.view`…) : ils ne donnaient presque plus rien. Chaque ancien nom est remplacé, pour les rôles comme
 * pour les permissions données directement à un utilisateur, par ses équivalents actuels — les mêmes noms que dans les
 * autres applications (filament-permission-manager : `{rubrique}.{liste}.{action}`). `view` donnait la liste et la fiche :
 * il devient `viewany` et `view`.
 *
 * Sans équivalent, retirés : `dashboard.view` (le tableau de bord est ouvert à tout le panel), `admin.*` (le Super Admin
 * reçoit toutes les permissions une par une), `msgraph.emails.send` (aucune action d'envoi). Les `data-sets.*` créées par
 * une synchronisation avant que la rubrique Catalogues prenne le slug `datasets` sont remplacées de la même façon.
 *
 * Chaque rôle existant reçoit aussi `panels.admin.access` : l'entrée dans le panel devient une permission
 * (HasPanelPermission) et personne ne doit la perdre.
 */
return new class extends Migration
{
    /** @return array<string, array<int, string>> ancien nom => nouveaux noms */
    private function renames(): array
    {
        $renames = [
            'dashboard.view' => [],
            'admin.*' => [],
            'msgraph.emails.send' => [],
            'msgraph.emails.view' => ['msgraph.msginuser.viewany', 'msgraph.msginuser.view'],
            'msgraph.drafts.*' => ['msgraph.msgdraftuser.*'],
            'products.*' => ['datasets.product.*'],
            'products.view' => ['datasets.product.viewany', 'datasets.product.view'],
            'products.create' => ['datasets.product.create'],
            'products.edit' => ['datasets.product.edit'],
            'products.delete' => ['datasets.product.delete'],
            'datasets.product.view' => ['datasets.product.viewany', 'datasets.product.view'],
        ];

        // Listes renommées : ancien préfixe => nouveau préfixe.
        $lists = [
            'users' => 'administration.user',
            'roles' => 'administration.role',
            'permissions' => 'administration.permission',
            'crm.companies' => 'crm.company',
            'crm.contacts' => 'crm.contact',
            'crm.suppliers' => 'crm.supplier',
            'crm.invoices' => 'crm.invoice',
            'crm.quotes' => 'crm.quote',
            'data-sets.product' => 'datasets.product',
        ];

        foreach ($lists as $old => $new) {
            $renames["{$old}.view"] = ["{$new}.viewany", "{$new}.view"];

            foreach (['viewany', 'create', 'edit', 'delete'] as $action) {
                $renames["{$old}.{$action}"] = ["{$new}.{$action}"];
            }

            // `roles.*` et `permissions.*` sont des jokers globaux encore créés par permissions:sync : on les garde.
            if (! in_array($old, ['roles', 'permissions'], true)) {
                $renames["{$old}.*"] = ["{$new}.*"];
            }
        }

        $renames['data-sets.*'] = ['datasets.*'];

        return $renames;
    }

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            foreach ($this->renames() as $old => $news) {
                $legacy = Permission::query()->where('name', $old)->where('guard_name', 'web')->first();

                if ($legacy === null) {
                    continue;
                }

                $replacements = collect($news)->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

                foreach ($legacy->roles as $role) {
                    $role->givePermissionTo($replacements);
                }

                foreach ($legacy->users as $user) {
                    $user->givePermissionTo($replacements);
                }

                $legacy->delete();
            }

            $panel = Permission::findOrCreate('panels.admin.access', 'web');
            Role::query()->where('guard_name', 'web')->each(fn (Role $role) => $role->givePermissionTo($panel));
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Les anciens noms ne sont vérifiés par aucun écran : pas de retour arrière.
    }
};
