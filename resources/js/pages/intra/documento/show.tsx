import { SecurePdfViewer } from '@/components/intra/SecurePdfViewer';
import IntraLayout from '@/layouts/intra-layout';
import type { BreadcrumbItem } from '@/types';
import type { Documento } from '@/types/models';
import { Head } from '@inertiajs/react';

type Props = {
    documento: Documento;
    breadcrumbs: BreadcrumbItem[];
};

export default function DocumentoShow({ documento, breadcrumbs }: Props) {
    const pdfUrl = documento.media ? `/storage/${documento.media.path}` : null;

    return (
        <IntraLayout breadcrumbs={breadcrumbs}>
            <Head title={`${documento.descripcion} - Intranet`} />

            <div className="flex flex-col px-6 py-8">
                {/* Header */}
                <div className="mb-4 text-center">
                    <h1 className="text-2xl font-bold text-base-content">{documento.descripcion}</h1>
                    {documento.codigo && (
                        <p className="mt-1 text-sm text-base-content/60">{documento.codigo}</p>
                    )}
                </div>

                {/* PDF Viewer */}
                {pdfUrl ? (
                    <div className="flex-1">
                        <SecurePdfViewer url={pdfUrl} title={documento.descripcion} />
                    </div>
                ) : (
                    <div className="flex items-center justify-center py-20">
                        <p className="text-base-content/60">No hay documento disponible</p>
                    </div>
                )}
            </div>
        </IntraLayout>
    );
}
