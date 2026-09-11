import { ExternalLinkIcon, PlusIcon, ScanLineIcon, SearchIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { LectorQr, lectorDisponible } from '@/components/qal/captura/lector-qr';
import { Select, SelectItem } from '@/components/ui/select';
import type { ObraOpcion } from './tipos';

type MarcaOpcion = { id: number; marca: string; lote: string | null; piezas: number };
type PiezaOpcion = { id: number; qr: string; qs: string | null };
type PiezaEncontrada = { obra_id: number; marca_id: number; marca: string; pieza_id: number; qr: string; qs: string | null };
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

/**
 * Agregar piezas al plan sin pegar texto: obra, marca y QR.
 *
 * Se elige en cascada —la obra trae sus marcas, la marca sus piezas— o se
 * escribe (o escanea) el QR y el formulario se rellena solo. Cada pieza que se
 * agrega suma una a su marca en la caja del plan: el plan cuenta piezas por
 * marca, así que el QR sirve para encontrarla, no se guarda. Un mismo QR no se
 * agrega dos veces.
 *
 * Si el QR es de otra obra no se mete en este plan: se ofrece abrir la suya.
 */
export function AgregarAlPlan({
    obras,
    obraId,
    onAgregar,
    onAbrirObra,
}: {
    obras: ObraOpcion[];
    /** La obra del plan que se está escribiendo. */
    obraId: number;
    onAgregar: (marca: string) => void;
    onAbrirObra: (obraId: number) => void;
}) {
    const [obra, setObra] = useState(String(obraId));
    const [marcas, setMarcas] = useState<MarcaOpcion[]>([]);
    const [marca, setMarca] = useState('');
    const [piezas, setPiezas] = useState<PiezaOpcion[]>([]);
    const [pieza, setPieza] = useState('');
    const [codigo, setCodigo] = useState('');
    const [buscando, setBuscando] = useState(false);
    const [leyendo, setLeyendo] = useState(false);
    const [aviso, setAviso] = useState<Aviso | null>(null);
    const [agregados, setAgregados] = useState<Set<string>>(() => new Set());

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

    const cargarMarcas = async (id: string): Promise<void> => {
        setMarcas([]);

        if (!id) {
            return;
        }

        const r = await pedir<MarcaOpcion[]>(`/admin/calidad/avance/marcas?obra=${id}`);

        if (r.ok) {
            setMarcas(r.datos);
        } else {
            setAviso({ tono: 'error', texto: r.mensaje });
        }
    };

    const cargarPiezas = async (id: string): Promise<void> => {
        setPiezas([]);

        if (!id) {
            return;
        }

        const r = await pedir<PiezaOpcion[]>(`/admin/calidad/avance/piezas?marca=${id}`);

        if (r.ok) {
            setPiezas(r.datos);
        } else {
            setAviso({ tono: 'error', texto: r.mensaje });
        }
    };

    const elegirObra = (id: string) => {
        setObra(id);
        setMarca('');
        setPieza('');
        setPiezas([]);
        setAviso(null);
        void cargarMarcas(id);
    };

    const elegirMarca = (id: string) => {
        setMarca(id);
        setPieza('');
        setAviso(null);
        void cargarPiezas(id);
    };

    const buscar = async (texto: string) => {
        const buscado = texto.trim();

        if (!buscado) {
            return;
        }

        setBuscando(true);
        setAviso(null);
        const r = await pedir<PiezaEncontrada>(`/admin/calidad/avance/pieza?codigo=${encodeURIComponent(buscado)}`);

        if (!r.ok) {
            setBuscando(false);
            setAviso({ tono: 'error', texto: r.mensaje });

            return;
        }

        const encontrada = r.datos;
        const suObra = String(encontrada.obra_id);

        if (suObra !== obra) {
            setObra(suObra);
            await cargarMarcas(suObra);
        }

        setMarca(String(encontrada.marca_id));
        await cargarPiezas(String(encontrada.marca_id));
        setPieza(String(encontrada.pieza_id));
        setBuscando(false);

        const deOtraObra = encontrada.obra_id !== obraId;
        const conocida = obras.some((o) => o.id === encontrada.obra_id);

        setAviso({
            tono: deOtraObra ? 'error' : 'ok',
            texto: `QR ${encontrada.qr}${encontrada.qs ? ` · QS ${encontrada.qs}` : ''} · ${encontrada.marca}${
                deOtraObra
                    ? conocida
                        ? ` — es de ${nombreDe(obras.find((o) => o.id === encontrada.obra_id))}, no de este plan.`
                        : ' — es de una obra que no está dada de alta en Calidad.'
                    : ''
            }`,
        });
    };

    const marcaElegida = marcas.find((m) => String(m.id) === marca);
    const piezaElegida = piezas.find((p) => String(p.id) === pieza);
    const otraObra = obra !== String(obraId);
    const obraConocida = obras.some((o) => String(o.id) === obra);

    const agregar = () => {
        if (!marcaElegida || otraObra) {
            return;
        }

        if (piezaElegida && agregados.has(piezaElegida.qr)) {
            setAviso({ tono: 'error', texto: `El QR ${piezaElegida.qr} ya lo agregaste.` });

            return;
        }

        onAgregar(marcaElegida.marca);

        if (piezaElegida) {
            setAgregados((actual) => new Set(actual).add(piezaElegida.qr));
        }

        setAviso({
            tono: 'ok',
            texto: `Se sumó 1 pieza de ${marcaElegida.marca}${piezaElegida ? ` (QR ${piezaElegida.qr})` : ''}. Guarda el plan para que cuente.`,
        });
        setPieza('');
        setCodigo('');
    };

    return (
        <div className="rounded-box border-base-300 bg-base-200/40 mb-3 space-y-2 border p-3">
            <div className="text-base-content/70 text-xs font-semibold">Agregar al plan</div>

            <div className="grid gap-2 sm:grid-cols-3">
                <Select value={obra} onValueChange={elegirObra} className="select-sm" aria-label="Obra">
                    {!obraConocida && obra && <SelectItem value={obra}>Obra fuera de Calidad</SelectItem>}
                    {obras.map((o) => (
                        <SelectItem key={o.id} value={String(o.id)}>
                            {nombreDe(o)}
                        </SelectItem>
                    ))}
                </Select>
                <Select value={marca} onValueChange={elegirMarca} className="select-sm" aria-label="Marca" placeholder="Marca">
                    {marcas.map((m) => (
                        <SelectItem key={m.id} value={String(m.id)}>
                            {m.marca}
                            {m.piezas ? ` · ${m.piezas} pz` : ''}
                        </SelectItem>
                    ))}
                </Select>
                <Select value={pieza} onValueChange={setPieza} className="select-sm" aria-label="QR" disabled={!marca}>
                    <SelectItem value="">{marca ? 'Cualquier pieza de la marca' : 'QR'}</SelectItem>
                    {piezas.map((p) => (
                        <SelectItem key={p.id} value={String(p.id)}>
                            QR {p.qr}
                            {p.qs ? ` · QS ${p.qs}` : ''}
                            {agregados.has(p.qr) ? ' · agregada' : ''}
                        </SelectItem>
                    ))}
                </Select>
            </div>

            <div className="flex flex-wrap gap-2">
                <form
                    className="join min-w-0 flex-1"
                    onSubmit={(e) => {
                        e.preventDefault();
                        void buscar(codigo);
                    }}
                >
                    <input
                        className="input input-sm input-bordered join-item min-w-0 flex-1 font-mono"
                        placeholder="…o escribe el QR (o el QS) y se rellena lo demás"
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

                {otraObra ? (
                    obraConocida && (
                        <button type="button" className="btn btn-sm btn-outline" onClick={() => onAbrirObra(Number(obra))}>
                            <ExternalLinkIcon className="size-4" />
                            Abrir el plan de esa obra
                        </button>
                    )
                ) : (
                    <button type="button" className="btn btn-sm btn-primary" disabled={!marcaElegida} onClick={agregar}>
                        <PlusIcon className="size-4" />
                        Agregar
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
