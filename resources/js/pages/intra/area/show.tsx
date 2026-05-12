import IntraLayout from '@/layouts/intra-layout';
import type { BreadcrumbItem } from '@/types';
import type { Area, Documento, DocumentosPorTipo } from '@/types/models';
import { Head, Link } from '@inertiajs/react';

type Props = {
    area: Area;
    documentosPorTipo: Partial<DocumentosPorTipo>;
    breadcrumbs: BreadcrumbItem[];
};

function DocumentCard({ documento }: { documento: Documento }) {
    return (
        <Link
            href={`/intra/doc/${documento.id}`}
            className="flex flex-col items-center justify-center rounded-xl border border-base-300 bg-base-100 p-4 text-center shadow-sm transition hover:border-primary/60 hover:bg-base-100/80"
        >
            <span className="text-sm font-medium text-base-content">{documento.descripcion}</span>
            {documento.codigo && (
                <span className="mt-1 text-xs text-base-content/60">{documento.codigo}</span>
            )}
        </Link>
    );
}

export default function AreaShow({ area, documentosPorTipo, breadcrumbs }: Props) {
    // Remove the last breadcrumb (current page) as IntraLayout adds it
    const parentBreadcrumbs = breadcrumbs.slice(0, -1);

    return (
        <IntraLayout breadcrumbs={parentBreadcrumbs.length > 0 ? breadcrumbs : [{ title: area.descripcion, href: `/intra/area/${area.id}` }]}>
            <Head title={`${area.descripcion} - Intranet`} />

            <div className="px-6 py-8">
                {/* Title */}
                <h1 className="mb-8 text-center text-4xl font-bold italic text-base-content">
                    {area.descripcion}
                </h1>

                {/* Child areas */}
                {area.children && area.children.length > 0 && (
                    <div className="mb-12">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {area.children.map((child) => (
                                <Link
                                    key={child.id}
                                    href={`/intra/area/${child.id}`}
                                    className="flex items-center justify-center rounded-xl border border-base-300 bg-base-100 p-6 text-center shadow-sm transition hover:border-primary/60 hover:bg-base-100/80"
                                >
                                    <span className="text-lg font-semibold text-base-content">
                                        {child.descripcion}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

                {/* Documents grouped by type */}
                {Object.entries(documentosPorTipo).map(([tipo, data]) => (
                    <section key={tipo} className="mb-8">
                        <h2 className="mb-4 text-lg font-semibold text-base-content/70">
                            {data.label}
                        </h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {data.documentos.map((doc) => (
                                <DocumentCard key={doc.id} documento={doc} />
                            ))}
                        </div>
                    </section>
                ))}

                {/* Empty state */}
                {Object.keys(documentosPorTipo).length === 0 && (!area.children || area.children.length === 0) && (
                    <div className="flex items-center justify-center py-20">
                        <p className="text-base-content/60">No hay contenido disponible en esta área</p>
                    </div>
                )}
            </div>
        </IntraLayout>
    );
}
