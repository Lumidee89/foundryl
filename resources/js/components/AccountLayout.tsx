import { Link, Head, usePage, router } from "@inertiajs/react";
import { Brand } from "./ui";
import type { ReactNode } from "react";
export default function AccountLayout({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    const { auth, flash } = usePage<{
        auth: { user: { name: string; is_superadmin: boolean } | null };
        flash: { success?: string };
    }>().props;
    return (
        <div className="platform-shell">
            <Head title={title} />
            <header className="workspace-header">
                <Brand />
                {auth.user ? (
                    <div className="account-navigation">
                        <Link
                            href={
                                auth.user.is_superadmin
                                    ? "/platform"
                                    : "/dashboard"
                            }
                            className="text-link"
                        >
                            Dashboard
                        </Link>
                        {!auth.user.is_superadmin && (
                            <Link href="/workspaces" className="text-link">
                                Workspaces
                            </Link>
                        )}
                        <button
                            className="button secondary small-button"
                            onClick={() => router.post("/logout")}
                        >
                            Log out
                        </button>
                    </div>
                ) : (
                    <Link href="/login" className="text-link">
                        Log in
                    </Link>
                )}
            </header>
            <main className="platform-main">
                {flash?.success && (
                    <div role="status" className="flash">
                        {flash.success}
                    </div>
                )}
                {children}
            </main>
        </div>
    );
}
