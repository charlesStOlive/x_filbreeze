<?php

namespace Tests\Feature\Tma;

use App\Models\Company;
use App\Models\User;
use CharlesStOlive\FilamentTma\Events\TimeEntryChanged;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\CreateInstance;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\ListInstances;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
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

        $log->add($ticket, 120, '2026-01-30', 'Analyse', Context::ui($agent));
        $log->add($ticket, 90, '2026-02-02', 'Correctif', Context::ui($agent));
        $log->add($ticket, 30, '2026-03-15', 'Recette', Context::ui($agent), billable: false);

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
        $entry = app(TimeLog::class)->add($ticket, 60, now(), 'Correctif', Context::ui($this->agent()));
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

        Livewire::test(TimeEntriesRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])
            ->callTableAction('logTime', data: ['worked_on' => '2026-10-05', 'duration' => 1.5, 'unit' => 'hours', 'description' => 'Correctif export', 'billable' => true])
            ->assertHasNoTableActionErrors();

        $entry = TimeEntry::query()->sole();
        $this->assertSame(90, $entry->minutes);
        $this->assertTrue($entry->actor->isUser($agent));

        Livewire::test(TimeEntriesRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])
            ->callTableAction('edit', $entry, data: ['duration' => 2, 'unit' => 'hours'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(120, $entry->refresh()->minutes);

        $entry->forceFill(['invoice_type' => 'invoice', 'invoice_id' => 1])->saveQuietly();
        Livewire::test(TimeEntriesRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])
            ->assertTableActionHidden('edit', $entry)
            ->assertTableActionHidden('delete', $entry);
    }

    public function test_the_master_registers_a_client_application(): void
    {
        $this->asMaster();
        $this->actingAs($this->agent('tma.instance.*'));
        $company = Company::create(['title' => 'DES', 'slug' => 'des']);
        $service = User::factory()->create(['name' => 'Compte Desapp']);

        Livewire::test(CreateInstance::class)
            ->fillForm([
                'name' => 'Desapp', 'uid' => self::CLIENT_UID, 'url' => 'https://desapp.test',
                'client_id' => $company->getKey(), 'api_user_id' => $service->getKey(), 'token' => 'secret-desapp', 'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $instance = Instance::query()->sole();
        $this->assertTrue($instance->client->is($company));
        $this->assertSame('secret-desapp', $instance->token);
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
