import AppLogoIcon from '@/components/app-logo-icon';
import IntraLayout from '@/layouts/intra-layout';
import type { Area, SeccionEstatica } from '@/types/models';
import { Head, Link } from '@inertiajs/react';

type Props = {
    secciones: SeccionEstatica[];
    areas: Area[];
};

export default function IntraIndex({ secciones, areas }: Props) {
    return (
        <IntraLayout>
            <Head title="Intranet - Steelex" />

            <div className="flex min-h-screen flex-col items-center px-6 py-12">
                {/* Logo */}
                <div className="mb-12 flex justify-center">
                    <AppLogoIcon className="h-20 md:h-28 text-primary" />
                </div>

                {/* Secciones estáticas */}
                <div className="mb-12 flex flex-wrap justify-center gap-4">
                    {secciones.map((seccion) =>
                        seccion.url_externa ? (
                            <a
                                key={seccion.id}
                                href={seccion.url_externa}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="rounded-lg bg-base-100 px-6 py-3 text-sm font-medium text-base-content shadow-sm transition hover:bg-base-300"
                            >
                                {seccion.boton}
                            </a>
                        ) : (
                            <Link
                                key={seccion.id}
                                href={`/intra/est/${seccion.slug}`}
                                className="rounded-lg bg-base-100 px-6 py-3 text-sm font-medium text-base-content shadow-sm transition hover:bg-base-300"
                            >
                                {seccion.boton}
                            </Link>
                        ),
                    )}
                </div>

                {/* Áreas */}
                <div className="grid w-full max-w-5xl grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {areas.map((area) => (
                        <Link
                            key={area.id}
                            href={`/intra/area/${area.id}`}
                            className="group flex flex-col items-center justify-center rounded-xl border border-base-300 bg-base-100 p-6 text-center shadow-sm transition hover:border-primary/60 hover:bg-base-100/80"
                        >
                            <span className="text-lg font-semibold text-base-content group-hover:text-primary">
                                {area.descripcion}
                            </span>
                        </Link>
                    ))}
                </div>
            </div>
        </IntraLayout>
    );
}
