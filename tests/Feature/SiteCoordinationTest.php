<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiteCoordinationTest extends TestCase
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
        $assignee = User::factory()->create();
        $organization = DB::table('organizations')->insertGetId(['name' => 'Builders']);
        $project = DB::table('projects')->insertGetId(['organization_id' => $organization, 'name' => 'Site', 'location' => 'Abuja']);
        DB::table('organization_users')->insert([
            ['organization_id' => $organization, 'user_id' => $owner->id, 'role' => 'owner'],
            ['organization_id' => $organization, 'user_id' => $assignee->id, 'role' => 'supervisor'],
        ]);
        DB::table('project_users')->insert(['organization_id' => $organization, 'project_id' => $project, 'user_id' => $assignee->id]);

        return [$owner, $assignee, $organization, $project];
    }

    public function test_instruction_requires_ordered_recipient_actions_and_independent_verification(): void
    {
        [$owner, $assignee, $organization, $project] = $this->workspace();
        $this->actingAs($owner)->post('/instructions', ['project_id' => $project, 'title' => 'Check shuttering', 'description' => 'Inspect before pouring.', 'assigned_to' => $assignee->id, 'due_date' => today()->toDateString()])->assertRedirect()->assertSessionHasNoErrors();
        $id = DB::table('site_instructions')->value('id');
        $this->assertDatabaseHas('workspace_notifications', ['user_id' => $assignee->id, 'project_id' => $project]);
        $this->patch('/instructions/'.$id, ['status' => 'Acknowledged', 'version' => 1])->assertForbidden();
        $this->actingAs($assignee)->patch('/instructions/'.$id, ['status' => 'Completed', 'version' => 1, 'completion_notes' => 'Done'])->assertSessionHasErrors('status');
        $this->patch('/instructions/'.$id, ['status' => 'Acknowledged', 'version' => 1])->assertRedirect();
        $this->patch('/instructions/'.$id, ['status' => 'In Progress', 'version' => 1])->assertStatus(409);
        $this->patch('/instructions/'.$id, ['status' => 'In Progress', 'version' => 2])->assertRedirect();
        $this->patch('/instructions/'.$id, ['status' => 'Completed', 'version' => 3])->assertSessionHasErrors('completion_notes');
        $this->patch('/instructions/'.$id, ['status' => 'Completed', 'version' => 3, 'completion_notes' => 'Inspected and secured.'])->assertRedirect();
        $this->patch('/instructions/'.$id, ['status' => 'Closed', 'version' => 4])->assertForbidden();
        $this->actingAs($owner)->patch('/instructions/'.$id, ['status' => 'Closed', 'version' => 4])->assertRedirect();
        $this->assertDatabaseHas('site_instructions', ['id' => $id, 'status' => 'Closed', 'version' => 5]);
        $this->assertSame(4, DB::table('audit_logs')->where('action', 'instruction.updated')->count());
    }

    public function test_instruction_and_notification_access_cannot_cross_company_or_recipient(): void
    {
        [$owner, $assignee, $organization, $project] = $this->workspace();
        [$otherOwner, $otherAssignee, $otherOrganization, $otherProject] = $this->workspace();
        $payload = ['project_id' => $project, 'title' => 'Inspection', 'description' => 'Inspect site', 'assigned_to' => $otherAssignee->id, 'due_date' => today()->toDateString()];
        $this->actingAs($owner)->post('/instructions', $payload)->assertSessionHasErrors('assigned_to');
        $payload['assigned_to'] = $assignee->id;
        $this->post('/instructions', $payload)->assertRedirect();
        $id = DB::table('site_instructions')->value('id');
        $notification = DB::table('workspace_notifications')->value('id');
        $this->post('/notifications/'.$notification.'/read')->assertNotFound();
        $this->actingAs($otherOwner)->patch('/instructions/'.$id, ['status' => 'Acknowledged', 'version' => 1])->assertNotFound();
        $this->get('/instructions?project='.$project)->assertNotFound();
        $this->actingAs($assignee)->post('/notifications/'.$notification.'/read')->assertRedirect();
        $this->assertNotNull(DB::table('workspace_notifications')->where('id', $notification)->value('read_at'));
    }

    public function test_reports_export_only_authorized_records_and_neutralize_spreadsheet_formulas(): void
    {
        [$owner, $assignee, $organization, $project] = $this->workspace();
        [$otherOwner, $otherAssignee, $otherOrganization, $otherProject] = $this->workspace();
        foreach ([[$owner, $project, '=1+1'], [$otherOwner, $otherProject, 'PRIVATE ACTIVITY']] as [$actor, $site, $title]) {
            $this->actingAs($actor)->post('/activities', ['project_id' => $site, 'title' => $title, 'location' => 'Block A', 'responsible' => 'Supervisor', 'planned_date' => today()->toDateString()])->assertRedirect();
        }
        $response = $this->actingAs($owner)->get('/reports/export')->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringNotContainsString('PRIVATE ACTIVITY', $csv);
        $this->get('/reports?project='.$otherProject)->assertNotFound();
        $this->get('/reports')->assertInertia(fn ($page) => $page->component('Reports')->has('activities', 1));
        $this->get('/audit')->assertOk();
        $this->actingAs($assignee)->get('/reports/export')->assertForbidden();
        $this->get('/audit')->assertForbidden();
    }
}
