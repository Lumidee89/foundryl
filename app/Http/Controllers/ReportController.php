<?php

namespace App\Http\Controllers;

use App\Services\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private Workspace $workspace) {}

    private function data(Request $request): array
    {
        $input = $request->validate(['project' => 'nullable|integer', 'date' => 'nullable|date_format:Y-m-d']);
        $date = $input['date'] ?? today()->toDateString();
        $projects = $this->workspace->projects()->get();
        $project = isset($input['project']) ? $this->workspace->project($input['project']) : null;
        $activities = $this->workspace->records('activities')->whereDate('planned_date', $date);
        $diary = $this->workspace->records('diary_entries')->whereDate('entry_date', $date);
        $gate = $this->workspace->records('gate_entries')->whereDate('arrived_at', '<=', $date)->where(fn ($q) => $q->whereNull('departed_at')->orWhereDate('departed_at', '>=', $date));
        $visitors = $this->workspace->records('visitor_entries')->whereDate('arrived_at', '<=', $date)->where(fn ($q) => $q->whereNull('departed_at')->orWhereDate('departed_at', '>=', $date));
        $deployments = $this->workspace->records('worker_deployments')->whereDate('work_date', $date);
        $instructions = $this->workspace->records('site_instructions')->whereDate('created_at', '<=', $date)->where('status', '!=', 'Closed');
        if ($project) {
            foreach ([$activities, $diary, $gate, $instructions, $visitors, $deployments] as $query) {
                $query->where('project_id', $project->id);
            }
        }

        return ['visitors' => $visitors->get(['id', 'name', 'project_id', 'purpose', 'arrived_at', 'departed_at']), 'deployments' => $deployments->get(['id', 'project_id', 'worker_id', 'team', 'location']), 'date' => $date, 'project' => $project, 'projects' => $projects, 'activities' => $activities->orderBy('project_id')->get(), 'diary' => $diary->orderBy('project_id')->get(), 'attendance' => $gate->get(['worker_id', 'project_id', 'arrived_at', 'departed_at']), 'instructions' => $instructions->orderBy('due_date')->get()];
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Reports', $this->data($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->workspace->authorize('reports.export');
        $data = $this->data($request);
        $this->workspace->audit('report.exported', 'projects', $data['project']?->id ?? 0, $data['project']?->id, ['date' => $data['date']]);

        return response()->streamDownload(function () use ($data) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Project', 'Date', 'Activity', 'Responsible', 'Status', 'Actual completion (%)', 'Remaining (%)', 'Reason'], ',', '"', '');
            foreach ($data['activities'] as $activity) {
                $row = [$data['projects']->firstWhere('id', $activity->project_id)?->name, $data['date'], $activity->title, $activity->responsible, $activity->status, $activity->progress, 100 - $activity->progress, $activity->reason];
                $row = array_map(fn ($v) => preg_match('/^[\s]*[=+@-]/u', (string) $v) ? "'".$v : $v, $row);
                fputcsv($output, $row, ',', '"', '');
            }fclose($output);
        }, 'foundryl-daily-plan-'.$data['date'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function audit(): Response
    {
        $this->workspace->authorize('audit.view');
        $organization = $this->workspace->organization()->id;

        return Inertia::render('Audit', ['events' => DB::table('audit_logs')->where('organization_id', $organization)->where(fn ($query) => $query->whereNull('project_id')->orWhereIn('project_id', $this->workspace->projects()->select('id')))->orderByDesc('id')->paginate(30, ['id', 'action', 'entity_type', 'entity_id', 'actor_user_id', 'project_id', 'created_at']), 'projects' => $this->workspace->projects()->get()]);
    }
}
