import { useForm } from "@inertiajs/react";
import AccountLayout from "../components/AccountLayout";
import PasswordInput from "../components/PasswordInput";
import { Errors } from "../components/ui";
export default function Recovery({
    mode,
    token = "",
    email = "",
}: {
    mode: "forgot" | "reset";
    token?: string;
    email?: string;
}) {
    const form = useForm({
        email,
        token,
        password: "",
        password_confirmation: "",
    });
    return (
        <AccountLayout title="Password recovery">
            <section className="panel platform-security">
                <h1>
                    {mode === "forgot"
                        ? "Forgot your password?"
                        : "Choose a new password"}
                </h1>
                <p>
                    {mode === "forgot"
                        ? "Enter your account email to receive a password reset link."
                        : "Use at least 6 characters. Your other sessions will be signed out."}
                </p>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(
                            mode === "forgot"
                                ? "/forgot-password"
                                : "/reset-password",
                            {
                                onFinish: () =>
                                    form.reset(
                                        "password",
                                        "password_confirmation",
                                    ),
                            },
                        );
                    }}
                >
                    <Errors errors={form.errors} />
                    <label>
                        Email
                        <input
                            required
                            type="email"
                            autoComplete="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData("email", e.target.value)
                            }
                        />
                    </label>
                    {mode === "reset" && (
                        <>
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
                                    label="Confirm password"
                                    required
                                    minLength={6}
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
                        {mode === "forgot"
                            ? "Send reset link"
                            : "Reset password"}
                    </button>
                </form>
            </section>
        </AccountLayout>
    );
}
