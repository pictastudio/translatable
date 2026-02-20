<?php

namespace PictaStudio\Translatable\Console\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

class InstallCommand extends Command
{
    protected $signature = 'translatable:install';

    protected $description = 'Install Translatable package';

    public function handle(): int
    {
        $this->components->info('Installing Translatable package...');

        $this->components->info('Publishing translatable configuration...');
        $this->call('vendor:publish', ['--tag' => 'translatable-config']);

        $this->components->info('Publishing translatable migrations...');
        $this->call('vendor:publish', ['--tag' => 'translatable-migrations']);

        if (confirm('Do you want to run migrations now?')) {
            $this->call('migrate');
        }

        $this->components->info('Translatable package installed successfully.');

        return self::SUCCESS;
    }
}
