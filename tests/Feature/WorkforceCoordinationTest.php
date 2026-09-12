<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkforceCoordinationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $host = User::factory()->create();
        $organization = DB::table('organizations')->insertGetId(['name' => 'Builders']);
        $project = DB::table('projects')->insertGetId(['organization_id' => $organization, 'name' => 'Site', 'location' => 'Abuja']);
        DB::table('organization_users')->insert([['organization_id' => $organization, 'user_id' => $owner->id, 'role' => 'owner'], ['organization_id' => $organization, 'user_id' => $host->id, 'role' => 'supervisor']]);
        DB::table('project_users')->insert(['organization_id' => $organization, 'project_id' => $project, 'user_id' => $host->id]);

        return [$owner, $host, $organization, $project];
    }

    public function test_visitors_need_host_authorization_before_gate_entry(): void
    {
        [$owner, $host, $organization, $project] = $this->workspace();
        $this->actingAs($owner)->post('/visitors', ['project_id' => $project, 'name' => 'Consultant', 'purpose' => 'Inspection', 'host_id' => $host->id])->assertRedirect()->assertSessionHasNoErrors();
        $id = DB::table('visitor_entries')->value('id');
        $this->patch('/visitors/'.$id, ['status' => 'Onsite'])->assertStatus(409);
        $this->patch('/visitors/'.$id, ['status' => 'Authorized'])->assertForbidden();
        $this->actingAs($host)->patch('/visitors/'.$id, ['status' => 'Authorized'])->assertRedirect();
        $this->actingAs($owner)->patch('/visitors/'.$id, ['status' => 'Onsite'])->assertRedirect();
        $this->get('/visitors?status=Onsite')->assertInertia(fn ($page) => $page->has('visitors.data', 1));
        $this->patch('/visitors/'.$id, ['status' => 'Departed'])->assertRedirect();
        $this->patch('/visitors/'.$id, ['status' => 'Onsite'])->assertStatus(409);
        $this->assertDatabaseHas('visitor_entries', ['id' => $id, 'status' => 'Departed', 'authorized_by' => $host->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'visitor.departed']);
        [$other] = $this->workspace();
        $this->actingAs($other)->patch('/visitors/'.$id, ['status' => 'Departed'])->assertNotFound();
    }

    public function test_deployment_prevents_duplicates_and_derives_absence_and_lateness_from_gate_records(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(9));
        [$owner, $host, $organization, $project] = $this->workspace();
        $this->actingAs($owner)->post('/workers', ['project_id' => $project, 'name' => 'Musa', 'trade' => 'Mason']);
        $worker = DB::table('workers')->value('id');
        $this->post('/activities', ['project_id' => $project, 'title' => 'Build wall', 'location' => 'Block A', 'responsible' => 'Supervisor', 'planned_date' => today()->toDateString()]);
        $activity = DB::table('activities')->value('id');
        $data = ['project_id' => $project, 'worker_id' => $worker, 'activity_id' => $activity, 'supervisor_id' => $host->id, 'team' => 'Masonry', 'location' => 'Block A', 'work_date' => today()->toDateString(), 'expected_at' => '08:00'];
        $this->post('/workforce', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/workforce', $data)->assertSessionHasErrors('worker_id');
        $this->assertDatabaseCount('worker_deployments', 1);
        $this->get('/workforce')->assertInertia(fn ($page) => $page->where('deployments.0.attendance', 'Absent'));
        $this->post('/gate', ['project_id' => $project, 'worker_id' => $worker])->assertRedirect();
        $this->get('/workforce')->assertInertia(fn ($page) => $page->where('deployments.0.attendance', 'Late')->where('deployments.0.onsite', true));
        DB::table('gate_entries')->where('worker_id', $worker)->update(['arrived_at' => today()->addHours(8)]);
        $this->get('/workforce')->assertInertia(fn ($page) => $page->where('deployments.0.attendance', 'Present'));
        [$other, $otherHost, $otherOrganization, $otherProject] = $this->workspace();
        $this->actingAs($other)->post('/workforce', $data)->assertNotFound();
        $this->get('/workforce?project='.$project)->assertNotFound();
    }
}
