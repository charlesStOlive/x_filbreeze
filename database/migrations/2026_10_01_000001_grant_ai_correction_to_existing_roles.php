<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * La correction orthographique d'un devis ou d'une facture (filament-prism) demande désormais sa permission
 * (`crm.quote.ai.orthography`, `crm.invoice.ai.orthography`). Qui s'en servait la garde : un rôle qui modifie les devis
 * (les factures) reçoit la famille entière, `crm.quote.ai.*` (`crm.invoice.ai.*`), y compris les tâches à venir. Un
 * rôle qui a déjà un joker plus large (`crm.*`, `crm.quote.*`…) n'a besoin de rien.
 */
return new class extends Migration
{
    /** Permission qu'il fallait avoir => celles qu'on donne. */
    private const GRANTS = [
        'crm.quote.edit' => ['crm.quote.ai.*'],
        'crm.invoice.edit' => ['crm.invoice.ai.*'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::GRANTS as $required => $granted) {
            foreach ($granted as $name) {
                Permission::findOrCreate($name, 'web');
            }

            Role::query()
                ->where('guard_name', 'web')
                ->whereHas('permissions', fn ($query) => $query->where('name', $required))
                ->each(fn (Role $role) => $role->givePermissionTo($granted));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->whereIn('name', array_merge(...array_values(self::GRANTS)))
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
