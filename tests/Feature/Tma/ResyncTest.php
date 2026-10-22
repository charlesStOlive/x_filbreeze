<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\EditInstance;
use CharlesStOlive\FilamentTma\Filament\Resources\Projects\Pages\ViewProject;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/** Remettre d'accord le maître et ses applications : les noms, puis les tickets, les tâches, les projets. */
class ResyncTest extends TmaTestCase
{
    private Instance $desapp;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asMaster();
        $this->desapp = Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test', 'token' => 'jeton-desapp']);
        $this->project = Project::create(['title' => 'Portail parrain', 'target_instance_uid' => self::CLIENT_UID]);

        // Desapp : Ada a changé de nom ; il reçoit ce qu'on lui envoie.
        Http::fake([
            'desapp.test/api/tma/v1/users*' => Http::response(['data' => [['id' => '7', 'name' => 'Ada King', 'email' => 'ada@client.test', 'service_desk' => true]]]),
            'desapp.test/*' => Http::response(['missing_media' => []]),
        ]);
    }

    private function task(string $number, string $state = 'in_progress', ?Project $project = null): ProjectTask
    {
        return ProjectTask::query()->create([
            'number' => $number, 'target_instance_uid' => self::CLIENT_UID, 'project_id' => ($project ?? $this->project)->getKey(),
            'title' => "Tâche {$number}", 'state' => $state, 'published_at' => $state === 'draft' ? null : now(),
        ]);
    }

    /** @return array<int, string> les numéros envoyés à Desapp */
    private function sent(): array
    {
        return Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/sync'))
            ->map(fn (array $pair): string => $pair[0]['task']['number'] ?? $pair[0]['ticket']['number'])
            ->sort()->values()->all();
    }

    public function test_the_command_refreshes_the_names_then_sends_again_what_is_shared(): void
    {
        // « Super Admin » renommé « Charles Sto (N) » : sa copie garde l'ancien nom tant qu'il n'a pas agi.
        $charles = $this->supportMember();
        $charles->forceFill(['name' => 'Charles Sto (N)'])->save();
        $copy = Actor::forUser($charles);
        $copy->forceFill(['name' => 'Super Admin'])->save();
        $ada = $this->clientActor();
        $ticket = $this->serviceDeskTicket();
        $this->task('T-0001');
        $this->task('T-0002', 'draft');
        $other = $this->task('T-0003', project: Project::create(['title' => 'Compta', 'target_instance_uid' => self::CLIENT_UID]));

        // À blanc : ce qui partirait, sans rien envoyer ni renommer.
        $this->artisan('tma:resync', ['--all' => true, '--dry-run' => true])
            ->expectsOutputToContain('3 ticket(s) ou tâche(s) partiraient.')
            ->assertSuccessful();
        Http::assertNothingSent();
        $this->assertSame('Super Admin', $copy->refresh()->name);

        $this->artisan('tma:resync', ['--all' => true])
            ->expectsOutputToContain('2 nom(s) mis à jour.')
            ->expectsOutputToContain('3 envoi(s) demandé(s), par la file.')
            ->assertSuccessful();

        $this->assertSame('Charles Sto (N)', $copy->refresh()->name);
        $this->assertSame('Ada King', $ada->refresh()->name);
        $this->assertSame([$ticket->number, 'T-0001', 'T-0003'], $this->sent());

        // Un projet seul : ses tâches publiées ; il faut dire quoi renvoyer.
        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
        $this->artisan('tma:resync', ['--project' => [$other->project->ulid]])->assertSuccessful();
        $this->assertSame(['T-0003'], $this->sent());
        $this->artisan('tma:resync')->assertFailed();
        $this->artisan('tma:resync', ['--instance' => 'Inconnue'])->assertFailed();

        // Dans une application cliente, rien : c'est le maître qui renvoie.
        $this->asClient();
        $this->artisan('tma:resync', ['--all' => true])->assertFailed();
    }

    public function test_an_admin_sends_again_a_project_or_a_whole_application(): void
    {
        $this->task('T-0001');
        $this->serviceDeskTicket();
        $admin = User::factory()->withPermissions('panels.admin.access', 'tma.project.*', 'tma.task.sync', 'tma.instance.*')->create();
        $this->actingAs($admin);

        Livewire::test(ViewProject::class, ['record' => $this->project->getKey()])
            ->callAction('resync')
            ->assertNotified('Envoi demandé');
        $this->assertSame(['T-0001'], $this->sent());

        Http::fake(['desapp.test/*' => Http::response(['missing_media' => []])]);
        Livewire::test(EditInstance::class, ['record' => $this->desapp->getKey()])
            ->assertActionVisible('resyncAll')
            ->callAction('resyncAll')
            ->assertNotified('Envoi demandé');
        $this->assertSame(['DES-0001', 'T-0001'], $this->sent());

        // Sans le droit de relancer une synchronisation, pas de bouton sur le projet.
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'tma.project.*')->create());
        Livewire::test(ViewProject::class, ['record' => $this->project->getKey()])->assertActionHidden('resync');
    }

    public function test_a_linked_support_member_takes_the_new_name_of_the_master(): void
    {
        $this->asClient();
        $charles = User::factory()->create(['email' => 'charles@notilac.fr']);
        $agent = Agent::query()->create(['user_id' => $charles->getKey(), 'master_uid' => self::MASTER_UID, 'master_user_id' => '1', 'name' => 'Super Admin', 'email' => 'charles@notilac.fr', 'token' => 'jeton', 'linked_at' => now()]);

        Mirror::actor(['instance_uid' => self::MASTER_UID, 'id' => '1', 'name' => 'Charles Sto (N)', 'email' => 'charles@notilac.fr']);

        $this->assertSame('Charles Sto (N)', $agent->refresh()->name);
        $this->assertSame(['1' => 'Charles Sto (N)'], Addressables::supportContacts());
    }

    public function test_a_person_from_here_keeps_the_name_known_here(): void
    {
        $this->asClient();
        $leo = User::factory()->create(['name' => 'Léo Neuf', 'email' => 'leo@client.test']);

        // Le maître renvoie une copie d'avant le changement de nom : elle ne l'emporte pas sur celui d'ici.
        $actor = Mirror::actor(['instance_uid' => self::CLIENT_UID, 'id' => (string) $leo->getKey(), 'name' => 'Léo Ancien', 'email' => 'leo@client.test']);

        $this->assertSame('Léo Neuf', $actor->name);
        $this->assertTrue($actor->isUser($leo));
    }
}
