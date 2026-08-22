import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/components/ui/formatted-date';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import { Select, SelectItem } from '@/components/ui/select';
import { RevisarImportacionModal } from '@/components/prod/revisar-importacion-modal';
import { etiquetaDePieza, etiquetaDeUnidad } from '@/lib/prod/piezas';
import type {
    Concepto,
    Obra,
    ProdDestajo,
    ProdGrupoTrabajo,
    ProdPieza,
    ProdPlanImportacion,
    ProdProceso,
} from '@/types/models';
import { useForm, usePage } from '@inertiajs/react';
import { ListChecksIcon, Loader2Icon, PlusIcon, UploadIcon } from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';

type MarcaDelCatalogo = Concepto & { obra?: Obra };

/** Lo que devuelve el endpoint de piezas: el QR y cuánto le falta por proceso. */
type PiezaConAvance = Pick<ProdPieza, 'id' | 'qr' | 'qs'> & {
    avance: Record<number, { capturado: number; disponible: number }>;
};

type Props = {
    destajo: ProdDestajo;
    /** Sólo las obras con catálogo vigente: el primer escalón de la captura. */
    obras: Obra[];
    procesos: ProdProceso[];
    /** Qué procesos paga cada obra: obraId => ids de proceso. */
    procesosPorObra: Record<number, number[]>;
    gruposTrabajo: ProdGrupoTrabajo[];
};

export function CapturarProduccion({
    destajo,
    obras,
    procesos,
    procesosPorObra,
    gruposTrabajo,
}: Props) {
    const soloFecha = (v: string) => v.slice(0, 10);

    const registroForm = useForm<{
        fecha: string;
        marca_id: string;
        proceso_id: string;
        piezas: number[];
        grupo_trabajo_id: string;
        porcentaje: number;
    }>({
        fecha: soloFecha(destajo.fecha_inicio),
        marca_id: '',
        proceso_id: '',
        piezas: [],
        grupo_trabajo_id: '',
        porcentaje: 100,
    });

    const csvForm = useForm<{ csv_file: File | null; fecha: string }>({
        csv_file: null,
        fecha: soloFecha(destajo.fecha_inicio),
    });

    // El import va en dos pasos: primero se analiza el archivo y se enseña el
    // plan, y sólo al confirmar se escribe. El plan se tira en cuanto cambia el
    // archivo o la fecha, para no confirmar nunca uno que ya no corresponde.
    const [plan, setPlan] = useState<ProdPlanImportacion | null>(null);
    const [analizando, setAnalizando] = useState(false);
    const [errorPlan, setErrorPlan] = useState<string | null>(null);
    const [modalAbierto, setModalAbierto] = useState(false);

    const flash = usePage<{ flash?: { success?: string } }>().props.flash;

    const olvidarPlan = () => {
        setPlan(null);
        setErrorPlan(null);
    };

    const analizar = async (e: FormEvent) => {
        e.preventDefault();

        if (!csvForm.data.csv_file) {
            return;
        }

        setModalAbierto(true);
        setAnalizando(true);
        setErrorPlan(null);

        const cuerpo = new FormData();
        cuerpo.append('csv_file', csvForm.data.csv_file);
        cuerpo.append('fecha', csvForm.data.fecha);

        // La app Inertia no expone un meta csrf-token: el token va en
        // X-XSRF-TOKEN leído de la cookie, o Laravel responde 419.
        const cookie = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

        try {
            const respuesta = await fetch(`/admin/prod/destajos/${destajo.id}/registros/analizar-csv`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie[1]) : '',
                },
                body: cuerpo,
            });

            const datos = await respuesta.json();

            if (!respuesta.ok) {
                const errores: string[] = Object.values(datos.errors ?? {}).flat() as string[];

                setErrorPlan(errores.join(' ') || 'No se pudo analizar el archivo.');

                return;
            }

            setPlan(datos as ProdPlanImportacion);
        } catch {
            setErrorPlan('No se pudo analizar el archivo.');
        } finally {
            setAnalizando(false);
        }
    };

    const confirmarImportacion = () => {
        csvForm.post(`/admin/prod/destajos/${destajo.id}/registros/import-csv`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setModalAbierto(false);
                olvidarPlan();
                csvForm.reset('csv_file');
            },
        });
    };

    // La obra se nombra por su descripción: el número de OP no le dice nada a
    // quien captura. Si viene vacía se cae al número, que siempre existe.
    const nombreDeObra = (obra: Obra): string => obra.descripcion?.trim() || obra.no;

    const obraOptions = obras.map((o) => ({ value: String(o.id), label: nombreDeObra(o) }));

    // El catálogo baja en tres tiempos: la obra viene en la pantalla, y marcas y
    // QR se piden al elegir el escalón de arriba. Una obra son decenas de miles
    // de piezas: mandarlas todas tumbaba la pantalla por memoria para acabar
    // usando las de una sola marca.
    const [obraId, setObraId] = useState('');
    const [marcas, setMarcas] = useState<MarcaDelCatalogo[]>([]);
    const [cargandoMarcas, setCargandoMarcas] = useState(false);
    const [piezasDeLaMarca, setPiezasDeLaMarca] = useState<PiezaConAvance[]>([]);
    const [cargandoPiezas, setCargandoPiezas] = useState(false);
    // Tras capturar hay que volver a preguntar: lo recién guardado consume cupo.
    const [refresco, setRefresco] = useState(0);
    const marcaId = registroForm.data.marca_id;

    /** Trae un escalón del catálogo y avisa si la petición ya se abandonó. */
    const pedir = <T,>(url: string, recibir: (datos: T | null) => void, marcarCarga: (v: boolean) => void) => {
        const control = new AbortController();
        marcarCarga(true);

        fetch(url, { headers: { Accept: 'application/json' }, signal: control.signal })
            .then((res) => (res.ok ? res.json() : Promise.reject(res)))
            .then(recibir)
            .catch(() => {
                if (!control.signal.aborted) recibir(null);
            })
            .finally(() => {
                if (!control.signal.aborted) marcarCarga(false);
            });

        return () => control.abort();
    };

    useEffect(() => {
        if (!obraId) {
            setMarcas([]);
            return;
        }

        return pedir<{ marcas: MarcaDelCatalogo[] }>(
            `/admin/prod/obras/${obraId}/marcas`,
            (datos) => setMarcas(datos?.marcas ?? []),
            setCargandoMarcas,
        );
    }, [destajo.id, obraId]);

    useEffect(() => {
        if (!marcaId) {
            setPiezasDeLaMarca([]);
            return;
        }

        return pedir<{ piezas: PiezaConAvance[] }>(
            `/admin/prod/marcas/${marcaId}/piezas`,
            (datos) => setPiezasDeLaMarca(datos?.piezas ?? []),
            setCargandoPiezas,
        );
    }, [destajo.id, marcaId, refresco]);

    const marcaOptions = marcas.map((m) => ({
        value: String(m.id),
        label: `${etiquetaDePieza(m.marca, m.lote)} - ${m.descripcion}`,
    }));

    const marcaElegida = marcas.find((m) => String(m.id) === marcaId);

    // Sólo los procesos que paga la obra: capturar pintura donde nadie la
    // presupuestó inventaría dinero.
    const procesosDisponibles = useMemo(() => {
        if (!obraId) return [];
        const permitidos = procesosPorObra[Number(obraId)] ?? [];
        return procesos.filter((p) => permitidos.includes(p.id));
    }, [obraId, procesos, procesosPorObra]);

    const procesoId = Number(registroForm.data.proceso_id);
    const disponibleDe = (pieza: PiezaConAvance): number => pieza.avance?.[procesoId]?.disponible ?? 1;

    const consumo = registroForm.data.porcentaje / 100;
    const seleccionadas = registroForm.data.piezas;

    // Las que ya no admiten lo que se quiere pagar: se marcan y no se pueden elegir.
    const sinCupo = (pieza: PiezaConAvance) => !!procesoId && consumo > disponibleDe(pieza) + 0.0001;

    const alternarPieza = (piezaId: number) => {
        registroForm.setData(
            'piezas',
            seleccionadas.includes(piezaId)
                ? seleccionadas.filter((id) => id !== piezaId)
                : [...seleccionadas, piezaId],
        );
    };

    const seleccionarTodasConCupo = () => {
        registroForm.setData(
            'piezas',
            piezasDeLaMarca.filter((p) => !sinCupo(p)).map((p) => p.id),
        );
    };

    const submitRegistro = (e: FormEvent) => {
        e.preventDefault();
        registroForm.post(`/admin/prod/destajos/${destajo.id}/registros`, {
            preserveScroll: true,
            onSuccess: () => {
                registroForm.setData('piezas', []);
                setRefresco((n) => n + 1);
            },
        });
    };

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <div className="rounded-box border border-base-300 p-4">
                <h3 className="mb-3 flex items-center gap-2 font-semibold">
                    <PlusIcon className="size-4" /> Capturar producción
                </h3>
                <form onSubmit={submitRegistro} className="space-y-3">
                    <FormField label="Obra" htmlFor="obra_id" required>
                        <SearchSelect
                            options={obraOptions}
                            value={obraId}
                            onValueChange={(v) => {
                                setObraId(v);
                                // La marca y el proceso cuelgan de la obra: al
                                // cambiarla dejan de tener sentido.
                                registroForm.setData('marca_id', '');
                                registroForm.setData('proceso_id', '');
                                registroForm.setData('piezas', []);
                            }}
                            placeholder="Buscar obra..."
                        />
                    </FormField>

                    <FormField
                        label="Marca"
                        htmlFor="marca_id"
                        error={registroForm.errors.marca_id}
                        description={
                            obraId && !cargandoMarcas && marcaOptions.length === 0
                                ? 'Esta obra no tiene marcas en su catálogo vigente.'
                                : undefined
                        }
                        required
                    >
                        <SearchSelect
                            options={marcaOptions}
                            value={registroForm.data.marca_id}
                            onValueChange={(v) => {
                                registroForm.setData('marca_id', v);
                                registroForm.setData('piezas', []);
                            }}
                            placeholder={
                                !obraId
                                    ? 'Elige primero una obra'
                                    : cargandoMarcas
                                      ? 'Cargando marcas...'
                                      : 'Buscar marca...'
                            }
                        />
                    </FormField>

                    <div className="grid grid-cols-2 gap-3">
                        <FormField
                            label="Proceso"
                            htmlFor="proceso_id"
                            error={registroForm.errors.proceso_id}
                            description={
                                marcaElegida && procesosDisponibles.length === 0
                                    ? 'La obra no tiene procesos configurados.'
                                    : undefined
                            }
                            required
                        >
                            <Select
                                value={registroForm.data.proceso_id}
                                onValueChange={(v) => registroForm.setData('proceso_id', v)}
                                placeholder="Selecciona proceso"
                                error={!!registroForm.errors.proceso_id}
                            >
                                {procesosDisponibles.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)}>
                                        {p.nombre}
                                    </SelectItem>
                                ))}
                            </Select>
                        </FormField>

                        <FormField
                            label="Grupo"
                            htmlFor="grupo_trabajo_id"
                            error={registroForm.errors.grupo_trabajo_id}
                            required
                        >
                            <Select
                                value={registroForm.data.grupo_trabajo_id}
                                onValueChange={(v) => registroForm.setData('grupo_trabajo_id', v)}
                                placeholder="Selecciona grupo"
                                error={!!registroForm.errors.grupo_trabajo_id}
                            >
                                {gruposTrabajo.map((g) => (
                                    <SelectItem key={g.id} value={String(g.id)}>
                                        {g.descripcion}
                                    </SelectItem>
                                ))}
                            </Select>
                        </FormField>
                    </div>

                    <FormField
                        label="% a pagar"
                        htmlFor="porcentaje"
                        error={registroForm.errors.porcentaje}
                        description="Usa menos de 100% para pagar un avance y liquidar el resto en otra semana. Aplica a todas las piezas seleccionadas."
                        required
                    >
                        <Input
                            id="porcentaje"
                            type="number"
                            min={1}
                            max={100}
                            step="0.01"
                            value={registroForm.data.porcentaje}
                            onChange={(e) => registroForm.setData('porcentaje', Number(e.target.value))}
                            error={!!registroForm.errors.porcentaje}
                        />
                    </FormField>

                    <FormField
                        label="Piezas (QR)"
                        htmlFor="piezas"
                        error={registroForm.errors.piezas}
                        description={
                            marcaElegida
                                ? `${seleccionadas.length} de ${piezasDeLaMarca.length} seleccionadas · el catálogo pide ${marcaElegida.cantidad}`
                                : 'Elige primero una marca y un proceso.'
                        }
                        required
                    >
                        <div className="rounded-box border-base-300 max-h-52 overflow-auto border p-2">
                            {cargandoPiezas ? (
                                <p className="text-base-content/50 flex items-center justify-center gap-2 py-4 text-center text-sm">
                                    <Loader2Icon className="size-4 animate-spin" /> Cargando piezas...
                                </p>
                            ) : piezasDeLaMarca.length === 0 ? (
                                <p className="text-base-content/50 py-4 text-center text-sm">
                                    {marcaElegida ? 'Esta marca no tiene piezas cargadas.' : 'Sin marca seleccionada'}
                                </p>
                            ) : (
                                <div className="grid grid-cols-2 gap-1 sm:grid-cols-3">
                                    {piezasDeLaMarca.map((pieza) => {
                                        const falta = procesoId ? disponibleDe(pieza) : 1;
                                        const bloqueada = sinCupo(pieza);

                                        return (
                                            <label
                                                key={pieza.id}
                                                className={`flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm ${
                                                    bloqueada ? 'text-base-content/40' : 'hover:bg-base-200'
                                                }`}
                                                title={[
                                                    etiquetaDeUnidad(pieza),
                                                    bloqueada
                                                        ? falta <= 0
                                                            ? 'Ya está pagada al 100% en este proceso'
                                                            : `Sólo le falta ${(falta * 100).toFixed(0)}%`
                                                        : null,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            >
                                                <input
                                                    type="checkbox"
                                                    className="checkbox checkbox-xs shrink-0"
                                                    checked={seleccionadas.includes(pieza.id)}
                                                    disabled={bloqueada}
                                                    onChange={() => alternarPieza(pieza.id)}
                                                />
                                                {/* El QR identifica; el QS sólo acompaña y puede venir vacío. */}
                                                <span className="truncate font-mono">{pieza.qr}</span>
                                                {pieza.qs && (
                                                    <span className="text-base-content/50 shrink-0 font-mono text-xs">
                                                        QS {pieza.qs}
                                                    </span>
                                                )}
                                                {procesoId > 0 && falta > 0 && falta < 1 && (
                                                    <span className="badge badge-xs badge-warning">
                                                        {(falta * 100).toFixed(0)}%
                                                    </span>
                                                )}
                                            </label>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </FormField>

                    <div className="flex items-end justify-between gap-3">
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={seleccionarTodasConCupo}
                            disabled={!marcaElegida || !procesoId || piezasDeLaMarca.length === 0}
                        >
                            Seleccionar las que faltan
                        </Button>
                        <FormField label="Fecha" htmlFor="fecha" error={registroForm.errors.fecha} className="w-44">
                            <Input
                                id="fecha"
                                type="date"
                                min={soloFecha(destajo.fecha_inicio)}
                                max={soloFecha(destajo.fecha_fin)}
                                value={registroForm.data.fecha}
                                onChange={(e) => registroForm.setData('fecha', e.target.value)}
                                error={!!registroForm.errors.fecha}
                            />
                        </FormField>
                    </div>

                    <div className="flex items-center justify-between gap-3">
                        <span className="text-base-content/60 text-xs">
                            Periodo {formatDate(destajo.fecha_inicio)} — {formatDate(destajo.fecha_fin)}
                        </span>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={registroForm.processing || seleccionadas.length === 0 || !procesoId}
                        >
                            {registroForm.processing && <Loader2Icon className="size-4 animate-spin" />}
                            Agregar {seleccionadas.length > 0 ? `(${seleccionadas.length})` : ''}
                        </Button>
                    </div>
                </form>
            </div>

            <div className="rounded-box border border-base-300 p-4">
                <h3 className="mb-3 flex items-center gap-2 font-semibold">
                    <UploadIcon className="size-4" /> Importar producción (CSV)
                </h3>
                {flash?.success && (
                    <div className="alert alert-success mb-3">
                        <span>{flash.success}</span>
                    </div>
                )}

                <form onSubmit={analizar} className="space-y-3">
                    <FormField label="Archivo CSV" htmlFor="csv_file" error={csvForm.errors.csv_file} required>
                        <input
                            id="csv_file"
                            type="file"
                            accept=".csv,.txt"
                            className="file-input file-input-bordered w-full"
                            onChange={(e) => {
                                csvForm.setData('csv_file', e.target.files?.[0] ?? null);
                                olvidarPlan();
                            }}
                        />
                    </FormField>

                    <FormField label="Fecha de los registros" htmlFor="csv_fecha" error={csvForm.errors.fecha} required>
                        <Input
                            id="csv_fecha"
                            type="date"
                            min={soloFecha(destajo.fecha_inicio)}
                            max={soloFecha(destajo.fecha_fin)}
                            value={csvForm.data.fecha}
                            onChange={(e) => {
                                csvForm.setData('fecha', e.target.value);
                                olvidarPlan();
                            }}
                            error={!!csvForm.errors.fecha}
                        />
                    </FormField>

                    <p className="text-base-content/60 text-xs">
                        Acepta el <strong>export de avance de planta</strong>: el número de{' '}
                        <span className="font-mono">Proceso</span> decide qué se paga (los eventos que no son de destajo
                        se ignoran) y cada ubicación debe estar en el catálogo de módulos y pertenecer a un solo grupo.
                        También acepta un CSV a mano con <span className="font-mono">Grupo, QS, Proceso</span> y
                        opcionalmente <span className="font-mono">Porcentaje</span> (si no viene, se paga al 100%).
                        Todos los renglones toman la fecha seleccionada.
                        <br />
                        Si el archivo no trae <span className="font-mono">QR</span>, cada movimiento se asigna a la
                        pieza con el <strong>QR disponible más chico</strong> de las que comparten ese QS. Nada se
                        guarda hasta que lo confirmes en la revisión.
                    </p>

                    <div className="flex justify-end">
                        <Button type="submit" size="sm" variant="outline" disabled={!csvForm.data.csv_file}>
                            <ListChecksIcon className="size-4" />
                            Revisar
                        </Button>
                    </div>
                </form>
            </div>

            <RevisarImportacionModal
                open={modalAbierto}
                onClose={() => setModalAbierto(false)}
                plan={plan}
                cargando={analizando}
                error={errorPlan}
                confirmando={csvForm.processing}
                onConfirmar={confirmarImportacion}
            />
        </div>
    );
}
