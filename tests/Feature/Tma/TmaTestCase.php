<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentPermissionManager\Support\PermissionCatalog;
use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Les tests de filament-tma (lot 1a). Filbreeze est le maître ; les tests du traitement local se mettent dans la
 * peau d'une application cliente (`asClient()`), le mode n'étant qu'un réglage.
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

    /** Tous les droits sur les tickets. */
    protected function agent(string ...$more): User
    {
        return User::factory()->withPermissions('panels.admin.access', 'tma.ticket.*', ...$more)->create();
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
        $requester = Actor::fromInstance(self::CLIENT_UID, '7', 'Ada Lovelace', 'ada@client.test');

        return Ticket::create([
            'title' => 'Export des parrains',
            'number' => $number,
            'source_instance_uid' => self::CLIENT_UID,
            'created_on_instance_uid' => self::CLIENT_UID,
            'handling_mode' => HandlingMode::ServiceDesk,
            'requester_actor_id' => $requester->getKey(),
            'created_by_actor_id' => $requester->getKey(),
            'state' => $state,
        ]);
    }
}
