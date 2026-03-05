<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportKnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_it_fails_when_no_admin_user_exists(): void
    {
        User::factory()->create()->assignRole('user');

        $this->artisan('import:knowledge-base')
            ->expectsOutputToContain('No admin user found')
            ->assertExitCode(1);
    }

    public function test_it_uses_admin_user_as_uploader(): void
    {
        $regularUser = User::factory()->create();
        $regularUser->assignRole('user');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        File::shouldReceive('isDirectory')->once()->andReturn(true);
        File::shouldReceive('glob')->once()->andReturn([]);

        $this->artisan('import:knowledge-base')
            ->expectsOutputToContain('No .md files found')
            ->assertExitCode(0);
    }

    public function test_it_fails_when_knowledge_directory_missing(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        File::shouldReceive('isDirectory')->once()->andReturn(false);

        $this->artisan('import:knowledge-base')
            ->expectsOutputToContain('Directory not found')
            ->assertExitCode(1);
    }
}
