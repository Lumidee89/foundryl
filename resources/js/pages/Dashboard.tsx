import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import {
    ArrowUpRight,
    Building2,
    Users,
    ClipboardCheck,
    TriangleAlert,
    Plus,
    MapPin,
    Megaphone,
    ArrowRight,
    Check,
    CalendarDays,
} from "lucide-react";
import {
    Shell,
    Heading,
    Badge,
    Empty,
    CardLink,
    Errors,
    type Project,
    type Shared,
} from "../components/ui";
import { useState } from "react";
type Activity = {
    id: number;
    title: string;
    responsible: string;
    status: string;
    progress: number;
    project_id: number;
};
export default function Dashboard({
    projects,
    stats,
    activities,
    directions,
    recent,
    today,
}: {
    projects: Project[];
    stats: {
        visitors: number;
        deployments: number;
        instructions: number;
        projects: number;
        onsite: number;
        planned: number;
        completed: number;
        delayed: number;
    };
    activities: Activity[];
    directions: {
        id: number;
        message: string;
        project_id: number;
        acknowledged: boolean;
    }[];
    recent: {
        id: number;
        action: string;
        created_at: string;
        project_id: number;
    }[];
    today: string;
}) {
    const { auth, permissions } = usePage<Shared>().props;
    const [messageOpen, setMessageOpen] = useState(false);
    const form = useForm({
        project_id: projects[0]?.id.toString() || "",
        message: "",
    });
    return (
        <Shell title="Overview">
            <Head title="Today at a glance" />
            <Heading
                eyebrow="YOUR SITE. YOUR PEOPLE. YOUR PROGRESS."
                title={`Hello, ${auth.user.name.split(" ")[0]}.`}
                description="Here’s the picture across your projects today."
                action={
                    <div className="date-label">
                        <CalendarDays size={17} />
                        {today}
                    </div>
                }
            />
            <div className="overview-grid">
                <div className="stat-grid">
                    {[
                        {
                            label: "Active projects",
                            value: stats.projects,
                            note: "Across your workspace",
                            icon: Building2,
                            href: "/projects",
                        },
                        {
                            label: "Workers onsite",
                            value: stats.onsite,
                            note: "Currently checked in",
                            icon: Users,
                            href: "/gate?status=onsite",
                        },
                        {
                            label: "Activities completed",
                            value: stats.completed,
                            note: `Of ${stats.planned} planned for today`,
                            icon: ClipboardCheck,
                            href: "/activities?date=today&status=Completed",
                        },
                        {
                            label: "Delayed activities",
                            value: stats.delayed,
                            note: "Delayed or blocked today",
                            icon: TriangleAlert,
                            href: "/activities?date=today&status=attention",
                        },
                    ].map(({ icon: Icon, ...s }) => (
                        <Link href={s.href} className="stat-card" key={s.label}>
                            <div>
                                <span className="stat-icon">
                                    <Icon size={19} />
                                </span>
                                <ArrowUpRight size={16} />
                            </div>
                            <span>{s.label}</span>
                            <strong>
                                {s.value.toString().padStart(2, "0")}
                            </strong>
                            <small>{s.note}</small>
                        </Link>
                    ))}
                </div>
                <section className="panel progress-panel">
                    <div className="panel-heading">
                        <h2>Project progress</h2>
                        <span className="chart-period">Current activities</span>
                    </div>
                    {projects.length ? (
                        <div
                            className="project-chart"
                            aria-label="Average activity progress by project"
                        >
                            {projects.slice(0, 6).map((project) => (
                                <Link
                                    href={`/projects/${project.id}`}
                                    className="chart-column"
                                    key={project.id}
                                    aria-label={`${project.name}: ${project.progress ?? 0}% activity progress`}
                                >
                                    <span className="chart-value">
                                        {project.progress ?? 0}%
                                    </span>
                                    <div className="chart-track">
                                        <div
                                            className="chart-bar"
                                            style={{
                                                height: `${project.progress ?? 0}%`,
                                            }}
                                        />
                                    </div>
                                    <span className="chart-label">
                                        {project.name}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <Empty
                            title="Your progress, at a glance"
                            description="Create a project and add activities to see progress here."
                        />
                    )}
                    <p className="chart-note">
                        Average completion of recorded activities ·{" "}
                        {projects.length > 6
                            ? "Showing first 6 projects"
                            : "Your authorized projects"}
                    </p>
                </section>
            </div>
            <div className="dashboard-columns">
                <section className="panel">
                    <div className="panel-heading">
                        <h2>
                            Your projects{" "}
                            <span className="count">{projects.length}</span>
                        </h2>
                        <CardLink href="/projects">View all projects</CardLink>
                    </div>
                    {projects.length === 0 ? (
                        <Empty
                            title="Your first project starts here"
                            description="Create a project to bring your site, team, and daily work into view."
                        />
                    ) : (
                        <div className="dashboard-projects">
                            {projects.slice(0, 4).map((p, i) => (
                                <Link
                                    className="project-row"
                                    href={`/projects/${p.id}`}
                                    key={p.id}
                                >
                                    <span
                                        className={`project-thumb thumb-${i % 3}`}
                                    >
                                        <Building2 size={29} />
                                    </span>
                                    <div className="project-info">
                                        <strong>{p.name}</strong>
                                        <small>
                                            <MapPin size={12} />
                                            {p.location}
                                        </small>
                                        <div className="progress-track">
                                            <i
                                                style={{
                                                    width: `${p.progress}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                    <div className="project-meta">
                                        <Badge>{p.status}</Badge>
                                        <small>
                                            {p.progress}% activity progress
                                        </small>
                                    </div>
                                    <ArrowUpRight size={18} />
                                </Link>
                            ))}
                        </div>
                    )}
                    {permissions.includes("projects.manage") && (
                        <Link
                            href="/projects?new=1"
                            className="panel-bottom-link"
                        >
                            <Plus size={16} />
                            Create a new project
                        </Link>
                    )}
                </section>
                <section className="panel direction-panel">
                    <div className="panel-heading">
                        <h2>
                            <Megaphone size={18} />
                            Today’s direction
                        </h2>
                        {permissions.includes("directions.manage") &&
                            projects.length > 0 && (
                                <button
                                    aria-label="Publish direction"
                                    className="icon-button"
                                    onClick={() => setMessageOpen(!messageOpen)}
                                >
                                    <Plus size={19} />
                                </button>
                            )}
                    </div>
                    {messageOpen && (
                        <form
                            className="inline-form"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post("/directions", {
                                    onSuccess: () => {
                                        setMessageOpen(false);
                                        form.reset("message");
                                    },
                                });
                            }}
                        >
                            <Errors errors={form.errors} />
                            <label>
                                Project
                                <select
                                    value={form.data.project_id}
                                    onChange={(e) =>
                                        form.setData(
                                            "project_id",
                                            e.target.value,
                                        )
                                    }
                                >
                                    {projects.map((p) => (
                                        <option value={p.id} key={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                Direction
                                <textarea
                                    required
                                    value={form.data.message}
                                    onChange={(e) =>
                                        form.setData("message", e.target.value)
                                    }
                                />
                            </label>
                            <button
                                className="button primary"
                                disabled={form.processing}
                            >
                                Publish direction
                            </button>
                        </form>
                    )}
                    {directions.length === 0 ? (
                        <Empty
                            title="A shared direction for the day"
                            description="Project leaders can publish a message here for their teams to acknowledge."
                        />
                    ) : (
                        directions.map((d) => (
                            <div className="direction" key={d.id}>
                                <span className="eyebrow">
                                    {
                                        projects.find(
                                            (p) => p.id === d.project_id,
                                        )?.name
                                    }
                                </span>
                                <p>{d.message}</p>
                                <button
                                    className="button secondary small-button"
                                    disabled={d.acknowledged}
                                    onClick={() =>
                                        router.post(
                                            `/directions/${d.id}/acknowledge`,
                                        )
                                    }
                                >
                                    <Check size={15} />
                                    {d.acknowledged
                                        ? "Acknowledged"
                                        : "Acknowledge"}
                                </button>
                            </div>
                        ))
                    )}
                </section>
            </div>
            <div className="dashboard-columns">
                <section className="panel">
                    <div className="panel-heading">
                        <h2>Today’s work plan</h2>
                        <CardLink href="/activities?date=today">
                            View work plan
                        </CardLink>
                    </div>
                    {activities.length === 0 ? (
                        <Empty
                            title="Make a plan for today"
                            description="Add activities, set responsibility, and track progress as work happens."
                        />
                    ) : (
                        <div className="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Activity / Owner</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {activities.map((a) => (
                                        <tr key={a.id}>
                                            <td>
                                                <Link
                                                    href={`/activities?project=${a.project_id}`}
                                                >
                                                    <strong>{a.title}</strong>
                                                </Link>
                                                <small>{a.responsible}</small>
                                            </td>
                                            <td>
                                                <div className="table-progress">
                                                    <div className="progress-track">
                                                        <i
                                                            style={{
                                                                width: `${a.progress}%`,
                                                            }}
                                                        />
                                                    </div>
                                                    <span>{a.progress}%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <Badge>{a.status}</Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
                <section className="panel">
                    <div className="panel-heading">
                        <h2>Latest site activity</h2>
                        <span className="live-dot" />
                    </div>
                    {recent.length === 0 ? (
                        <Empty
                            title="Your site story starts here"
                            description="Project updates will appear as your team records the day’s work."
                        />
                    ) : (
                        <div className="timeline">
                            {recent.map((r) => (
                                <div key={r.id}>
                                    <span className="timeline-dot" />
                                    <div>
                                        <strong>
                                            {r.action
                                                .replaceAll(".", " ")
                                                .replaceAll("_", " ")}
                                        </strong>
                                        <p>
                                            {
                                                projects.find(
                                                    (p) =>
                                                        p.id === r.project_id,
                                                )?.name
                                            }
                                        </p>
                                        <small>
                                            {new Date(
                                                r.created_at + "Z",
                                            ).toLocaleString()}
                                        </small>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
            <section className="panel form-panel">
                <h2>Site coordination</h2>
                <div className="workspace-tools">
                    <Link href="/visitors?status=Onsite">
                        {stats.visitors} visitors onsite →
                    </Link>
                    <Link href="/workforce">
                        {stats.deployments} workers deployed today →
                    </Link>
                    <Link href="/instructions">
                        {stats.instructions} open instructions →
                    </Link>
                </div>
            </section>
        </Shell>
    );
}
