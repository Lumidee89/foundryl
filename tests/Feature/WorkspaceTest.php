<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function workspace(string $role = 'owner'): array
    {
        $u = User::factory()->create();
        $o = DB::table('organizations')->insertGetId(['name' => 'Company '.$u->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('organization_users')->insert(['organization_id' => $o, 'user_id' => $u->id, 'role' => $role]);
        $p = DB::table('projects')->insertGetId(['organization_id' => $o, 'name' => 'Site '.$o, 'location' => 'Abuja', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);

        return [$u, $o, $p];
    }

    public function test_registration_creates_owner_and_hashes_password(): void
    {
        $this->post('/register', ['name' => 'Ada Builder', 'company' => 'Ada Construction', 'email' => 'ada@example.test', 'password' => 'StrongPass1234', 'password_confirmation' => 'StrongPass1234'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('organization_users', ['role' => 'owner']);
        $this->assertTrue(Hash::check('StrongPass1234', User::first()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'workspace.created']);
    }

    public function test_tenant_cannot_read_or_write_another_tenants_project(): void
    {
        [$a,$oa,$pa] = $this->workspace();
        [$b,$ob,$pb] = $this->workspace();
        $this->actingAs($a)->get('/projects/'.$pb)->assertNotFound();
        $this->post('/workers', ['project_id' => $pb, 'name' => 'Intruder', 'trade' => 'Mason'])->assertNotFound();
        $this->get('/workers?project='.$pb)->assertNotFound();
        $this->get('/projects')->assertOk()->assertInertia(fn ($page) => $page->component('Projects')->has('projects', 1)->where('projects.0.id', $pa));
    }

    public function test_project_member_only_sees_assigned_site_and_cannot_administer_projects(): void
    {
        [$u,$o,$p] = $this->workspace('supervisor');
        $other = DB::table('projects')->insertGetId(['organization_id' => $o, 'name' => 'Restricted', 'location' => 'Lagos']);
        DB::table('project_users')->insert(['organization_id' => $o, 'project_id' => $p, 'user_id' => $u->id]);
        $this->actingAs($u)->get('/projects/'.$p)->assertOk();
        $this->get('/projects/'.$other)->assertNotFound();
        $this->post('/projects', ['name' => 'No permission', 'location' => 'Lagos'])->assertForbidden();
    }

    public function test_gate_lifecycle_is_audited_and_prevents_duplicate_arrivals(): void
    {
        [$u,$o,$p] = $this->workspace();
        $this->actingAs($u)->post('/workers', ['project_id' => $p, 'name' => 'Ibrahim Musa', 'trade' => 'Steel fixer'])->assertRedirect();
        $worker = DB::table('workers')->first();
        $payload = ['project_id' => $p, 'worker_id' => $worker->id];
        $this->post('/gate', $payload)->assertRedirect();
        $this->post('/gate', $payload)->assertSessionHasErrors('worker_id');
        $this->assertDatabaseCount('gate_entries', 1);
        $gate = DB::table('gate_entries')->first();
        $this->assertNull($gate->departed_at);
        $this->post('/gate/'.$gate->id.'/depart')->assertRedirect();
        $this->post('/gate/'.$gate->id.'/depart')->assertRedirect();
        $this->assertNotNull(DB::table('gate_entries')->first()->departed_at);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'gate.departed')->count());
        $this->post('/gate', $payload)->assertRedirect();
        $this->assertDatabaseCount('gate_entries', 2);
    }

    public function test_activity_delay_needs_reason_and_updates_dashboard(): void
    {
        [$u,$o,$p] = $this->workspace();
        $this->actingAs($u)->post('/activities', ['project_id' => $p, 'title' => 'Pour slab', 'location' => 'Block A', 'responsible' => 'Ada', 'planned_date' => today()->toDateString()])->assertRedirect();
        $id = DB::table('activities')->value('id');
        $this->patch('/activities/'.$id, ['status' => 'Delayed', 'progress' => 30])->assertSessionHasErrors('reason');
        $this->patch('/activities/'.$id, ['status' => 'Delayed', 'progress' => 30, 'reason' => 'Rain'])->assertRedirect();
        $this->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page->component('Dashboard')->where('stats.delayed', 1));
        $this->patch('/activities/'.$id, ['status' => 'Completed', 'progress' => 80])->assertRedirect();
        $this->assertDatabaseHas('activities', ['id' => $id, 'progress' => 100, 'status' => 'Completed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'activity.updated']);
    }

    public function test_cross_tenant_activity_and_gate_mutations_are_denied(): void
    {
        [$u,$o,$p] = $this->workspace();
        [$other,$oo,$op] = $this->workspace();
        $this->actingAs($other)->post('/activities', ['project_id' => $op, 'title' => 'Private activity', 'location' => 'Site', 'responsible' => 'B', 'planned_date' => today()->toDateString()]);
        $id = DB::table('activities')->value('id');
        $this->actingAs($u)->patch('/activities/'.$id, ['status' => 'Completed', 'progress' => 100])->assertNotFound();
        $this->assertDatabaseHas('activities', ['id' => $id, 'status' => 'Planned']);
    }

    public function test_viewer_cannot_create_operational_records(): void
    {
        [$u,$o,$p] = $this->workspace('viewer');
        DB::table('project_users')->insert(['organization_id' => $o, 'project_id' => $p, 'user_id' => $u->id]);
        $this->actingAs($u)->post('/workers', ['project_id' => $p, 'name' => 'No', 'trade' => 'Mason'])->assertForbidden();
        $this->post('/diary', ['project_id' => $p, 'title' => 'No', 'notes' => 'No', 'entry_date' => today()->toDateString()])->assertForbidden();
    }

    public function test_direction_acknowledgment_is_idempotent_and_tenant_scoped(): void
    {
        [$u,$o,$p] = $this->workspace();
        [$other] = $this->workspace();
        $this->actingAs($u)->post('/directions', ['project_id' => $p, 'message' => 'Review the plan before starting.'])->assertRedirect();
        $id = DB::table('directions')->value('id');
        $this->post('/directions/'.$id.'/acknowledge')->assertRedirect();
        $this->post('/directions/'.$id.'/acknowledge')->assertRedirect();
        $this->assertDatabaseCount('direction_acknowledgments', 1);
        $this->actingAs($other)->post('/directions/'.$id.'/acknowledge')->assertNotFound();
    }

    public function test_guests_are_redirected_and_login_is_rate_limited(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'none@example.test', 'password' => 'bad'])->assertSessionHasErrors('email');
        }$this->post('/login', ['email' => 'none@example.test', 'password' => 'bad'])->assertStatus(429);
    }

    public function test_registration_rejects_case_variant_of_existing_email(): void
    {
        User::factory()->create(['email' => 'existing@example.test']);
        $this->post('/register', ['name' => 'Another User', 'company' => 'Another Company', 'email' => 'EXISTING@example.test', 'password' => 'StrongPass1234', 'password_confirmation' => 'StrongPass1234'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_worker_from_other_project_cannot_be_checked_into_authorized_project(): void
    {
        [$user, $organization, $project] = $this->workspace();
        [$other, $otherOrganization, $otherProject] = $this->workspace();
        $this->actingAs($other)->post('/workers', ['project_id' => $otherProject, 'name' => 'Private Worker', 'trade' => 'Mason']);
        $worker = DB::table('workers')->value('id');
        $this->actingAs($user)->post('/gate', ['project_id' => $project, 'worker_id' => $worker])->assertNotFound();
        $this->assertDatabaseCount('gate_entries', 0);
    }

    public function test_registration_requires_at_least_six_characters(): void
    {
        $data = ['name' => 'Builder', 'company' => 'Build Co', 'email' => 'six@example.test', 'password' => 'abcde', 'password_confirmation' => 'abcde'];
        $this->post('/register', $data)->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
        $data['password'] = $data['password_confirmation'] = 'abcdef';
        $this->post('/register', $data)->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }
}
