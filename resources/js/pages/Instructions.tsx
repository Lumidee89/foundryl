import { useState } from "react";
import { useForm, usePage, router } from "@inertiajs/react";
import {
    Shell,
    Heading,
    Badge,
    Errors,
    Empty,
    type Project,
    type Shared,
} from "../components/ui";
import { PageLinks } from "../components/PlatformLayout";
type Instruction = {
    id: number;
    project_id: number;
    title: string;
    description: string;
    assigned_to: number;
    status: string;
    due_date: string;
    version: number;
    completion_notes: string | null;
};
export default function Instructions({
    instructions,
    projects,
    members,
    selectedProject,
}: {
    instructions: {
        data: Instruction[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    projects: Project[];
    members: {
        id: number;
        name: string;
        role: string;
        project_ids: number[];
    }[];
    selectedProject: string;
}) {
    const { auth, permissions } = usePage<Shared>().props;
    const user = auth.user as typeof auth.user & { id: number };
    const form = useForm({
        project_id: selectedProject || String(projects[0]?.id || ""),
        title: "",
        description: "",
        assigned_to: "",
        due_date: "",
    });
    const [editing, setEditing] = useState<Instruction | null>(null);
    const update = useForm({ status: "", version: 1, completion_notes: "" });
    const next: Record<string, string> = {
        Issued: "Acknowledged",
        Acknowledged: "In Progress",
        "In Progress": "Completed",
        Completed: "Closed",
    };
    return (
        <Shell title="Site instructions">
            <Heading
                title="Site instructions"
                description="Issue clear direction, acknowledge responsibility, and verify completed work."
            />
            {permissions.includes("instructions.manage") &&
                projects.length > 0 && (
                    <section className="panel form-panel">
                        <div className="panel-heading">
                            <h2>Issue an instruction</h2>
                        </div>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post("/instructions", {
                                    onSuccess: () =>
                                        form.reset(
                                            "title",
                                            "description",
                                            "assigned_to",
                                            "due_date",
                                        ),
                                });
                            }}
                        >
                            <Errors errors={form.errors} />
                            <div className="form-grid">
                                <label>
                                    Project
                                    <select
                                        value={form.data.project_id}
                                        onChange={(e) => {
                                            form.setData(
                                                "project_id",
                                                e.target.value,
                                            );
                                            form.setData("assigned_to", "");
                                        }}
                                    >
                                        {projects.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label>
                                    Title
                                    <input
                                        required
                                        value={form.data.title}
                                        onChange={(e) =>
                                            form.setData(
                                                "title",
                                                e.target.value,
                                            )
                                        }
                                    />
                                </label>
                                <label>
                                    Assigned recipient
                                    <select
                                        required
                                        value={form.data.assigned_to}
                                        onChange={(e) =>
                                            form.setData(
                                                "assigned_to",
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            Choose a member
                                        </option>
                                        {members
                                            .filter(
                                                (m) =>
                                                    m.role === "owner" ||
                                                    m.project_ids.includes(
                                                        Number(
                                                            form.data
                                                                .project_id,
                                                        ),
                                                    ),
                                            )
                                            .map((m) => (
                                                <option value={m.id} key={m.id}>
                                                    {m.name}
                                                </option>
                                            ))}
                                    </select>
                                </label>
                                <label>
                                    Due date
                                    <input
                                        required
                                        type="date"
                                        value={form.data.due_date}
                                        onChange={(e) =>
                                            form.setData(
                                                "due_date",
                                                e.target.value,
                                            )
                                        }
                                    />
                                </label>
                                <label className="full-width">
                                    Instruction
                                    <textarea
                                        required
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setData(
                                                "description",
                                                e.target.value,
                                            )
                                        }
                                    />
                                </label>
                            </div>
                            <button
                                className="button primary"
                                disabled={form.processing}
                            >
                                Issue instruction
                            </button>
                        </form>
                    </section>
                )}
            {editing && (
                <section className="panel form-panel">
                    <div className="panel-heading">
                        <h2>
                            {editing.title} → {update.data.status}
                        </h2>
                        <button
                            className="button secondary small-button"
                            onClick={() => setEditing(null)}
                        >
                            Cancel
                        </button>
                    </div>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            update.patch(`/instructions/${editing.id}`, {
                                onSuccess: () => setEditing(null),
                            });
                        }}
                    >
                        <Errors errors={update.errors} />
                        {update.data.status === "Completed" && (
                            <label>
                                Completion notes
                                <textarea
                                    required
                                    value={update.data.completion_notes}
                                    onChange={(e) =>
                                        update.setData(
                                            "completion_notes",
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                        )}
                        <button
                            className="button primary spaced"
                            disabled={update.processing}
                        >
                            Confirm {update.data.status.toLowerCase()}
                        </button>
                    </form>
                </section>
            )}
            <div className="list-toolbar">
                <span>Instructions across your assigned projects</span>
                <select
                    aria-label="Filter instructions by project"
                    value={selectedProject}
                    onChange={(e) =>
                        router.get(
                            "/instructions",
                            e.target.value ? { project: e.target.value } : {},
                        )
                    }
                >
                    <option value="">All projects</option>
                    {projects.map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.name}
                        </option>
                    ))}
                </select>
            </div>
            <section className="panel">
                {instructions.data.length ? (
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Instruction</th>
                                    <th>Project / Recipient</th>
                                    <th>Due</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {instructions.data.map((i) => (
                                    <tr key={i.id}>
                                        <td>
                                            <strong>{i.title}</strong>
                                            <p className="record-notes">
                                                {i.description}
                                            </p>
                                            {i.completion_notes && (
                                                <small>
                                                    Completion:{" "}
                                                    {i.completion_notes}
                                                </small>
                                            )}
                                        </td>
                                        <td>
                                            {
                                                projects.find(
                                                    (p) =>
                                                        p.id === i.project_id,
                                                )?.name
                                            }
                                            <small>
                                                {members.find(
                                                    (m) =>
                                                        m.id === i.assigned_to,
                                                )?.name ||
                                                    "Former project member"}
                                            </small>
                                        </td>
                                        <td>{i.due_date}</td>
                                        <td>
                                            <Badge>{i.status}</Badge>
                                        </td>
                                        <td>
                                            {next[i.status] &&
                                                ((i.status === "Completed" &&
                                                    permissions.includes(
                                                        "instructions.verify",
                                                    ) &&
                                                    i.assigned_to !==
                                                        user.id) ||
                                                    (i.status !== "Completed" &&
                                                        i.assigned_to ===
                                                            user.id)) && (
                                                    <button
                                                        className="button secondary small-button"
                                                        onClick={() => {
                                                            setEditing(i);
                                                            update.clearErrors();
                                                            update.setData({
                                                                status: next[
                                                                    i.status
                                                                ],
                                                                version:
                                                                    i.version,
                                                                completion_notes:
                                                                    "",
                                                            });
                                                        }}
                                                    >
                                                        {next[i.status] ===
                                                        "Closed"
                                                            ? "Verify & close"
                                                            : next[i.status]}
                                                    </button>
                                                )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty
                        title="No site instructions yet"
                        description="Project leaders can issue instructions to assigned team members."
                    />
                )}
            </section>
            <PageLinks data={instructions} />
        </Shell>
    );
}
