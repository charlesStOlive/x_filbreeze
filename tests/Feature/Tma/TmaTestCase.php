<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentPermissionManager\Support\PermissionCatalog;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Les tests de filament-tma. Filbreeze est le maître ; les tests de l'application cliente se mettent dans sa peau
 * (`asClient()`), le mode n'étant qu'un réglage. Un ticket va toujours du client au Support (addendum §2).
 */
abstract class TmaTestCase extends TestCase
{
    use RefreshDatabase;

    protected const MASTER_UID = '01JTESTMASTER0000000000000';

    protected const CLIENT_UID = '01JTESTCLIENT0000000000000';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionCatalog::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function asClient(): void
    {
        config(['filament-tma.mode' => 'client', 'filament-tma.instance.uid' => self::CLIENT_UID, 'filament-tma.instance.ticket_prefix' => 'DES']);
    }

    protected function asMaster(): void
    {
        config(['filament-tma.mode' => 'master', 'filament-tma.instance.uid' => self::MASTER_UID, 'filament-tma.instance.ticket_prefix' => 'FIL']);
    }

    /** Tous les droits sur les tickets (forçages compris). */
    protected function agent(string ...$more): User
    {
        return User::factory()->withPermissions('panels.admin.access', 'tma.ticket.*', ...$more)->create();
    }

    /** Un membre du Support (maître), sans les forçages. */
    protected function supportMember(string ...$more): User
    {
        return User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view', 'tma.ticket.create',
            'tma.ticket.edit', 'tma.ticket.handle', 'tma.ticket.note', 'tma.ticket.remind', ...$more)->create();
    }

    /** Un correspondant du Support (application cliente), sans les forçages. */
    protected function correspondent(string ...$more): User
    {
        return User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view', 'tma.ticket.create',
            'tma.ticket.edit', 'tma.ticket.delete', 'tma.ticket.correspond', 'tma.ticket.note', 'tma.ticket.remind', ...$more)->create();
    }

    /** Une personne de l'application cliente, vue du maître. */
    protected function clientActor(string $id = '7', string $name = 'Ada Lovelace'): Actor
    {
        return Actor::fromInstance(self::CLIENT_UID, $id, $name, strtolower(strtok($name, ' ')).'@client.test');
    }

    /** L'application cliente qui agit, dans le maître, au nom de l'une de ses personnes (le demandeur par défaut). */
    protected function fromClient(?Actor $actor = null): Context
    {
        return Context::api($actor ?? $this->clientActor(), self::CLIENT_UID, fromMaster: false);
    }

    protected function workflow(): TicketWorkflow
    {
        return app(TicketWorkflow::class);
    }

    protected function draft(User $user, array $attributes = []): Ticket
    {
        return $this->workflow()->create(['title' => 'Le formulaire de contact ne part plus', ...$attributes], Context::ui($user));
    }

    /** Un ticket arrivé d'une application cliente, pris en charge par le Support (vu du maître). */
    protected function serviceDeskTicket(string $state = 'in_progress', string $number = 'DES-0001'): Ticket
    {
        $requester = $this->clientActor();

        return Ticket::create([
            'title' => 'Export des parrains',
            'number' => $number,
            'source_instance_uid' => self::CLIENT_UID,
            'created_on_instance_uid' => self::CLIENT_UID,
            'requester_actor_id' => $requester->getKey(),
            'created_by_actor_id' => $requester->getKey(),
            'state' => $state,
        ]);
    }
}
