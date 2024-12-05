<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunPrivateSeeder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:seed-private {seeder : The name of the private seeder (without namespace)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a private database seeder by name';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $seederName = $this->argument('seeder');
        $namespace = "Database\\Seeders\\Private\\";
        $className = $namespace . $seederName;

        // Vérifiez si la classe existe
        if (!class_exists($className)) {
            $this->error("Seeder class '{$className}' not found. Make sure it exists in 'database/seeders/private/'.");
            return Command::FAILURE;
        }

        // Exécutez le seeder
        $this->info("Running private seeder: {$className}");
        Artisan::call('db:seed', ['--class' => $className]);
        $this->output->write(Artisan::output());

        return Command::SUCCESS;
    }
}
