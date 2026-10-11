<?php

namespace Tests\Feature;

use App\Models\Declaration;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RunDailyDeclarationsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_failed_qonto_sync_does_not_stop_the_month_from_being_opened(): void
    {
        Carbon::setTestNow('2026-10-11 07:45:00');
        config([
            'declarations.start_from.vat' => '2026-09-01',
            'declarations.start_from.urssaf' => '2026-07-01',
            // Qonto injoignable : la synchronisation échoue.
            'qonto.base_uri' => 'http://127.0.0.1:9/',
            'qonto.http.retry_times' => 0,
        ]);

        $this->artisan('declarations:daily')->assertFailed();

        $this->assertEqualsCanonicalizing(
            ['vat 2026-09-01', 'vat 2026-10-01', 'urssaf 2026-07-01', 'urssaf 2026-10-01'],
            Declaration::query()->get()->map(fn (Declaration $declaration): string => $declaration->type.' '.$declaration->period_start->toDateString())->all(),
        );
    }

    public function test_it_is_the_only_declaration_task_scheduled_each_morning(): void
    {
        Artisan::call('schedule:list');

        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'declarations:') || str_contains((string) $event->command, 'qonto:sync '));

        $this->assertCount(1, $events);
        $this->assertStringContainsString('declarations:daily', $events->first()->command);
        $this->assertSame('45 7 * * *', $events->first()->expression);
        $this->assertSame('Europe/Paris', $events->first()->timezone);
    }
}
