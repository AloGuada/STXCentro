import { SecurePdfViewer } from '@/components/intra/SecurePdfViewer';
import IntraLayout from '@/layouts/intra-layout';
import type { BreadcrumbItem } from '@/types';
import type { SeccionEstatica } from '@/types/models';
import { Head } from '@inertiajs/react';

type Props = {
    seccion: SeccionEstatica;
};

export default function SeccionShow({ seccion }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: seccion.boton, href: `/intra/est/${seccion.slug}` },
    ];

    const pdfUrl = seccion.media ? `/storage/${seccion.media.path}` : null;

    return (
        <IntraLayout breadcrumbs={breadcrumbs}>
            <Head title={`${seccion.titulo} - Intranet`} />

            <div className="flex flex-col px-6 py-8">
                {/* Title */}
                <h1 className="mb-2 text-lg text-base-content/70">{seccion.descripcion}</h1>

                {/* PDF Viewer */}
                {pdfUrl ? (
                    <div className="flex-1">
                        <SecurePdfViewer url={pdfUrl} title={seccion.titulo} />
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
