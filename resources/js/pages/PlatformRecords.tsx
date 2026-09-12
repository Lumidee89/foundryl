import { Link } from "@inertiajs/react";
import PlatformLayout, { PageLinks } from "../components/PlatformLayout";
import { Heading, Empty } from "../components/ui";
export default function PlatformRecords({
    company,
    module,
    columns,
    records,
}: {
    company: { id: number; name: string };
    module: string;
    columns: string[];
    records: {
        data: Record<string, string | number | null>[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    return (
        <PlatformLayout title={`${company.name} · ${module}`}>
            <Link
                className="text-link back-link"
                href={`/platform/companies/${company.id}`}
            >
                ← {company.name}
            </Link>
            <Heading
                title={module.replaceAll("_", " ")}
                description={`${records.total} records · ${company.name} · Read-only platform review`}
            />
            <section className="panel">
                {records.data.length ? (
                    <div className="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    {columns.map((c) => (
                                        <th key={c}>
                                            {c.replaceAll("_", " ")}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {records.data.map((row) => (
                                    <tr key={row.id}>
                                        {columns.map((c) => (
                                            <td
                                                className="platform-record-cell"
                                                key={c}
                                            >
                                                {row[c] ?? "—"}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty
                        title="No records yet"
                        description="Company records will appear as the team uses this module."
                    />
                )}
            </section>
            <PageLinks data={records} />
        </PlatformLayout>
    );
}
