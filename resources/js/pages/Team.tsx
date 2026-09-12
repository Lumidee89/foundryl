import { useState } from "react";
import { useForm, router, usePage } from "@inertiajs/react";
import {
    Shell,
    Heading,
    Badge,
    Errors,
    type Project,
    type Shared,
} from "../components/ui";
type Member = {
    id: number;
    user_id: number;
    name: string;
    email: string;
    role: string;
    department: string | null;
    project_ids: number[];
};
export default function Team({
    members,
    projects,
    departments,
    invitations,
    roles,
}: {
    members: Member[];
    projects: Project[];
    departments: { id: number; name: string }[];
    invitations: {
        id: number;
        email: string;
        role: string;
        expires_at: string;
        accepted_at: string | null;
        revoked_at: string | null;
    }[];
    roles: string[];
}) {
    const { organization } = usePage<Shared>().props;
    const [editing, setEditing] = useState<Member | null>(null);
    const form = useForm({
        email: "",
        role: "viewer",
        department: "",
        project_ids: [] as number[],
    });
    const department = useForm({ name: "" });
    const company = useForm({ name: organization.name });
    const cancel = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
    };
    return (
        <Shell title="Team administration">
            <Heading
                title="Team & company"
                description="Invite colleagues, organize departments, and assign each person to the right projects."
            />
            <section className="panel form-panel">
                <div className="panel-heading">
                    <h2>
                        {editing
                            ? `Edit access: ${editing.name}`
                            : "Invite a colleague"}
                    </h2>
                    {editing && (
                        <button
                            className="button secondary small-button"
                            onClick={cancel}
                        >
                            Cancel editing
                        </button>
                    )}
                </div>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        const options = { onSuccess: cancel };
                        if (editing) {
                            form.patch(`/team/members/${editing.id}`, options);
                        } else {
                            form.post("/team/invitations", options);
                        }
                    }}
                >
                    <Errors errors={form.errors} />
                    <div className="form-grid">
                        {!editing && (
                            <label>
                                Email address
                                <input
                                    required
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData("email", e.target.value)
                                    }
                                />
                            </label>
                        )}
                        <label>
                            Role
                            <select
                                value={form.data.role}
                                onChange={(e) =>
                                    form.setData("role", e.target.value)
                                }
                            >
                                {roles.map((r) => (
                                    <option key={r}>{r}</option>
                                ))}
                            </select>
                        </label>
                        <label>
                            Department
                            <select
                                value={form.data.department}
                                onChange={(e) =>
                                    form.setData("department", e.target.value)
                                }
                            >
                                <option value="">No department</option>
                                {departments.map((d) => (
                                    <option key={d.id}>{d.name}</option>
                                ))}
                            </select>
                        </label>
                        <fieldset className="project-checkboxes">
                            <legend>
                                Project access (choose at least one)
                            </legend>
                            {projects.map((p) => (
                                <label key={p.id}>
                                    <input
                                        type="checkbox"
                                        checked={form.data.project_ids.includes(
                                            p.id,
                                        )}
                                        onChange={(e) =>
                                            form.setData(
                                                "project_ids",
                                                e.target.checked
                                                    ? [
                                                          ...form.data
                                                              .project_ids,
                                                          p.id,
                                                      ]
                                                    : form.data.project_ids.filter(
                                                          (id) => id !== p.id,
                                                      ),
                                            )
                                        }
                                    />
                                    {p.name}
                                </label>
                            ))}
                        </fieldset>
                    </div>
                    <button
                        className="button primary"
                        disabled={form.processing || projects.length === 0}
                    >
                        {editing ? "Save access" : "Send invitation"}
                    </button>
                    {projects.length === 0 && (
                        <p>
                            Create a project before inviting project-scoped
                            colleagues.
                        </p>
                    )}
                </form>
            </section>
            <section className="panel">
                <div className="panel-heading">
                    <h2>Workspace members</h2>
                </div>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Name / Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Projects</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {members.map((m) => (
                                <tr key={m.id}>
                                    <td>
                                        {m.name}
                                        <small>{m.email}</small>
                                    </td>
                                    <td>
                                        <Badge>{m.role}</Badge>
                                    </td>
                                    <td>{m.department || "—"}</td>
                                    <td>
                                        {m.role === "owner"
                                            ? "All company projects"
                                            : projects
                                                  .filter((p) =>
                                                      m.project_ids.includes(
                                                          p.id,
                                                      ),
                                                  )
                                                  .map((p) => p.name)
                                                  .join(", ")}
                                    </td>
                                    <td>
                                        {m.role !== "owner" && (
                                            <div className="row-actions">
                                                <button
                                                    className="button secondary small-button"
                                                    onClick={() => {
                                                        setEditing(m);
                                                        form.setData({
                                                            email: m.email,
                                                            role: m.role,
                                                            department:
                                                                m.department ||
                                                                "",
                                                            project_ids:
                                                                m.project_ids,
                                                        });
                                                        window.scrollTo({
                                                            top: 0,
                                                            behavior: "smooth",
                                                        });
                                                    }}
                                                >
                                                    Edit access
                                                </button>
                                                <button
                                                    className="button secondary small-button"
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                `Remove ${m.name} from this workspace? Historical records will remain.`,
                                                            )
                                                        )
                                                            router.delete(
                                                                `/team/members/${m.id}`,
                                                            );
                                                    }}
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
            <section className="panel spaced">
                <div className="panel-heading">
                    <h2>Invitations</h2>
                </div>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Expires (UTC)</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {invitations.map((i) => (
                                <tr key={i.id}>
                                    <td>{i.email}</td>
                                    <td>{i.role}</td>
                                    <td>
                                        {i.accepted_at
                                            ? "Accepted"
                                            : i.revoked_at
                                              ? "Revoked"
                                              : new Date(i.expires_at + "Z") <
                                                  new Date()
                                                ? "Expired"
                                                : "Pending"}
                                    </td>
                                    <td>{i.expires_at}</td>
                                    <td>
                                        {!i.accepted_at && !i.revoked_at && (
                                            <button
                                                className="button secondary small-button"
                                                onClick={() =>
                                                    router.delete(
                                                        `/team/invitations/${i.id}`,
                                                    )
                                                }
                                            >
                                                Revoke
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
            <div className="dashboard-columns spaced">
                <section className="panel form-panel">
                    <div className="panel-heading">
                        <h2>Departments</h2>
                    </div>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            department.post("/team/departments", {
                                onSuccess: () => department.reset(),
                            });
                        }}
                    >
                        <Errors errors={department.errors} />
                        <p>
                            {departments.map((d) => d.name).join(" · ") ||
                                "No departments yet."}
                        </p>
                        <label>
                            Department name
                            <input
                                required
                                value={department.data.name}
                                onChange={(e) =>
                                    department.setData("name", e.target.value)
                                }
                            />
                        </label>
                        <button
                            className="button primary spaced"
                            disabled={department.processing}
                        >
                            Add department
                        </button>
                    </form>
                </section>
                <section className="panel form-panel">
                    <div className="panel-heading">
                        <h2>Company settings</h2>
                    </div>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            company.patch("/team/company");
                        }}
                    >
                        <Errors errors={company.errors} />
                        <label>
                            Company name
                            <input
                                required
                                value={company.data.name}
                                onChange={(e) =>
                                    company.setData("name", e.target.value)
                                }
                            />
                        </label>
                        <button
                            className="button primary spaced"
                            disabled={company.processing}
                        >
                            Save company name
                        </button>
                    </form>
                </section>
            </div>
        </Shell>
    );
}
