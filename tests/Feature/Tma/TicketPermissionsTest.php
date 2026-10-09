<?php

namespace Tests\Feature\Tma;

use CharlesStOlive\FilamentPermissionManager\Support\PermissionCatalog;

/** Les droits déclarés par filament-tma, vus par filament-permission-manager (écran des rôles, `permissions:sync`). */
class TicketPermissionsTest extends TmaTestCase
{
    public function test_the_role_screen_shows_the_ticket_permissions_with_one_per_move(): void
    {
        $tickets = collect(PermissionCatalog::build()['groups'])
            ->flatMap(fn (array $group): array => $group['resources'])
            ->firstWhere('key', 'tma.ticket');

        $this->assertNotNull($tickets);

        $families = collect($tickets['families'])->keyBy('label');
        $this->assertSame('tma.ticket.workflow.*', $families['Passages du ticket']['joker']);

        $names = collect($tickets['families'])->flatMap(fn (array $family): array => array_column($family['items'], 'name'))
            ->merge(array_column($tickets['specific'], 'name'))
            ->all();

        foreach (['tma.ticket.workflow.take-charge', 'tma.ticket.workflow.validate', 'tma.ticket.comment', 'tma.ticket.note'] as $permission) {
            $this->assertContains($permission, $names);
        }

        // Les mouvements du planificateur ne sont pas des droits.
        $this->assertNotContains('tma.ticket.workflow.auto-close', $names);
    }

    public function test_permissions_sync_keeps_the_pinned_prefixes(): void
    {
        $this->artisan('permissions:sync', ['--force' => true])->assertSuccessful();

        $names = \Spatie\Permission\Models\Permission::query()->pluck('name');

        foreach (['tma.ticket.viewany', 'tma.ticket.workflow.take-charge', 'tma.task.workflow.publish', 'tma.instance.edit', 'tma.allowance.reset', 'tma.audit.viewany'] as $permission) {
            $this->assertContains($permission, $names);
        }

        // Le nom de la Resource seul ne sert plus : il suivait le menu, pas le préfixe épinglé.
        $this->assertNotContains('ticket.viewany', $names);
    }
}
