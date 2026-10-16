<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\CreateTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Notifications\NoticeNotification;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;
use Livewire\Livewire;

/** Les brouillons du maître (addendum §3) : préparés pour un client, invisibles, publiés tout de suite ou à date. */
class MasterDraftTicketTest extends TmaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asMaster();
        Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test', 'token' => 'jeton']);
        Http::fake([
            'desapp.test/api/tma/v1/users' => Http::response(['data' => [['id' => '7', 'name' => 'Ada Lovelace', 'email' => 'ada@des.test', 'service_desk' => true]]]),
            'desapp.test/*' => Http::response(['missing_media' => []]),
        ]);
    }

    public function test_a_prepared_ticket_takes_the_number_of_the_support_never_one_of_the_client(): void
    {
        $other = Instance::create(['uid' => '01JTESTAUTRE00000000000000', 'name' => 'Autre', 'url' => 'https://autre.test']);
        $context = Context::ui($this->agent());
        $requester = ['id' => '7', 'name' => 'Ada Lovelace'];

        // Des tickets transmis par Desapp gardent leur numéro ; le Support ne connaît pas ceux qu'elle garde chez elle.
        $this->serviceDeskTicket('submitted', 'DES-0006');

        $first = $this->workflow()->createForClient(Instance::forUid(self::CLIENT_UID), $requester, ['title' => 'Un'], $context);
        $second = $this->workflow()->createForClient($other, $requester, ['title' => 'Deux'], $context);

        // Un seul compteur, celui du Support, pour tous les clients : pas de doublon, pas de DES-0007 pris à Desapp.
        $this->assertSame('FIL-0001', $first->number);
        $this->assertSame('FIL-0002', $second->number);
    }

    public function test_the_master_prepares_a_ticket_in_the_name_of_a_client_user_and_publishes_it(): void
    {
        $this->actingAs($this->agent());

        Livewire::test(CreateTicket::class)
            ->fillForm(['instance_uid' => self::CLIENT_UID, 'requester_id' => '7', 'title' => 'Mise à jour des mentions légales', 'priority' => 'normal'])
            ->call('create')
            ->assertHasNoFormErrors();

        $ticket = Ticket::query()->sole();
        $this->assertSame('draft', $ticket->state::getMorphClass());
        $this->assertSame(self::CLIENT_UID, $ticket->source_instance_uid);
        $this->assertSame('Ada Lovelace', $ticket->requester->name);
        $this->assertFalse($ticket->isShared());
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/sync'));

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionHidden('move_submit')
            ->assertActionHidden('move_transmit')
            ->callAction('move_publish');

        $this->assertInstanceOf(Submitted::class, $ticket->refresh()->state);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/sync') && $request['ticket']['state'] === 'submitted');
    }

    public function test_a_scheduled_draft_publishes_itself(): void
    {
        $agent = $this->agent();
        $ticket = $this->workflow()->createForClient(
            Instance::query()->sole(), ['id' => '7', 'name' => 'Ada Lovelace'],
            ['title' => 'Relance annuelle', 'publish_at' => now()->addDay()], Context::ui($agent),
        );

        $this->artisan('tma:publish-scheduled')->expectsOutputToContain('0 tâche(s) ou ticket(s)');
        $this->travel(2)->days();
        $this->artisan('tma:publish-scheduled')->expectsOutputToContain('1 tâche(s) ou ticket(s)');

        $this->assertInstanceOf(Submitted::class, $ticket->refresh()->state);
        $this->assertSame('auto-publish', $ticket->transitions()->sole()->move);
    }

    public function test_the_client_receives_it_and_can_tell_the_requester(): void
    {
        $ticket = $this->workflow()->createForClient(Instance::query()->sole(), ['id' => '7', 'name' => 'Ada Lovelace'], ['title' => 'Relance annuelle'], Context::ui($this->agent()));
        $this->workflow()->run($ticket, 'publish', [], Context::ui($this->agent()));
        $snapshot = Payload::snapshot($ticket->refresh());
        $ticket->transitions()->delete();
        $ticket->forceDelete();

        // Côté client, Ada est l'utilisateur 7.
        Notification::fake();
        $this->asClient();
        config(['filament-tma.notifications.immediate' => ['publish'], 'filament-tma.api.master_user' => 'filbreeze@api.test']);
        // Le Support ne prépare un ticket que pour un correspondant du Support.
        $ada = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.workflow.transmit')->create(['id' => 7, 'name' => 'Ada Lovelace']);
        Passport::actingAs(User::factory()->create(['email' => 'filbreeze@api.test']), [], 'api');

        // Un brouillon n'arrive jamais.
        $this->postJson('/api/tma/v1/sync', [...$snapshot, 'ticket' => [...$snapshot['ticket'], 'state' => 'draft']])->assertNotFound();

        $this->postJson('/api/tma/v1/sync', $snapshot)->assertOk();

        $received = Ticket::query()->sole();
        $this->assertTrue($received->requester->isUser($ada));
        $this->assertSame('service_desk', $received->handling_mode->value);
        Notification::assertSentTo($ada, NoticeNotification::class, fn (NoticeNotification $mail): bool => str_contains($mail->notice->data['line'], 'a ouvert ce ticket pour vous'));
    }
}
