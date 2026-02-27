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
                <div className="mb-12 text-center">
                    <h1 className="text-6xl font-bold italic text-white tracking-tight">
                        Steelex
                    </h1>
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
                                className="rounded-lg bg-slate-800/50 px-6 py-3 text-sm font-medium text-slate-200 transition hover:bg-slate-700/50 hover:text-white"
                            >
                                {seccion.boton}
                            </a>
                        ) : (
                            <Link
                                key={seccion.id}
                                href={`/intra/est/${seccion.slug}`}
                                className="rounded-lg bg-slate-800/50 px-6 py-3 text-sm font-medium text-slate-200 transition hover:bg-slate-700/50 hover:text-white"
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
                            className="group flex flex-col items-center justify-center rounded-xl border border-slate-600 bg-slate-800/30 p-6 text-center transition hover:border-slate-500 hover:bg-slate-700/30"
                        >
                            <span className="text-lg font-semibold text-white group-hover:text-blue-300">
                                {area.descripcion}
                            </span>
                        </Link>
                    ))}
                </div>
            </div>
        </IntraLayout>
    );
}
