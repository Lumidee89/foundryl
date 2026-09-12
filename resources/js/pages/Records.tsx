import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { Plus, X, LogOut } from "lucide-react";
import { useState } from "react";
import {
    Shell,
    Heading,
    Badge,
    Empty,
    Errors,
    type Project,
    type Shared,
} from "../components/ui";
type Row = {
    id: number;
    project_id: number;
    name?: string;
    trade?: string;
    phone?: string;
    title?: string;
    notes?: string;
    weather?: string;
    entry_date?: string;
    planned_date?: string;
    responsible?: string;
    location?: string;
    status?: string;
    progress?: number;
    reason?: string;
    worker_id?: number;
    arrived_at?: string;
    departed_at?: string;
};
const config = {
    workers: {
        title: "People",
        description:
            "The people behind your projects. Keep your workforce connected.",
        add: "Add worker",
    },
    gate: {
        title: "Gate & attendance",
        description:
            "Know who’s onsite. Record arrivals and departures as they happen.",
        add: "Record arrival",
    },
    activities: {
        title: "Daily work plan",
        description:
            "Set the plan, assign responsibility, and see the day’s progress.",
        add: "Plan activity",
    },
    diary: {
        title: "Site diary",
        description: "A reliable record of what happened, where, and when.",
        add: "Add diary entry",
    },
};
export default function Records({
    module,
    records,
    projects,
    workers,
    filters,
}: {
    module: keyof typeof config;
    records: {
        data: Row[];
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
    projects: Project[];
    workers: { id: number; name: string; project_id: number }[];
    filters: { project?: string; status?: string; date?: string };
}) {
    const { permissions } = usePage<Shared>().props;
    const canManage = permissions.includes(module + ".manage");
    const [open, setOpen] = useState(false);
    const [edit, setEdit] = useState<Row | null>(null);
    const c = config[module];
    const today = new Date().toLocaleDateString("en-CA");
    const form = useForm({
        project_id: filters.project || projects[0]?.id.toString() || "",
        name: "",
        trade: "",
        phone: "",
        title: "",
        location: "",
        responsible: "",
        planned_date: today,
        entry_date: today,
        notes: "",
        weather: "",
        worker_id: "",
    });
    const update = useForm({ status: "Planned", progress: 0, reason: "" });
    const projectName = (id: number) =>
        projects.find((p) => p.id === id)?.name || "";
    const workerName = (id?: number) =>
        workers.find((w) => w.id === id)?.name || "Worker";
    const field = (
        key: keyof typeof form.data,
        label: string,
        type = "text",
        required = true,
    ) => (
        <label>
            {label}
            <input
                type={type}
                required={required}
                value={form.data[key]}
                onChange={(e) => form.setData(key, e.target.value)}
            />
        </label>
    );
    return (
        <Shell title={c.title}>
            <Head title={c.title} />
            <Heading
                eyebrow="EVERYDAY SITE OPERATIONS"
                title={c.title}
                description={c.description}
                action={
                    canManage &&
                    projects.length > 0 && (
                        <button
                            className="button primary"
                            onClick={() => setOpen(!open)}
                        >
                            <Plus size={17} />
                            {c.add}
                        </button>
                    )
                }
            />
            {open && (
                <section className="panel form-panel">
                    <div className="panel-heading">
                        <h2>{c.add}</h2>
                        <button
                            aria-label="Close form"
                            className="icon-button"
                            onClick={() => setOpen(false)}
                        >
                            <X />
                        </button>
                    </div>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(`/${module}`, {
                                onSuccess: () => {
                                    setOpen(false);
                                    form.reset();
                                },
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
                                    onChange={(e) => {
                                        form.setData(
                                            "project_id",
                                            e.target.value,
                                        );
                                        form.setData("worker_id", "");
                                    }}
                                >
                                    <option value="">Select project</option>
                                    {projects.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            {module === "workers" && (
                                <>
                                    {field("name", "Full name")}
                                    {field("trade", "Trade / team")}
                                    {field("phone", "Phone", "tel", false)}
                                </>
                            )}
                            {module === "gate" && (
                                <label>
                                    Worker
                                    <select
                                        required
                                        aria-label="Worker"
                                        aria-describedby="worker-help"
                                        value={form.data.worker_id}
                                        onChange={(e) =>
                                            form.setData(
                                                "worker_id",
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Select worker</option>
                                        {workers
                                            .filter(
                                                (w) =>
                                                    w.project_id ===
                                                    Number(
                                                        form.data.project_id,
                                                    ),
                                            )
                                            .map((w) => (
                                                <option key={w.id} value={w.id}>
                                                    {w.name}
                                                </option>
                                            ))}
                                    </select>
                                    <small id="worker-help">
                                        Add the worker to this project in People
                                        first.
                                    </small>
                                </label>
                            )}
                            {module === "activities" && (
                                <>
                                    {field("title", "Activity")}
                                    {field("location", "Work location")}
                                    {field(
                                        "responsible",
                                        "Responsible supervisor",
                                    )}
                                    {field(
                                        "planned_date",
                                        "Planned date",
                                        "date",
                                    )}
                                </>
                            )}
                            {module === "diary" && (
                                <>
                                    {field("title", "Entry title")}
                                    {field("entry_date", "Site date", "date")}
                                    {field("weather", "Weather", "text", false)}
                                    <label className="full-width">
                                        Site notes
                                        <textarea
                                            required
                                            value={form.data.notes}
                                            onChange={(e) =>
                                                form.setData(
                                                    "notes",
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Work completed, delays, observations, and outstanding matters…"
                                        />
                                    </label>
                                </>
                            )}
                        </div>
                        <button
                            className="button primary"
                            disabled={form.processing}
                        >
                            {form.processing ? "Saving…" : c.add}
                        </button>
                    </form>
                </section>
            )}
            {edit && (
                <section className="panel form-panel">
                    <div className="panel-heading">
                        <h2>Update: {edit.title}</h2>
                        <button
                            className="icon-button"
                            aria-label="Close update"
                            onClick={() => setEdit(null)}
                        >
                            <X />
                        </button>
                    </div>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            update.patch(`/activities/${edit.id}`, {
                                onSuccess: () => setEdit(null),
                            });
                        }}
                    >
                        <Errors errors={update.errors} />
                        <div className="form-grid">
                            <label>
                                Status
                                <select
                                    value={update.data.status}
                                    onChange={(e) =>
                                        update.setData("status", e.target.value)
                                    }
                                >
                                    {[
                                        "Planned",
                                        "In Progress",
                                        "Completed",
                                        "Delayed",
                                        "Blocked",
                                        "Not Completed",
                                    ].map((s) => (
                                        <option key={s}>{s}</option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                Progress (%)
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={update.data.progress}
                                    onChange={(e) =>
                                        update.setData(
                                            "progress",
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </label>
                            <label className="full-width">
                                Reason / update
                                <textarea
                                    required={[
                                        "Delayed",
                                        "Blocked",
                                        "Not Completed",
                                    ].includes(update.data.status)}
                                    value={update.data.reason}
                                    onChange={(e) =>
                                        update.setData("reason", e.target.value)
                                    }
                                />
                            </label>
                        </div>
                        <button
                            className="button primary"
                            disabled={update.processing}
                        >
                            Save progress
                        </button>
                    </form>
                </section>
            )}
            <div className="list-toolbar">
                <span>
                    {records.total} records
                    {filters.status ? ` · ${filters.status}` : ""}
                    {filters.date ? " · Today" : ""}
                </span>
                <div className="toolbar-filters">
                    {(filters.status || filters.date) && (
                        <Link href={`/${module}`} className="text-link">
                            Clear filters
                        </Link>
                    )}
                    <select
                        aria-label="Filter by project"
                        value={filters.project || ""}
                        onChange={(e) =>
                            router.get(
                                `/${module}`,
                                { ...filters, project: e.target.value },
                                { preserveState: true },
                            )
                        }
                    >
                        <option value="">All authorized projects</option>
                        {projects.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </select>
                </div>
            </div>
            <section className="panel">
                {records.data.length === 0 ? (
                    <Empty
                        title={
                            projects.length
                                ? "No records yet"
                                : "Create a project first"
                        }
                        description={
                            projects.length
                                ? `Use “${c.add}” to start capturing your site’s working day.`
                                : "Projects give your workforce and operations a shared home."
                        }
                    />
                ) : (
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>
                                        {module === "workers"
                                            ? "Worker"
                                            : module === "gate"
                                              ? "Person"
                                              : "Record"}
                                    </th>
                                    <th>Project</th>
                                    <th>
                                        {module === "workers"
                                            ? "Trade"
                                            : module === "gate"
                                              ? "Arrival"
                                              : module === "diary"
                                                ? "Site date"
                                                : "Planned date"}
                                    </th>
                                    <th>
                                        {module === "workers"
                                            ? "Phone"
                                            : module === "gate"
                                              ? "Status"
                                              : module === "diary"
                                                ? "Weather"
                                                : "Progress / Status"}
                                    </th>
                                    {canManage &&
                                        ["gate", "activities"].includes(
                                            module,
                                        ) && <th>Action</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {records.data.map((r) => (
                                    <tr key={r.id}>
                                        <td>
                                            <strong>
                                                {module === "gate"
                                                    ? workerName(r.worker_id)
                                                    : r.name || r.title}
                                            </strong>
                                            {r.responsible && (
                                                <small>
                                                    {r.responsible} ·{" "}
                                                    {r.location}
                                                </small>
                                            )}
                                            {r.notes && (
                                                <p className="record-notes">
                                                    {r.notes}
                                                </p>
                                            )}
                                            {r.reason && (
                                                <small>{r.reason}</small>
                                            )}
                                        </td>
                                        <td>
                                            <Link
                                                href={`/projects/${r.project_id}`}
                                            >
                                                {projectName(r.project_id)}
                                            </Link>
                                        </td>
                                        <td>
                                            {module === "workers"
                                                ? r.trade
                                                : module === "gate"
                                                  ? new Date(
                                                        r.arrived_at + "Z",
                                                    ).toLocaleString()
                                                  : r.entry_date ||
                                                    r.planned_date}
                                        </td>
                                        <td>
                                            {module === "workers" ? (
                                                r.phone || "—"
                                            ) : module === "gate" ? (
                                                <>
                                                    <Badge>
                                                        {r.departed_at
                                                            ? "Left site"
                                                            : "Onsite"}
                                                    </Badge>
                                                    {r.departed_at && (
                                                        <small>
                                                            {new Date(
                                                                r.departed_at +
                                                                    "Z",
                                                            ).toLocaleString()}
                                                        </small>
                                                    )}
                                                </>
                                            ) : module === "diary" ? (
                                                r.weather || "—"
                                            ) : (
                                                <>
                                                    <Badge>{r.status}</Badge>
                                                    <small>
                                                        {r.progress}% complete
                                                    </small>
                                                </>
                                            )}
                                        </td>
                                        {canManage && module === "gate" && (
                                            <td>
                                                {!r.departed_at && (
                                                    <button
                                                        className="button secondary small-button"
                                                        onClick={() =>
                                                            router.post(
                                                                `/gate/${r.id}/depart`,
                                                            )
                                                        }
                                                    >
                                                        <LogOut size={14} />
                                                        Check out
                                                    </button>
                                                )}
                                            </td>
                                        )}
                                        {canManage &&
                                            module === "activities" && (
                                                <td>
                                                    <button
                                                        className="button secondary small-button"
                                                        onClick={() => {
                                                            setEdit(r);
                                                            update.setData({
                                                                status:
                                                                    r.status ||
                                                                    "Planned",
                                                                progress:
                                                                    r.progress ||
                                                                    0,
                                                                reason:
                                                                    r.reason ||
                                                                    "",
                                                            });
                                                        }}
                                                    >
                                                        Update
                                                    </button>
                                                </td>
                                            )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
            {(records.prev_page_url || records.next_page_url) && (
                <div className="pagination">
                    {records.prev_page_url && (
                        <Link
                            className="button secondary"
                            href={records.prev_page_url}
                        >
                            Previous
                        </Link>
                    )}
                    {records.next_page_url && (
                        <Link
                            className="button secondary"
                            href={records.next_page_url}
                        >
                            Next
                        </Link>
                    )}
                </div>
            )}
        </Shell>
    );
}
