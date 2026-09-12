import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { Plus, Building2, MapPin, ArrowUpRight, X, Search } from "lucide-react";
import { useState } from "react";
import {
    Shell,
    Heading,
    Badge,
    Empty,
    Errors,
    type Project,
    type Shared,
} from "../components/ui";
export default function Projects({ projects }: { projects: Project[] }) {
    const { permissions } = usePage<Shared>().props;
    const [open, setOpen] = useState(
        new URLSearchParams(location.search).has("new"),
    );
    const [search, setSearch] = useState("");
    const form = useForm({
        name: "",
        location: "",
        description: "",
        start_date: "",
    });
    const filtered = projects.filter((p) =>
        `${p.name} ${p.location}`.toLowerCase().includes(search.toLowerCase()),
    );
    return (
        <Shell title="Projects">
            <Head title="Your projects" />
            <Heading
                eyebrow="A CLEAR VIEW OF EVERY SITE"
                title="Your projects"
                description="One workspace for everything you’re building."
                action={
                    permissions.includes("projects.manage") && (
                        <button
                            className="button primary"
                            onClick={() => setOpen(!open)}
                        >
                            <Plus size={17} />
                            Create project
                        </button>
                    )
                }
            />
            {open && permissions.includes("projects.manage") && (
                <section className="panel form-panel">
                    <div className="panel-heading">
                        <h2>Create a project</h2>
                        <button
                            className="icon-button"
                            aria-label="Close form"
                            onClick={() => setOpen(false)}
                        >
                            <X />
                        </button>
                    </div>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post("/projects", {
                                onSuccess: () => {
                                    setOpen(false);
                                    form.reset();
                                },
                            });
                        }}
                    >
                        <Errors errors={form.errors} />
                        <div className="form-grid">
                            <label>
                                Project name
                                <input
                                    required
                                    placeholder="e.g. Riverside Residences"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData("name", e.target.value)
                                    }
                                />
                            </label>
                            <label>
                                Site location
                                <input
                                    required
                                    placeholder="e.g. Maitama, Abuja"
                                    value={form.data.location}
                                    onChange={(e) =>
                                        form.setData("location", e.target.value)
                                    }
                                />
                            </label>
                            <label>
                                Start date
                                <input
                                    type="date"
                                    value={form.data.start_date}
                                    onChange={(e) =>
                                        form.setData(
                                            "start_date",
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                            <label>
                                Description
                                <input
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            "description",
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
                            Create project
                        </button>
                    </form>
                </section>
            )}
            <div className="list-toolbar">
                <span>{projects.length} projects in your workspace</span>
                <label className="search">
                    <Search size={17} />
                    <input
                        aria-label="Search projects"
                        placeholder="Search projects…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </label>
            </div>
            {filtered.length === 0 ? (
                <section className="panel">
                    <Empty
                        title={
                            search
                                ? "No matching projects"
                                : "Let’s bring your first site online"
                        }
                        description={
                            search
                                ? "Try a different project name or location."
                                : "Create a project, add your workforce, and start planning the day."
                        }
                    />
                </section>
            ) : (
                <div className="project-grid">
                    {filtered.map((p, i) => (
                        <Link
                            href={`/projects/${p.id}`}
                            key={p.id}
                            className="project-card"
                        >
                            <div className={`project-card-art thumb-${i % 3}`}>
                                <Building2 size={92} strokeWidth={0.7} />
                                <Badge>{p.status}</Badge>
                                <span>
                                    FOUNDRYL / {String(p.id).padStart(3, "0")}
                                </span>
                            </div>
                            <div className="project-card-body">
                                <h2>{p.name}</h2>
                                <p>
                                    <MapPin size={14} />
                                    {p.location}
                                </p>
                                <div>
                                    Open project workspace{" "}
                                    <ArrowUpRight size={18} />
                                </div>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </Shell>
    );
}
