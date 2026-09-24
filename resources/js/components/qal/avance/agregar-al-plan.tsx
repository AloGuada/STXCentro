import { router } from '@inertiajs/react';
import { CheckIcon, ExternalLinkIcon, PlusIcon, ScanLineIcon, SearchIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { LectorQr, lectorDisponible } from '@/components/qal/captura/lector-qr';
import { SearchSelect } from '@/components/ui/search-select';
import { Select, SelectItem } from '@/components/ui/select';
import type { Fase, GrupoOpcion, ObraOpcion } from './tipos';

type MarcaOpcion = { id: number; marca: string; lote: string | null; piezas: number };
type EstadoPieza = 'libre' | 'en_plan' | 'pendiente' | 'fabricada';
type PiezaOpcion = { id: number; qr: string; qs: string | null; estado: EstadoPieza };
type PiezaEncontrada = {
    obra_id: number;
    marca_id: number;
    marca: string;
    lote: string | null;
    pieza_id: number;
    qr: string;
    qs: string | null;
};
type Aviso = { tono: 'ok' | 'error'; texto: string };

type Respuesta<T> = { ok: true; datos: T } | { ok: false; mensaje: string };

async function pedir<T>(url: string): Promise<Respuesta<T>> {
    try {
        const respuesta = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const cuerpo = await respuesta.json().catch(() => null);

        if (!respuesta.ok) {
            const candidatas: string[] = cuerpo?.candidatas ?? [];

            return {
                ok: false,
                mensaje: (cuerpo?.message ?? 'No se pudo consultar.') + (candidatas.length ? ` (${candidatas.join(' · ')})` : ''),
            };
        }

        return { ok: true, datos: cuerpo as T };
    } catch {
        return { ok: false, mensaje: 'No se pudo consultar: revisa la conexión.' };
    }
}

const nombreDe = (obra: ObraOpcion | undefined) => (obra ? [obra.no, obra.descripcion].filter(Boolean).join(' — ') : '');

/** Lo que se lee en la ficha de una pieza que no se puede elegir. */
const MOTIVO: Record<'en_plan' | 'fabricada', string> = {
    en_plan: 'ya en el plan',
    fabricada: 'ya hecha',
};


const elegible = (pieza: PiezaOpcion) => pieza.estado !== 'en_plan' && pieza.estado !== 'fabricada';

/**
 * Agregar piezas al plan de la semana, en el orden en que se piensa: de qué
 * lote y qué marca, cuáles piezas, y qué grupo de trabajo las hace en qué
 * módulo.
 *
 * Las piezas se eligen como en el destajo —fichas con su QR, todas las que
 * faltan o las primeras N— o de una en una escribiendo o escaneando el QR, que
 * rellena el lote y la marca y deja la pieza marcada. Agregar ya guarda; el
 * grupo y el módulo se quedan puestos para la siguiente tanda.
 *
 * Si el QR es de otra obra no se mete en este plan: se ofrece abrir la suya.
 */
export function AgregarAlPlan({
    obras,
    obraId,
    fase,
    semana,
    grupos,
    version,
    onAbrirObra,
}: {
    obras: ObraOpcion[];
    /** La obra del plan que se está escribiendo. */
    obraId: number;
    fase: Fase;
    semana: string;
    grupos: GrupoOpcion[];
    /** Cambia cuando cambia el plan: las fichas se vuelven a pedir con su estado al día. */
    version: string;
    onAbrirObra: (obraId: number) => void;
}) {
    const [marcas, setMarcas] = useState<MarcaOpcion[]>([]);
    const [lote, setLote] = useState('');
    const [marca, setMarca] = useState('');
    const [piezas, setPiezas] = useState<PiezaOpcion[]>([]);
    const [cargando, setCargando] = useState(false);
    const [marcadas, setMarcadas] = useState<number[]>([]);
    const [cuantas, setCuantas] = useState('');
    const [grupo, setGrupo] = useState('');
    const [modulo, setModulo] = useState('');
    const [codigo, setCodigo] = useState('');
    const [buscando, setBuscando] = useState(false);
    const [leyendo, setLeyendo] = useState(false);
    const [guardando, setGuardando] = useState(false);
    const [aviso, setAviso] = useState<Aviso | null>(null);
    const [otraObra, setOtraObra] = useState<number | null>(null);

    // Las marcas de la obra del plan, al abrirlo.
    useEffect(() => {
        let vigente = true;

        pedir<MarcaOpcion[]>(`/admin/calidad/avance/marcas?obra=${obraId}`).then((r) => {
            if (vigente && r.ok) {
                setMarcas(r.datos);
            }
        });

        return () => {
            vigente = false;
        };
    }, [obraId]);

    const lotes = useMemo(
        () =>
            [...new Set(marcas.map((m) => m.lote).filter((l): l is string => !!l))].sort((a, b) =>
                a.localeCompare(b, 'es', { numeric: true }),
            ),
        [marcas],
    );
    const marcasDelLote = lote ? marcas.filter((m) => m.lote === lote) : marcas;
    const libres = piezas.filter(elegible);

    const cargarPiezas = async (id: string): Promise<PiezaOpcion[]> => {
        setPiezas([]);
        setMarcadas([]);
        setCuantas('');

        if (!id) {
            return [];
        }

        setCargando(true);
        const r = await pedir<PiezaOpcion[]>(`/admin/calidad/avance/piezas?marca=${id}&fase=${fase}&semana=${semana}`);
        setCargando(false);

        if (!r.ok) {
            setAviso({ tono: 'error', texto: r.mensaje });

            return [];
        }

        setPiezas(r.datos);

        return r.datos;
    };

    // El plan cambió —se agregó o se quitó una pieza—: las
    // fichas de la marca abierta se piden otra vez.
    useEffect(() => {
        if (marca) {
            void cargarPiezas(marca);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [version]);

    const elegirLote = (valor: string) => {
        setLote(valor);
        setMarca('');
        setAviso(null);
        setOtraObra(null);
        void cargarPiezas('');
    };

    const elegirMarca = (id: string) => {
        setMarca(id);
        setAviso(null);
        setOtraObra(null);
        void cargarPiezas(id);
    };

    const alternar = (id: number) => setMarcadas((actual) => (actual.includes(id) ? actual.filter((x) => x !== id) : [...actual, id]));

    const marcarPrimeras = (texto: string) => {
        setCuantas(texto);
        const n = Math.max(0, Math.min(libres.length, Math.floor(Number(texto)) || 0));
        setMarcadas(libres.slice(0, n).map((p) => p.id));
    };

    const buscar = async (texto: string) => {
        const buscado = texto.trim();

        if (!buscado) {
            return;
        }

        setBuscando(true);
        setAviso(null);
        setOtraObra(null);
        const r = await pedir<PiezaEncontrada>(`/admin/calidad/avance/pieza?codigo=${encodeURIComponent(buscado)}`);

        if (!r.ok) {
            setBuscando(false);
            setAviso({ tono: 'error', texto: r.mensaje });

            return;
        }

        const encontrada = r.datos;
        const etiqueta = `QR ${encontrada.qr}${encontrada.qs ? ` · QS ${encontrada.qs}` : ''} · ${encontrada.marca}`;

        if (encontrada.obra_id !== obraId) {
            const suObra = obras.find((o) => o.id === encontrada.obra_id);

            setBuscando(false);
            setOtraObra(suObra ? suObra.id : null);
            setAviso({
                tono: 'error',
                texto: `${etiqueta} — ${suObra ? `es de ${nombreDe(suObra)}, no de este plan.` : 'es de una obra que no está dada de alta en Calidad.'}`,
            });

            return;
        }

        setLote(encontrada.lote ?? '');
        setMarca(String(encontrada.marca_id));
        const suyas = await cargarPiezas(String(encontrada.marca_id));
        const pieza = suyas.find((p) => p.id === encontrada.pieza_id);
        setBuscando(false);
        setCodigo('');

        if (pieza && elegible(pieza)) {
            setMarcadas([pieza.id]);
            setAviso({ tono: 'ok', texto: `${etiqueta}: marcada. Elige el grupo y agrégala.` });
        } else {
            setAviso({ tono: 'error', texto: `${etiqueta}: ${pieza ? MOTIVO[pieza.estado as keyof typeof MOTIVO] : 'no se puede programar'}.` });
        }
    };

    const listo = marcadas.length > 0 && !!grupo && !guardando;

    const agregar = () => {
        if (!listo) {
            return;
        }

        setGuardando(true);
        setAviso(null);
        router.post(
            '/admin/calidad/avance/plan',
            {
                obra_id: obraId,
                fase,
                semana,
                piezas: marcadas,
                grupo_trabajo_id: Number(grupo),
                modulo: modulo.trim() || null,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errores) => setAviso({ tono: 'error', texto: Object.values(errores).join(' ') }),
                onFinish: () => setGuardando(false),
            },
        );
    };

    return (
        <div className="space-y-4 p-4">
            {/* 1 · qué pieza: lote y marca, o el QR directo */}
            <div className="grid gap-3 lg:grid-cols-[12rem_1fr_1fr]">
                <label className="text-xs">
                    <span className="text-base-content/60 mb-1 block">Lote</span>
                    <Select value={lote} onValueChange={elegirLote} className="select-sm w-full">
                        <SelectItem value="">Todos los lotes</SelectItem>
                        {lotes.map((l) => (
                            <SelectItem key={l} value={l}>
                                {l}
                            </SelectItem>
                        ))}
                    </Select>
                </label>
                <label className="text-xs">
                    <span className="text-base-content/60 mb-1 block">Marca</span>
                    <SearchSelect
                        options={marcasDelLote.map((m) => ({
                            value: String(m.id),
                            label: `${m.marca}${m.lote ? ` · ${m.lote}` : ''} · ${m.piezas} pz`,
                        }))}
                        value={marca}
                        onValueChange={elegirMarca}
                        placeholder="Buscar la marca…"
                        inputClassName="input-sm"
                        maxOptions={50}
                    />
                </label>
                <div className="text-xs">
                    <span className="text-base-content/60 mb-1 block">…o la pieza por su QR</span>
                    <form
                        className="join w-full"
                        onSubmit={(e) => {
                            e.preventDefault();
                            void buscar(codigo);
                        }}
                    >
                        <input
                            className="input input-sm input-bordered join-item min-w-0 flex-1 font-mono"
                            placeholder="QR o QS"
                            value={codigo}
                            onChange={(e) => setCodigo(e.target.value)}
                        />
                        <button type="submit" className="btn btn-sm join-item" disabled={buscando || !codigo.trim()} title="Buscar">
                            <SearchIcon className="size-4" />
                        </button>
                        {lectorDisponible() && (
                            <button type="button" className="btn btn-sm join-item" onClick={() => setLeyendo(true)} title="Escanear QR">
                                <ScanLineIcon className="size-4" />
                            </button>
                        )}
                    </form>
                </div>
            </div>

            {/* 2 · cuáles piezas de la marca */}
            {marca && (
                <div className="space-y-2">
                    <div className="flex flex-wrap items-center gap-2 text-xs">
                        <span className="text-base-content/60">
                            {cargando ? 'Cargando piezas…' : `${libres.length} por programar de ${piezas.length}`}
                        </span>
                        {libres.length > 0 && (
                            <>
                                <button type="button" className="btn btn-xs btn-ghost" onClick={() => setMarcadas(libres.map((p) => p.id))}>
                                    Seleccionar las que faltan
                                </button>
                                <label className="flex items-center gap-1">
                                    Marcar
                                    <input
                                        type="number"
                                        min={0}
                                        max={libres.length}
                                        value={cuantas}
                                        onChange={(e) => marcarPrimeras(e.target.value)}
                                        className="input input-xs input-bordered w-16 text-right"
                                    />
                                    de {libres.length}
                                </label>
                                {marcadas.length > 0 && (
                                    <button type="button" className="btn btn-xs btn-ghost" onClick={() => marcarPrimeras('')}>
                                        Quitar la selección
                                    </button>
                                )}
                            </>
                        )}
                    </div>

                    {piezas.length > 0 && (
                        <div className="border-base-300 flex max-h-44 flex-wrap gap-1.5 overflow-y-auto rounded-lg border p-2">
                            {piezas.map((p) => {
                                const marcada = marcadas.includes(p.id);
                                const puede = elegible(p);

                                return (
                                    <button
                                        key={p.id}
                                        type="button"
                                        aria-pressed={marcada}
                                        disabled={!puede}
                                        onClick={() => alternar(p.id)}
                                        title={puede ? (p.estado === 'pendiente' ? 'Pendiente de una semana anterior' : undefined) : MOTIVO[p.estado as keyof typeof MOTIVO]}
                                        className={`btn btn-xs font-mono ${
                                            !puede
                                                ? 'btn-ghost line-through'
                                                : marcada
                                                  ? 'btn-primary'
                                                  : p.estado !== 'libre'
                                                    ? 'btn-outline btn-info'
                                                    : 'btn-outline'
                                        }`}
                                    >
                                        {marcada && <CheckIcon className="size-3" />}
                                        {p.qr}
                                        {p.qs ? <span className="opacity-60">· QS {p.qs}</span> : null}
                                        {!puede && <span className="no-underline opacity-70">({MOTIVO[p.estado as keyof typeof MOTIVO]})</span>}
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}

            {/* 3 · quién las hace y dónde */}
            <div className="flex flex-wrap items-end gap-3">
                <label className="text-xs">
                    <span className="text-base-content/60 mb-1 block">Grupo de trabajo</span>
                    <Select value={grupo} onValueChange={setGrupo} className="select-sm w-56" placeholder="Elige el grupo">
                        {grupos.map((g) => (
                            <SelectItem key={g.id} value={String(g.id)}>
                                {g.descripcion}
                            </SelectItem>
                        ))}
                    </Select>
                </label>
                <label className="text-xs">
                    <span className="text-base-content/60 mb-1 block">
                        Módulo <span className="text-base-content/40">· ej. 1.2</span>
                    </span>
                    <input
                        value={modulo}
                        onChange={(e) => setModulo(e.target.value)}
                        maxLength={50}
                        className="input input-sm input-bordered w-32 font-mono"
                        placeholder="1.2"
                    />
                </label>

                <button type="button" className="btn btn-sm btn-primary" disabled={!listo} onClick={agregar}>
                    <PlusIcon className="size-4" />
                    {guardando ? 'Agregando…' : marcadas.length > 0 ? `Agregar ${marcadas.length} pieza(s)` : 'Agregar al plan'}
                </button>

                {otraObra !== null && (
                    <button type="button" className="btn btn-sm btn-outline" onClick={() => onAbrirObra(otraObra)}>
                        <ExternalLinkIcon className="size-4" />
                        Abrir el plan de esa obra
                    </button>
                )}
            </div>

            {aviso && <p className={`text-xs ${aviso.tono === 'error' ? 'text-error' : 'text-success'}`}>{aviso.texto}</p>}

            {leyendo && (
                <LectorQr
                    onLeido={(leido) => {
                        setLeyendo(false);
                        setCodigo(leido);
                        void buscar(leido);
                    }}
                    onCerrar={() => setLeyendo(false)}
                />
            )}
        </div>
    );
}
