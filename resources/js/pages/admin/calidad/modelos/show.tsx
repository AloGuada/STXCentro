/**
 * Un modelo 3D: sus marcas y cómo va cada cordón según las juntas que se
 * capturaron encima.
 *
 * Una marca se fabrica muchas veces, así que el modelo es la plantilla: un
 * cordón está con defecto si alguna pieza lo tiene así ahora, correcto si
 * todas las revisadas lo tienen bien, y naranja si nadie lo ha revisado. Es el
 * reporte que sustituye al mapeo sobre el plano en papel.
 */

import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { PanelCordon } from '@/components/qal/juntas3d/panel-cordon';
import { cargarMarca, type MarcaVisor } from '@/components/qal/juntas3d/tipos';
import { Visor } from '@/components/qal/juntas3d/visor';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SharedData } from '@/types';
import { PastillaModelo, type ModeloResumen } from './index';

type MarcaFila = {
    id: number;
    marca: string;
    nombre: string | null;
    piezas: number;
    peso_kg: string;
    soldaduras: number;
    en_catalogo: boolean;
    cordones: { correctos: number; con_defecto: number; sin_junta: number };
};

type Props = {
    modelo: ModeloResumen;
    marcas: MarcaFila[];
};

const REFRESCO_MS = 5000;

export default function ModeloShow({ modelo, marcas }: Props) {
    const { can } = useCan();
    const { props } = usePage<SharedData & { flash?: { success?: string | null } }>();
    const error = (props.errors as Record<string, string> | undefined)?.modelo;
    const [buscar, setBuscar] = useState('');
    const [marca, setMarca] = useState<MarcaVisor | null>(null);
    const [cargandoMarca, setCargandoMarca] = useState<number | null>(null);
    const [falla, setFalla] = useState<string | null>(null);
    const [cordonSel, setCordonSel] = useState<number | null>(null);

    const enCurso = modelo.estatus === 'pendiente' || modelo.estatus === 'procesando';

    useEffect(() => {
        if (!enCurso) {
            return;
        }
        const reloj = window.setInterval(() => router.reload({ only: ['modelo', 'marcas'] }), REFRESCO_MS);
        return () => window.clearInterval(reloj);
    }, [enCurso]);

    const visibles = useMemo(() => {
        const texto = buscar.trim().toUpperCase();
        return texto ? marcas.filter((fila) => fila.marca.includes(texto) || (fila.nombre ?? '').toUpperCase().includes(texto)) : marcas;
    }, [marcas, buscar]);

    const abrir = async (id: number) => {
        setCargandoMarca(id);
        setFalla(null);
        setCordonSel(null);
        try {
            setMarca(await cargarMarca(id));
        } catch (e) {
            setFalla(e instanceof Error ? e.message : 'No se pudo cargar la marca.');
        } finally {
            setCargandoMarca(null);
        }
    };

    const confirmar = (mensaje: string, accion: () => void) => window.confirm(mensaje) && accion();
    const cordon = marca?.cordones.find((c) => c.id === cordonSel) ?? null;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Calidad', href: '/admin/calidad/catalogos' },
        { title: 'Modelos 3D', href: '/admin/calidad/modelos' },
        { title: `v${modelo.version}`, href: `/admin/calidad/modelos/${modelo.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Calidad — Modelo v${modelo.version}`} />

            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex flex-wrap items-center gap-2 text-2xl font-semibold">
                            Modelo v{modelo.version}
                            <PastillaModelo modelo={modelo} />
                        </h1>
                        <p className="text-base-content/60 text-sm">
                            {[modelo.obra, modelo.nombre_original, modelo.welds_version && `cordones ${modelo.welds_version}`]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                        {modelo.resumen?.marcas !== undefined && (
                            <p className="text-sm">
                                {modelo.resumen.marcas} marcas · {modelo.resumen.cordones ?? 0} cordones
                                {!!modelo.resumen.marcas_sin_catalogo && (
                                    <span className="text-warning"> · {modelo.resumen.marcas_sin_catalogo} sin marca en el catálogo vigente</span>
                                )}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can('qal.modelos.crear') && modelo.estatus === 'listo' && (
                            <button type="button" className="btn btn-sm btn-outline" onClick={() => router.post(`/admin/calidad/modelos/${modelo.id}/resolver-marcas`, {}, { preserveScroll: true })}>
                                Amarrar marcas al catálogo
                            </button>
                        )}
                        {can('qal.modelos.crear') && modelo.estatus.match(/listo|error/) && (
                            <button
                                type="button"
                                className="btn btn-sm btn-outline"
                                onClick={() =>
                                    confirmar(`¿Convertir otra vez el mismo IFC como versión ${modelo.version + 1}? Esta versión se conserva.`, () =>
                                        router.post(`/admin/calidad/modelos/${modelo.id}/reprocesar`),
                                    )
                                }
                            >
                                Reprocesar
                            </button>
                        )}
                        {can('qal.modelos.eliminar') && (
                            <button
                                type="button"
                                className="btn btn-sm btn-ghost text-error"
                                onClick={() => confirmar(`¿Borrar el modelo v${modelo.version} con sus archivos?`, () => router.delete(`/admin/calidad/modelos/${modelo.id}`))}
                            >
                                Borrar
                            </button>
                        )}
                    </div>
                </div>

                {props.flash?.success && <div className="alert alert-success text-sm">{props.flash.success}</div>}
                {error && <div className="alert alert-error text-sm">{error}</div>}
                {modelo.error && <div className="alert alert-error text-sm">{modelo.error}</div>}
                {enCurso && (
                    <div className="alert alert-info text-sm">
                        El IFC se está convirtiendo en el servicio de modelos. Esta pantalla se actualiza sola.
                    </div>
                )}

                {marcas.length > 0 && (
                    <div className="grid gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
                        <div className="border-base-300 bg-base-100 rounded-xl border">
                            <div className="border-base-300 border-b p-3">
                                <input
                                    value={buscar}
                                    onChange={(e) => setBuscar(e.target.value)}
                                    placeholder="Buscar marca…"
                                    className="input input-bordered input-sm w-full"
                                />
                            </div>
                            <ul className="max-h-[560px] overflow-y-auto text-sm">
                                {visibles.map((fila) => (
                                    <li key={fila.id}>
                                        <button
                                            type="button"
                                            onClick={() => abrir(fila.id)}
                                            className={`hover:bg-base-200 flex w-full items-center justify-between gap-2 px-3 py-2 text-left ${
                                                marca?.id === fila.id ? 'bg-primary/10' : ''
                                            }`}
                                        >
                                            <span>
                                                <span className="font-semibold">{fila.marca}</span>
                                                {!fila.en_catalogo && <span className="text-warning ml-1 text-xs">sin catálogo</span>}
                                                <span className="text-base-content/50 block text-xs">
                                                    {fila.nombre} · {fila.piezas} pz · {Number(fila.peso_kg)} kg
                                                </span>
                                            </span>
                                            <span className="flex gap-1 font-mono text-xs">
                                                <span className="text-success" title="cordones correctos">{fila.cordones.correctos}</span>
                                                <span className="text-error" title="cordones con defecto">{fila.cordones.con_defecto}</span>
                                                <span className="text-warning" title="cordones sin junta">{fila.cordones.sin_junta}</span>
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="space-y-3">
                            {falla && <div className="alert alert-error text-sm">{falla}</div>}
                            {cargandoMarca !== null && <div className="text-base-content/60 text-sm">Cargando la marca…</div>}
                            {!marca && cargandoMarca === null && (
                                <div className="border-base-300 text-base-content/60 rounded-xl border border-dashed p-10 text-center text-sm">
                                    Elige una marca para ver su geometría y sus cordones.
                                </div>
                            )}
                            {marca && (
                                <>
                                    <div className="text-sm font-semibold">
                                        {marca.marca} · {marca.cordones.length} cordones · toca uno para ver su ficha
                                    </div>
                                    <Visor
                                        key={marca.glb_url}
                                        glbUrl={marca.glb_url}
                                        cordones={marca.cordones}
                                        seleccionado={cordonSel}
                                        onSeleccionar={setCordonSel}
                                        className="h-[520px]"
                                    />
                                    {cordon && <PanelCordon cordon={cordon} />}
                                </>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
