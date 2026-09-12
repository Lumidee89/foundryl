import { Head, router, useForm, usePage } from "@inertiajs/react";
import {
    Shell,
    Heading,
    Badge,
    Errors,
    Empty,
    type Project,
    type Shared,
} from "../components/ui";
type Deployment = {
    id: number;
    worker_id: number;
    project_id: number;
    supervisor_id: number;
    activity_id: number;
    team: string;
    location: string;
    attendance: string;
    onsite: boolean;
    expected_at: string;
};
type Person = { id: number; name: string };
export default function Workforce({
    deployments,
    date,
    selectedProject,
    projects,
    workers,
    activities,
    members,
    timezone,
}: {
    deployments: Deployment[];
    date: string;
    selectedProject: string | number;
    projects: Project[];
    workers: (Person & { project_id: number; trade: string })[];
    activities: { id: number; title: string; project_id: number }[];
    members: Person[];
    timezone: string;
}) {
    const { permissions } = usePage<Shared>().props;
    const form = useForm({
        project_id: "",
        worker_id: "",
        activity_id: "",
        supervisor_id: "",
        team: "",
        location: "",
        work_date: date,
        expected_at: "08:00",
    });
    const present = deployments.filter((d) =>
        ["Present", "Late"].includes(d.attendance),
    ).length;
    return (
        <Shell title="Workforce deployment">
            <Head title="Workforce deployment" />
            <Heading
                title="Workforce deployment"
                description="Connect each worker to a team, work location, activity, and responsible supervisor."
            />
            <div className="list-toolbar">
                <label>
                    Work date
                    <input
                        type="date"
                        value={date}
                        onChange={(e) =>
                            router.get("/workforce", {
                                date: e.target.value,
                                project: selectedProject,
                            })
                        }
                    />
                </label>
                <label>
                    Project
                    <select
                        value={selectedProject}
                        onChange={(e) =>
                            router.get("/workforce", {
                                date,
                                project: e.target.value,
                            })
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
            </div>
            <section className="panel form-panel">
                <h2>
                    {deployments.length} expected · {present} attended ·{" "}
                    {deployments.length - present} not yet attended
                </h2>
                <p>
                    {
                        deployments.filter((d) => d.attendance === "Absent")
                            .length
                    }{" "}
                    absent after expected arrival ·{" "}
                    {deployments.filter((d) => d.attendance === "Late").length}{" "}
                    late · {deployments.filter((d) => d.onsite).length}{" "}
                    currently onsite
                </p>
                <small>
                    Times use {timezone}. Attendance uses gate records for this
                    date. Currently onsite reflects open gate entries. One
                    deployment per worker per day.
                </small>
            </section>
            {permissions.includes("activities.manage") && (
                <section className="panel form-panel">
                    <h2>Assign a worker</h2>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post("/workforce", {
                                onSuccess: () => form.reset("worker_id"),
                            });
                        }}
                    >
                        <Errors errors={form.errors} />
                        <div className="form-grid">
                            <label>
                                Project
                                <select
                                    required
                                    value={form.data.project_id}
                                    onChange={(e) =>
                                        form.setData({
                                            ...form.data,
                                            project_id: e.target.value,
                                            worker_id: "",
                                            activity_id: "",
                                        })
                                    }
                                >
                                    <option value="">Choose project</option>
                                    {projects.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                Worker
                                <select
                                    required
                                    value={form.data.worker_id}
                                    onChange={(e) =>
                                        form.setData(
                                            "worker_id",
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Choose worker</option>
                                    {workers
                                        .filter(
                                            (w) =>
                                                w.project_id ===
                                                Number(form.data.project_id),
                                        )
                                        .map((w) => (
                                            <option key={w.id} value={w.id}>
                                                {w.name} · {w.trade}
                                            </option>
                                        ))}
                                </select>
                            </label>
                            <label>
                                Activity on {date}
                                <select
                                    required
                                    value={form.data.activity_id}
                                    onChange={(e) =>
                                        form.setData(
                                            "activity_id",
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        Choose planned activity
                                    </option>
                                    {activities
                                        .filter(
                                            (a) =>
                                                a.project_id ===
                                                Number(form.data.project_id),
                                        )
                                        .map((a) => (
                                            <option key={a.id} value={a.id}>
                                                {a.title}
                                            </option>
                                        ))}
                                </select>
                            </label>
                            <label>
                                Supervisor
                                <select
                                    required
                                    value={form.data.supervisor_id}
                                    onChange={(e) =>
                                        form.setData(
                                            "supervisor_id",
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Choose supervisor</option>
                                    {members.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                Team / trade
                                <input
                                    required
                                    maxLength={120}
                                    value={form.data.team}
                                    onChange={(e) =>
                                        form.setData("team", e.target.value)
                                    }
                                />
                            </label>
                            <label>
                                Work location
                                <input
                                    required
                                    maxLength={160}
                                    value={form.data.location}
                                    onChange={(e) =>
                                        form.setData("location", e.target.value)
                                    }
                                />
                            </label>
                            <label>
                                Expected arrival ({timezone})
                                <input
                                    type="time"
                                    required
                                    value={form.data.expected_at}
                                    onChange={(e) =>
                                        form.setData(
                                            "expected_at",
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                        </div>
                        <button
                            disabled={form.processing}
                            className="button primary"
                        >
                            Deploy worker
                        </button>
                    </form>
                </section>
            )}
            <section className="panel">
                {!deployments.length ? (
                    <Empty
                        title="No planned deployment"
                        description="Assign workers to today's activities to compare expected and actual attendance."
                    />
                ) : (
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Worker / team</th>
                                    <th>Activity / location</th>
                                    <th>Supervisor</th>
                                    <th>Attendance</th>
                                </tr>
                            </thead>
                            <tbody>
                                {deployments.map((d) => (
                                    <tr key={d.id}>
                                        <td>
                                            <strong>
                                                {
                                                    workers.find(
                                                        (w) =>
                                                            w.id ===
                                                            d.worker_id,
                                                    )?.name
                                                }
                                            </strong>
                                            <small>
                                                {d.team} ·{" "}
                                                {
                                                    projects.find(
                                                        (p) =>
                                                            p.id ===
                                                            d.project_id,
                                                    )?.name
                                                }
                                            </small>
                                        </td>
                                        <td>
                                            {
                                                activities.find(
                                                    (a) =>
                                                        a.id === d.activity_id,
                                                )?.title
                                            }
                                            <small>{d.location}</small>
                                        </td>
                                        <td>
                                            {members.find(
                                                (m) => m.id === d.supervisor_id,
                                            )?.name || "Former member"}
                                        </td>
                                        <td>
                                            <Badge>{d.attendance}</Badge>
                                            <small>
                                                Expected {d.expected_at}
                                                {d.onsite
                                                    ? " · Currently onsite"
                                                    : ""}
                                            </small>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </Shell>
    );
}
