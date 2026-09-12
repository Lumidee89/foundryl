import { Link, usePage, router } from "@inertiajs/react";
import {
    LayoutDashboard,
    Building2,
    Users,
    ClipboardList,
    DoorOpen,
    NotebookPen,
    LogOut,
    ArrowUpRight,
    HardHat,
    Menu,
    X,
    Bell,
    FileText,
    ListChecks,
    Settings,
} from "lucide-react";
import { useState, type ReactNode } from "react";
export type Project = {
    id: number;
    name: string;
    location: string;
    status: string;
    progress?: number;
    activities_count?: number;
    workers_count?: number;
};
export type Shared = {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            is_superadmin: boolean;
        };
    };
    organization: { name: string };
    flash: { success?: string };
    permissions: string[];
    [key: string]: unknown;
};
export const nav = [
    ["Overview", "/dashboard", LayoutDashboard],
    ["Projects", "/projects", Building2],
    ["People", "/workers", Users],
    ["Gate & attendance", "/gate", DoorOpen],
    ["Daily work plan", "/activities", ClipboardList],
    ["Site diary", "/diary", NotebookPen],
] as const;
export function Brand() {
    return (
        <Link href="/" className="brand">
            <img src="/brand/logo.png" alt="Foundryl" />
            <span>
                foundryl<span className="brand-build">BUILD</span>
            </span>
        </Link>
    );
}
export function Shell({
    children,
    title,
}: {
    children: ReactNode;
    title: string;
}) {
    const { auth, organization, flash, permissions } = usePage<Shared>().props;
    const [open, setOpen] = useState(false);
    const extraNav = [
        ["Visitors", "/visitors", DoorOpen],
        ["Workforce", "/workforce", Users],
        ["Instructions", "/instructions", ListChecks],
        ["Reports", "/reports", FileText],
        ["Notifications", "/notifications", Bell],
        ...(permissions.includes("team.manage")
            ? [["Team & company", "/team", Users]]
            : []),
        ...(permissions.includes("audit.view")
            ? [["Audit history", "/audit", FileText]]
            : []),
        ["Account & security", "/account", Settings],
        ["Switch workspace", "/workspaces", Building2],
    ] as [string, string, typeof Users][];
    return (
        <div className="workspace">
            <header className="workspace-header">
                <Brand />
                <nav
                    className="header-navigation"
                    aria-label="Workspace navigation"
                >
                    {nav.map(([name, url]) => (
                        <Link
                            key={url}
                            href={url}
                            className={
                                location.pathname.startsWith(url)
                                    ? "selected"
                                    : ""
                            }
                            aria-current={
                                location.pathname.startsWith(url)
                                    ? "page"
                                    : undefined
                            }
                        >
                            {name}
                        </Link>
                    ))}
                </nav>
                <div className="header-account">
                    <span className="company-name" title={organization.name}>
                        {organization.name}
                    </span>
                    <span className="avatar" title={auth.user.name}>
                        {auth.user.name.slice(0, 2).toUpperCase()}
                    </span>
                </div>
                <button
                    className="icon-button mobile-toggle"
                    onClick={() => setOpen(!open)}
                    aria-label="Open navigation"
                    aria-expanded={open}
                >
                    <Menu />
                </button>
            </header>
            <aside className={open ? "sidebar open" : "sidebar"}>
                <button
                    className="icon-button mobile-close"
                    onClick={() => setOpen(false)}
                    aria-label="Close menu"
                >
                    <X />
                </button>
                <nav aria-label="Quick navigation">
                    {nav.map(([name, url, Icon]) => (
                        <Link
                            className={
                                location.pathname.startsWith(url)
                                    ? "nav-link active"
                                    : "nav-link"
                            }
                            key={url}
                            href={url}
                            aria-label={name}
                            title={name}
                            aria-current={
                                location.pathname.startsWith(url)
                                    ? "page"
                                    : undefined
                            }
                        >
                            <Icon size={20} />
                            <span className="rail-label">{name}</span>
                        </Link>
                    ))}
                </nav>
                <div className="sidebar-bottom">
                    <button
                        className="icon-button"
                        aria-label="Log out"
                        title="Log out"
                        onClick={() => router.post("/logout")}
                    >
                        <LogOut size={20} />
                    </button>
                </div>
            </aside>
            <div className="workspace-main">
                <main className="main-content" aria-label={title}>
                    {flash?.success && (
                        <div role="status" className="flash">
                            {flash.success}
                        </div>
                    )}
                    <nav
                        className="workspace-tools"
                        aria-label="More workspace tools"
                    >
                        {extraNav.map(([label, href, Icon]) => (
                            <Link
                                key={href}
                                href={href}
                                className={
                                    location.pathname === href ? "selected" : ""
                                }
                            >
                                <Icon size={15} />
                                {label}
                            </Link>
                        ))}
                    </nav>
                    {children}
                </main>
                <footer className="workspace-footer">
                    © {new Date().getFullYear()} Foundryl Technologies
                    <span>Built for the way you build.</span>
                </footer>
            </div>
        </div>
    );
}

export function Heading({
    eyebrow,
    title,
    description,
    action,
}: {
    eyebrow?: string;
    title: string;
    description: string;
    action?: ReactNode;
}) {
    return (
        <div className="page-heading">
            <div>
                {eyebrow && <div className="eyebrow">{eyebrow}</div>}
                <h1>{title}</h1>
                <p>{description}</p>
            </div>
            {action}
        </div>
    );
}
export function Badge({ children }: { children: ReactNode }) {
    const key = String(children).toLowerCase().replaceAll(" ", "-");
    return <span className={`badge ${key}`}>{children}</span>;
}
export function Empty({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <div className="empty">
            <HardHat size={30} />
            <h3>{title}</h3>
            <p>{description}</p>
        </div>
    );
}
export function CardLink({
    href,
    children,
}: {
    href: string;
    children: ReactNode;
}) {
    return (
        <Link href={href} className="text-link">
            {children}
            <ArrowUpRight size={15} />
        </Link>
    );
}
export function Errors({ errors }: { errors: Record<string, string> }) {
    return Object.keys(errors).length > 0 ? (
        <div className="errors" role="alert">
            {Object.values(errors).map((e, i) => (
                <p key={i}>{e}</p>
            ))}
        </div>
    ) : null;
}
