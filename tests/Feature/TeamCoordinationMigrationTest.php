<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeamCoordinationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_after_notification_table_failure_preserves_completed_steps_and_data(): void
    {
        $user = User::factory()->create();
        $organization = DB::table('organizations')->insertGetId(['name' => 'Existing company']);
        DB::table('departments')->insert(['organization_id' => $organization, 'name' => 'Engineering']);
        DB::table('organization_users')->insert(['organization_id' => $organization, 'user_id' => $user->id, 'role' => 'owner', 'department' => 'Engineering']);
        Schema::drop('workspace_notifications');
        DB::table('migrations')->where('migration', '2026_09_12_193622_add_team_and_site_coordination')->delete();

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('workspace_notifications'));
        $this->assertTrue(Schema::hasTable('invitations'));
        $this->assertTrue(Schema::hasTable('site_instructions'));
        $this->assertDatabaseHas('departments', ['organization_id' => $organization, 'name' => 'Engineering']);
        $this->assertDatabaseHas('organization_users', ['user_id' => $user->id, 'department' => 'Engineering']);
        $this->assertDatabaseHas('migrations', ['migration' => '2026_09_12_193622_add_team_and_site_coordination']);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_notification_creation_timestamp_has_an_explicit_default(): void
    {
        $user = User::factory()->create();
        $organization = DB::table('organizations')->insertGetId(['name' => 'Company']);
        $id = DB::table('workspace_notifications')->insertGetId(['organization_id' => $organization, 'user_id' => $user->id, 'title' => 'Notification', 'url' => '/notifications']);

        $this->assertNotNull(DB::table('workspace_notifications')->where('id', $id)->value('created_at'));
        $this->assertNull(DB::table('workspace_notifications')->where('id', $id)->value('read_at'));
    }
}
