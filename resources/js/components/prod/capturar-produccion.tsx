import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/components/ui/formatted-date';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import { SearchSelect } from '@/components/ui/search-select';
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

    const registroForm = useForm<{ fecha: string; concepto_id: string; grupo_trabajo_id: string; cantidad: number }>({
        fecha: soloFecha(destajo.fecha_inicio),
        concepto_id: '',
        grupo_trabajo_id: '',
        cantidad: 1,
    });

    const csvForm = useForm<{ csv_file: File | null; fecha: string }>({
        csv_file: null,
        fecha: soloFecha(destajo.fecha_inicio),
    });

    const conceptoOptions = conceptos.map((c) => ({
        value: String(c.id),
        label: `${c.obra ? `[${c.obra.no}] ` : ''}${c.marca} - ${c.descripcion}`,
    }));

    const submitRegistro = (e: FormEvent) => {
        e.preventDefault();
        registroForm.post(`/admin/prod/destajos/${destajo.id}/registros`, {
            preserveScroll: true,
            onSuccess: () => registroForm.reset('concepto_id', 'cantidad'),
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
                                error={!!registroForm.errors.cantidad}
                            />
                        </FormField>
                    </div>

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

                    <div className="flex justify-end">
                        <Button type="submit" size="sm" disabled={registroForm.processing}>
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
                        Columnas: <span className="font-mono">Grupo, Marca, Cantidad</span>. El grupo debe coincidir con su
                        descripción y la marca con una pieza activa. Todos los renglones toman la fecha seleccionada.
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
