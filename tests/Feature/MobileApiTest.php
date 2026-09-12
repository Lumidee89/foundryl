<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_native_registration_and_operational_writes_return_json_and_preserve_rules(): void
    {
        $this->getJson('/api/v1/session')->assertOk()->assertJsonStructure(['csrf_token', 'user']);
        $this->postJson('/api/v1/register', ['name' => 'Mobile Owner', 'company' => 'Mobile Builders', 'email' => 'mobile@example.test', 'password' => 'abcdef', 'password_confirmation' => 'abcdef'])->assertOk()->assertJsonPath('user.email', 'mobile@example.test')->assertJsonMissingPath('user.password');
        $this->postJson('/api/v1/projects', ['name' => 'Mobile site', 'location' => 'Abuja'])->assertOk();
        $project = DB::table('projects')->value('id');
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('stats.projects', 1)->assertJsonPath('auth.user.email', 'mobile@example.test');
        $this->postJson('/api/v1/activities', ['project_id' => $project, 'title' => 'Pour slab', 'location' => 'Block A', 'responsible' => 'Supervisor', 'planned_date' => today()->toDateString()])->assertOk();
        $id = DB::table('activities')->value('id');
        $this->postJson('/api/v1/activities/'.$id, ['status' => 'Delayed', 'progress' => 10])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson('/api/v1/activities/'.$id, ['status' => 'Completed', 'progress' => 100])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'activity.updated']);
        $this->postJson('/api/v1/logout')->assertOk();
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_mobile_reads_and_writes_reject_foreign_projects_and_viewer_mutations(): void
    {
        $user = User::factory()->create();
        $organization = DB::table('organizations')->insertGetId(['name' => 'Assigned company']);
        DB::table('organization_users')->insert(['organization_id' => $organization, 'user_id' => $user->id, 'role' => 'viewer']);
        $foreign = DB::table('organizations')->insertGetId(['name' => 'Private company']);
        $project = DB::table('projects')->insertGetId(['organization_id' => $foreign, 'name' => 'Private site', 'location' => 'Lagos']);
        $this->actingAs($user)->getJson('/api/v1/projects/'.$project)->assertNotFound();
        $this->getJson('/api/v1/activities?project='.$project)->assertNotFound();
        $this->postJson('/api/v1/workers', ['project_id' => $project, 'name' => 'Worker', 'trade' => 'Mason'])->assertForbidden();
        $this->getJson('/api/v1/projects')->assertOk()->assertJsonCount(0, 'projects');
        $this->getJson('/api/v1/platform')->assertNotFound();
    }

    public function test_superadmin_mobile_login_does_not_bypass_platform_mfa(): void
    {
        $user = User::factory()->create(['password' => 'abcdef']);
        $user->forceFill(['is_superadmin' => true])->save();
        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'abcdef'])->assertForbidden();
        $this->assertGuest();
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_mobile_account_and_workspace_selection_work_without_membership(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/workspaces')->assertOk()->assertJsonCount(0, 'workspaces');
        $this->getJson('/api/v1/account')->assertOk()->assertJsonPath('profile.email', $user->email);
        $this->getJson('/api/v1/dashboard')->assertForbidden();
    }

    public function test_native_password_recovery_keeps_account_existence_private(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $known = $this->postJson('/api/v1/forgot-password', ['email' => $user->email])->assertOk();
        $unknown = $this->postJson('/api/v1/forgot-password', ['email' => 'unknown@example.test'])->assertOk();
        $this->assertSame($known->json('message'), $unknown->json('message'));
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_native_password_change_validates_current_password_and_returns_new_csrf_token(): void
    {
        $user = User::factory()->create(['password' => 'oldpass']);
        $data = ['current_password' => 'wrong', 'password' => 'abcdef', 'password_confirmation' => 'abcdef'];
        $this->actingAs($user)->postJson('/api/v1/account-password', $data)->assertUnprocessable();
        $data['current_password'] = 'oldpass';
        $this->postJson('/api/v1/account-password', $data)->assertOk()->assertJsonStructure(['message', 'csrf_token']);
        $this->assertTrue(Hash::check('abcdef', $user->fresh()->password));
    }
}
