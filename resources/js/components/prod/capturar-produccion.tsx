import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/components/ui/formatted-date';
import { Input } from '@/components/ui/input';
import { SearchSelect } from '@/components/ui/search-select';
import { Select, SelectItem } from '@/components/ui/select';
import { etiquetaDePieza } from '@/lib/prod/piezas';
import type { Concepto, Obra, ProdDestajo, ProdGrupoTrabajo, ProdPieza, ProdProceso } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, UploadIcon } from 'lucide-react';
import { useMemo, type FormEvent } from 'react';

type MarcaConPiezas = Concepto & { obra?: Obra; piezas?: ProdPieza[] };

type Props = {
    destajo: ProdDestajo;
    marcas: MarcaConPiezas[];
    procesos: ProdProceso[];
    /** Qué procesos paga cada obra: obraId => ids de proceso. */
    procesosPorObra: Record<number, number[]>;
    /** Avance por pieza y proceso, para saber cuánto le falta a cada QS. */
    avance: Record<number, Record<number, { capturado: number; disponible: number }>>;
    gruposTrabajo: ProdGrupoTrabajo[];
};

export function CapturarProduccion({
    destajo,
    marcas,
    procesos,
    procesosPorObra,
    avance,
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

    const marcaOptions = marcas.map((m) => ({
        value: String(m.id),
        label: `${m.obra ? `[${m.obra.no}] ` : ''}${etiquetaDePieza(m.marca, m.lote)} - ${m.descripcion}`,
    }));

    const marcaElegida = marcas.find((m) => String(m.id) === registroForm.data.marca_id);

    // Sólo los procesos que paga la obra de esa marca: capturar pintura donde
    // nadie la presupuestó inventaría dinero.
    const procesosDisponibles = useMemo(() => {
        if (!marcaElegida) return [];
        const permitidos = procesosPorObra[marcaElegida.obra_id] ?? [];
        return procesos.filter((p) => permitidos.includes(p.id));
    }, [marcaElegida, procesos, procesosPorObra]);

    const procesoId = Number(registroForm.data.proceso_id);
    const disponibleDe = (piezaId: number): number => avance[piezaId]?.[procesoId]?.disponible ?? 1;

    const piezasDeLaMarca = marcaElegida?.piezas ?? [];
    const consumo = registroForm.data.porcentaje / 100;
    const seleccionadas = registroForm.data.piezas;

    // Las que ya no admiten lo que se quiere pagar: se marcan y no se pueden elegir.
    const sinCupo = (pieza: ProdPieza) => !!procesoId && consumo > disponibleDe(pieza.id) + 0.0001;

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
            onSuccess: () => registroForm.setData('piezas', []),
        });
    };

    const submitCsv = (e: FormEvent) => {
        e.preventDefault();
        csvForm.post(`/admin/prod/destajos/${destajo.id}/registros/import-csv`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => csvForm.reset('csv_file'),
        });
    };

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <div className="rounded-box border border-base-300 p-4">
                <h3 className="mb-3 flex items-center gap-2 font-semibold">
                    <PlusIcon className="size-4" /> Capturar producción
                </h3>
                <form onSubmit={submitRegistro} className="space-y-3">
                    <FormField label="Marca" htmlFor="marca_id" error={registroForm.errors.marca_id} required>
                        <SearchSelect
                            options={marcaOptions}
                            value={registroForm.data.marca_id}
                            onValueChange={(v) => {
                                registroForm.setData('marca_id', v);
                                registroForm.setData('piezas', []);
                            }}
                            placeholder="Buscar marca..."
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
                        label="Piezas (QS)"
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
                            {piezasDeLaMarca.length === 0 ? (
                                <p className="text-base-content/50 py-4 text-center text-sm">
                                    {marcaElegida ? 'Esta marca no tiene piezas cargadas.' : 'Sin marca seleccionada'}
                                </p>
                            ) : (
                                <div className="grid grid-cols-2 gap-1 sm:grid-cols-3">
                                    {piezasDeLaMarca.map((pieza) => {
                                        const falta = procesoId ? disponibleDe(pieza.id) : 1;
                                        const bloqueada = sinCupo(pieza);

                                        return (
                                            <label
                                                key={pieza.id}
                                                className={`flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm ${
                                                    bloqueada ? 'text-base-content/40' : 'hover:bg-base-200'
                                                }`}
                                                title={
                                                    bloqueada
                                                        ? falta <= 0
                                                            ? 'Ya está pagada al 100% en este proceso'
                                                            : `Sólo le falta ${(falta * 100).toFixed(0)}%`
                                                        : undefined
                                                }
                                            >
                                                <input
                                                    type="checkbox"
                                                    className="checkbox checkbox-xs"
                                                    checked={seleccionadas.includes(pieza.id)}
                                                    disabled={bloqueada}
                                                    onChange={() => alternarPieza(pieza.id)}
                                                />
                                                <span className="font-mono">{pieza.qs}</span>
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
                <form onSubmit={submitCsv} className="space-y-3">
                    <FormField label="Archivo CSV" htmlFor="csv_file" error={csvForm.errors.csv_file} required>
                        <input
                            id="csv_file"
                            type="file"
                            accept=".csv,.txt"
                            className="file-input file-input-bordered w-full"
                            onChange={(e) => csvForm.setData('csv_file', e.target.files?.[0] ?? null)}
                        />
                    </FormField>

                    <FormField label="Fecha de los registros" htmlFor="csv_fecha" error={csvForm.errors.fecha} required>
                        <Input
                            id="csv_fecha"
                            type="date"
                            min={soloFecha(destajo.fecha_inicio)}
                            max={soloFecha(destajo.fecha_fin)}
                            value={csvForm.data.fecha}
                            onChange={(e) => csvForm.setData('fecha', e.target.value)}
                            error={!!csvForm.errors.fecha}
                        />
                    </FormField>

                    <p className="text-base-content/60 text-xs">
                        Acepta el <strong>export de avance de planta</strong>: la pieza se resuelve por{' '}
                        <span className="font-mono">QS</span> y el número de <span className="font-mono">Proceso</span>{' '}
                        decide qué se paga (los eventos que no son de destajo se ignoran). Cada ubicación debe estar en
                        el catálogo de módulos y pertenecer a un solo grupo. También acepta un CSV a mano con{' '}
                        <span className="font-mono">Grupo, QS, Proceso</span> y opcionalmente{' '}
                        <span className="font-mono">Porcentaje</span> (si no viene, se paga al 100%). Todos los
                        renglones toman la fecha seleccionada.
                    </p>

                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            disabled={csvForm.processing || !csvForm.data.csv_file}
                        >
                            {csvForm.processing && <Loader2Icon className="size-4 animate-spin" />}
                            Importar
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
