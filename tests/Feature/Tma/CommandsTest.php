<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\TimeEntry;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

/** Les commandes `tma:*` (lot 1d), protégées par un jeton personnel, dans le maître et depuis un client. */
class CommandsTest extends TmaTestCase
{
    private function tokenFor(User $user): string
    {
        Artisan::call('passport:client', ['--personal' => true, '--name' => 'Commandes TMA', '--provider' => 'users', '--no-interaction' => true]);

        return $user->createToken('tma-cli')->accessToken;
    }

    public function test_an_ai_lists_reads_and_logs_time_with_a_personal_token(): void
    {
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket('in_progress');
        $token = $this->tokenFor($agent);

        $this->artisan('tma:list', ['--token' => $token, '--json' => true])
            ->expectsOutputToContain('"ref": "DES-0001"')
            ->assertSuccessful();

        $this->artisan('tma:show', ['ref' => 'DES-0001', '--token' => $token])
            ->expectsOutputToContain('DES-0001 — Export des parrains')
            ->assertSuccessful();

        $summary = "Correction de l'export CSV des parrains.\n\nLa requête filtrait sur un statut obsolète ; ajout d'un test.";
        $this->artisan('tma:log', ['ref' => 'DES-0001', '--hours' => '2', '--summary' => $summary, '--date' => '2026-10-07', '--token' => $token])
            ->expectsOutputToContain('2 h enregistrées le 2026-10-07')
            ->assertSuccessful();

        $time = TimeEntry::query()->sole();
        $this->assertSame(120, $time->minutes);
        $this->assertSame('command', $time->origin->value);
        $this->assertTrue($time->actor->isUser($agent));
        $this->assertSame("Correction de l'export CSV des parrains.", $time->description);

        // Le résumé est enregistré à part, dans la chronologie, partagé avec le client.
        $entry = Entry::query()->sole();
        $this->assertSame('summary', $entry->type->value);
        $this->assertStringContainsString('<p>La requête filtrait', $entry->body);
        $this->assertSame($time->ulid, $entry->meta['time_entry']);

        $this->artisan('tma:comment', ['ref' => 'DES-0001', '--body' => 'À surveiller au prochain import.', '--note' => true, '--token' => $token, '--json' => true])
            ->expectsOutputToContain('"type": "note"')
            ->assertSuccessful();
    }

    public function test_project_tasks_are_listed_and_take_time_too(): void
    {
        $this->asMaster();
        Http::fake();
        Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test']);
        $agent = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.*', 'tma.task.*')->create();
        $task = app(TaskWorkflow::class)->create(['title' => 'Portail parrain', 'target_instance_uid' => self::CLIENT_UID, 'project' => 'Parrains'], Context::ui($agent));
        $this->serviceDeskTicket();
        $token = $this->tokenFor($agent);

        $this->artisan('tma:list', ['--type' => 'tasks', '--token' => $token, '--json' => true])
            ->expectsOutputToContain('"ref": "T-0001"')
            ->doesntExpectOutputToContain('DES-0001')
            ->assertSuccessful();

        Artisan::call('tma:list', ['--token' => $token, '--json' => true]);
        // Le plus récemment actif d'abord : le ticket, puis la tâche.
        $this->assertSame(['ticket', 'task'], array_column(json_decode(Artisan::output(), true), 'kind'));

        $this->artisan('tma:log', ['ref' => 'T-0001', '--days' => '0.5', '--summary' => 'Atelier de cadrage', '--token' => $token])
            ->expectsOutputToContain('3 h 30 enregistrées')
            ->assertSuccessful();

        $this->assertSame(210, $task->timeEntries()->sole()->minutes);
        $this->artisan('tma:show', ['ref' => 'T-0001', '--token' => $token])->expectsOutputToContain('Projet : Parrains')->assertSuccessful();
    }

    public function test_no_token_no_command(): void
    {
        $this->asMaster();
        $this->serviceDeskTicket();

        $this->artisan('tma:list')->expectsOutputToContain('Jeton manquant')->assertFailed();
        $this->artisan('tma:list', ['--token' => 'pas-un-jeton'])->expectsOutputToContain('Jeton invalide')->assertFailed();
    }

    public function test_the_command_has_the_rights_of_its_user(): void
    {
        $this->asMaster();
        $this->serviceDeskTicket();
        $viewer = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view')->create();
        $token = $this->tokenFor($viewer);

        $this->artisan('tma:log', ['ref' => 'DES-0001', '--minutes' => 30, '--token' => $token, '--json' => true])
            ->expectsOutputToContain('"error"')
            ->assertFailed();
        $this->assertSame(0, TimeEntry::query()->count());

        $this->artisan('tma:show', ['ref' => 'DES-9999', '--token' => $token])->expectsOutputToContain('introuvable')->assertFailed();
    }

    public function test_from_a_client_application_the_command_acts_in_the_master(): void
    {
        $this->asClient();
        config(['filament-tma.master.url' => 'https://master.test', 'filament-tma.master.token' => 'jeton-instance']);
        Http::fake(['master.test/api/tma/v1/agent/tickets/DES-0042/time' => Http::response(['data' => [
            'ticket' => 'DES-0042', 'time_entry' => ['ulid' => 'x', 'minutes' => 90, 'worked_on' => '2026-10-08', 'billable' => true],
            'summary_entry' => 'y', 'time_total_minutes' => 300,
        ]], 201)]);

        $this->artisan('tma:log', ['ref' => 'DES-0042', '--hours' => '1.5', '--summary' => 'Recette', '--token' => 'jeton-perso-filbreeze'])
            ->expectsOutputToContain('1 h 30 enregistrées')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer jeton-perso-filbreeze')
            && $request['minutes'] === 90 && $request['summary'] === 'Recette');
    }

    public function test_the_agent_api_is_for_master_users_not_for_client_applications(): void
    {
        $this->asMaster();
        $this->serviceDeskTicket();
        $service = User::factory()->create();
        Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test', 'api_user_id' => $service->getKey()]);

        Passport::actingAs($service, [], 'api');
        $this->postJson('/api/tma/v1/agent/tickets/DES-0001/time', ['minutes' => 30])->assertForbidden();

        Passport::actingAs($this->agent(), [], 'api');
        $this->postJson('/api/tma/v1/agent/tickets/DES-0001/time', ['minutes' => 30, 'summary' => 'Vérification'])
            ->assertCreated()
            ->assertJsonPath('data.time_entry.minutes', 30);
        $this->assertSame('api', TimeEntry::query()->sole()->origin->value);

        $this->getJson('/api/tma/v1/agent/tickets/DES-0404')->assertNotFound();
    }
}
