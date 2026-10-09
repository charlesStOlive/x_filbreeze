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
            '--mode' => 'client', '--uid' => self::CLIENT_UID, '--master-url' => 'https://filbreeze.test',
            '--master-token' => 'jeton-du-maitre', '--master-account' => 'centre-de-service@tma.local',
        ])->expectsOutputToContain('Son jeton')->assertSuccessful();

        $account = User::query()->where('email', 'centre-de-service@tma.local')->sole();
        $this->assertSame(0, $account->roles()->count());
        $this->assertSame(1, $account->tokens()->where('revoked', false)->count());
        $this->assertStringContainsString("TMA_MASTER_API_USER=centre-de-service@tma.local\n", $this->env());
        $this->assertStringContainsString("TMA_MASTER_TOKEN=jeton-du-maitre\n", $this->env());

        // Relancée, elle révoque l'ancien jeton : un seul reste valide.
        $this->artisan('tma:install', ['--mode' => 'client', '--master-account' => 'centre-de-service@tma.local'])->assertSuccessful();
        $this->assertSame(1, $account->tokens()->where('revoked', false)->count());
    }

    public function test_the_master_proposes_an_identifier_and_configures_the_client(): void
    {
        $this->asMaster();
        $this->actingAs($this->agent('tma.instance.*'));

        $form = Livewire::test(CreateInstance::class);
        $uid = $form->get('data.uid');
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $uid);

        $form->fillForm(['name' => 'Desapp', 'url' => 'https://desapp.test'])->call('create')->assertHasNoFormErrors();
        $instance = Instance::query()->sole();
        $this->assertSame($uid, $instance->uid);

        $page = Livewire::test(ListInstances::class)
            ->callAction(TestAction::make('setupClient')->table($instance), [
                'account_email' => 'desapp@tma.local', 'master_url' => 'http://filbreeze.test', 'ticket_prefix' => 'DES',
            ])
            // La fenêtre du formulaire laisse la place à celle qui montre la configuration.
            ->assertActionMounted('clientEnv');

        $command = $page->get('mountedActions.0.arguments.command');
        $this->assertStringContainsString('php artisan tma:install --mode=client', $command);
        $this->assertStringContainsString("--uid={$uid}", $command);
        $this->assertStringContainsString("--master-url='http://filbreeze.test'", $command);
        $this->assertStringContainsString('--master-account=centre-de-service@tma.local', $command);
        $this->assertStringContainsString("TMA_INSTANCE_UID={$uid}", $page->get('mountedActions.0.arguments.env'));

        $html = view('filament-tma::instances.client-env', ['command' => $command, 'env' => 'TMA_MODE=client'])->render();
        $this->assertStringContainsString('Ce jeton ne sera plus affiché', $html);
        $this->assertStringContainsString(e($command), $html);

        $account = User::query()->where('email', 'desapp@tma.local')->sole();
        $this->assertSame($account->getKey(), $instance->refresh()->api_user_id);

        // Le jeton émis ouvre bien l'API du maître, au nom de cette application.
        $token = $account->tokens()->where('revoked', false)->sole();
        $this->assertNotNull($token);
        \Laravel\Passport\Passport::actingAs($account, [], 'api');
        $this->getJson('/api/tma/v1/master/ping')->assertOk()->assertJsonPath('mode', 'master');
    }
}
