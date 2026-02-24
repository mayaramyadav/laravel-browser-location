<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\Commands;

use Illuminate\Console\Command;

class InstallBrowserLocationCommand extends Command
{
    protected $signature = 'browser-location:install
        {--force : Overwrite published package files}
        {--without-migrate : Skip running database migrations}';

    protected $description = 'Install Laravel Browser Location config, views, and migrations';

    public function handle(): int
    {
        $this->components->info('Publishing configuration...');
        $this->call('vendor:publish', $this->publishArguments('browser-location-config'));

        $this->components->info('Publishing Blade views...');
        $this->call('vendor:publish', $this->publishArguments('browser-location-views'));

        $this->components->info('Publishing migrations...');
        $this->call('vendor:publish', $this->publishArguments('browser-location-migrations'));

        if (! $this->option('without-migrate')) {
            $this->components->info('Running migrations...');
            $this->call('migrate');
        }

        $this->newLine();
        $this->components->info('Laravel Browser Location is ready.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, bool|string>
     */
    private function publishArguments(string $tag): array
    {
        $arguments = ['--tag' => $tag];

        if ((bool) $this->option('force')) {
            $arguments['--force'] = true;
        }

        return $arguments;
    }
}
