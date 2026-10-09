<?php

namespace App\Console\Commands;

use App\Modules\Administration\Database\Seeders\EmailTemplateSeeder;
use App\Modules\Administration\Database\Seeders\PermissionSeeder;
use App\Modules\Administration\Database\Seeders\RoleSeeder;
use App\Modules\Reports\Database\Seeders\ScheduledJobSeeder;
use Illuminate\Console\Command;

class ReseedPermissions extends Command
{
    protected $signature = 'permissions:reseed
                            {--only= : Comma-separated extra steps (always runs permissions + role_permissions first): email_templates, scheduled_jobs}';

    protected $description = 'Reseed permission definitions and role assignments; use --only to also reseed templates or jobs';

    /** @var list<string> */
    private const DEFAULT_STEPS = ['permissions', 'role_permissions'];

    /** @var array<string, callable(): void> */
    protected array $steps = [];

    public function __construct()
    {
        parent::__construct();

        $this->steps = [
            'permissions' => fn () => $this->seedPermissions(),
            'role_permissions' => fn () => $this->seedRolePermissions(),
            'email_templates' => fn () => $this->seedEmailTemplates(),
            'scheduled_jobs' => fn () => $this->seedScheduledJobs(),
        ];
    }

    public function handle(): int
    {
        $selected = $this->resolveSteps();

        if ($selected === null) {
            return self::FAILURE;
        }

        foreach ($selected as $key => $step) {
            $step();
            $this->info("  • {$key}");
        }

        $this->info('Permissions reseeded successfully.');

        return self::SUCCESS;
    }

    protected function seedPermissions(): void
    {
        $this->call(PermissionSeeder::class);
    }

    protected function seedRolePermissions(): void
    {
        $this->call(RoleSeeder::class);
    }

    protected function seedEmailTemplates(): void
    {
        $this->call(EmailTemplateSeeder::class);
    }

    protected function seedScheduledJobs(): void
    {
        $this->call(ScheduledJobSeeder::class);
    }

    /**
     * @return array<string, callable(): void>|null
     */
    protected function resolveSteps(): ?array
    {
        $only = trim((string) $this->option('only'));

        if ($only === '') {
            return $this->stepsForKeys(self::DEFAULT_STEPS);
        }

        $keys = array_filter(array_map(
            fn (string $key) => strtolower(trim(str_replace('-', '_', $key))),
            explode(',', $only)
        ));

        $selected = [];
        $invalid = [];

        foreach ($keys as $key) {
            if (isset($this->steps[$key])) {
                $selected[$key] = $this->steps[$key];
            } else {
                $invalid[] = $key;
            }
        }

        if ($invalid !== []) {
            $this->error('Unknown step(s): '.implode(', ', $invalid));
            $this->line('Valid options: '.implode(', ', array_keys($this->steps)));

            return null;
        }

        if ($selected === []) {
            $this->error('Provide at least one step via --only=');

            return null;
        }

        $mergedKeys = array_values(array_unique([
            ...self::DEFAULT_STEPS,
            ...array_keys($selected),
        ]));

        return $this->stepsForKeys($mergedKeys);
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, callable(): void>
     */
    protected function stepsForKeys(array $keys): array
    {
        $selected = [];

        foreach ($keys as $key) {
            if (isset($this->steps[$key])) {
                $selected[$key] = $this->steps[$key];
            }
        }

        return $selected;
    }
}
