<?php

namespace App\Console\Commands;

use App\Actions\InstallMinistry;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class InstallMinistryCommand extends Command
{
    protected $signature = 'ministry:install
        {--name= : Public ministry name}
        {--domain= : Public domain without protocol}
        {--timezone=UTC : IANA time zone}
        {--location=Main Campus : First location name}
        {--tagline= : Public tagline}
        {--contact-email= : Public contact email}
        {--admin-name= : Initial administrator name}
        {--admin-email= : Initial administrator email}';

    protected $description = 'Configure this installation and create its first administrator';

    public function handle(InstallMinistry $installer): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run interactively so the administrator password is entered securely.');

            return self::FAILURE;
        }

        $input = [
            'name' => $this->option('name') ?: $this->ask('Ministry name'),
            'domain' => $this->option('domain') ?: $this->ask('Public domain (without https://)'),
            'timezone' => $this->option('timezone'),
            'location' => $this->option('location'),
            'tagline' => $this->option('tagline'),
            'contact_email' => $this->option('contact-email'),
            'admin_name' => $this->option('admin-name') ?: $this->ask('Administrator name'),
            'admin_email' => $this->option('admin-email') ?: $this->ask('Administrator email'),
            'password' => $this->secret('Administrator password (at least 12 characters)'),
        ];

        try {
            $installer->execute($input);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->info('Ministry configured. All available modules are enabled.');

        return self::SUCCESS;
    }
}
