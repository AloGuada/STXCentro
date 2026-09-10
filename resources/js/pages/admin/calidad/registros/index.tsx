/**
 * Registros — la base de datos de lo capturado, en crudo.
 *
 * Es el `Registros_Steelex.html` de la aplicación anterior, portado al mono. No
 * es un reporte: es la tabla de auditoría. Cuando alguien pregunta «¿esta pieza
 * se inspeccionó?, ¿quién la liberó?, ¿por qué se rechazó en julio?», se
 * responde aquí, y por eso la pantalla no resume nada —los resúmenes están en
 * el tablero y en el reporte semanal—.
 *
 * Dos conjuntos distintos comparten la pantalla: **piezas** (una unidad
 * concreta, revisada al 100%) y **lotes de accesorios** (cientos de piezas
 * iguales, aceptadas o rechazadas por muestreo). No se mezclan en una sola
 * tabla porque no comparten ni columnas ni unidad de conteo; se cambia de
 * conjunto con el primer control de la barra.
 *
 * Lo que se conserva del original porque es criterio, no adorno:
 *
 *  - **Registro ≠ pieza.** Una pieza reinspeccionada tres veces son tres
 *    registros y una sola pieza. El contador dice las dos cosas: llamarlos
 *    «piezas» hacía creer que se había inspeccionado el triple de lo real.
 *  - **La última inspección del sublote manda.** Un grupo con dos inspecciones
 *    cuenta una vez, con el veredicto de la más reciente.
 *  - **Un lote rechazado sin disposición se señala en rojo.** Es material
 *    detenido que nadie decidió qué hacer, y en una tabla en blanco no se ve.
 *
 * Lo que se corrige del original:
 *
 *  - El filtro de fecha se ocultaba al pasar a accesorios pero **seguía
 *    aplicándose**: si venías de filtrar un día en piezas, el listado de lotes
 *    salía recortado sin decir por qué. Aquí sólo se esconden Transformación y
 *    Estatus, que de verdad no existen en un sublote, y la fecha se queda
 *    visible porque sí filtra.
 *  - La ficha abría un registro suelto. Ahora trae el historial de la pieza
 *    (RF-18.4), que es lo que explica un rechazo.
 *
 * Todavía no lee de la base: `qal_inspecciones` y las tablas de accesorios no
 * existen. Las filas salen de `components/qal/registros/datos.ts` y la pantalla
 * lo dice en su encabezado — una pantalla de auditoría que no distinga lo real
 * de lo de ejemplo es peor que no tenerla.
 */

import { Head } from '@inertiajs/react';
import { DownloadIcon, EyeIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { OBRAS } from '@/components/qal/captura/datos';
import {
    ESTATUS,
    FASES,
    INSPECTORES,
    clavePieza,
    registrosDeEjemplo,
    sinDisposicion,
    subloteLiberado,
    sublotesDeEjemplo,
    type Estatus,
    type RegistroPieza,
    type RegistroSublote,
} from '@/components/qal/registros/datos';
import { FichaPieza, FichaSublote } from '@/components/qal/registros/ficha';
import { Filtro, FiltroSelect, PastillaEstatus, Tabla, Th, type Orden } from '@/components/qal/registros/ui';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calidad', href: '/admin/calidad/catalogos' },
    { title: 'Registros', href: '/admin/calidad/registros' },
];

type Que = 'pza' | 'acc';

type Filtros = {
    fase: string;
    obra: string;
    inspector: string;
    estatus: string;
    fecha: string;
    buscar: string;
};

const FILTROS_VACIOS: Filtros = { fase: '', obra: '', inspector: '', estatus: '', fecha: '', buscar: '' };

/** Compara dos valores para el orden del cliente, con los vacíos al final. */
function comparar(a: unknown, b: unknown): number {
    const x = a ?? '';
    const y = b ?? '';
    if (typeof x === 'number' && typeof y === 'number') {
        return x - y;
    }
    return String(x).localeCompare(String(y), 'es', { numeric: true });
}

function ordenar<T extends Record<string, unknown>>(filas: T[], orden: Orden): T[] {
    return [...filas].sort((a, b) => comparar(a[orden.campo], b[orden.campo]) * orden.dir);
}

/**
 * La marca de orden de bytes que abre el CSV.
 *
 * Va a proposito y se escribe por su codigo, no como caracter: un BOM literal en
 * el fuente es invisible y el linter lo rechaza con razon. Sin el, Excel abre el
 * archivo en ANSI y parte todos los acentos, y la base se exporta para revisarla
 * en Excel, no en un editor de texto.
 */
const BOM = String.fromCharCode(0xfeff);

/** Escapa un valor para CSV: comillas dobles y separador dentro del campo. */
function celdaCsv(valor: unknown): string {
    const texto = valor === null || valor === undefined ? '' : String(valor);
    return /[",\n]/.test(texto) ? `"${texto.replace(/"/g, '""')}"` : texto;
}

function descargarCsv(nombre: string, encabezados: string[], filas: unknown[][]): void {
    const cuerpo = [encabezados, ...filas].map((fila) => fila.map(celdaCsv).join(',')).join('\n');
    // El BOM va a propósito: sin él, Excel abre el CSV en ANSI y parte todos los
    // acentos. La base se exporta para revisarla en Excel, no en un editor.
    const blob = new Blob([BOM + cuerpo], { type: 'text/csv;charset=utf-8;' });
    const enlace = document.createElement('a');
    enlace.href = URL.createObjectURL(blob);
    enlace.download = nombre;
    enlace.click();
    URL.revokeObjectURL(enlace.href);
}

export default function RegistrosCalidad() {
    const registros = useMemo(() => registrosDeEjemplo(), []);
    const sublotes = useMemo(() => sublotesDeEjemplo(), []);

    const [que, setQue] = useState<Que>('pza');
    const [filtros, setFiltros] = useState<Filtros>(FILTROS_VACIOS);
    const [orden, setOrden] = useState<Orden>({ campo: 'ts', dir: -1 });
    const [fichaPieza, setFichaPieza] = useState<RegistroPieza | null>(null);
    const [fichaSublote, setFichaSublote] = useState<RegistroSublote | null>(null);

    const esPiezas = que === 'pza';

    const cambiar = (campo: keyof Filtros, valor: string) => setFiltros((previos) => ({ ...previos, [campo]: valor }));

    const ordenarPor = (campo: string) =>
        setOrden((previo) => ({ campo, dir: previo.campo === campo ? ((previo.dir * -1) as 1 | -1) : 1 }));

    const buscado = filtros.buscar.trim().toUpperCase();

    const piezasFiltradas = useMemo(() => {
        const filas = registros.filter(
            (r) =>
                (!filtros.fase || r.fase === filtros.fase) &&
                (!filtros.obra || r.obra === filtros.obra) &&
                (!filtros.inspector || r.inspector === filtros.inspector) &&
                (!filtros.estatus || r.estatus === filtros.estatus) &&
                (!filtros.fecha || r.fecha === filtros.fecha) &&
                (!buscado || `${r.marca} ${r.folio}`.toUpperCase().includes(buscado)),
        );
        return ordenar(filas, orden);
    }, [registros, filtros, buscado, orden]);

    /**
     * Un grupo puede tener varias inspecciones: manda la última.
     *
     * Sin esto, un sublote reinspeccionado aparecería dos veces y el conteo de
     * lotes aceptados saldría inflado.
     */
    const sublotesFiltrados = useMemo(() => {
        const ultimos = new Map<string, RegistroSublote>();
        sublotes.forEach((x) => {
            const previo = ultimos.get(x.grupo);
            if (!previo || x.ninsp >= previo.ninsp) {
                ultimos.set(x.grupo, x);
            }
        });

        const filas = [...ultimos.values()].filter(
            (x) =>
                (!filtros.obra || x.obra === filtros.obra) &&
                (!filtros.inspector || x.inspector === filtros.inspector) &&
                (!filtros.fecha || x.fecha === filtros.fecha) &&
                (!buscado || `${x.marca} ${x.grupo}`.toUpperCase().includes(buscado)),
        );
        return ordenar(filas, orden);
    }, [sublotes, filtros, buscado, orden]);

    /** Registros ≠ piezas: el contador dice las dos cosas para no engañar. */
    const piezasDistintas = useMemo(
        () => new Set(piezasFiltradas.map(clavePieza)).size,
        [piezasFiltradas],
    );

    const conteo = esPiezas
        ? `${piezasFiltradas.length} de ${registros.length} registros · ${piezasDistintas} ${piezasDistintas === 1 ? 'pieza' : 'piezas'}`
        : `${sublotesFiltrados.length} inspecciones de sublote`;

    const exportar = () => {
        if (esPiezas) {
            descargarCsv(
                'calidad-registros-piezas.csv',
                ['Fecha', 'Semana', 'Fase', 'Sub-etapa', 'Obra', 'Marca', 'Folio', 'Tipo', 'Consec', 'Insp', 'Modulo', 'Linea', 'Inspector', 'Estatus', 'Kg'],
                piezasFiltradas.map((r) => [
                    r.fecha, r.semana, r.fase, r.p2_subetapa ?? r.p1_subtipo ?? '', r.obra, r.marca, r.folio,
                    r.tipo, r.consec, r.ninsp, r.modulo, r.linea, r.inspector, r.estatus, r.kg,
                ]),
            );
            return;
        }

        descargarCsv(
            'calidad-registros-accesorios.csv',
            ['Fecha', 'Obra', 'Marca del lote', 'Sublote', 'Unidades del plano', 'Unidades', 'Nivel', 'Muestra', 'Rechazadas', 'Insp', 'Veredicto', 'Disposicion', 'Inspector'],
            sublotesFiltrados.map((x) => [
                x.fecha, x.obra, x.marca, x.grupo, x.totalLote, x.unidades, x.nivel, x.muestra,
                x.rechazadas, x.ninsp, x.veredicto, x.disposicion, x.inspector,
            ]),
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calidad — Registros" />

            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Registros</h1>
                        <p className="text-base-content/60 text-sm">
                            Todo lo capturado, sin resumir. Para responder qué se inspeccionó, quién lo liberó y por qué
                            se rechazó.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <span className="badge badge-warning badge-sm font-semibold">Maqueta · datos de ejemplo</span>
                        {/* En la maqueta lo ve cualquiera. RF-18.3 lo reserva al
                            administrador: cuando esto lea de la base, el boton
                            va detras de `qal.registros.exportar` y la descarga
                            se arma en el servidor, no aqui. */}
                        <button type="button" onClick={exportar} className="btn btn-sm btn-outline">
                            <DownloadIcon className="size-4" />
                            Exportar CSV
                        </button>
                    </div>
                </div>

                <div className="border-base-300 bg-base-100 flex flex-wrap items-end gap-3 rounded-xl border p-4">
                    <Filtro label="Qué se lista" className="w-44">
                        <Select
                            value={que}
                            onValueChange={(valor) => {
                                setQue(valor as Que);
                                // La fase y el estatus no existen en un sublote: se
                                // limpian al cambiar para que no queden filtrando a
                                // escondidas al volver a piezas.
                                setFiltros((previos) => ({ ...previos, fase: '', estatus: '' }));
                                setOrden({ campo: 'ts', dir: -1 });
                            }}
                            className="select-sm"
                        >
                            <SelectItem value="pza">Piezas</SelectItem>
                            <SelectItem value="acc">Lotes de accesorios</SelectItem>
                        </Select>
                    </Filtro>

                    {esPiezas && (
                        <FiltroSelect
                            label="Transformación"
                            value={filtros.fase}
                            onChange={(valor) => cambiar('fase', valor)}
                            opciones={FASES}
                            todas="Todas"
                            className="w-36"
                        />
                    )}

                    <FiltroSelect
                        label="Obra"
                        value={filtros.obra}
                        onChange={(valor) => cambiar('obra', valor)}
                        opciones={OBRAS}
                        todas="Todas"
                        className="w-56"
                    />

                    <FiltroSelect
                        label="Inspector"
                        value={filtros.inspector}
                        onChange={(valor) => cambiar('inspector', valor)}
                        opciones={INSPECTORES}
                        todas="Todos"
                        className="w-44"
                    />

                    {esPiezas && (
                        <FiltroSelect
                            label="Estatus"
                            value={filtros.estatus}
                            onChange={(valor) => cambiar('estatus', valor)}
                            opciones={ESTATUS}
                            todas="Todos"
                            className="w-40"
                        />
                    )}

                    <Filtro label="Fecha" className="w-40">
                        <Input
                            type="date"
                            value={filtros.fecha}
                            onChange={(e) => cambiar('fecha', e.target.value)}
                            className="input-sm"
                        />
                    </Filtro>

                    <Filtro label={esPiezas ? 'Buscar marca / folio' : 'Buscar marca / sublote'} className="w-56">
                        <Input
                            value={filtros.buscar}
                            onChange={(e) => cambiar('buscar', e.target.value)}
                            placeholder={esPiezas ? 'ej. CM22' : 'ej. ACC-PL12'}
                            className="input-sm"
                        />
                    </Filtro>

                    <div className="ml-auto flex items-center gap-2">
                        <span className="bg-base-200 text-base-content/70 rounded-full px-3 py-1.5 text-sm">
                            {conteo}
                        </span>
                        <button
                            type="button"
                            onClick={() => setFiltros(FILTROS_VACIOS)}
                            className="btn btn-sm btn-ghost"
                        >
                            Limpiar
                        </button>
                    </div>
                </div>

                {esPiezas ? (
                    <TablaPiezas
                        filas={piezasFiltradas}
                        orden={orden}
                        onOrdenar={ordenarPor}
                        onVer={setFichaPieza}
                    />
                ) : (
                    <TablaSublotes
                        filas={sublotesFiltrados}
                        orden={orden}
                        onOrdenar={ordenarPor}
                        onVer={setFichaSublote}
                    />
                )}
            </div>

            <FichaPieza
                registro={fichaPieza}
                registros={registros}
                onIr={setFichaPieza}
                onCerrar={() => setFichaPieza(null)}
            />
            <FichaSublote registro={fichaSublote} onCerrar={() => setFichaSublote(null)} />
        </AppLayout>
    );
}

function BotonFicha({ onClick }: { onClick: () => void }) {
    return (
        <button type="button" onClick={onClick} className="btn btn-ghost btn-xs" aria-label="Ver ficha">
            <EyeIcon className="size-4" />
        </button>
    );
}

function TablaPiezas({
    filas,
    orden,
    onOrdenar,
    onVer,
}: {
    filas: RegistroPieza[];
    orden: Orden;
    onOrdenar: (campo: string) => void;
    onVer: (registro: RegistroPieza) => void;
}) {
    return (
        <Tabla vacio={filas.length ? null : 'Ningún registro coincide con los filtros.'}>
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
                    <Th campo="consec" orden={orden} onOrdenar={onOrdenar}>
                        Consec.
                    </Th>
                    <Th campo="ninsp" orden={orden} onOrdenar={onOrdenar}>
                        Insp.
                    </Th>
                    <Th campo="obra" orden={orden} onOrdenar={onOrdenar}>
                        Obra
                    </Th>
                    <Th campo="modulo" orden={orden} onOrdenar={onOrdenar}>
                        Módulo
                    </Th>
                    <Th campo="inspector" orden={orden} onOrdenar={onOrdenar}>
                        Inspector
                    </Th>
                    <Th campo="estatus" orden={orden} onOrdenar={onOrdenar}>
                        Estatus
                    </Th>
                    <Th className="w-12" />
                </tr>
            </thead>
            <tbody>
                {filas.map((r) => {
                    const sub = r.p2_subetapa ?? r.p1_subtipo;

                    return (
                        <tr key={r.id} className="hover:bg-base-200/50">
                            <td className="font-mono text-sm">{r.fecha}</td>
                            <td>
                                {r.fase}
                                {sub && <span className="text-base-content/50 ml-1 text-xs">· {sub}</span>}
                            </td>
                            <td>
                                <span className="font-semibold">{r.marca || r.folio || '—'}</span>
                                {r.marca && r.folio && (
                                    <div className="text-base-content/50 font-mono text-xs">{r.folio}</div>
                                )}
                            </td>
                            <td className="font-mono">{r.consec}</td>
                            <td className="font-mono">
                                {/* Una segunda inspección significa que la pieza se
                                    rechazó antes: se marca para que se note sin
                                    tener que abrir la ficha. */}
                                {r.ninsp > 1 ? (
                                    <span className="badge badge-sm badge-warning">#{r.ninsp}</span>
                                ) : (
                                    r.ninsp
                                )}
                            </td>
                            <td className="text-sm">{r.obra}</td>
                            <td className="font-mono text-sm">{r.modulo}</td>
                            <td className="text-sm">{r.inspector}</td>
                            <td>
                                <PastillaEstatus estatus={r.estatus as Estatus} />
                            </td>
                            <td className="text-right">
                                <BotonFicha onClick={() => onVer(r)} />
                            </td>
                        </tr>
                    );
                })}
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
    filas: RegistroSublote[];
    orden: Orden;
    onOrdenar: (campo: string) => void;
    onVer: (registro: RegistroSublote) => void;
}) {
    return (
        <Tabla vacio={filas.length ? null : 'Ninguna inspección de sublote coincide con los filtros.'}>
            <thead>
                <tr>
                    <Th campo="fecha" orden={orden} onOrdenar={onOrdenar}>
                        Fecha
                    </Th>
                    <Th campo="marca" orden={orden} onOrdenar={onOrdenar}>
                        Marca del lote
                    </Th>
                    <Th campo="grupo" orden={orden} onOrdenar={onOrdenar}>
                        Sublote
                    </Th>
                    <Th campo="obra" orden={orden} onOrdenar={onOrdenar}>
                        Obra
                    </Th>
                    <Th campo="unidades" orden={orden} onOrdenar={onOrdenar}>
                        Unidades
                    </Th>
                    <Th campo="muestra" orden={orden} onOrdenar={onOrdenar}>
                        Muestra
                    </Th>
                    <Th campo="rechazadas" orden={orden} onOrdenar={onOrdenar}>
                        Rech.
                    </Th>
                    <Th campo="veredicto" orden={orden} onOrdenar={onOrdenar}>
                        Veredicto
                    </Th>
                    <Th campo="disposicion" orden={orden} onOrdenar={onOrdenar}>
                        Disposición
                    </Th>
                    <Th campo="inspector" orden={orden} onOrdenar={onOrdenar}>
                        Inspector
                    </Th>
                    <Th className="w-12" />
                </tr>
            </thead>
            <tbody>
                {filas.map((x) => (
                    <tr key={x.id} className="hover:bg-base-200/50">
                        <td className="font-mono text-sm">{x.fecha}</td>
                        <td className="font-semibold">{x.marca}</td>
                        <td className="font-mono text-sm">{x.grupo}</td>
                        <td className="text-sm">{x.obra}</td>
                        <td className="font-mono">
                            {x.unidades}
                            <span className="text-base-content/50 text-xs"> / {x.totalLote}</span>
                        </td>
                        <td className="font-mono">
                            {x.muestra}
                            <span className="text-base-content/50 text-xs"> · {x.nivel}</span>
                        </td>
                        <td className="font-mono">
                            {x.rechazadas > 0 ? (
                                <span className="text-error font-bold">{x.rechazadas}</span>
                            ) : (
                                x.rechazadas
                            )}
                        </td>
                        <td>
                            <span
                                className={`rounded-full px-2.5 py-0.5 text-xs font-bold ${
                                    subloteLiberado(x) ? 'bg-success/15 text-success' : 'bg-error/15 text-error'
                                }`}
                            >
                                {x.veredicto}
                            </span>
                        </td>
                        <td className="text-sm">
                            {sinDisposicion(x) ? (
                                <span className="text-error text-xs font-semibold">sin decidir</span>
                            ) : (
                                x.disposicion || <span className="text-base-content/40">—</span>
                            )}
                        </td>
                        <td className="text-sm">{x.inspector}</td>
                        <td className="text-right">
                            <BotonFicha onClick={() => onVer(x)} />
                        </td>
                    </tr>
                ))}
            </tbody>
        </Tabla>
    );
}
