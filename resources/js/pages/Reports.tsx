import { useForm, usePage, Link } from "@inertiajs/react";
import {
    Shell,
    Heading,
    Badge,
    Empty,
    type Project,
    type Shared,
} from "../components/ui";
export default function Reports({
    date,
    project,
    projects,
    activities,
    diary,
    attendance,
    instructions,
    visitors,
    deployments,
}: {
    visitors: {
        id: number;
        name: string;
        purpose: string;
        arrived_at: string;
        departed_at: string | null;
    }[];
    deployments: { id: number; team: string; location: string }[];
    date: string;
    project: Project | null;
    projects: Project[];
    activities: {
        id: number;
        project_id: number;
        title: string;
        responsible: string;
        status: string;
        progress: number;
        reason: string | null;
    }[];
    diary: {
        id: number;
        title: string;
        notes: string;
        weather: string | null;
    }[];
    attendance: {
        worker_id: number;
        project_id: number;
        arrived_at: string;
        departed_at: string | null;
    }[];
    instructions: {
        id: number;
        title: string;
        status: string;
        due_date: string;
    }[];
}) {
    const { permissions } = usePage<Shared>().props;
    const form = useForm({ date, project: project?.id.toString() || "" });
    const query = new URLSearchParams({
        date,
        ...(project ? { project: String(project.id) } : {}),
    });
    return (
        <Shell title="Daily reports">
            <Heading
                title="Daily site report"
                description="Review the work plan, attendance records, site notes, and outstanding instructions."
                action={
                    permissions.includes("reports.export") && (
                        <a
                            href={`/reports/export?${query}`}
                            className="button primary"
                        >
                            Export work plan CSV
                        </a>
                    )
                }
            />
            <form
                className="report-filters panel inline-form"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.get("/reports");
                }}
            >
                <label>
                    Site date
                    <input
                        type="date"
                        required
                        value={form.data.date}
                        onChange={(e) => form.setData("date", e.target.value)}
                    />
                </label>
                <label>
                    Project
                    <select
                        value={form.data.project}
                        onChange={(e) =>
                            form.setData("project", e.target.value)
                        }
                    >
                        <option value="">All authorized projects</option>
                        {projects.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </select>
                </label>
                <button className="button secondary" disabled={form.processing}>
                    View report
                </button>
            </form>
            <div className="stat-grid spaced">
                {[
                    ["Planned activities", activities.length],
                    [
                        "Completed",
                        activities.filter((a) => a.status === "Completed")
                            .length,
                    ],
                    [
                        "Delayed / blocked",
                        activities.filter((a) =>
                            ["Delayed", "Blocked"].includes(a.status),
                        ).length,
                    ],
                    [
                        "Workers present that day",
                        new Set(attendance.map((a) => a.worker_id)).size,
                    ],
                ].map(([label, value]) => (
                    <div className="stat-card" key={label}>
                        <span>{label}</span>
                        <strong>{value}</strong>
                    </div>
                ))}
            </div>
            <section className="panel">
                <div className="panel-heading">
                    <h2>Plan versus recorded accomplishment</h2>
                </div>
                {activities.length ? (
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Activity / Project</th>
                                    <th>Responsible</th>
                                    <th>Status</th>
                                    <th>Actual</th>
                                    <th>Remaining</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                {activities.map((a) => (
                                    <tr key={a.id}>
                                        <td>
                                            <Link
                                                href={`/activities?project=${a.project_id}`}
                                            >
                                                {a.title}
                                            </Link>
                                            <small>
                                                {
                                                    projects.find(
                                                        (p) =>
                                                            p.id ===
                                                            a.project_id,
                                                    )?.name
                                                }
                                            </small>
                                        </td>
                                        <td>{a.responsible}</td>
                                        <td>
                                            <Badge>{a.status}</Badge>
                                        </td>
                                        <td>{a.progress}%</td>
                                        <td>{100 - a.progress}%</td>
                                        <td>{a.reason || "—"}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty
                        title="No activities planned for this date"
                        description="Choose another date or add activities to your work plan."
                    />
                )}
            </section>
            <div className="dashboard-columns spaced">
                <section className="panel">
                    <div className="panel-heading">
                        <h2>Site diary</h2>
                    </div>
                    {diary.length ? (
                        diary.map((d) => (
                            <article key={d.id} className="diary-note">
                                <h3>{d.title}</h3>
                                <small>{d.weather}</small>
                                <p>{d.notes}</p>
                            </article>
                        ))
                    ) : (
                        <Empty
                            title="No diary entries"
                            description="No site notes were recorded for this date."
                        />
                    )}
                </section>
                <section className="panel">
                    <div className="panel-heading">
                        <h2>Currently outstanding instructions</h2>
                    </div>
                    {instructions.length ? (
                        instructions.map((i) => (
                            <article key={i.id} className="diary-note">
                                <Link href="/instructions">
                                    <h3>{i.title}</h3>
                                </Link>
                                <Badge>{i.status}</Badge>
                                <small> Due {i.due_date}</small>
                            </article>
                        ))
                    ) : (
                        <Empty
                            title="No outstanding instructions"
                            description="There are no open instructions created on or before this date."
                        />
                    )}
                </section>
            </div>
            <p className="report-note">
                Activity progress reflects the latest recorded values for the
                selected plan date. This report is not a frozen historical
                snapshot.
            </p>
            <section className="panel form-panel">
                <h2>Visitor and deployment summary</h2>
                <p>
                    {visitors.length} visitor entries overlapped this date ·{" "}
                    {deployments.length} workers deployed
                </p>
                {visitors.map((v) => (
                    <p key={v.id}>
                        <strong>{v.name}</strong> · {v.purpose} · arrival{" "}
                        {v.arrived_at} UTC
                        {v.departed_at
                            ? ` · departure ${v.departed_at} UTC`
                            : " · departure not recorded"}
                    </p>
                ))}
                <Link
                    className="text-link"
                    href={`/workforce?date=${date}${project ? `&project=${project.id}` : ""}`}
                >
                    View deployment and attendance detail →
                </Link>
            </section>
        </Shell>
    );
}
