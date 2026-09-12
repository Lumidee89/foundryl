import { Shell, Heading, type Project } from "../components/ui";
import { PageLinks } from "../components/PlatformLayout";
export default function Audit({
    events,
    projects,
}: {
    events: {
        data: {
            id: number;
            action: string;
            entity_type: string;
            entity_id: number;
            actor_user_id: number;
            project_id: number | null;
            created_at: string;
            previous_state: string | null;
            new_state: string | null;
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    projects: Project[];
}) {
    return (
        <Shell title="Audit history">
            <Heading
                title="Workspace audit history"
                description="Review recorded changes and access events. Records cannot be edited here."
            />
            <section className="panel">
                <div className="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Time (UTC)</th>
                                <th>Action</th>
                                <th>Actor</th>
                                <th>Project</th>
                                <th>Record</th>
                                <th>Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            {events.data.map((e) => (
                                <tr key={e.id}>
                                    <td>{e.created_at}</td>
                                    <td>{e.action}</td>
                                    <td>User #{e.actor_user_id}</td>
                                    <td>
                                        {projects.find(
                                            (p) => p.id === e.project_id,
                                        )?.name || "Company"}
                                    </td>
                                    <td>
                                        {e.entity_type} #{e.entity_id}
                                    </td>
                                    <td>
                                        <details>
                                            <summary>View details</summary>
                                            <pre className="audit-state">
                                                {JSON.stringify(
                                                    {
                                                        before: e.previous_state
                                                            ? JSON.parse(
                                                                  e.previous_state,
                                                              )
                                                            : null,
                                                        after: e.new_state
                                                            ? JSON.parse(
                                                                  e.new_state,
                                                              )
                                                            : null,
                                                    },
                                                    null,
                                                    2,
                                                )}
                                            </pre>
                                        </details>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
            <PageLinks data={events} />
        </Shell>
    );
}
