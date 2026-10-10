<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\CreateInstance;
use CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\ListInstances;
use CharlesStOlive\FilamentTma\Models\Instance;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

/** Mettre en place une installation sans rien générer à la main : `tma:install`, et le maître qui configure un client. */
class InstallTest extends TmaTestCase
{
    private string $envDir;

    protected function setUp(): void
    {
        parent::setUp();

        // tma:install écrit dans .env : jamais dans le vrai pendant les tests.
        $this->envDir = sys_get_temp_dir().'/tma-env-'.uniqid();
        File::makeDirectory($this->envDir);
        File::put($this->envDir.'/.env', "APP_NAME=Test\nTMA_MODE=client\n");
        $this->app->useEnvironmentPath($this->envDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->envDir);
        parent::tearDown();
    }

    private function env(): string
    {
        return File::get($this->envDir.'/.env');
    }

    public function test_install_generates_the_identifier_and_writes_the_env(): void
    {
        config(['filament-tma.instance.uid' => null]);

        $this->artisan('tma:install', ['--mode' => 'master', '--name' => 'Filbreeze', '--prefix' => 'fil'])
            ->expectsOutputToContain('.env mis à jour')
            ->assertSuccessful();

        $this->assertMatchesRegularExpression('/^TMA_INSTANCE_UID=[0-9A-Z]{26}$/m', $this->env());
        $this->assertStringContainsString("TMA_MODE=master\n", $this->env());
        $this->assertStringContainsString("TMA_TICKET_PREFIX=FIL\n", $this->env());
        $this->assertSame(1, substr_count($this->env(), 'TMA_MODE='));
    }

    public function test_the_identifier_never_changes_once_tickets_exist(): void
    {
        $this->asMaster();
        $this->serviceDeskTicket();

        $this->artisan('tma:install', ['--uid' => '01JTESTAUTRE00000000000000'])
            ->expectsOutputToContain('il ne change plus')
            ->assertFailed();

        $this->assertStringNotContainsString('TMA_INSTANCE_UID', $this->env());
    }

    public function test_a_client_creates_the_account_of_the_master_with_its_token(): void
    {
        $this->asClient();

        $this->artisan('tma:install', [
            '--mode' => 'client', '--uid' => self::CLIENT_UID, '--short-name' => 'EDM', '--master-url' => 'https://filbreeze.test',
            '--master-token' => 'jeton-du-maitre', '--master-account' => 'support@tma.local',
        ])->expectsOutputToContain('Son jeton')->assertSuccessful();

        $this->assertStringContainsString("TMA_INSTANCE_SHORT_NAME=EDM\n", $this->env());

        $account = User::query()->where('email', 'support@tma.local')->sole();
        $this->assertSame(0, $account->roles()->count());
        $this->assertSame(1, $account->tokens()->where('revoked', false)->count());
        $this->assertStringContainsString("TMA_MASTER_API_USER=support@tma.local\n", $this->env());
        $this->assertStringContainsString("TMA_MASTER_TOKEN=jeton-du-maitre\n", $this->env());

        // Relancée, elle révoque l'ancien jeton : un seul reste valide.
        $this->artisan('tma:install', ['--mode' => 'client', '--master-account' => 'support@tma.local'])->assertSuccessful();
        $this->assertSame(1, $account->tokens()->where('revoked', false)->count());
    }

    public function test_the_master_proposes_an_identifier_and_configures_the_client(): void
    {
        $this->asMaster();
        config(['filament-tma.api.public_url' => 'http://filbreeze.test']);
        $this->actingAs($this->agent('tma.instance.*'));

        $form = Livewire::test(CreateInstance::class);
        $uid = $form->get('data.uid');
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $uid);

        $form->fillForm(['name' => 'Desapp', 'short_name' => 'EDM', 'url' => 'https://desapp.test'])->call('create')->assertHasNoFormErrors();
        $instance = Instance::query()->sole();
        $this->assertSame($uid, $instance->uid);

        // L'application a son propre compte de service, créé avec elle : sans rôle, jamais celui de quelqu'un.
        $account = User::query()->where('email', 'tma-desapp@tma.local')->sole();
        $this->assertSame($account->getKey(), $instance->api_user_id);
        $this->assertSame(0, $account->roles()->count());

        $page = Livewire::test(ListInstances::class)
            ->callAction(TestAction::make('setupClient')->table($instance), ['ticket_prefix' => 'DES'])
            // La fenêtre du formulaire laisse la place à celle qui montre la configuration.
            ->assertActionMounted('clientEnv');

        $command = $page->get('mountedActions.0.arguments.command');
        $this->assertStringContainsString('php artisan tma:install --mode=client', $command);
        $this->assertStringContainsString("--uid={$uid}", $command);
        $this->assertStringContainsString("--master-url='http://filbreeze.test'", $command);
        $this->assertStringContainsString('--master-account=support@tma.local', $command);
        $this->assertStringContainsString("TMA_INSTANCE_UID={$uid}", $page->get('mountedActions.0.arguments.env'));
        // Le nom court du client part avec : « Équipe EDM », « Publier chez EDM » chez lui aussi.
        $this->assertStringContainsString("--short-name='EDM'", $command);
        $this->assertStringContainsString("TMA_INSTANCE_SHORT_NAME=EDM\n", $page->get('mountedActions.0.arguments.env'));

        $html = view('filament-tma::instances.client-env', ['command' => $command, 'env' => 'TMA_MODE=client'])->render();
        $this->assertStringContainsString('transmet son jeton', $html);
        $this->assertStringContainsString(e($command), $html);

        // Le jeton émis ouvre bien l'API du maître, au nom de cette application.
        $this->assertSame(1, $account->tokens()->where('revoked', false)->count());
        \Laravel\Passport\Passport::actingAs($account, [], 'api');
        $this->getJson('/api/tma/v1/master/ping')->assertOk()->assertJsonPath('mode', 'master');
    }

    public function test_a_persons_account_is_never_taken_as_service_account(): void
    {
        $this->asMaster();
        $person = User::factory()->withPermissions('panels.admin.access')->create(['email' => 'tma-desapp@tma.local']);
        $person->assignRole(\Spatie\Permission\Models\Role::findOrCreate('editeur', 'web'));

        $instance = Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test']);
        $account = $instance->ensureServiceAccount();

        $this->assertNotSame($person->getKey(), $account->getAuthIdentifier());
        $this->assertStringStartsWith('tma-desapp-', $account->email);
    }

    public function test_the_client_hands_its_token_and_the_master_calls_back(): void
    {
        $this->asMaster();
        $instance = Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'http://desapp.test']);
        $account = $instance->ensureServiceAccount();
        $desappUp = true;
        \Illuminate\Support\Facades\Http::fake(['desapp.test/*' => function () use (&$desappUp) {
            return $desappUp
                ? \Illuminate\Support\Facades\Http::response(['instance_uid' => self::CLIENT_UID])
                : \Illuminate\Support\Facades\Http::response([], 500);
        }]);
        \Laravel\Passport\Passport::actingAs($account, [], 'api');

        $this->postJson('/api/tma/v1/master/register', ['token' => str_repeat('j', 40)])
            ->assertOk()
            ->assertJsonPath('instance', 'Desapp')
            ->assertJsonPath('callback', true);

        $this->assertSame(str_repeat('j', 40), $instance->refresh()->token);
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.str_repeat('j', 40)));

        // Rappel impossible : le jeton est gardé, la réponse dit pourquoi.
        $desappUp = false;
        $this->postJson('/api/tma/v1/master/register', ['token' => str_repeat('k', 40)])
            ->assertOk()
            ->assertJsonPath('callback', false);
        $this->assertSame(str_repeat('k', 40), $instance->refresh()->token);
    }

    public function test_install_transmits_the_token_or_shows_it_when_it_cannot(): void
    {
        $this->asClient();
        $masterUp = true;
        \Illuminate\Support\Facades\Http::fake(['filbreeze.test/*' => function () use (&$masterUp) {
            return $masterUp
                ? \Illuminate\Support\Facades\Http::response(['instance' => 'Desapp', 'callback' => true, 'callback_error' => null])
                : \Illuminate\Support\Facades\Http::response([], 503);
        }]);

        $this->artisan('tma:install', [
            '--mode' => 'client', '--uid' => self::CLIENT_UID, '--master-url' => 'http://filbreeze.test',
            '--master-token' => 'jeton-du-maitre', '--master-account' => 'support@tma.local',
        ])
            ->expectsOutputToContain('Jeton transmis au Support')
            ->assertSuccessful();

        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->url() === 'http://filbreeze.test/api/tma/v1/master/register'
            && $request->hasHeader('Authorization', 'Bearer jeton-du-maitre')
            && strlen($request['token']) > 100);

        // Le Support ne répond pas : le jeton s'affiche, à coller à la main.
        $masterUp = false;
        $this->artisan('tma:install', ['--mode' => 'client', '--master-account' => 'support@tma.local'])
            ->expectsOutputToContain('Saisir le jeton à la main')
            ->assertSuccessful();
    }
}
