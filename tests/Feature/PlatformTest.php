<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['password' => 'AdminPassword123']);
        $user->is_superadmin = true;
        $user->save();

        return $user;
    }

    private function verified(User $user): static
    {
        return $this->actingAs($user)->withSession(['platform_mfa_user' => $user->id, 'platform_mfa_until' => time() + 3600]);
    }

    private function company(string $name = 'Company'): int
    {
        return DB::table('organizations')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_customers_cannot_access_platform_or_promote_themselves(): void
    {
        $this->post('/register', ['name' => 'Customer', 'email' => 'customer@example.test', 'company' => 'Customer Co', 'password' => 'CustomerPassword123', 'password_confirmation' => 'CustomerPassword123', 'is_superadmin' => true])->assertRedirect('/dashboard');
        $this->assertFalse(User::first()->is_superadmin);
        $this->get('/platform')->assertForbidden();
        $this->get('/platform/security')->assertForbidden();
        $this->post('/platform/security', ['code' => '123456'])->assertForbidden();
    }

    public function test_admin_requires_mfa_and_login_redirects_to_security(): void
    {
        $admin = $this->admin();
        $this->post('/login', ['email' => $admin->email, 'password' => 'AdminPassword123'])->assertRedirect('/platform/security');
        $this->get('/platform')->assertRedirect('/platform/security');
        $this->get('/platform/companies/1/records/workers')->assertRedirect('/platform/security');
        $this->get('/dashboard')->assertForbidden();
    }

    public function test_mfa_setup_encrypts_secret_and_rejects_replay(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/platform/security')->assertOk();
        $secret = session('platform_pending_totp');
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post('/platform/security', ['code' => 'abc'])->assertSessionHasErrors('code');
        $this->post('/platform/security', ['code' => $code])->assertRedirect('/platform');
        $this->assertNotSame($secret, DB::table('users')->where('id', $admin->id)->value('platform_totp_secret'));
        $this->assertSame($secret, $admin->fresh()->platform_totp_secret);
        $this->post('/platform/security', ['code' => $code])->assertSessionHasErrors('code');
        $this->get('/platform')->assertOk();
        $this->withSession(['platform_mfa_until' => time() - 1])->get('/platform')->assertRedirect('/platform/security');
    }

    public function test_platform_sees_companies_and_scopes_each_record_list(): void
    {
        $admin = $this->admin();
        $a = $this->company('Alpha');
        $b = $this->company('Beta');
        DB::table('projects')->insert([['organization_id' => $a, 'name' => 'Alpha site', 'location' => 'Abuja'], ['organization_id' => $b, 'name' => 'Beta site', 'location' => 'Lagos']]);
        $this->verified($admin)->get('/platform')->assertOk()->assertInertia(fn ($page) => $page->component('Platform')->where('stats.Companies', 2));
        $this->get('/platform/companies/'.$a)->assertOk();
        $this->get('/platform/companies/'.$a.'/records/projects')->assertOk()->assertInertia(fn ($page) => $page->has('records.data', 1)->where('records.data.0.name', 'Alpha site'));
        $this->get('/platform/companies/'.$a.'/records/users')->assertNotFound();
        $this->assertDatabaseHas('platform_audit_logs', ['actor_user_id' => $admin->id, 'organization_id' => $a, 'action' => 'company.records_viewed']);
    }

    public function test_suspension_requires_password_and_blocks_existing_customer_sessions(): void
    {
        $admin = $this->admin();
        $company = $this->company();
        $customer = User::factory()->create();
        DB::table('organization_users')->insert(['organization_id' => $company, 'user_id' => $customer->id, 'role' => 'owner']);
        $this->verified($admin)->patch('/platform/companies/'.$company, ['suspended' => true, 'reason' => 'Investigating misuse', 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertNull(DB::table('organizations')->where('id', $company)->value('suspended_at'));
        $this->patch('/platform/companies/'.$company, ['suspended' => true, 'reason' => 'Investigating misuse', 'password' => 'AdminPassword123'])->assertRedirect();
        $this->actingAs($customer)->get('/dashboard')->assertForbidden();
        $this->post('/projects', ['name' => 'Blocked', 'location' => 'Abuja'])->assertForbidden();
        $this->verified($admin)->patch('/platform/companies/'.$company, ['suspended' => false, 'reason' => 'Investigation resolved', 'password' => 'AdminPassword123'])->assertRedirect();
        $this->actingAs($customer)->get('/dashboard')->assertOk();
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'company.reactivated']);
    }

    public function test_command_creates_dedicated_admin_without_company(): void
    {
        $this->artisan('foundryl:superadmin', ['email' => 'admin@foundryl.com', '--name' => 'Foundryl Administrator'])->expectsQuestion('Password (at least 6 characters)', 'abcdef')->expectsQuestion('Confirm password', 'abcdef')->expectsOutput('Superadmin created. Log in at /login, then set up an authenticator app. All company workspaces remain free.')->assertSuccessful();
        $admin = User::where('email', 'admin@foundryl.com')->firstOrFail();
        $this->assertTrue($admin->is_superadmin);
        $this->assertTrue(Hash::check('abcdef', $admin->password));
        $this->assertDatabaseCount('organization_users', 0);
    }

    public function test_verification_session_cannot_be_reused_by_another_admin(): void
    {
        $one = $this->admin();
        $two = $this->admin();
        $this->verified($one)->actingAs($two)->get('/platform')->assertRedirect('/platform/security');
    }
}
