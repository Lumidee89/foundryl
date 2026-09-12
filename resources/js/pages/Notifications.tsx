import { Link, router } from "@inertiajs/react";
import { Shell, Heading, Empty } from "../components/ui";
import { PageLinks } from "../components/PlatformLayout";
export default function Notifications({
    notifications,
}: {
    notifications: {
        data: {
            id: number;
            title: string;
            url: string;
            created_at: string;
            read_at: string | null;
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    return (
        <Shell title="Notifications">
            <Heading
                title="Your notifications"
                description="Management direction and instruction updates for your authorized projects."
            />
            <section className="panel">
                {notifications.data.length ? (
                    notifications.data.map((n) => (
                        <article className="notification-row" key={n.id}>
                            <div>
                                <Link href={n.url}>
                                    <strong>{n.title}</strong>
                                </Link>
                                <small>
                                    {new Date(
                                        n.created_at + "Z",
                                    ).toLocaleString()}
                                </small>
                            </div>
                            {n.read_at ? (
                                <span className="badge">Read</span>
                            ) : (
                                <button
                                    className="button secondary small-button"
                                    onClick={() =>
                                        router.post(
                                            `/notifications/${n.id}/read`,
                                        )
                                    }
                                >
                                    Mark read
                                </button>
                            )}
                        </article>
                    ))
                ) : (
                    <Empty
                        title="You’re up to date"
                        description="New project messages and instruction updates will appear here."
                    />
                )}
            </section>
            <PageLinks data={notifications} />
        </Shell>
    );
}
