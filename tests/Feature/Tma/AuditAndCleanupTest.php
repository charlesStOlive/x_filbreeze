<?php

namespace Tests\Feature\Tma;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Events\CleanupFinished;
use CharlesStOlive\FilamentTma\Filament\Resources\Audits\AuditResource;
use CharlesStOlive\FilamentTma\Filament\Resources\Audits\Pages\ListAudits;
use CharlesStOlive\FilamentTma\Models\Audit;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Support\Auditor;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use CharlesStOlive\FilamentTma\Workflow\TimeLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** L'audit (CDC §23) et le nettoyage (CDC §18), lot 3. */
class AuditAndCleanupTest extends TmaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asMaster();
        Http::fake();
    }

    public function test_time_changes_are_audited_with_author_and_values(): void
    {
        $agent = $this->agent();
        $this->actingAs($agent);
        $ticket = $this->serviceDeskTicket();

        $entry = app(TimeLog::class)->add($ticket, 60, '2026-10-01', Context::ui($agent));
        $entry->update(['minutes' => 90]);
        $entry->delete();

        $audits = Audit::query()->where('action', 'like', 'time.%')->orderBy('id')->get();
        $this->assertSame(['time.created', 'time.updated', 'time.deleted'], $audits->pluck('action')->all());

        $updated = $audits[1];
        $this->assertSame(['minutes' => 60], $updated->old_values);
        $this->assertSame(['minutes' => 90], $updated->new_values);
        $this->assertTrue($updated->actor->isUser($agent));
        $this->assertSame(self::MASTER_UID, $updated->instance_uid);
        $this->assertSame('ui', $updated->origin->value);
    }

    public function test_a_command_is_audited_as_its_user_and_secrets_are_never_kept(): void
    {
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket();

        Auditor::as(Context::command($agent), fn () => app(TimeLog::class)->add($ticket, 30, null, Context::command($agent)));
        $audit = Audit::query()->where('action', 'time.created')->sole();
        $this->assertSame('command', $audit->origin->value);
        $this->assertTrue($audit->actor->isUser($agent));

        $instance = Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test', 'token' => 'secret']);
        $instance->update(['token' => 'autre-secret']);

        $this->assertStringNotContainsString('secret', Audit::query()->where('action', 'like', 'instance.%')->get()->toJson());
        $this->assertSame('••••', Audit::query()->where('action', 'instance.updated')->sole()->new_values['token']);
    }

    public function test_a_state_change_alone_is_left_to_the_transition_history(): void
    {
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket('submitted');

        $this->workflow()->run($ticket, 'pause', ['until' => now()->addDay()->toDateTimeString()], Context::ui($agent));

        $this->assertSame(0, Audit::query()->where('action', 'ticket.updated')->count());
        $this->assertSame(1, $ticket->transitions()->count());
    }

    public function test_the_audit_screen_is_read_only(): void
    {
        $this->actingAs($this->agent('tma.audit.viewany'));
        app(TimeLog::class)->add($this->serviceDeskTicket(), 45, null, Context::ui(auth()->user()));

        Livewire::test(ListAudits::class)->assertCanSeeTableRecords(Audit::all());
        $this->assertFalse(AuditResource::canEdit(Audit::query()->first()));
        $this->assertFalse(AuditResource::canDelete(Audit::query()->first()));
    }

    public function test_cleanup_removes_what_is_old_and_keeps_what_is_invoiced(): void
    {
        Event::fake([CleanupFinished::class]);
        Storage::fake('local');
        $agent = $this->agent();

        // Un ticket supprimé il y a longtemps, avec une entrée et un fichier.
        $old = $this->serviceDeskTicket('in_progress', 'DES-0001');
        $entry = app(Timeline::class)->add($old, EntryType::Note, '<p>Journal</p>', Context::ui($agent));
        $entry->addMedia(UploadedFile::fake()->createWithContent('log.txt', "Erreur 500 à 10h02\n"))->toMediaCollection('attachments');
        app(TimeLog::class)->add($old, 30, null, Context::ui($agent));
        $old->delete();

        // Un autre, supprimé aussi, mais dont le temps a été facturé : on le garde.
        $invoiced = $this->serviceDeskTicket('in_progress', 'DES-0002');
        $time = app(TimeLog::class)->add($invoiced, 60, null, Context::ui($agent));
        $time->forceFill(['invoice_type' => 'invoice', 'invoice_id' => 3])->save();
        $invoiced->delete();

        // Un fichier envoyé par un formulaire et jamais rattaché.
        Storage::disk('local')->put('tma-uploads/abandon.png', 'x');

        $this->travel(100)->days();
        Storage::disk('local')->put('tma-uploads/recent.png', 'x');
        // L'horloge des tests avance, pas celle du disque : on date les fichiers à la main.
        touch(Storage::disk('local')->path('tma-uploads/recent.png'), now()->getTimestamp());
        touch(Storage::disk('local')->path('tma-uploads/abandon.png'), now()->subDays(3)->getTimestamp());

        $this->artisan('tma:cleanup', ['--dry-run' => true])->expectsOutputToContain('Simulation')->assertSuccessful();
        $this->assertSame(2, Ticket::withTrashed()->count());

        $this->artisan('tma:cleanup')->assertSuccessful();

        $this->assertSame(['DES-0002'], Ticket::withTrashed()->pluck('number')->all());
        $this->assertSame(0, Entry::withTrashed()->count());
        $this->assertSame(1, TimeEntry::withTrashed()->count());
        Storage::disk('local')->assertMissing('tma-uploads/abandon.png');
        Storage::disk('local')->assertExists('tma-uploads/recent.png');
        $this->assertSame(1, Audit::query()->where('action', 'cleanup.done')->sole()->new_values['protected']);
        Event::assertDispatched(CleanupFinished::class, fn ($e) => ! $e->dryRun && $e->report['tickets'] === 1);
    }
}
