<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountAndTeamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function workspace(): array
    {
        $user = User::factory()->create();
        $organization = DB::table('organizations')->insertGetId(['name' => 'Builders']);
        DB::table('organization_users')->insert(['organization_id' => $organization, 'user_id' => $user->id, 'role' => 'owner']);
        $project = DB::table('projects')->insertGetId(['organization_id' => $organization, 'name' => 'Site', 'location' => 'Abuja']);

        return [$user, $organization, $project];
    }

    public function test_home_identifies_the_browser_user_without_requiring_a_workspace(): void
    {
        $this->get('/')->assertInertia(fn ($page) => $page->where('auth.user', null));
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertInertia(fn ($page) => $page->where('auth.user.email', $user->email)->missing('auth.user.password'));
    }

    public function test_reset_accepts_six_characters_revokes_sessions_and_consumes_token(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'other-browser', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $token = Password::createToken($user);
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'abcde', 'password_confirmation' => 'abcde'];
        $this->post('/reset-password', $data)->assertSessionHasErrors('password');
        $data['password'] = $data['password_confirmation'] = 'abcdef';
        $this->post('/reset-password', $data)->assertRedirect('/login');
        $this->assertTrue(Hash::check('abcdef', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-browser']);
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
    }

    public function test_email_verification_is_signed_and_bound_to_current_user(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->actingAs($user)->get($url)->assertRedirect('/account');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_invitation_uses_stored_role_and_project_and_can_only_be_accepted_once(): void
    {
        Notification::fake();
        [$owner, $organization, $project] = $this->workspace();
        $this->actingAs($owner)->post('/team/invitations', ['email' => 'invite@example.test', 'role' => 'supervisor', 'project_ids' => [$project]])->assertRedirect()->assertSessionHasNoErrors();
        $token = null;
        Notification::assertSentOnDemand(WorkspaceInvitation::class, function ($notification) use (&$token) {
            $token = basename($notification->acceptUrl);

            return true;
        });
        $this->assertDatabaseHas('invitations', ['token_hash' => hash('sha256', $token)]);
        $this->post('/logout');
        $this->post('/invitations/'.$token, ['name' => 'Site Supervisor', 'password' => 'abcdef', 'password_confirmation' => 'abcdef', 'role' => 'owner', 'is_superadmin' => true])->assertRedirect('/dashboard');
        $member = User::where('email', 'invite@example.test')->firstOrFail();
        $this->assertFalse($member->is_superadmin);
        $this->assertDatabaseHas('organization_users', ['organization_id' => $organization, 'user_id' => $member->id, 'role' => 'supervisor']);
        $this->assertDatabaseHas('project_users', ['project_id' => $project, 'user_id' => $member->id]);
        $this->get('/invitations/'.$token)->assertNotFound();
        $this->get('/team')->assertForbidden();
    }

    public function test_expired_invitation_and_foreign_project_invitation_are_rejected(): void
    {
        Notification::fake();
        [$owner, $organization, $project] = $this->workspace();
        [$other, $foreignOrganization, $foreignProject] = $this->workspace();
        $this->actingAs($owner)->post('/team/invitations', ['email' => 'invite@example.test', 'role' => 'viewer', 'project_ids' => [$foreignProject]])->assertNotFound();
        Notification::assertNothingSent();
        DB::table('invitations')->insert(['organization_id' => $organization, 'email' => 'invite@example.test', 'role' => 'viewer', 'project_ids' => json_encode([$project]), 'token_hash' => hash('sha256', 'expired'), 'invited_by' => $owner->id, 'expires_at' => now()->subMinute()]);
        $this->get('/invitations/expired')->assertNotFound();
        $this->post('/workspaces/switch', ['organization_id' => $foreignOrganization])->assertForbidden();
    }

    public function test_member_access_changes_take_effect_and_owner_cannot_be_removed(): void
    {
        [$owner, $organization, $project] = $this->workspace();
        $second = DB::table('projects')->insertGetId(['organization_id' => $organization, 'name' => 'Second', 'location' => 'Lagos']);
        $member = User::factory()->create();
        $membership = DB::table('organization_users')->insertGetId(['organization_id' => $organization, 'user_id' => $member->id, 'role' => 'supervisor']);
        DB::table('project_users')->insert(['organization_id' => $organization, 'project_id' => $project, 'user_id' => $member->id]);
        $this->actingAs($owner)->patch('/team/members/'.$membership, ['role' => 'viewer', 'project_ids' => [$second]])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($member)->get('/projects/'.$project)->assertNotFound();
        $this->get('/projects/'.$second)->assertOk();
        $this->post('/workers', ['project_id' => $second, 'name' => 'Worker', 'trade' => 'Mason'])->assertForbidden();
        $ownerMembership = DB::table('organization_users')->where('user_id', $owner->id)->value('id');
        $this->actingAs($owner)->delete('/team/members/'.$ownerMembership)->assertForbidden();
    }

    public function test_account_without_a_workspace_can_log_in_and_accept_its_invitation(): void
    {
        [$owner, $organization, $project] = $this->workspace();
        $user = User::factory()->create(['password' => 'abcdef']);
        DB::table('invitations')->insert(['organization_id' => $organization, 'email' => $user->email, 'role' => 'viewer', 'project_ids' => json_encode([$project]), 'token_hash' => hash('sha256', 'existing'), 'invited_by' => $owner->id, 'expires_at' => now()->addDay()]);
        $this->get('/invitations/existing')->assertOk();
        $this->post('/login', ['email' => $user->email, 'password' => 'abcdef'])->assertRedirect('/invitations/existing');
        $this->post('/invitations/existing')->assertRedirect('/dashboard');
        $this->assertDatabaseHas('organization_users', ['organization_id' => $organization, 'user_id' => $user->id, 'role' => 'viewer']);
    }

    public function test_password_change_requires_current_password_and_revokes_other_sessions(): void
    {
        $user = User::factory()->create(['password' => 'oldpass']);
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'own-other-browser', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'unrelated-browser', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);
        $data = ['current_password' => 'wrong', 'password' => 'abcdef', 'password_confirmation' => 'abcdef'];
        $this->actingAs($user)->patch('/account/password', $data)->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('oldpass', $user->fresh()->password));
        $data['current_password'] = 'oldpass';
        $this->patch('/account/password', $data)->assertRedirect();
        $this->assertTrue(Hash::check('abcdef', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'own-other-browser']);
        $this->assertDatabaseHas('sessions', ['id' => 'unrelated-browser']);
    }
}
