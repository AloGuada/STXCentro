/**
 * Una versión del modelo 3D: la estructura entera de un vistazo y, marca por
 * marca, su geometría con sus cordones.
 *
 * El modelo completo se enseña sin cordones —son decenas de miles y no dicen
 * nada juntos—; los cordones se ven al abrir una marca. Una marca se fabrica
 * muchas veces, así que su modelo es la plantilla: un cordón está con defecto
 * si alguna pieza lo tiene así ahora, correcto si todas las revisadas lo
 * tienen bien, y naranja si nadie lo ha revisado. Es el reporte que sustituye
 * al mapeo sobre el plano en papel.
 */

import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { BotonesHoja } from '@/components/qal/juntas3d/hoja-impresa';
import { PanelCordon } from '@/components/qal/juntas3d/panel-cordon';
import { cargarMarca, type MarcaVisor } from '@/components/qal/juntas3d/tipos';
import { Visor } from '@/components/qal/juntas3d/visor';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import type { SharedData } from '@/types';
import { migasDelModelo, PastillaModelo, type CatalogoDelModelo, type ModeloResumen } from './index';

type MarcaFila = {
    id: number;
    marca: string;
    nombre: string | null;
    piezas: number;
    peso_kg: string;
    soldaduras: number;
    en_catalogo: boolean;
};

type Props = {
    /** El catálogo de la obra, para volver a él. Null si la obra ya no tiene. */
    catalogo: CatalogoDelModelo | null;
    modelo: ModeloResumen & { modelo_url: string | null };
    marcas: MarcaFila[];
};

const REFRESCO_MS = 5000;

export default function ModeloShow({ catalogo, modelo, marcas }: Props) {
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

    const breadcrumbs = [...migasDelModelo(catalogo), { title: `v${modelo.version}`, href: `/admin/prod/modelos/${modelo.id}` }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Modelo 3D v${modelo.version}`} />

            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex flex-wrap items-center gap-2 text-2xl font-semibold">
                            Modelo 3D v{modelo.version}
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
                            <button type="button" className="btn btn-sm btn-outline" onClick={() => router.post(`/admin/prod/modelos/${modelo.id}/resolver-marcas`, {}, { preserveScroll: true })}>
                                Amarrar marcas al catálogo
                            </button>
                        )}
                        {can('qal.modelos.crear') && modelo.estatus.match(/listo|error/) && (
                            <button
                                type="button"
                                className="btn btn-sm btn-outline"
                                onClick={() =>
                                    confirmar(`¿Convertir otra vez el mismo IFC como versión ${modelo.version + 1}? Esta versión se conserva.`, () =>
                                        router.post(`/admin/prod/modelos/${modelo.id}/reprocesar`),
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
                                onClick={() => confirmar(`¿Borrar el modelo v${modelo.version} con sus archivos?`, () => router.delete(`/admin/prod/modelos/${modelo.id}`))}
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
                        El IFC se está convirtiendo en el servicio de modelos. Las marcas aparecen conforme terminan y ya
                        se pueden abrir; el modelo completo llega al final. Esta pantalla se actualiza sola.
                    </div>
                )}

                {(marcas.length > 0 || modelo.modelo_url) && (
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
                                        <div
                                            className={`flex w-full items-center justify-between gap-2 px-3 py-2 ${
                                                marca?.id === fila.id ? 'bg-primary/10' : ''
                                            }`}
                                        >
                                            <span className="min-w-0">
                                                <span className="font-semibold">{fila.marca}</span>
                                                {!fila.en_catalogo && <span className="text-warning ml-1 text-xs">sin catálogo</span>}
                                                <span className="text-base-content/50 block truncate text-xs">
                                                    {fila.nombre} · {fila.piezas} pz · {Number(fila.peso_kg)} kg · {fila.soldaduras} cordones
                                                </span>
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => abrir(fila.id)}
                                                disabled={cargandoMarca === fila.id}
                                                className="btn btn-xs btn-outline shrink-0"
                                            >
                                                Ver cordones
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="space-y-3">
                            {falla && <div className="alert alert-error text-sm">{falla}</div>}
                            {cargandoMarca !== null && <div className="text-base-content/60 text-sm">Cargando la marca…</div>}
                            {!marca && cargandoMarca === null && modelo.modelo_url && (
                                <>
                                    <div className="text-sm font-semibold">
                                        Estructura completa · sin cordones · «Ver cordones» en una marca para abrirla sola
                                    </div>
                                    <Visor
                                        key={modelo.modelo_url}
                                        glbUrl={modelo.modelo_url}
                                        cordones={[]}
                                        seleccionado={null}
                                        onSeleccionar={() => undefined}
                                        className="h-[520px]"
                                    />
                                </>
                            )}
                            {!marca && cargandoMarca === null && !modelo.modelo_url && (
                                <div className="border-base-300 text-base-content/60 rounded-xl border border-dashed p-10 text-center text-sm">
                                    {enCurso
                                        ? 'El modelo completo llega al terminar la conversión. Mientras, abre una marca.'
                                        : 'Elige una marca para ver su geometría y sus cordones.'}
                                </div>
                            )}
                            {marca && (
                                <>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="text-sm font-semibold">
                                            {marca.marca} · {marca.cordones.length} cordones · toca uno para ver su ficha
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <button type="button" className="btn btn-xs btn-ghost" onClick={() => setMarca(null)}>
                                                Volver al modelo completo
                                            </button>
                                            <BotonesHoja marca={marca} />
                                        </div>
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
