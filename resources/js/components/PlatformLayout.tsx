import { Head, Link, router, usePage } from "@inertiajs/react";
import { ShieldCheck, LogOut, ArrowLeft } from "lucide-react";
import { Brand } from "./ui";
import type { ReactNode } from "react";
export default function PlatformLayout({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    const { flash, auth } = usePage().props as any;
    return (
        <div className="platform-shell">
            <Head title={`${title} · Platform admin`} />
            <header className="workspace-header">
                <Brand />
                <Link className="text-link" href="/platform">
                    <ShieldCheck size={18} />
                    Foundryl administration
                </Link>
                <button
                    className="button secondary small-button"
                    onClick={() => router.post("/logout")}
                >
                    <LogOut size={16} />
                    Log out
                </button>
            </header>
            <main className="platform-main">
                <div className="platform-context">
                    <span>{auth.user.email}</span>
                    <span className="badge active">
                        Free for everyone · No subscriptions
                    </span>
                </div>
                {flash?.success && (
                    <div className="flash" role="status">
                        {flash.success}
                    </div>
                )}
                {children}
            </main>
        </div>
    );
}
export function BackToPlatform() {
    return (
        <Link className="text-link back-link" href="/platform">
            <ArrowLeft size={15} />
            Platform overview
        </Link>
    );
}
export function PageLinks({
    data,
}: {
    data: { prev_page_url: string | null; next_page_url: string | null };
}) {
    return (
        <div className="pagination">
            {data.prev_page_url && (
                <Link className="button secondary" href={data.prev_page_url}>
                    Previous
                </Link>
            )}
            {data.next_page_url && (
                <Link className="button secondary" href={data.next_page_url}>
                    Next
                </Link>
            )}
        </div>
    );
}
