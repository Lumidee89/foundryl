import { useForm, router } from "@inertiajs/react";
import AccountLayout from "../components/AccountLayout";
import PasswordInput from "../components/PasswordInput";
import { Heading, Errors, Badge } from "../components/ui";
export default function Account({
    profile,
    sessions,
}: {
    profile: { name: string; email: string; email_verified_at: string | null };
    sessions: {
        current: boolean;
        ip_address: string;
        user_agent: string;
        last_activity: number;
    }[];
}) {
    const form = useForm({
        current_password: "",
        password: "",
        password_confirmation: "",
    });
    const revoke = useForm({ password: "" });
    return (
        <AccountLayout title="Account security">
            <Heading
                title="Account & security"
                description={`${profile.name} · ${profile.email}`}
            />
            <section className="panel form-panel">
                <div className="panel-heading">
                    <h2>Email verification</h2>
                    <Badge>
                        {profile.email_verified_at
                            ? "Verified"
                            : "Not verified"}
                    </Badge>
                </div>
                {!profile.email_verified_at && (
                    <div className="inline-form">
                        <p>
                            Verify your email address to confirm ownership of
                            this account.
                        </p>
                        <button
                            className="button secondary"
                            onClick={() => router.post("/email/verification")}
                        >
                            Send verification link
                        </button>
                    </div>
                )}
            </section>
            <section className="panel form-panel">
                <div className="panel-heading">
                    <h2>Change password</h2>
                </div>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch("/account/password", {
                            onFinish: () => form.reset(),
                        });
                    }}
                >
                    <Errors errors={form.errors} />
                    <div className="form-grid">
                        <label>
                            Current password
                            <PasswordInput
                                required
                                autoComplete="current-password"
                                label="Current password"
                                value={form.data.current_password}
                                onChange={(e) =>
                                    form.setData(
                                        "current_password",
                                        e.target.value,
                                    )
                                }
                            />
                        </label>
                        <label>
                            New password
                            <PasswordInput
                                required
                                minLength={6}
                                autoComplete="new-password"
                                value={form.data.password}
                                onChange={(e) =>
                                    form.setData("password", e.target.value)
                                }
                            />
                        </label>
                        <label>
                            Confirm password
                            <PasswordInput
                                required
                                minLength={6}
                                label="Confirm password"
                                autoComplete="new-password"
                                value={form.data.password_confirmation}
                                onChange={(e) =>
                                    form.setData(
                                        "password_confirmation",
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
                        Update password
                    </button>
                </form>
            </section>
            <section className="panel form-panel">
                <div className="panel-heading">
                    <h2>Browser sessions</h2>
                </div>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Browser</th>
                                <th>Address</th>
                                <th>Last activity</th>
                                <th>Session</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sessions.map((s, i) => (
                                <tr key={i}>
                                    <td className="platform-record-cell">
                                        {s.user_agent}
                                    </td>
                                    <td>{s.ip_address}</td>
                                    <td>
                                        {new Date(
                                            s.last_activity * 1000,
                                        ).toLocaleString()}
                                    </td>
                                    <td>
                                        {s.current
                                            ? "This browser"
                                            : "Other browser"}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        revoke.delete("/account/sessions", {
                            onFinish: () => revoke.reset(),
                        });
                    }}
                >
                    <Errors errors={revoke.errors} />
                    <label>
                        Confirm password to sign out other sessions
                        <PasswordInput
                            required
                            autoComplete="current-password"
                            value={revoke.data.password}
                            onChange={(e) =>
                                revoke.setData("password", e.target.value)
                            }
                        />
                    </label>
                    <button
                        className="button secondary spaced"
                        disabled={revoke.processing}
                    >
                        Sign out other sessions
                    </button>
                </form>
            </section>
        </AccountLayout>
    );
}
