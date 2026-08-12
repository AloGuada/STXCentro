import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { PlugZapIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    titulo: string;
    /** Qué es la entidad, en una línea. */
    descripcion: string;
    /** Tabla real que respalda la pantalla, para no perder de vista que ya existe. */
    tabla: string;
    /** Los campos que la tabla ya guarda hoy. */
    campos: string[];
    /** Qué falta acordar antes de construirla. */
    pendiente: ReactNode;
};

/**
 * Pantalla del módulo de Calidad todavía sin construir.
 *
 * A diferencia de una maqueta, aquí no se dibujan datos de ejemplo a propósito:
 * las tablas ya existen y las llena una aplicación externa por API, así que
 * inventar filas daría la impresión de que la pantalla lee algo. Lo que se
 * muestra es qué guarda cada tabla, para poder decidir qué enseñar.
 */
export function PantallaPendiente({ titulo, descripcion, tabla, campos, pendiente }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Calidad', href: '/admin/calidad/obras' },
        { title: titulo, href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Calidad — ${titulo}`} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">{titulo}</h1>
                    <p className="text-base-content/60 mt-1 text-sm">{descripcion}</p>
                </div>

                <div className="alert alert-info mb-4">
                    <PlugZapIcon className="size-4" />
                    <span>
                        Pantalla por construir. Los datos ya existen y los captura la aplicación de inspección por API;
                        lo que falta es la vista web.
                    </span>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div className="rounded-box border-base-300 border p-4">
                        <h2 className="mb-2 font-medium">
                            Qué guarda hoy <span className="font-mono text-sm">{tabla}</span>
                        </h2>
                        <ul className="text-base-content/70 list-inside list-disc space-y-1 text-sm">
                            {campos.map((campo) => (
                                <li key={campo}>{campo}</li>
                            ))}
                        </ul>
                    </div>

                    <div className="rounded-box border-base-300 border p-4">
                        <h2 className="mb-2 font-medium">Qué falta acordar</h2>
                        <div className="text-base-content/70 space-y-2 text-sm">{pendiente}</div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
