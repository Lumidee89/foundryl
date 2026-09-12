import { router } from "@inertiajs/react";
import AccountLayout from "../components/AccountLayout";
import { Heading, Badge, Empty } from "../components/ui";
export default function Workspaces({
    workspaces,
}: {
    workspaces: {
        id: number;
        name: string;
        role: string;
        suspended_at: string | null;
    }[];
}) {
    return (
        <AccountLayout title="Your workspaces">
            <Heading
                title="Your workspaces"
                description="Switch between companies you belong to. Your permissions stay specific to each company."
            />
            {workspaces.length ? (
                <div className="project-grid">
                    {workspaces.map((w) => (
                        <section className="panel inline-form" key={w.id}>
                            <h2>{w.name}</h2>
                            <p>{w.role}</p>
                            <Badge>
                                {w.suspended_at ? "Suspended" : "Active"}
                            </Badge>
                            <div className="spaced">
                                <button
                                    className="button primary"
                                    disabled={!!w.suspended_at}
                                    onClick={() =>
                                        router.post("/workspaces/switch", {
                                            organization_id: w.id,
                                        })
                                    }
                                >
                                    Open workspace
                                </button>
                            </div>
                        </section>
                    ))}
                </div>
            ) : (
                <Empty
                    title="No active memberships"
                    description="Ask a company administrator to invite you to their workspace."
                />
            )}
        </AccountLayout>
    );
}
