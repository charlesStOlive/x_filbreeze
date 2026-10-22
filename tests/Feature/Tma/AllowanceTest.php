<?php

namespace Tests\Feature\Tma;

use App\Models\Company;
use App\Models\User;
use CharlesStOlive\FilamentTma\Events\AllowanceThresholdReached;
use CharlesStOlive\FilamentTma\Filament\Resources\Allowances\Pages\CreateAllowance;
use CharlesStOlive\FilamentTma\Filament\Widgets\AllowanceOverview;
use CharlesStOlive\FilamentTma\Models\Allowance;
use CharlesStOlive\FilamentTma\Models\AllowanceStatus;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Notifications\AllowanceThresholdNotification;
use CharlesStOlive\FilamentTma\Support\AllowanceMeter;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TimeLog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;
use Livewire\Livewire;

/** Les forfaits de TMA et leurs seuils (CDC §11, lot 2). */
class AllowanceTest extends TmaTestCase
{
    private Company $des;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asMaster();
        Http::fake(['*' => Http::response(['missing_media' => [], 'count' => 1])]);

        $this->des = Company::create(['title' => 'DES', 'slug' => 'des']);
        Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test', 'token' => 't', 'client_type' => $this->des->getMorphClass(), 'client_id' => $this->des->getKey()]);
        Instance::create(['uid' => '01JTESTOTHER00000000000000', 'name' => 'EDM', 'url' => 'https://edm.test', 'token' => 't', 'client_type' => $this->des->getMorphClass(), 'client_id' => $this->des->getKey()]);
        $this->agent = $this->agent();
    }

    private function allowance(array $attributes = []): Allowance
    {
        return Allowance::create([
            'client_type' => $this->des->getMorphClass(), 'client_id' => $this->des->getKey(),
            'period' => 'monthly', 'minutes' => 600, 'starts_on' => '2026-01-01', ...$attributes,
        ]);
    }

    private function log(int $minutes, string $day, string $number = 'DES-0001', string $source = self::CLIENT_UID, bool $billable = true): void
    {
        $ticket = Ticket::query()->where('number', $number)->first()
            ?? tap($this->serviceDeskTicket('in_progress', $number), fn ($t) => $t->forceFill(['source_instance_uid' => $source])->save());

        app(TimeLog::class)->add($ticket, $minutes, $day, Context::ui($this->agent), $billable);
    }

    public function test_consumption_counts_billable_ticket_time_of_the_period(): void
    {
        $shared = $this->allowance();
        $desappOnly = $this->allowance(['instance_uid' => self::CLIENT_UID, 'period' => 'quarterly', 'minutes' => 1200]);

        $this->log(120, '2026-10-02');
        $this->log(60, '2026-10-20', 'EDM-0001', '01JTESTOTHER00000000000000');
        $this->log(30, '2026-10-21', billable: false);
        $this->log(240, '2026-11-03');

        $october = AllowanceMeter::status($shared, '2026-10');
        $this->assertSame(180, $october['consumed_minutes']);
        $this->assertSame(420, $october['remaining_minutes']);
        $this->assertSame(30.0, $october['percent']);

        $q4 = AllowanceMeter::status($desappOnly, '2026-Q4');
        $this->assertSame(360, $q4['consumed_minutes']);
        $this->assertSame(['2026-10' => 120, '2026-11' => 240], $q4['by_month']);
    }

    public function test_each_threshold_fires_once_per_period(): void
    {
        Event::fake([AllowanceThresholdReached::class]);
        $this->allowance();

        $this->log(500, '2026-10-02');
        Event::assertDispatched(AllowanceThresholdReached::class, fn ($e) => $e->threshold === 80 && $e->periodKey === '2026-10');
        Event::assertNotDispatched(AllowanceThresholdReached::class, fn ($e) => $e->threshold === 90);

        $this->log(60, '2026-10-03');  // 93 %
        $this->log(60, '2026-10-04');  // 103 % : 90 et 100
        $this->log(5, '2026-10-05');   // pas de nouveau seuil
        Event::assertDispatchedTimes(AllowanceThresholdReached::class, 3);
        Event::assertDispatched(AllowanceThresholdReached::class, fn ($e) => $e->threshold === 100 && $e->isExhausted());

        // Une autre période repart de zéro.
        $this->log(500, '2026-11-02');
        Event::assertDispatched(AllowanceThresholdReached::class, fn ($e) => $e->threshold === 80 && $e->periodKey === '2026-11');
    }

    public function test_a_reset_lets_thresholds_fire_again(): void
    {
        Event::fake([AllowanceThresholdReached::class]);
        $allowance = $this->allowance();
        $this->log(500, '2026-10-02');

        AllowanceMeter::resetThresholds($allowance, '2026-10');
        $this->assertSame([80], AllowanceMeter::checkThresholds($allowance, '2026-10'));
    }

    public function test_the_configured_addresses_are_told(): void
    {
        Notification::fake();
        config(['filament-tma.allowances.notify' => ['compta@exemple.fr']]);
        $this->allowance();

        $this->log(700, '2026-10-02');

        Notification::assertSentOnDemand(AllowanceThresholdNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'compta@exemple.fr' && $n->event->threshold === 110);
    }

    public function test_overage_can_be_blocked(): void
    {
        config(['filament-tma.allowances.block_overage' => true]);
        $this->allowance();
        $this->log(590, '2026-10-02');

        try {
            $this->log(30, '2026-10-03');
            $this->fail('La saisie aurait dû être refusée.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('serait dépassé', $e->getMessage());
        }

        // Le temps non facturable ne consomme pas le forfait.
        $this->log(30, '2026-10-03', billable: false);
        $this->assertSame(620, (int) TimeEntry::query()->sum('minutes'));
    }

    public function test_the_master_creates_an_allowance_in_days_and_the_client_gets_its_status(): void
    {
        $this->actingAs($this->agent('tma.allowance.*'));

        Livewire::test(CreateAllowance::class)
            ->fillForm(['client_id' => $this->des->getKey(), 'instance_uid' => self::CLIENT_UID, 'period' => 'monthly', 'volume' => 2, 'unit' => 'days', 'starts_on' => now()->startOfMonth()->toDateString()])
            ->call('create')
            ->assertHasNoFormErrors();

        $allowance = Allowance::query()->sole();
        $this->assertSame(840, $allowance->minutes);
        $this->assertTrue($allowance->client->is($this->des));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://desapp.test/api/tma/v1/allowances'
            && $request['data'][0]['allocated_minutes'] === 840);

        // Côté client : l'état reçu s'affiche.
        $status = AllowanceMeter::status($allowance);
        $this->asClient();
        $master = User::factory()->create(['email' => 'filbreeze@api.test']);
        config(['filament-tma.api.master_user' => 'filbreeze@api.test']);
        Passport::actingAs($master, [], 'api');

        $this->postJson('/api/tma/v1/allowances', ['data' => [$status]])->assertOk()->assertJsonPath('count', 1);
        $this->postJson('/api/tma/v1/allowances', ['data' => [$status]])->assertOk();
        $this->assertSame(1, AllowanceStatus::query()->count());

        $this->actingAs($this->agent('widgets.allowanceoverview.viewany'));
        Livewire::test(AllowanceOverview::class)->assertSee('0 h / 2 j');
    }
}
