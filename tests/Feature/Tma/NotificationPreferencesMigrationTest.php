<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentPermissionManager\FilamentPermissionManagerServiceProvider;
use CharlesStOlive\FilamentTma\FilamentTmaPlugin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationPreferencesMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.tma_migration_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('tma_migration_test');
        Schema::create('users', fn (Blueprint $table) => $table->id());
        $migration = require dirname((new \ReflectionClass(FilamentPermissionManagerServiceProvider::class))->getFileName(), 2)
            .'/database/migrations/2026_10_18_000000_create_user_options_table.php';
        $migration->up();
    }

    protected function tearDown(): void
    {
        DB::purge('tma_migration_test');

        parent::tearDown();
    }

    private function user(): User
    {
        $id = DB::table('users')->insertGetId([]);

        return User::query()->findOrFail($id);
    }

    private function migration(): Migration
    {
        return require dirname((new \ReflectionClass(FilamentTmaPlugin::class))->getFileName(), 2)
            .'/database/migrations/move_tma_notification_preferences_to_user_options.php.stub';
    }

    private function legacyTable(): void
    {
        Schema::create('tma_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id', 64)->unique();
            $table->json('channels')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_moves_channels_without_overwriting_existing_choices_and_can_restore_them(): void
    {
        $this->legacyTable();
        $user = $this->user();
        $customized = $this->user();
        $default = $this->user();
        DB::table('tma_notification_preferences')->insert([
            ['user_id' => (string) $user->id, 'channels' => '["database"]'],
            ['user_id' => (string) $customized->id, 'channels' => '["mail"]'],
            ['user_id' => (string) $default->id, 'channels' => null],
            ['user_id' => '999999', 'channels' => '[]'],
            ['user_id' => 'not-a-local-user', 'channels' => '[]'],
        ]);
        DB::table('user_options')->insert([
            'user_id' => $customized->id, 'name' => 'tma',
            'data' => '{"notifications":{"channels":[],"digest":false}}',
        ]);

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        $this->assertFalse(Schema::hasTable('tma_notification_preferences'));
        $this->assertSame(['database'], $user->userOption('tma.notifications.channels'));
        $this->assertSame([], $customized->userOption('tma.notifications.channels'));
        $this->assertFalse($customized->userOption('tma.notifications.digest'));
        $this->assertDatabaseMissing('user_options', ['user_id' => $default->id, 'name' => 'tma']);
        $this->assertDatabaseCount('user_options', 2);

        $migration->down();
        $this->assertSame(['database'], json_decode(DB::table('tma_notification_preferences')->where('user_id', (string) $user->id)->value('channels'), true, 512, JSON_THROW_ON_ERROR));
        $this->assertDatabaseCount('tma_notification_preferences', 2);
    }

    public function test_without_permission_manager_the_legacy_table_is_removed(): void
    {
        $this->legacyTable();
        Schema::drop('user_options');

        $this->migration()->up();

        $this->assertFalse(Schema::hasTable('tma_notification_preferences'));
        $this->assertFalse(Schema::hasTable('user_options'));
    }

    public function test_invalid_channel_data_keeps_the_legacy_table_and_rolls_back_the_copy(): void
    {
        $this->legacyTable();
        $first = $this->user();
        $second = $this->user();
        DB::table('tma_notification_preferences')->insert([
            ['user_id' => (string) $first->id, 'channels' => '["database"]'],
            ['user_id' => (string) $second->id, 'channels' => '{"not":"a list"}'],
        ]);

        try {
            $this->migration()->up();
            $this->fail('Invalid channels must stop the migration.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Invalid TMA notification channels', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('tma_notification_preferences'));
        $this->assertDatabaseCount('user_options', 0);
    }
}
