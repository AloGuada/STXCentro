/**
 * Registro 2 — la misma base en crudo que Registros, en paralelo y sin tocarla.
 *
 * Lee las mismas tablas y comparte con Registros la ficha, la barra de filtros
 * y la paginación. Lo que cambia es lo que pedía el backlog:
 *
 *  - **Periodo, no un día.** Desde y hasta; cualquiera de los dos puede ir
 *    solo.
 *  - **Indicadores arriba.** Registros y piezas únicas por separado, porque
 *    no son lo mismo: una pieza reinspeccionada son dos registros.
 *  - **Inspectores con historial.** Sólo quien tiene algo capturado, y el que
 *    ya está dado de baja se marca, pero se sigue pudiendo filtrar.
 *  - **Accesorios desglosados.** Total del lote, unidades, nivel AQL y muestra
 *    van en columnas propias.
 *
 * Filtros, orden y página van en la URL y los resuelve el servidor.
 */

import { Head, router, usePage } from '@inertiajs/react';
import { DownloadIcon, EyeIcon } from 'lucide-react';
import { useState } from 'react';
import {
    FichaPieza,
    FichaSublote,
    type FichaDeSublote,
    type FichaInspeccion,
} from '@/components/qal/registros/ficha';
import {
    Filtro,
    FiltroSelect,
    Paginacion,
    PastillaVeredicto,
    Tabla,
    Th,
    type Orden,
} from '@/components/qal/registros/ui';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, SharedData } from '@/types';
import type { PaginatedData } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calidad', href: '/admin/calidad/catalogos' },
    { title: 'Registro 2', href: '/admin/calidad/registros2' },
];

const RUTA = '/admin/calidad/registros2';
const FASES = ['1ª', '2ª', '3ª'];
const ESTATUS: [string, string][] = [
    ['liberado', 'Liberado'],
    ['rechazado', 'Rechazado'],
    ['pendiente', 'Pendiente'],
];

type Filtros = {
    que: 'pza' | 'acc';
    obra: number | null;
    fase: string | null;
    inspector: number | null;
    estatus: string | null;
    desde: string | null;
    hasta: string | null;
    buscar: string | null;
    sort_by: string;
    sort_dir: 'asc' | 'desc';
};

type FilaPieza = {
    id: number;
    folio: string;
    fecha: string;
    fase: string;
    etapa: string | null;
    obra: string | null;
    marca: string;
    lote: string | null;
    qr: string | null;
    consecutivo: number | null;
    numero_inspeccion: number;
    modulo: string | null;
    inspector: string | null;
    estatus: string;
};

type FilaSublote = {
    id: number;
    fecha: string;
    /** '2ª' si se revisó soldada, '3ª' si se revisó pintada. */
    fase: string;
    obra: string | null;
    marca: string;
    total_lote: number;
    unidades: number;
    nivel: string;
    muestra: number;
    rechazadas: number;
    numero_inspeccion: number;
    veredicto: string | null;
    disposicion: string | null;
    liberado: boolean;
    sin_disposicion: boolean;
    inspector: string | null;
};

type Props = {
    filtros: Filtros;
    obras: { id: number; no: string | null; descripcion: string | null }[];
    inspectores: { id: number; nombre: string; activo: boolean }[];
    registros: PaginatedData<FilaPieza> | null;
    sublotes: PaginatedData<FilaSublote> | null;
    conteo: { registros: number; piezas: number | null; total: number };
    ficha: FichaInspeccion | null;
    fichaSublote: FichaDeSublote | null;
};

/** Los filtros como parámetros de la URL, sin los vacíos: la dirección se comparte limpia. */
function parametros(
    filtros: Filtros,
    cambios: Record<string, string | number | null> = {},
): Record<string, string> {
    return Object.fromEntries(
        Object.entries({ ...filtros, ...cambios })
            .filter(
                ([, valor]) =>
                    valor !== null && valor !== undefined && valor !== '',
            )
            .map(([clave, valor]) => [clave, String(valor)]),
    );
}

/** Los parámetros que trae la URL ahora, incluida la página. */
function actuales(): Record<string, string> {
    return Object.fromEntries(new URLSearchParams(window.location.search));
}

export default function Registro2Calidad({
    filtros,
    obras,
    inspectores,
    registros,
    sublotes,
    conteo,
    ficha,
    fichaSublote,
}: Props) {
    const { can } = useCan();
    const { props } = usePage<
        SharedData & { flash?: { success?: string | null } }
    >();
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    const esPiezas = filtros.que === 'pza';
    const orden: Orden = { campo: filtros.sort_by, dir: filtros.sort_dir };

    const filtrar = (cambios: Record<string, string | number | null>) =>
        router.get(RUTA, parametros(filtros, cambios), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    const ordenarPor = (campo: string) =>
        filtrar({
            sort_by: campo,
            sort_dir:
                filtros.sort_by === campo && filtros.sort_dir === 'asc'
                    ? 'desc'
                    : 'asc',
        });

    /** La ficha va en la URL: se puede compartir, y tras corregir se vuelve a ella. */
    const abrir = (clave: 'ficha' | 'sublote', id: number | null) => {
        const resto = actuales();
        delete resto.ficha;
        delete resto.sublote;
        router.get(
            RUTA,
            id === null ? resto : { ...resto, [clave]: String(id) },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['ficha', 'fichaSublote'],
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calidad — Registro 2" />

            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Registro 2</h1>
                        <p className="text-sm text-base-content/60">
                            Todo lo capturado, sin resumir, filtrado por
                            periodo. Para responder qué se inspeccionó, quién lo
                            liberó y por qué se rechazó.
                        </p>
                    </div>

                    {can('qal.registros.exportar') && (
                        <a
                            href={`${RUTA}/exportar?${new URLSearchParams(parametros(filtros))}`}
                            className="btn btn-outline btn-sm"
                        >
                            <DownloadIcon className="size-4" />
                            Exportar a Excel
                        </a>
                    )}
                </div>

                {props.flash?.success && (
                    <div className="alert text-sm alert-success">
                        {props.flash.success}
                    </div>
                )}

                <div className="grid gap-3 sm:grid-cols-3">
                    {esPiezas ? (
                        <>
                            <Indicador
                                titulo="Registros"
                                valor={conteo.registros}
                                detalle={`de ${conteo.total} capturados`}
                            />
                            <Indicador
                                titulo="Piezas únicas"
                                valor={conteo.piezas ?? 0}
                                detalle="una pieza reinspeccionada cuenta una vez"
                            />
                        </>
                    ) : (
                        <Indicador
                            titulo="Sublotes"
                            valor={conteo.registros}
                            detalle={`de ${conteo.total} · su última inspección`}
                        />
                    )}
                </div>

                <div className="flex flex-wrap items-end gap-3 rounded-xl border border-base-300 bg-base-100 p-4">
                    <Filtro label="Qué se lista" className="w-44">
                        <Select
                            value={filtros.que}
                            onValueChange={(valor) =>
                                // El estatus no existe en un sublote —lo suyo es el
                                // veredicto— y se limpia para que no filtre a escondidas.
                                filtrar({
                                    que: valor,
                                    estatus: null,
                                    sort_by: 'fecha',
                                    sort_dir: 'desc',
                                })
                            }
                            className="select-sm"
                        >
                            <SelectItem value="pza">Piezas</SelectItem>
                            <SelectItem value="acc">
                                Sublotes de accesorios
                            </SelectItem>
                        </Select>
                    </Filtro>

                    {/* En 1ª no hay lotes de accesorios: la pieza todavía es material cortado. */}
                    <FiltroSelect
                        label="Transformación"
                        value={filtros.fase ?? ''}
                        onChange={(valor) => filtrar({ fase: valor })}
                        opciones={
                            esPiezas
                                ? FASES
                                : FASES.filter((fase) => fase !== '1ª')
                        }
                        todas="Todas"
                        className="w-36"
                    />

                    <FiltroSelect
                        label="Obra"
                        value={filtros.obra ? String(filtros.obra) : ''}
                        onChange={(valor) => filtrar({ obra: valor })}
                        opciones={obras.map(
                            (obra) =>
                                [
                                    String(obra.id),
                                    [obra.no, obra.descripcion]
                                        .filter(Boolean)
                                        .join(' — '),
                                ] as const,
                        )}
                        todas="Todas"
                        className="w-56"
                    />

                    <FiltroSelect
                        label="Inspector"
                        value={
                            filtros.inspector ? String(filtros.inspector) : ''
                        }
                        onChange={(valor) => filtrar({ inspector: valor })}
                        opciones={inspectores.map(
                            (inspector) =>
                                [
                                    String(inspector.id),
                                    inspector.activo
                                        ? inspector.nombre
                                        : `${inspector.nombre} (inactivo)`,
                                ] as const,
                        )}
                        todas="Todos"
                        className="w-48"
                    />

                    {esPiezas && (
                        <FiltroSelect
                            label="Estatus"
                            value={filtros.estatus ?? ''}
                            onChange={(valor) => filtrar({ estatus: valor })}
                            opciones={ESTATUS}
                            todas="Todos"
                            className="w-40"
                        />
                    )}

                    <Filtro label="Desde" className="w-40">
                        <Input
                            type="date"
                            value={filtros.desde ?? ''}
                            max={filtros.hasta ?? undefined}
                            onChange={(e) => filtrar({ desde: e.target.value })}
                            className="input-sm"
                        />
                    </Filtro>

                    <Filtro label="Hasta" className="w-40">
                        <Input
                            type="date"
                            value={filtros.hasta ?? ''}
                            min={filtros.desde ?? undefined}
                            onChange={(e) => filtrar({ hasta: e.target.value })}
                            className="input-sm"
                        />
                    </Filtro>

                    <Filtro
                        label={
                            esPiezas
                                ? 'Buscar marca / folio / QR'
                                : 'Buscar marca'
                        }
                        className="w-56"
                    >
                        <Input
                            value={buscar}
                            onChange={(e) => setBuscar(e.target.value)}
                            onKeyDown={(e) =>
                                e.key === 'Enter' && filtrar({ buscar })
                            }
                            onBlur={() =>
                                buscar !== (filtros.buscar ?? '') &&
                                filtrar({ buscar })
                            }
                            placeholder={esPiezas ? 'ej. CM22' : 'ej. ACC-PL12'}
                            className="input-sm"
                        />
                    </Filtro>

                    <div className="ml-auto">
                        <button
                            type="button"
                            onClick={() => {
                                setBuscar('');
                                router.get(
                                    RUTA,
                                    filtros.que === 'acc' ? { que: 'acc' } : {},
                                    { replace: true },
                                );
                            }}
                            className="btn btn-ghost btn-sm"
                        >
                            Limpiar
                        </button>
                    </div>
                </div>

                {esPiezas && registros && (
                    <>
                        <TablaPiezas
                            filas={registros.data}
                            orden={orden}
                            onOrdenar={ordenarPor}
                            onVer={(id) => abrir('ficha', id)}
                        />
                        <Paginacion datos={registros} />
                    </>
                )}
                {!esPiezas && sublotes && (
                    <>
                        <TablaSublotes
                            filas={sublotes.data}
                            orden={orden}
                            onOrdenar={ordenarPor}
                            onVer={(id) => abrir('sublote', id)}
                        />
                        <Paginacion datos={sublotes} />
                    </>
                )}
            </div>

            <FichaPieza
                ficha={ficha}
                onIr={(id) => abrir('ficha', id)}
                onCerrar={() => abrir('ficha', null)}
            />
            <FichaSublote
                ficha={fichaSublote}
                onIr={(id) => abrir('sublote', id)}
                onCerrar={() => abrir('sublote', null)}
            />
        </AppLayout>
    );
}

function Indicador({
    titulo,
    valor,
    detalle,
}: {
    titulo: string;
    valor: number;
    detalle: string;
}) {
    return (
        <div className="rounded-xl border border-base-300 bg-base-100 p-4">
            <div className="text-xs font-medium text-base-content/60">
                {titulo}
            </div>
            <div className="text-2xl font-semibold tabular-nums">
                {valor.toLocaleString('es-MX')}
            </div>
            <div className="text-xs text-base-content/50">{detalle}</div>
        </div>
    );
}

const ETIQUETAS_ESTATUS: Record<string, string> = Object.fromEntries(ESTATUS);

/**
 * El estatus tal como está guardado. Los tres conocidos llevan su color; uno
 * que no se reconoce se enseña en gris con su valor, sin disfrazarlo de
 * pendiente.
 */
function PastillaEstatus({ estatus }: { estatus: string }) {
    const tono =
        estatus === 'liberado'
            ? 'bg-success/15 text-success'
            : estatus === 'rechazado'
              ? 'bg-error/15 text-error'
              : estatus === 'pendiente'
                ? 'bg-warning/15 text-warning'
                : 'bg-base-200 text-base-content/70';

    return (
        <span
            className={cn('rounded-full px-2.5 py-0.5 text-xs font-bold', tono)}
        >
            {ETIQUETAS_ESTATUS[estatus] ?? estatus}
        </span>
    );
}

function BotonFicha({ onClick }: { onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="btn btn-ghost btn-xs"
            aria-label="Ver ficha"
        >
            <EyeIcon className="size-4" />
        </button>
    );
}

/** Una segunda inspección significa que antes se rechazó: se marca para que se note sin abrir la ficha. */
function NumeroInspeccion({ numero }: { numero: number }) {
    return numero > 1 ? (
        <span className="badge badge-sm badge-warning">#{numero}</span>
    ) : (
        <>{numero}</>
    );
}

function TablaPiezas({
    filas,
    orden,
    onOrdenar,
    onVer,
}: {
    filas: FilaPieza[];
    orden: Orden;
    onOrdenar: (campo: string) => void;
    onVer: (id: number) => void;
}) {
    return (
        <Tabla
            vacio={
                filas.length
                    ? null
                    : 'Ningún registro coincide con los filtros.'
            }
        >
            <thead>
                <tr>
                    <Th campo="fecha" orden={orden} onOrdenar={onOrdenar}>
                        Fecha
                    </Th>
                    <Th campo="fase" orden={orden} onOrdenar={onOrdenar}>
                        Fase
                    </Th>
                    <Th campo="marca" orden={orden} onOrdenar={onOrdenar}>
                        Marca / Folio
                    </Th>
                    <Th campo="consecutivo" orden={orden} onOrdenar={onOrdenar}>
                        QR
                    </Th>
                    <Th
                        campo="numero_inspeccion"
                        orden={orden}
                        onOrdenar={onOrdenar}
                    >
                        Insp.
                    </Th>
                    <Th>Obra</Th>
                    <Th campo="modulo" orden={orden} onOrdenar={onOrdenar}>
                        Módulo
                    </Th>
                    <Th>Inspector</Th>
                    <Th campo="estatus" orden={orden} onOrdenar={onOrdenar}>
                        Status
                    </Th>
                    <Th className="w-12" />
                </tr>
            </thead>
            <tbody>
                {filas.map((fila) => (
                    <tr key={fila.id} className="hover:bg-base-200/50">
                        <td className="font-mono text-sm">{fila.fecha}</td>
                        <td>
                            {fila.fase}
                            {fila.etapa && (
                                <span className="ml-1 text-xs text-base-content/50">
                                    · {fila.etapa}
                                </span>
                            )}
                        </td>
                        <td>
                            <span className="font-semibold">{fila.marca}</span>
                            {fila.lote && (
                                <span className="ml-1 text-xs text-base-content/50">
                                    lote {fila.lote}
                                </span>
                            )}
                            <div className="font-mono text-xs text-base-content/50">
                                {fila.folio}
                            </div>
                        </td>
                        <td className="font-mono text-sm">
                            {fila.qr ??
                                (fila.consecutivo
                                    ? `#${fila.consecutivo}`
                                    : '')}
                        </td>
                        <td className="font-mono">
                            <NumeroInspeccion numero={fila.numero_inspeccion} />
                        </td>
                        <td className="text-sm">{fila.obra}</td>
                        <td className="font-mono text-sm">{fila.modulo}</td>
                        <td className="text-sm">{fila.inspector}</td>
                        <td>
                            <PastillaEstatus estatus={fila.estatus} />
                        </td>
                        <td className="text-right">
                            <BotonFicha onClick={() => onVer(fila.id)} />
                        </td>
                    </tr>
                ))}
            </tbody>
        </Tabla>
    );
}

function TablaSublotes({
    filas,
    orden,
    onOrdenar,
    onVer,
}: {
    filas: FilaSublote[];
    orden: Orden;
    onOrdenar: (campo: string) => void;
    onVer: (id: number) => void;
}) {
    return (
        <Tabla
            vacio={
                filas.length ? null : 'Ningún sublote coincide con los filtros.'
            }
        >
            <thead>
                <tr>
                    <Th campo="fecha" orden={orden} onOrdenar={onOrdenar}>
                        Fecha
                    </Th>
                    <Th campo="fase" orden={orden} onOrdenar={onOrdenar}>
                        Fase
                    </Th>
                    <Th>Obra</Th>
                    <Th>Marca</Th>
                    <Th>Total del lote</Th>
                    <Th campo="unidades" orden={orden} onOrdenar={onOrdenar}>
                        Unidades
                    </Th>
                    <Th>Nivel AQL</Th>
                    <Th campo="muestra" orden={orden} onOrdenar={onOrdenar}>
                        Muestra
                    </Th>
                    <Th campo="rechazadas" orden={orden} onOrdenar={onOrdenar}>
                        Rechazadas
                    </Th>
                    <Th
                        campo="numero_inspeccion"
                        orden={orden}
                        onOrdenar={onOrdenar}
                    >
                        N.º insp.
                    </Th>
                    <Th campo="veredicto" orden={orden} onOrdenar={onOrdenar}>
                        Veredicto
                    </Th>
                    <Th>Disposición</Th>
                    <Th>Inspector</Th>
                    <Th className="w-12" />
                </tr>
            </thead>
            <tbody>
                {filas.map((fila) => (
                    <tr key={fila.id} className="hover:bg-base-200/50">
                        <td className="font-mono text-sm">{fila.fecha}</td>
                        <td className="text-sm">{fila.fase}</td>
                        <td className="text-sm">{fila.obra}</td>
                        <td className="font-semibold">{fila.marca}</td>
                        <td className="font-mono">{fila.total_lote}</td>
                        <td className="font-mono">{fila.unidades}</td>
                        <td className="font-mono">{fila.nivel}</td>
                        <td className="font-mono">{fila.muestra}</td>
                        <td className="font-mono">
                            {fila.rechazadas > 0 ? (
                                <span className="font-bold text-error">
                                    {fila.rechazadas}
                                </span>
                            ) : (
                                fila.rechazadas
                            )}
                        </td>
                        <td className="font-mono">
                            <NumeroInspeccion numero={fila.numero_inspeccion} />
                        </td>
                        <td>
                            <PastillaVeredicto
                                veredicto={fila.veredicto}
                                liberado={fila.liberado}
                            />
                        </td>
                        <td className="text-sm">
                            {fila.sin_disposicion ? (
                                <span className="text-xs font-semibold text-error">
                                    sin decidir
                                </span>
                            ) : (
                                fila.disposicion || (
                                    <span className="text-base-content/40">
                                        —
                                    </span>
                                )
                            )}
                        </td>
                        <td className="text-sm">{fila.inspector}</td>
                        <td className="text-right">
                            <BotonFicha onClick={() => onVer(fila.id)} />
                        </td>
                    </tr>
                ))}
            </tbody>
        </Tabla>
    );
}
