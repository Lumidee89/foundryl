import { Head, Link, usePage, router } from "@inertiajs/react";
import {
    ArrowRight,
    ArrowUpRight,
    Check,
    HardHat,
    Users,
    ClipboardCheck,
    Building2,
    Activity,
    NotebookPen,
} from "lucide-react";
import { Brand } from "../components/ui";
export default function Home() {
    const { auth } = usePage<{
        auth: {
            user: {
                name: string;
                email: string;
                is_superadmin: boolean;
            } | null;
        };
    }>().props;
    const user = auth.user;
    const destination = user
        ? user.is_superadmin
            ? "/platform"
            : "/dashboard"
        : "/register";
    const action = user
        ? user.is_superadmin
            ? "Platform dashboard"
            : "Open your workspace"
        : "Get started";
    return (
        <>
            <Head title="Know your site. Build with confidence." />
            <div className="landing">
                <header className="landing-nav">
                    <Brand />
                    <nav>
                        <a href="#platform">The platform</a>
                        <a href="#workflow">How it works</a>
                        <a href="#built-for">Built for you</a>
                    </nav>
                    <div className="home-account">
                        {user ? (
                            <>
                                <span
                                    className="signed-in-user"
                                    title={user.email}
                                >
                                    <span className="avatar">
                                        {user.name.slice(0, 2).toUpperCase()}
                                    </span>
                                    <span>{user.name}</span>
                                </span>
                                <Link
                                    className="button primary small-button"
                                    href={destination}
                                >
                                    {user.is_superadmin
                                        ? "Platform"
                                        : "Dashboard"}
                                    <ArrowUpRight size={16} />
                                </Link>
                                <button
                                    type="button"
                                    className="login-link home-logout"
                                    onClick={() => router.post("/logout")}
                                >
                                    Log out
                                </button>
                            </>
                        ) : (
                            <>
                                <Link className="login-link" href="/login">
                                    Log in
                                </Link>
                                <Link
                                    className="button primary small-button"
                                    href="/register"
                                >
                                    Get started
                                    <ArrowUpRight size={16} />
                                </Link>
                            </>
                        )}
                    </div>
                </header>
                <section className="hero">
                    <div className="hero-copy">
                        <span className="pill">
                            <span className="live-dot" /> THE EVERYDAY
                            CONSTRUCTION WORKSPACE
                        </span>
                        <h1>
                            Your site.
                            <br />
                            Your people.
                            <br />
                            <span>One clear picture.</span>
                        </h1>
                        <p>
                            Know what’s happening on your construction site,
                            even when you’re not there. Bring your people, daily
                            plans, and site progress together.
                        </p>
                        <div className="hero-actions">
                            <Link href={destination} className="button primary">
                                {user ? action : "Start your workspace"}{" "}
                                <ArrowRight size={18} />
                            </Link>
                            <a href="#platform" className="button secondary">
                                Explore the platform
                            </a>
                        </div>
                        <div className="hero-checks">
                            <span>
                                <Check size={16} />
                                Free for everyone
                            </span>
                            <span>
                                <Check size={16} />
                                Connected to the office
                            </span>
                        </div>
                    </div>
                    <div className="hero-visual">
                        <div className="orbit orbit-one" />
                        <div className="orbit orbit-two" />
                        <div className="orbit orbit-three" />
                        <div className="hero-spark">✦</div>
                        <div className="blueprint-grid" />
                        <div className="tower tower-one">
                            <i />
                            <i />
                            <i />
                            <i />
                            <i />
                            <i />
                        </div>
                        <div className="tower tower-two">
                            <i />
                            <i />
                            <i />
                            <i />
                            <i />
                        </div>
                        <div className="crane">
                            <div className="crane-arm" />
                            <div className="crane-rope" />
                        </div>
                        <div className="visual-caption">
                            <span>FROM THE GROUND UP</span>
                            <strong>Build with confidence.</strong>
                        </div>
                        <div className="floating-card field-card">
                            <span className="orange-icon">
                                <HardHat size={23} />
                            </span>
                            <div>
                                <small>YOUR SITE, CONNECTED</small>
                                <strong>Every detail. In view.</strong>
                            </div>
                            <span className="live-dot" />
                        </div>
                        <div className="floating-card progress-card">
                            <div>
                                <span className="orange-icon">
                                    <Activity size={17} />
                                </span>
                                <strong>A clearer working day</strong>
                            </div>
                            <p>Plan → Capture → Review</p>
                            <div className="mini-progress">
                                <i />
                            </div>
                            <small>From daily direction to daily report.</small>
                        </div>
                        <span className="visual-tag">
                            FOUNDRYL BUILD / SITE OPERATIONS
                        </span>
                    </div>
                </section>
                <div className="principles">
                    <span>
                        ONE WORKSPACE.
                        <br />
                        <strong>Every part of your day.</strong>
                    </span>
                    {[
                        [Users, "People & attendance"],
                        [ClipboardCheck, "Daily work & progress"],
                        [Building2, "Projects & oversight"],
                    ].map(([Icon, label]) => (
                        <div key={String(label)}>
                            {typeof Icon !== "string" && <Icon size={22} />}
                            <strong>{String(label)}</strong>
                        </div>
                    ))}
                </div>
                <section className="platform-section" id="platform">
                    <div className="section-heading">
                        <div>
                            <div className="eyebrow">
                                LESS CHASING. MORE BUILDING.
                            </div>
                            <h2>
                                From the site gate <br />
                                to the big picture.
                            </h2>
                        </div>
                        <p>
                            Give the people doing the work and the people making
                            the decisions a shared place to see what’s
                            happening.
                        </p>
                    </div>
                    <div className="feature-grid">
                        {[
                            {
                                icon: Users,
                                n: "01",
                                title: "Keep your people in view",
                                text: "Manage worker profiles, record site arrivals and departures, and see who is currently onsite.",
                            },
                            {
                                icon: ClipboardCheck,
                                n: "02",
                                title: "Turn plans into progress",
                                text: "Set the day’s activities, assign responsibility, and keep delays and completed work visible.",
                            },
                            {
                                icon: Building2,
                                n: "03",
                                title: "See across your projects",
                                text: "Move from a company overview into individual sites, daily records, and the people responsible.",
                            },
                            {
                                icon: NotebookPen,
                                n: "04",
                                title: "Keep the full site story",
                                text: "Capture site notes, the day’s conditions, and outstanding matters in a shared project diary.",
                            },
                        ].map(({ icon: Icon, ...f }) => (
                            <article key={f.n}>
                                <div className="feature-top">
                                    <Icon />
                                    <span>{f.n}</span>
                                </div>
                                <h3>
                                    <span>{f.title}</span>
                                </h3>
                                <p>{f.text}</p>
                                <Link
                                    href={destination}
                                    className="feature-link"
                                >
                                    <span>
                                        <ArrowUpRight size={20} />
                                    </span>
                                    {action}
                                </Link>
                            </article>
                        ))}
                    </div>
                </section>
                <section id="workflow" className="workflow-section">
                    <div className="eyebrow">A BETTER RHYTHM, EVERY DAY</div>
                    <h2>Start clear. Stay connected. Close informed.</h2>
                    <div className="steps">
                        {[
                            [
                                "01",
                                "Set up your workspace",
                                "Create your company and add your first project.",
                            ],
                            [
                                "02",
                                "Bring the site together",
                                "Add your workforce and plan today’s activities.",
                            ],
                            [
                                "03",
                                "See the day unfold",
                                "Capture attendance, update progress, and review your diary.",
                            ],
                        ].map(([n, t, d]) => (
                            <div key={n}>
                                <span>{n}</span>
                                <h3>{t}</h3>
                                <p>{d}</p>
                            </div>
                        ))}
                    </div>
                </section>
                <section className="cta" id="built-for">
                    <div>
                        <div className="eyebrow">
                            BUILT FOR PEOPLE WHO BUILD
                        </div>
                        <h2>
                            Your next working day,
                            <br />
                            with a clearer view.
                        </h2>
                        <p>
                            For site teams, project managers, and company
                            leadership.
                        </p>
                    </div>
                    <Link href={destination} className="button primary">
                        {user ? action : "Create your workspace"}{" "}
                        <ArrowRight size={18} />
                    </Link>
                </section>
                <footer className="landing-footer">
                    <Link href="/" aria-label="Foundryl Technologies home">
                        <img
                            className="full-logo"
                            src="/brand/logofull.png"
                            alt="Foundryl Technologies"
                        />
                    </Link>
                    <span>
                        © {new Date().getFullYear()} Foundryl Technologies
                    </span>
                    <Link href={user ? destination : "/login"}>
                        {user ? action : "Workspace login"}{" "}
                        <ArrowUpRight size={14} />
                    </Link>
                </footer>
            </div>
        </>
    );
}
