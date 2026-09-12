import { Head, Link } from "@inertiajs/react";
import {
    ArrowLeft,
    Users,
    ClipboardCheck,
    NotebookPen,
    ArrowUpRight,
} from "lucide-react";
import {
    Shell,
    Heading,
    Badge,
    Empty,
    type Project as ProjectType,
} from "../components/ui";
export default function Project({
    project,
    activities,
    workers,
    diary,
}: {
    project: ProjectType;
    activities: {
        id: number;
        title: string;
        status: string;
        progress: number;
    }[];
    workers: { id: number; name: string; trade: string }[];
    diary: { id: number; title: string; notes: string; entry_date: string }[];
}) {
    return (
        <Shell title="Projects">
            <Head title={project.name} />
            <Link className="text-link back-link" href="/projects">
                <ArrowLeft size={15} />
                All projects
            </Link>
            <Heading
                eyebrow={project.location}
                title={project.name}
                description="Your people, daily work, and site records in one place."
                action={<Badge>{project.status}</Badge>}
            />
            <div className="stat-grid three">
                {[
                    [Users, "Workforce", workers.length, "workers"],
                    [
                        ClipboardCheck,
                        "Activities",
                        activities.length,
                        "activities",
                    ],
                    [NotebookPen, "Site diary", diary.length, "diary"],
                ].map(([Icon, name, count, path]) => (
                    <Link
                        href={`/${path}?project=${project.id}`}
                        className="stat-card"
                        key={String(path)}
                    >
                        <div>
                            {typeof Icon !== "string" &&
                                typeof Icon !== "number" && <Icon size={21} />}
                            <ArrowUpRight size={18} />
                        </div>
                        <span>{String(name)}</span>
                        <strong>{String(count)}</strong>
                        <small>View and manage records</small>
                    </Link>
                ))}
            </div>
            <section className="panel">
                <div className="panel-heading">
                    <h2>Project work plan</h2>
                    <Link
                        className="text-link"
                        href={`/activities?project=${project.id}`}
                    >
                        Manage activities <ArrowUpRight size={16} />
                    </Link>
                </div>
                {activities.length ? (
                    <table>
                        <thead>
                            <tr>
                                <th>Activity</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {activities.map((a) => (
                                <tr key={a.id}>
                                    <td>{a.title}</td>
                                    <td>{a.progress}%</td>
                                    <td>
                                        <Badge>{a.status}</Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <Empty
                        title="No activities yet"
                        description="Create your first activity in the daily work plan."
                    />
                )}
            </section>
            <section className="panel spaced">
                <div className="panel-heading">
                    <h2>Recent site diary</h2>
                </div>
                {diary.length ? (
                    diary.map((d) => (
                        <article className="diary-note" key={d.id}>
                            <small>{d.entry_date}</small>
                            <h3>{d.title}</h3>
                            <p>{d.notes}</p>
                        </article>
                    ))
                ) : (
                    <Empty
                        title="Capture the story of your site"
                        description="Save notes, weather, completed work, and outstanding matters in the site diary."
                    />
                )}
            </section>
        </Shell>
    );
}
