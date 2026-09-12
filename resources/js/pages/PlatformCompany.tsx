import { Link, useForm } from "@inertiajs/react";
import PlatformLayout, { BackToPlatform } from "../components/PlatformLayout";
import { Heading, Badge, Errors } from "../components/ui";
export default function PlatformCompany({
    company,
    counts,
    members,
}: {
    company: { id: number; name: string; suspended_at: string | null };
    counts: Record<string, number>;
    members: { name: string; email: string; role: string }[];
}) {
    const form = useForm({
        suspended: !company.suspended_at,
        reason: "",
        password: "",
    });
    return (
        <PlatformLayout title={company.name}>
            <BackToPlatform />
            <Heading
                title={company.name}
                description="Company oversight. Viewing these records is recorded in the platform audit history."
                action={
                    <Badge>
                        {company.suspended_at ? "Suspended" : "Active"}
                    </Badge>
                }
            />
            <div className="stat-grid">
                {Object.entries(counts).map(([module, count]) => (
                    <Link
                        href={`/platform/companies/${company.id}/records/${module}`}
                        className="stat-card"
                        key={module}
                    >
                        <span>{module.replaceAll("_", " ")}</span>
                        <strong>{count}</strong>
                        <small>Review records →</small>
                    </Link>
                ))}
            </div>
            <section className="panel">
                <div className="panel-heading">
                    <h2>Company members</h2>
                </div>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            {members.map((m) => (
                                <tr key={m.email}>
                                    <td>{m.name}</td>
                                    <td>{m.email}</td>
                                    <td>{m.role}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
            <section className="panel form-panel spaced">
                <div className="panel-heading">
                    <h2>
                        {company.suspended_at
                            ? "Reactivate workspace"
                            : "Suspend workspace"}
                    </h2>
                </div>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            suspended: !company.suspended_at,
                        }));
                        form.patch(`/platform/companies/${company.id}`, {
                            onFinish: () => form.reset("password"),
                            onSuccess: () => form.reset("reason"),
                        });
                    }}
                >
                    <p>
                        {company.suspended_at
                            ? "Restore the company’s access to its workspace."
                            : "Suspension blocks company members from accessing their workspace. Records are retained. This is an operational control, not a payment restriction."}
                    </p>
                    <Errors errors={form.errors} />
                    <div className="form-grid">
                        <label>
                            Reason
                            <textarea
                                required
                                minLength={10}
                                value={form.data.reason}
                                onChange={(e) =>
                                    form.setData("reason", e.target.value)
                                }
                            />
                        </label>
                        <label>
                            Confirm your password
                            <input
                                required
                                type="password"
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(e) =>
                                    form.setData("password", e.target.value)
                                }
                            />
                        </label>
                    </div>
                    <button
                        className="button primary"
                        disabled={form.processing}
                    >
                        {company.suspended_at
                            ? "Reactivate company"
                            : "Suspend company"}
                    </button>
                </form>
            </section>
        </PlatformLayout>
    );
}
