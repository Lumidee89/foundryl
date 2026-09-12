import { useForm } from "@inertiajs/react";
import PlatformLayout from "../components/PlatformLayout";
import { Errors } from "../components/ui";
export default function PlatformSecurity({
    setup,
    secret,
}: {
    setup: boolean;
    secret: string | null;
}) {
    const form = useForm({ code: "" });
    return (
        <PlatformLayout title="Verify your identity">
            <section className="panel platform-security">
                <h1>
                    {setup
                        ? "Protect your platform account"
                        : "Verify your identity"}
                </h1>
                <p>
                    {setup
                        ? "Add a time-based account to your authenticator app using the setup key below. Enter its six-digit code to finish."
                        : "Enter the current six-digit code from your authenticator app. Verification grants one hour of platform access."}
                </p>
                {setup && (
                    <>
                        <label>
                            Account name
                            <input readOnly value="Foundryl platform" />
                        </label>
                        <label>
                            Authenticator setup key
                            <code className="setup-key">{secret}</code>
                        </label>
                        <p>
                            Save this key in your password manager so you can
                            restore your authenticator if you change devices.
                        </p>
                    </>
                )}
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post("/platform/security", {
                            onFinish: () => form.reset("code"),
                        });
                    }}
                >
                    <Errors errors={form.errors} />
                    <label>
                        Authentication code
                        <input
                            required
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            pattern="[0-9]{6}"
                            maxLength={6}
                            value={form.data.code}
                            onChange={(e) =>
                                form.setData("code", e.target.value)
                            }
                        />
                    </label>
                    <button
                        className="button primary"
                        disabled={form.processing}
                    >
                        Verify and continue
                    </button>
                </form>
            </section>
        </PlatformLayout>
    );
}
