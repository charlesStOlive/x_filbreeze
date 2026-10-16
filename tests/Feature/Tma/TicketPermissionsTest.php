<?php

namespace Tests\Feature\Tma;

use CharlesStOlive\FilamentPermissionManager\Support\PermissionCatalog;
use Spatie\Permission\Models\Permission;

/** Les droits déclarés par filament-tma, vus par filament-permission-manager (écran des rôles, `permissions:sync`). */
class TicketPermissionsTest extends TmaTestCase
{
    public function test_the_role_screen_shows_who_is_who_and_the_forcings_never_one_per_move(): void
    {
        $tickets = collect(PermissionCatalog::build()['groups'])
            ->flatMap(fn (array $group): array => $group['resources'])
            ->firstWhere('key', 'tma.ticket');

        $this->assertNotNull($tickets);

        $names = collect($tickets['families'])->flatMap(fn (array $family): array => array_column($family['items'], 'name'))
            ->merge(array_column($tickets['specific'], 'name'))
            ->all();

        foreach (['tma.ticket.correspond', 'tma.ticket.handle', 'tma.ticket.force-close', 'tma.ticket.force-return', 'tma.ticket.note', 'tma.ticket.remind'] as $permission) {
            $this->assertContains($permission, $names);
        }

        // Les passages normaux suivent le tour : ce ne sont pas des droits.
        $this->assertSame([], array_values(array_filter($names, fn (string $name): bool => str_starts_with($name, 'tma.ticket.workflow.'))));
        $this->assertNotContains('tma.ticket.comment', $names);
        $labels = collect($tickets['specific'])->pluck('label', 'name');
        $this->assertStringContainsString('Correspondant du Support', $labels['tma.ticket.correspond']);
        $this->assertStringContainsString('Forçage', $labels['tma.ticket.force-close']);
    }

    public function test_permissions_sync_keeps_the_pinned_prefixes(): void
    {
        $this->artisan('permissions:sync', ['--force' => true])->assertSuccessful();

        $names = Permission::query()->pluck('name');

        foreach (['tma.ticket.viewany', 'tma.ticket.correspond', 'tma.ticket.handle', 'tma.ticket.force-close', 'tma.task.workflow.publish', 'tma.instance.edit', 'tma.allowance.reset', 'tma.audit.viewany'] as $permission) {
            $this->assertContains($permission, $names);
        }

        $this->assertNotContains('tma.ticket.workflow.take-charge', $names);

        // Le nom de la Resource seul ne sert plus : il suivait le menu, pas le préfixe épinglé.
        $this->assertNotContains('ticket.viewany', $names);
    }
}
