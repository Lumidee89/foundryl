import { Link, useForm, usePage, router } from "@inertiajs/react";
import AccountLayout from "../components/AccountLayout";
import PasswordInput from "../components/PasswordInput";
import { Errors } from "../components/ui";
export default function Invitation({
    invitation,
    token,
    existingAccount,
}: {
    invitation: { email: string; company: string; role: string };
    token: string;
    existingAccount: boolean;
}) {
    const { auth } = usePage<{
        auth: { user: { email: string } | null };
    }>().props;
    const form = useForm({ name: "", password: "", password_confirmation: "" });
    const correct = auth.user?.email === invitation.email;
    return (
        <AccountLayout title="Join your team">
            <section className="panel platform-security">
                <h1>Join {invitation.company}</h1>
                <p>
                    You’re invited as a {invitation.role}, using{" "}
                    {invitation.email}.
                </p>
                {auth.user && !correct ? (
                    <>
                        <p>
                            You’re signed in with a different email. Log out,
                            then return to this invitation.
                        </p>
                        <button
                            className="button secondary"
                            onClick={() => router.post("/logout")}
                        >
                            Log out
                        </button>
                    </>
                ) : existingAccount && !auth.user ? (
                    <>
                        <p>
                            Log in with the invited email. You’ll return here to
                            accept the invitation.
                        </p>
                        <Link href="/login" className="button primary">
                            Log in
                        </Link>
                    </>
                ) : (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(`/invitations/${token}`, {
                                onFinish: () =>
                                    form.reset(
                                        "password",
                                        "password_confirmation",
                                    ),
                            });
                        }}
                    >
                        <Errors errors={form.errors} />
                        {!existingAccount && (
                            <>
                                <label>
                                    Your name
                                    <input
                                        required
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData("name", e.target.value)
                                        }
                                    />
                                </label>
                                <label>
                                    Password
                                    <PasswordInput
                                        required
                                        minLength={6}
                                        autoComplete="new-password"
                                        value={form.data.password}
                                        onChange={(e) =>
                                            form.setData(
                                                "password",
                                                e.target.value,
                                            )
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
                            </>
                        )}
                        <button
                            className="button primary"
                            disabled={form.processing}
                        >
                            Accept invitation
                        </button>
                    </form>
                )}
            </section>
        </AccountLayout>
    );
}
