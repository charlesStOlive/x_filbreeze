<?php

namespace Tests\Feature\Tma;

use App\Models\Company;
use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Events\TimeEntryChanged;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\CreateInstance;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\EditInstance;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\ListInstances;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use CharlesStOlive\FilamentTma\Workflow\TimeLog;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/** Le temps (CDC §10, maître seulement) et les applications clientes (écran du maître). */
class TimeAndInstancesTest extends TmaTestCase
{
    public function test_durations_convert_with_the_configured_day(): void
    {
        $this->assertSame(150, Duration::toMinutes('2,5', Duration::HOURS));
        $this->assertSame(630, Duration::toMinutes(1.5, Duration::DAYS));
        config(['filament-tma.time.day_minutes' => 480]);
        $this->assertSame(240, Duration::toMinutes(0.5, Duration::DAYS));
        $this->assertSame('2 h 30', Duration::format(150));
        $this->assertSame('0,5 j', Duration::format(240, Duration::DAYS));
    }

    public function test_time_is_split_by_month_and_sent_as_totals(): void
    {
        $this->asMaster();
        Event::fake([TimeEntryChanged::class]);
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket();
        $log = app(TimeLog::class);

        $log->add($ticket, 120, '2026-01-30', Context::ui($agent));
        $log->add($ticket, 90, '2026-02-02', Context::ui($agent));
        $log->add($ticket, 30, '2026-03-15', Context::ui($agent), billable: false);

        $this->assertSame(['total_minutes' => 240, 'by_month' => ['2026-01' => 120, '2026-02' => 90, '2026-03' => 30]], Payload::ticket($ticket)['time']);
        Event::assertDispatchedTimes(TimeEntryChanged::class, 3);
    }

    public function test_time_is_only_logged_by_the_master_with_its_permission(): void
    {
        $this->asMaster();
        $ticket = $this->serviceDeskTicket();
        $viewer = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.view')->create();

        $this->assertFalse(app(TimeLog::class)->canLog($ticket, Context::ui($viewer)));
        $this->assertTrue(app(TimeLog::class)->canLog($ticket, Context::ui($this->agent())));

        $this->asClient();
        $this->assertFalse(app(TimeLog::class)->canLog($ticket, Context::ui($this->agent())));
    }

    public function test_an_invoiced_entry_is_locked(): void
    {
        $this->asMaster();
        $ticket = $this->serviceDeskTicket();
        $entry = app(TimeLog::class)->add($ticket, 60, now(), Context::ui($this->agent()));
        $entry->forceFill(['invoice_type' => 'invoice', 'invoice_id' => 12, 'invoiced_at' => now()])->save();

        try {
            $entry->update(['minutes' => 30]);
            $this->fail('Une saisie facturée ne se modifie plus.');
        } catch (\LogicException) {
        }

        try {
            $entry->delete();
            $this->fail('Une saisie facturée ne se supprime plus.');
        } catch (\LogicException) {
        }

        // Défaire le rattachement reste possible (lot 3), puis la saisie se modifie.
        $entry->refresh()->forceFill(['invoice_type' => null, 'invoice_id' => null, 'invoiced_at' => null])->save();
        $entry->update(['minutes' => 30]);
        $this->assertSame(30, $entry->refresh()->minutes);
    }

    public function test_logging_time_from_the_ticket_page(): void
    {
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket();
        $this->actingAs($agent);

        // Les raccourcis remplissent la durée et son unité.
        $preset = fn (string $name): TestAction => TestAction::make($name)->schemaComponent('durationPresets', schema: 'mountedActionSchema0');
        Livewire::test(TimeEntriesRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])
            ->mountAction(TestAction::make('logTime')->table())
            ->callAction($preset('duration_45m'))
            ->assertSchemaStateSet(['duration' => 45, 'unit' => 'minutes'], 'mountedActionSchema0')
            ->callAction($preset('duration_halfDay'))
            ->assertSchemaStateSet(['duration' => 0.5, 'unit' => 'days'], 'mountedActionSchema0');

        // Avec un message : il s'écrit dans l'historique, et le temps porte dessus.
        Livewire::test(TimeEntriesRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])
            ->callTableAction('logTime', data: ['worked_on' => '2026-10-05', 'duration' => 1.5, 'unit' => 'hours', 'message' => '<p>Correctif export</p>', 'billable' => true])
            ->assertHasNoTableActionErrors()
            ->assertSee('Sur : Message de');

        $entry = TimeEntry::query()->sole();
        $this->assertSame(90, $entry->minutes);
        $this->assertTrue($entry->actor->isUser($agent));
        $message = $ticket->entries()->sole();
        $this->assertSame('comment', $message->type->value);
        $this->assertStringContainsString('Correctif export', $message->body);
        $this->assertTrue($entry->entry->is($message));

        Livewire::test(TimeEntriesRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])
            ->callTableAction('edit', $entry, data: ['duration' => 2, 'unit' => 'hours'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(120, $entry->refresh()->minutes);

        $entry->forceFill(['invoice_type' => 'invoice', 'invoice_id' => 1])->saveQuietly();
        Livewire::test(TimeEntriesRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])
            ->assertTableActionHidden('edit', $entry)
            ->assertTableActionHidden('delete', $entry);
    }

    public function test_choosing_a_message_or_a_note_drops_the_new_message(): void
    {
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket();
        $note = app(Timeline::class)->add($ticket, EntryType::Note, '<p>Vu en recette.</p>', Context::ui($agent));
        $this->actingAs($agent);

        // Le champ du message disparaît dans le navigateur dès qu'un message ou une note est choisi ; son texte, s'il
        // en avait, est ignoré.
        Livewire::test(TimeEntriesRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])
            ->mountAction(TestAction::make('logTime')->table())
            ->assertFormFieldVisible('message')
            ->fillForm(['worked_on' => now()->toDateString(), 'duration' => 20, 'unit' => 'minutes', 'entry_id' => $note->getKey(), 'message' => '<p>Oublié là.</p>'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertDispatched(TimeEntriesRelationManager::TIMELINE_CHANGED);

        $this->assertSame(1, $ticket->entries()->count());
        $this->assertTrue(TimeEntry::query()->sole()->entry->is($note));
    }

    public function test_the_page_shows_the_message_written_with_the_time(): void
    {
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket();
        $this->actingAs($agent);

        // La liste du temps est un composant à part : elle prévient la page, qui relit l'historique.
        $page = Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])->assertDontSee('Export corrigé');
        Livewire::test(TimeEntriesRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])
            ->callTableAction('logTime', data: ['worked_on' => now()->toDateString(), 'duration' => 1, 'unit' => 'hours', 'message' => '<p>Export corrigé.</p>', 'billable' => true])
            ->assertDispatched(TimeEntriesRelationManager::TIMELINE_CHANGED);
        $page->dispatch(TimeEntriesRelationManager::TIMELINE_CHANGED)->assertSee(['Export corrigé', '1 h']);
    }

    public function test_the_master_registers_a_client_application(): void
    {
        $this->asMaster();
        $this->actingAs($this->agent('tma.instance.*'));
        $company = Company::create(['title' => 'DES', 'slug' => 'des']);

        Livewire::test(CreateInstance::class)
            ->fillForm([
                'name' => 'Desapp', 'uid' => self::CLIENT_UID, 'url' => 'https://desapp.test',
                'client_id' => $company->getKey(), 'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $instance = Instance::query()->sole();
        $this->assertTrue($instance->client->is($company));

        // Le jeton de l'application, saisi à la main (quand la commande n'a pas pu le transmettre).
        Livewire::test(EditInstance::class, ['record' => $instance->getKey()])
            ->assertSee('En attente')
            ->fillForm(['token' => 'secret-desapp'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('secret-desapp', $instance->refresh()->token);
        // Chiffré en base.
        $this->assertStringNotContainsString('secret-desapp', (string) \DB::table('tma_instances')->value('token'));

        Http::fake(['desapp.test/api/tma/v1/ping' => Http::response(['instance_uid' => self::CLIENT_UID, 'name' => 'Desapp'])]);
        Livewire::test(ListInstances::class)
            ->callAction(TestAction::make('ping')->table($instance))
            ->assertNotified('Connexion réussie : Desapp');
    }

    public function test_a_ticket_in_error_can_be_sent_again(): void
    {
        $this->asMaster();
        $agent = $this->agent();
        Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test', 'token' => 't']);
        $ticket = $this->serviceDeskTicket();
        $ticket->forceFill(['sync_status' => 'error', 'last_sync_error' => 'Délai dépassé'])->save();
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
        $this->actingAs($agent);

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertSee('Délai dépassé')
            ->callAction('resync');

        $this->assertSame('synced', $ticket->refresh()->sync_status->value);
        Http::assertSentCount(1);
    }
}
