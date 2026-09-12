import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import {
    Shell,
    Heading,
    Badge,
    Errors,
    Empty,
    type Project,
    type Shared,
} from "../components/ui";
type Visit = {
    id: number;
    project_id: number;
    name: string;
    purpose: string;
    vehicle: string | null;
    host_id: number;
    status: string;
    arrived_at: string | null;
    departed_at: string | null;
};
export default function Visitors({
    visitors,
    projects,
    members,
    filters,
}: {
    visitors: {
        data: Visit[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    projects: Project[];
    members: { id: number; name: string }[];
    filters: { project?: string; status?: string };
}) {
    const { permissions, auth } = usePage<Shared>().props;
    const canGate = permissions.includes("gate.manage");
    const form = useForm({
        project_id: "",
        name: "",
        purpose: "",
        vehicle: "",
        host_id: "",
    });
    const action = useForm({ status: "" });
    const change = (id: number, status: string) => {
        action.transform(() => ({ status }));
        action.patch(`/visitors/${id}`);
    };
    return (
        <Shell title="Visitors">
            <Head title="Visitors" />
            <Heading
                title="Visitors"
                description="Request a visit, obtain host authorization, and record entry and departure."
            />
            {canGate && (
                <section className="panel form-panel">
                    <h2>Request a visit</h2>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post("/visitors", {
                                onSuccess: () =>
                                    form.reset("name", "purpose", "vehicle"),
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
                                        form.setData(
                                            "project_id",
                                            e.target.value,
                                        )
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
                                Visitor name
                                <input
                                    required
                                    maxLength={160}
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData("name", e.target.value)
                                    }
                                />
                            </label>
                            <label>
                                Purpose
                                <input
                                    required
                                    maxLength={500}
                                    value={form.data.purpose}
                                    onChange={(e) =>
                                        form.setData("purpose", e.target.value)
                                    }
                                />
                            </label>
                            <label>
                                Vehicle registration (optional)
                                <input
                                    maxLength={80}
                                    value={form.data.vehicle}
                                    onChange={(e) =>
                                        form.setData("vehicle", e.target.value)
                                    }
                                />
                            </label>
                            <label>
                                Host
                                <select
                                    required
                                    value={form.data.host_id}
                                    onChange={(e) =>
                                        form.setData("host_id", e.target.value)
                                    }
                                >
                                    <option value="">
                                        Choose project host
                                    </option>
                                    {members.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>
                        <button
                            className="button primary"
                            disabled={form.processing}
                        >
                            Request authorization
                        </button>
                    </form>
                </section>
            )}
            <div className="list-toolbar">
                <label>
                    Project
                    <select
                        value={filters.project || ""}
                        onChange={(e) =>
                            router.get("/visitors", {
                                ...filters,
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
                <Link
                    href={
                        filters.status ? "/visitors" : "/visitors?status=Onsite"
                    }
                    className="text-link"
                >
                    {filters.status ? "Show all visits" : "Currently onsite"}
                </Link>
            </div>
            <Errors errors={action.errors} />
            <section className="panel">
                {!visitors.data.length ? (
                    <Empty
                        title="No visits"
                        description="Visitor requests and arrival history will appear here."
                    />
                ) : (
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Visitor</th>
                                    <th>Project / host</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {visitors.data.map((v) => (
                                    <tr key={v.id}>
                                        <td>
                                            <strong>{v.name}</strong>
                                            <small>{v.purpose}</small>
                                            <small>
                                                {v.vehicle || "No vehicle"}
                                            </small>
                                        </td>
                                        <td>
                                            {
                                                projects.find(
                                                    (p) =>
                                                        p.id === v.project_id,
                                                )?.name
                                            }
                                            <small>
                                                {members.find(
                                                    (m) => m.id === v.host_id,
                                                )?.name || "Former member"}
                                            </small>
                                        </td>
                                        <td>
                                            <Badge>{v.status}</Badge>
                                            {v.arrived_at && (
                                                <small>
                                                    Arrived {v.arrived_at} UTC
                                                </small>
                                            )}
                                            {v.departed_at && (
                                                <small>
                                                    Departed {v.departed_at} UTC
                                                </small>
                                            )}
                                        </td>
                                        <td>
                                            {v.status === "Pending" &&
                                                auth.user.id === v.host_id && (
                                                    <>
                                                        <button
                                                            disabled={
                                                                action.processing
                                                            }
                                                            className="button secondary small-button"
                                                            onClick={() =>
                                                                change(
                                                                    v.id,
                                                                    "Authorized",
                                                                )
                                                            }
                                                        >
                                                            Authorize
                                                        </button>
                                                        <button
                                                            disabled={
                                                                action.processing
                                                            }
                                                            className="button secondary small-button"
                                                            onClick={() =>
                                                                change(
                                                                    v.id,
                                                                    "Rejected",
                                                                )
                                                            }
                                                        >
                                                            Reject
                                                        </button>
                                                    </>
                                                )}
                                            {canGate &&
                                                [
                                                    "Authorized",
                                                    "Onsite",
                                                ].includes(v.status) && (
                                                    <button
                                                        disabled={
                                                            action.processing
                                                        }
                                                        className="button secondary small-button"
                                                        onClick={() =>
                                                            change(
                                                                v.id,
                                                                v.status ===
                                                                    "Authorized"
                                                                    ? "Onsite"
                                                                    : "Departed",
                                                            )
                                                        }
                                                    >
                                                        {v.status ===
                                                        "Authorized"
                                                            ? "Record arrival"
                                                            : "Record departure"}
                                                    </button>
                                                )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
            <div className="pagination">
                {visitors.prev_page_url && (
                    <Link href={visitors.prev_page_url}>Previous</Link>
                )}
                {visitors.next_page_url && (
                    <Link href={visitors.next_page_url}>Next</Link>
                )}
            </div>
        </Shell>
    );
}
