import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/components/ui/formatted-date';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import { SearchSelect } from '@/components/ui/search-select';
import { etiquetaDePieza } from '@/lib/prod/piezas';
import type { Concepto, Obra, ProdDestajo, ProdGrupoTrabajo } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, UploadIcon } from 'lucide-react';
import { type FormEvent } from 'react';

type Props = {
    destajo: ProdDestajo;
    conceptos: (Concepto & { obra?: Obra })[];
    gruposTrabajo: ProdGrupoTrabajo[];
};

export function CapturarProduccion({ destajo, conceptos, gruposTrabajo }: Props) {
    const soloFecha = (v: string) => v.slice(0, 10);

    const registroForm = useForm<{
        fecha: string;
        concepto_id: string;
        grupo_trabajo_id: string;
        cantidad: number;
        porcentaje: number;
    }>({
        fecha: soloFecha(destajo.fecha_inicio),
        concepto_id: '',
        grupo_trabajo_id: '',
        cantidad: 1,
        porcentaje: 100,
    });

    const csvForm = useForm<{ csv_file: File | null; fecha: string }>({
        csv_file: null,
        fecha: soloFecha(destajo.fecha_inicio),
    });

    const conceptoOptions = conceptos.map((c) => {
        const faltan = c.disponible ?? 0;

        return {
            value: String(c.id),
            label:
                `${c.obra ? `[${c.obra.no}] ` : ''}${etiquetaDePieza(c.marca, c.etapa)} - ${c.descripcion} · ` +
                (faltan === 0 ? 'completa' : `faltan ${faltan} de ${c.cantidad}`),
            // Pintadas en rojo: ya se pagó todo lo que el catálogo manda.
            danger: faltan === 0,
        };
    });

    const conceptoElegido = conceptos.find((c) => String(c.id) === registroForm.data.concepto_id);
    const disponible = conceptoElegido?.disponible ?? 0;

    // Piezas equivalentes que consume la captura: 10 al 60% gastan 6.
    const consumo = (registroForm.data.cantidad * registroForm.data.porcentaje) / 100;
    const rebasa = !!conceptoElegido && consumo > disponible + 0.0001;
    const esParcial = registroForm.data.porcentaje < 100;

    const submitRegistro = (e: FormEvent) => {
        e.preventDefault();
        registroForm.post(`/admin/prod/destajos/${destajo.id}/registros`, {
            preserveScroll: true,
            onSuccess: () => registroForm.reset('concepto_id', 'cantidad', 'porcentaje'),
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
                    <FormField label="Pieza" htmlFor="concepto_id" error={registroForm.errors.concepto_id} required>
                        <SearchSelect
                            options={conceptoOptions}
                            value={registroForm.data.concepto_id}
                            onValueChange={(v) => registroForm.setData('concepto_id', v)}
                            placeholder="Buscar pieza..."
                        />
                    </FormField>

                    <div className="grid grid-cols-2 gap-3">
                        <FormField label="Grupo" htmlFor="grupo_trabajo_id" error={registroForm.errors.grupo_trabajo_id} required>
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
                        <FormField label="Cantidad" htmlFor="cantidad" error={registroForm.errors.cantidad} required>
                            <Input
                                id="cantidad"
                                type="number"
                                min={1}
                                value={registroForm.data.cantidad}
                                onChange={(e) => registroForm.setData('cantidad', Number(e.target.value))}
                                error={!!registroForm.errors.cantidad || rebasa}
                            />
                        </FormField>
                    </div>

                    <FormField
                        label="% a pagar"
                        htmlFor="porcentaje"
                        error={registroForm.errors.porcentaje}
                        description={
                            conceptoElegido
                                ? `Pagadas ${conceptoElegido.capturado ?? 0} de ${conceptoElegido.cantidad} · faltan ${disponible}` +
                                  (esParcial ? ` · esta captura consume ${consumo.toLocaleString('es-MX')}` : '')
                                : 'Usa menos de 100% para pagar un avance y liquidar el resto en otra semana.'
                        }
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
                            error={!!registroForm.errors.porcentaje || rebasa}
                        />
                    </FormField>

                    <FormField
                        label="Fecha"
                        htmlFor="fecha"
                        error={registroForm.errors.fecha}
                        description={`Dentro del periodo ${formatDate(destajo.fecha_inicio)} — ${formatDate(destajo.fecha_fin)}`}
                        required
                    >
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

                    <div className="flex items-center justify-between gap-3">
                        {conceptoElegido && disponible === 0 ? (
                            <span className="text-error text-xs">
                                Pieza pagada al 100%. Si son piezas rehechas, págalas como pago extra.
                            </span>
                        ) : (
                            rebasa && (
                                <span className="text-error text-xs">
                                    Se pasa por {(consumo - disponible).toLocaleString('es-MX')} pieza(s): solo
                                    quedan {disponible} por pagar.
                                </span>
                            )
                        )}
                        <Button
                            type="submit"
                            size="sm"
                            className="ml-auto"
                            disabled={registroForm.processing || rebasa || (!!conceptoElegido && disponible === 0)}
                        >
                            {registroForm.processing && <Loader2Icon className="size-4 animate-spin" />}
                            Agregar
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
                        Acepta el <strong>export de avance de planta</strong>: se toman sólo los movimientos del evento{' '}
                        <span className="font-mono">55</span> y se suman por <span className="font-mono">Ubicacion</span>,{' '}
                        <span className="font-mono">Marca</span> y <span className="font-mono">Etapa</span>; cada
                        ubicación debe estar en el catálogo de módulos y pertenecer a un solo grupo. También acepta un
                        CSV a mano con <span className="font-mono">Grupo, Marca, Cantidad</span> y opcionalmente{' '}
                        <span className="font-mono">Etapa</span> y <span className="font-mono">Porcentaje</span> (si no
                        viene, se paga al 100%). Si el archivo no trae <span className="font-mono">Etapa</span> y esa
                        marca está repetida en varias etapas del catálogo, el renglón se reporta en vez de cargarse.
                        Todos los renglones toman la fecha seleccionada.
                    </p>

                    <div className="flex justify-end">
                        <Button type="submit" size="sm" variant="outline" disabled={csvForm.processing || !csvForm.data.csv_file}>
                            {csvForm.processing && <Loader2Icon className="size-4 animate-spin" />}
                            Importar
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
