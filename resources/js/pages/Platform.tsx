import { Link, useForm } from "@inertiajs/react";
import { ArrowUpRight, Search } from "lucide-react";
import PlatformLayout, { PageLinks } from "../components/PlatformLayout";
import { Heading, Badge, Empty, Errors } from "../components/ui";
type Company = {
    id: number;
    name: string;
    suspended_at: string | null;
    created_at: string;
};
export default function Platform({
    companies,
    stats,
    events,
    search,
}: {
    companies: {
        data: Company[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    stats: Record<string, number>;
    events: {
        id: number;
        actor: string;
        action: string;
        organization_id: number | null;
        reason: string | null;
        created_at: string;
    }[];
    search: string;
}) {
    const form = useForm({ search });
    return (
        <PlatformLayout title="Platform overview">
            <Heading
                eyebrow="FOUNDRYL SUPERADMIN"
                title="Your platform, in view."
                description="Monitor every company and the work happening across Foundryl."
            />
            <div className="stat-grid">
                {Object.entries(stats).map(([label, value]) => (
                    <div className="stat-card" key={label}>
                        <span>{label}</span>
                        <strong>{value}</strong>
                    </div>
                ))}
            </div>
            <section className="panel">
                <div className="panel-heading">
                    <h2>
                        Companies{" "}
                        <span className="count">{companies.total}</span>
                    </h2>
                    <form
                        className="platform-search"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.get("/platform");
                        }}
                    >
                        <input
                            aria-label="Search companies"
                            placeholder="Search companies"
                            value={form.data.search}
                            onChange={(e) =>
                                form.setData("search", e.target.value)
                            }
                        />
                        <button className="button secondary small-button">
                            <Search size={16} />
                            Search
                        </button>
                    </form>
                </div>
                <Errors errors={form.errors} />
                {companies.data.length ? (
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Joined</th>
                                    <th>Status</th>
                                    <th>Access</th>
                                </tr>
                            </thead>
                            <tbody>
                                {companies.data.map((c) => (
                                    <tr key={c.id}>
                                        <td>
                                            <Link
                                                href={`/platform/companies/${c.id}`}
                                            >
                                                {c.name}
                                            </Link>
                                        </td>
                                        <td>{c.created_at?.slice(0, 10)}</td>
                                        <td>
                                            <Badge>
                                                {c.suspended_at
                                                    ? "Suspended"
                                                    : "Active"}
                                            </Badge>
                                        </td>
                                        <td>
                                            <Link
                                                className="text-link"
                                                href={`/platform/companies/${c.id}`}
                                            >
                                                Review company{" "}
                                                <ArrowUpRight size={15} />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty
                        title="No companies found"
                        description="Registered companies appear here automatically."
                    />
                )}
            </section>
            <PageLinks data={companies} />
            <section className="panel spaced">
                <div className="panel-heading">
                    <h2>Platform administration history</h2>
                </div>
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Administrator</th>
                                <th>Action</th>
                                <th>Company</th>
                                <th>Reason</th>
                                <th>Time (UTC)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {events.map((e) => (
                                <tr key={e.id}>
                                    <td>{e.actor}</td>
                                    <td>{e.action}</td>
                                    <td>
                                        {e.organization_id ? (
                                            <Link
                                                href={`/platform/companies/${e.organization_id}`}
                                            >
                                                Company #{e.organization_id}
                                            </Link>
                                        ) : (
                                            "Platform"
                                        )}
                                    </td>
                                    <td>{e.reason || "—"}</td>
                                    <td>{e.created_at}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </PlatformLayout>
    );
}
