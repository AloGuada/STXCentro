/**
 * Pestaña Registros de la captura: qué va de cada marca de la obra.
 *
 * Es «Lotes por marca» de la aplicación anterior. Allí cada marca desplegaba
 * sus consecutivos y el inspector registraba el que faltaba; aquí la pieza es
 * el QR de Producción, así que cada marca despliega sus piezas con la última
 * inspección de cada etapa, y desde la fila se captura la que falta, se abre
 * la ficha o se reinspecciona la que quedó rechazada.
 *
 * Las marcas llegan con sus cuentas; las piezas se piden al abrir la marca.
 */

import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Boton, Pastilla, Pista, Tarjeta } from './ui';

export type MarcaConAvance = {
    id: number;
    marca: string;
    lote: string | null;
    descripcion: string | null;
    piezas: number;
    inspeccionadas: number;
    liberadas: number;
    rechazadas: number;
    pendientes: number;
};

type Estatus = 'liberado' | 'rechazado' | 'pendiente';

export type EtapaDePieza = {
    id: number;
    folio: string;
    estatus: Estatus;
    fecha: string;
    numero_inspeccion: number;
    inspecciones: number;
};

export type PiezaConAvance = {
    id: number;
    qr: string | null;
    qs: string | null;
    etapas: { armado: EtapaDePieza | null; soldado: EtapaDePieza | null; pintura: EtapaDePieza | null };
};

export type PiezasDeMarca = { conceptoId: number; piezas: PiezaConAvance[] };

const TONO: Record<Estatus, 'lib' | 'rej' | 'pen'> = { liberado: 'lib', rechazado: 'rej', pendiente: 'pen' };
const TEXTO: Record<Estatus, string> = { liberado: 'Liberado', rechazado: 'Rechazado', pendiente: 'Pendiente' };
const ETAPAS: [keyof PiezaConAvance['etapas'], string][] = [
    ['armado', 'Armado'],
    ['soldado', 'Soldado'],
    ['pintura', 'Pintura'],
];

/** Sin obra no hay marcas; sin filtro las marcas se buscan por texto. */
export function RegistrosDeCaptura({
    obraId,
    marcas,
    piezasDeMarca,
    onCapturar,
}: {
    obraId: string;
    marcas: MarcaConAvance[];
    piezasDeMarca: PiezasDeMarca | null;
    /** Lleva el QR a la pestaña Capturar. */
    onCapturar: (qr: string) => void;
}) {
    const [buscar, setBuscar] = useState('');
    const [abierta, setAbierta] = useState<number | null>(null);
    const [cargando, setCargando] = useState<number | null>(null);

    if (!obraId) {
        return (
            <Tarjeta titulo="Registros">
                <Pista className="mb-0">Elige una obra en la pestaña Capturar para ver el avance de sus marcas.</Pista>
            </Tarjeta>
        );
    }

    const consulta = buscar.trim().toUpperCase();
    const visibles = marcas.filter(
        (m) => !consulta || m.marca.toUpperCase().includes(consulta) || (m.descripcion ?? '').toUpperCase().includes(consulta),
    );
    const total = marcas.reduce(
        (a, m) => ({ piezas: a.piezas + m.piezas, inspeccionadas: a.inspeccionadas + m.inspeccionadas }),
        { piezas: 0, inspeccionadas: 0 },
    );

    const abrir = (id: number) => {
        if (abierta === id) {
            setAbierta(null);
            return;
        }
        setAbierta(id);
        if (piezasDeMarca?.conceptoId !== id) {
            router.reload({
                only: ['piezasDeMarca'],
                data: { marca: id },
                onStart: () => setCargando(id),
                onFinish: () => setCargando(null),
            });
        }
    };

    return (
        <Tarjeta titulo={`Marcas de la obra (${marcas.length})`}>
            <Pista>
                {total.inspeccionadas} de {total.piezas} piezas presentadas a inspección. Abre una marca para ver cada
                QR con su última inspección por etapa; desde ahí se captura la que falta o se reinspecciona la rechazada.
            </Pista>

            <input
                type="search"
                className="input input-bordered mb-3 w-full"
                placeholder="Buscar marca o descripción…"
                value={buscar}
                onChange={(e) => setBuscar(e.target.value)}
            />

            {visibles.length === 0 ? (
                <Pista className="mb-0">
                    {marcas.length === 0 ? 'La obra no tiene marcas en su catálogo vigente.' : 'Ninguna marca coincide.'}
                </Pista>
            ) : (
                <div className="space-y-2">
                    {visibles.map((m) => {
                        const completa = m.piezas > 0 && m.liberadas >= m.piezas;
                        const estaAbierta = abierta === m.id;
                        const piezas = estaAbierta && piezasDeMarca?.conceptoId === m.id ? piezasDeMarca.piezas : null;

                        return (
                            <div key={m.id} className="rounded-box border border-base-300">
                                <button
                                    type="button"
                                    onClick={() => abrir(m.id)}
                                    aria-expanded={estaAbierta}
                                    className="flex w-full items-center gap-2 px-3 py-2 text-left"
                                >
                                    <span className="text-base-content/50 text-xs">{estaAbierta ? '▾' : '▸'}</span>
                                    <span className="min-w-0 flex-1">
                                        <b>{m.marca}</b>
                                        {m.lote && <span className="text-base-content/60 text-xs"> · lote {m.lote}</span>}
                                        {m.descripcion && (
                                            <span className="text-base-content/60 block truncate text-xs">{m.descripcion}</span>
                                        )}
                                    </span>
                                    <span className="flex shrink-0 items-center gap-1.5 text-xs">
                                        {m.rechazadas > 0 && <Pastilla tono="rej">{m.rechazadas} rech.</Pastilla>}
                                        {m.pendientes > 0 && <Pastilla tono="pen">{m.pendientes} pend.</Pastilla>}
                                        <b className={`text-[15px] ${completa ? 'text-success' : 'text-error'}`}>
                                            {m.inspeccionadas}/{m.piezas}
                                        </b>
                                    </span>
                                </button>

                                {estaAbierta && (
                                    <div className="border-t border-base-300 px-3 py-2">
                                        {piezas === null ? (
                                            <div className="text-base-content/60 py-2 text-xs">
                                                {cargando === m.id ? 'Cargando piezas…' : 'Sin piezas.'}
                                            </div>
                                        ) : piezas.length === 0 ? (
                                            <div className="text-base-content/60 py-2 text-xs">
                                                La marca no tiene piezas con QR en el catálogo.
                                            </div>
                                        ) : (
                                            <div className="overflow-x-auto">
                                                <table className="table table-xs">
                                                    <thead>
                                                        <tr>
                                                            <th>Pieza</th>
                                                            {ETAPAS.map(([clave, texto]) => (
                                                                <th key={clave}>{texto}</th>
                                                            ))}
                                                            <th />
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {piezas.map((p) => {
                                                            const rechazada = ETAPAS.map(([clave]) => p.etapas[clave]).find(
                                                                (e) => e?.estatus === 'rechazado',
                                                            );

                                                            return (
                                                                <tr key={p.id}>
                                                                    <td className="whitespace-nowrap">
                                                                        <b>{p.qr ?? '—'}</b>
                                                                        {p.qs && (
                                                                            <span className="text-base-content/60 text-xs"> · QS {p.qs}</span>
                                                                        )}
                                                                    </td>
                                                                    {ETAPAS.map(([clave]) => {
                                                                        const e = p.etapas[clave];
                                                                        return (
                                                                            <td key={clave} className="whitespace-nowrap">
                                                                                {e ? (
                                                                                    <Link
                                                                                        href={`/admin/calidad/registros?ficha=${e.id}`}
                                                                                        title={`${e.folio} · ${e.fecha}`}
                                                                                    >
                                                                                        <Pastilla tono={TONO[e.estatus]}>
                                                                                            {TEXTO[e.estatus]}
                                                                                            {e.inspecciones > 1 && ` ×${e.inspecciones}`}
                                                                                        </Pastilla>
                                                                                    </Link>
                                                                                ) : (
                                                                                    <span className="text-base-content/30">—</span>
                                                                                )}
                                                                            </td>
                                                                        );
                                                                    })}
                                                                    <td className="text-right whitespace-nowrap">
                                                                        {rechazada ? (
                                                                            <Link
                                                                                href={`/admin/calidad/inspecciones/${rechazada.id}/reinspeccionar`}
                                                                                className="btn btn-xs btn-primary"
                                                                            >
                                                                                Reinspeccionar
                                                                            </Link>
                                                                        ) : (
                                                                            p.qr && (
                                                                                <Boton
                                                                                    tono="claro"
                                                                                    className="btn-xs"
                                                                                    onClick={() => onCapturar(p.qr as string)}
                                                                                >
                                                                                    Capturar
                                                                                </Boton>
                                                                            )
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            );
                                                        })}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </Tarjeta>
    );
}
