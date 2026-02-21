<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SystemSettingsSeeder::class,
            DefaultOrganizationSeeder::class,
            // Phase 3
            NotificationTemplatesSeeder::class,
            // Phase 4
            SampleWorkflowProcessSeeder::class,
            // Phase 5
            VideoConferenceProvidersSeeder::class,
            // Phase 6
            Phase6RegistrySeeder::class,
        ]);
    }
}
