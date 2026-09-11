/**
 * Dosier de calidad — lo que se entrega al cliente al cerrar la obra.
 *
 * Es `Dossier_Steelex.html` simplificado a lo que pidió calidad: dos pestañas,
 * los dosieres de cada obra y el catálogo de plantillas. Un dosier es un
 * repositorio: nace del árbol de una plantilla, en cada sección se suben a
 * mano los PDF necesarios y «Descargar» los une con portada, índice y
 * separadores. Sin biblioteca ni secciones que se llenen solas, de momento.
 */

import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { CatalogoDePlantillas } from '@/components/qal/dosier/catalogo';
import type { PlantillaDosier } from '@/components/qal/dosier/tipos';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calidad', href: '/admin/calidad/catalogos' },
    { title: 'Dosier', href: '/admin/calidad/dosier' },
];

type Tab = 'dosieres' | 'catalogo';

type Props = {
    tab: Tab;
    plantillaElegida: number | null;
    plantillas: PlantillaDosier[];
};

export default function Dosier({ tab, plantillaElegida, plantillas }: Props) {
    const { can } = useCan();
    const [actual, setActual] = useState<Tab>(tab);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dosier de calidad" />

            <div className="p-6">
                <div className="mb-5">
                    <h1 className="text-2xl font-semibold">Dosier</h1>
                    <p className="text-base-content/60 mt-1 text-sm">
                        El expediente que se entrega al cliente: por sección se suben los PDF necesarios y «Descargar» los une
                        con portada, índice y separadores.
                    </p>
                </div>

                <div role="tablist" className="tabs tabs-boxed mb-5">
                    <button type="button" role="tab" className={`tab ${actual === 'dosieres' ? 'tab-active' : ''}`} onClick={() => setActual('dosieres')}>
                        Dosieres
                    </button>
                    <button type="button" role="tab" className={`tab ${actual === 'catalogo' ? 'tab-active' : ''}`} onClick={() => setActual('catalogo')}>
                        Catálogo
                        <span className="text-base-content/50 ml-1.5 text-xs">{plantillas.filter((p) => p.activo).length}</span>
                    </button>
                </div>

                {actual === 'dosieres' ? (
                    <div className="rounded-box border-base-300 text-base-content/60 border border-dashed p-10 text-center text-sm">
                        Los dosieres de obra llegan en la siguiente entrega: cada uno nacerá de una plantilla del catálogo y en
                        cada sección se subirán sus PDF.
                    </div>
                ) : (
                    <CatalogoDePlantillas plantillas={plantillas} plantillaElegida={plantillaElegida} puedeEditar={can('qal.dossier.editar')} />
                )}
            </div>
        </AppLayout>
    );
}
