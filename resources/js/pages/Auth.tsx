import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { ArrowRight, HardHat } from "lucide-react";
import PasswordInput from "../components/PasswordInput";
import { Brand, Errors } from "../components/ui";
export default function Auth({ mode }: { mode: "login" | "register" }) {
    const { flash } = usePage<{ flash: { success?: string } }>().props;
    const register = mode === "register";
    const form = useForm({
        name: "",
        company: "",
        email: "",
        password: "",
        password_confirmation: "",
    });
    return (
        <div className="auth-page">
            <Head title={register ? "Create your workspace" : "Welcome back"} />
            <section className="auth-story">
                <Brand />
                <div>
                    <span className="eyebrow">
                        YOUR PEOPLE. YOUR PROJECTS. CONNECTED.
                    </span>
                    <h1>
                        A good day’s work
                        <br />
                        starts with a<br />
                        <em>clear picture.</em>
                    </h1>
                    <p>The everyday workspace for your construction company.</p>
                </div>
                <span>
                    <HardHat size={19} /> Built for the way you build.
                </span>
            </section>
            <section className="auth-form">
                <div>
                    <Link href="/" className="text-link">
                        ← Back to Foundryl
                    </Link>
                    <h1>
                        {register
                            ? "Let’s build your workspace."
                            : "Welcome back."}
                    </h1>
                    <p>
                        {register
                            ? "Bring your company and your sites together. Free to use, with no subscription."
                            : "Your projects and people, right where you left them."}
                    </p>
                    {flash.success && (
                        <p className="flash" role="status">
                            {flash.success}
                        </p>
                    )}
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(register ? "/register" : "/login", {
                                onFinish: () =>
                                    form.reset(
                                        "password",
                                        "password_confirmation",
                                    ),
                            });
                        }}
                    >
                        <Errors errors={form.errors} />
                        {register && (
                            <>
                                <label>
                                    Your name
                                    <input
                                        required
                                        autoComplete="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData("name", e.target.value)
                                        }
                                    />
                                </label>
                                <label>
                                    Company name
                                    <input
                                        required
                                        autoComplete="organization"
                                        value={form.data.company}
                                        onChange={(e) =>
                                            form.setData(
                                                "company",
                                                e.target.value,
                                            )
                                        }
                                    />
                                </label>
                            </>
                        )}
                        <label>
                            Email address
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
                        <label>
                            Password
                            <PasswordInput
                                required
                                minLength={register ? 6 : undefined}
                                autoComplete={
                                    register
                                        ? "new-password"
                                        : "current-password"
                                }
                                value={form.data.password}
                                onChange={(e) =>
                                    form.setData("password", e.target.value)
                                }
                            />
                        </label>
                        {register && (
                            <>
                                <small>Use at least 6 characters.</small>
                                <label>
                                    Confirm password
                                    <PasswordInput
                                        label="Confirm password"
                                        required
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
                            {form.processing
                                ? "Please wait…"
                                : register
                                  ? "Create workspace"
                                  : "Log in"}
                            <ArrowRight size={17} />
                        </button>
                    </form>
                    {!register && (
                        <Link
                            href="/forgot-password"
                            className="text-link recovery-link"
                        >
                            Forgot your password?
                        </Link>
                    )}
                    <p className="auth-switch">
                        {register
                            ? "Already have a workspace?"
                            : "New to Foundryl?"}{" "}
                        <Link href={register ? "/login" : "/register"}>
                            {register ? "Log in" : "Create your workspace"}
                        </Link>
                    </p>
                </div>
            </section>
        </div>
    );
}
