import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import type { ProdDestajo, ProdGrupoTrabajo, ProdTipoPagoExtra } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon } from 'lucide-react';
import { type FormEvent } from 'react';

type Props = {
    destajo: ProdDestajo;
    tipos: ProdTipoPagoExtra[];
    gruposTrabajo: ProdGrupoTrabajo[];
};

export function AgregarPagoExtra({ destajo, tipos, gruposTrabajo }: Props) {
    const form = useForm<{
        descripcion: string;
        tipo_id: string;
        grupo_trabajo_id: string;
        precio: number;
        dias: number;
        personas: number;
    }>({
        descripcion: '',
        tipo_id: '',
        grupo_trabajo_id: '',
        precio: 0,
        dias: 1,
        personas: 1,
    });

    const monto = Number(form.data.precio) * form.data.dias * form.data.personas;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/admin/prod/destajos/${destajo.id}/pagos-extra`, {
            preserveScroll: true,
            onSuccess: () => form.reset('descripcion', 'precio', 'dias', 'personas'),
        });
    };

    return (
        <div className="rounded-box border border-base-300 p-4">
            <h3 className="mb-3 flex items-center gap-2 font-semibold">
                <PlusIcon className="size-4" /> Agregar pago extra
            </h3>
            <form onSubmit={submit} className="space-y-3">
                <div className="grid gap-3 sm:grid-cols-2">
                    <FormField label="Tipo" htmlFor="tipo_id" error={form.errors.tipo_id} required>
                        <Select
                            value={form.data.tipo_id}
                            onValueChange={(v) => form.setData('tipo_id', v)}
                            placeholder="Selecciona tipo"
                            error={!!form.errors.tipo_id}
                        >
                            {tipos.map((t) => (
                                <SelectItem key={t.id} value={String(t.id)}>
                                    {t.descripcion}
                                </SelectItem>
                            ))}
                        </Select>
                    </FormField>
                    <FormField label="Grupo" htmlFor="pe_grupo" error={form.errors.grupo_trabajo_id} required>
                        <Select
                            value={form.data.grupo_trabajo_id}
                            onValueChange={(v) => form.setData('grupo_trabajo_id', v)}
                            placeholder="Selecciona grupo"
                            error={!!form.errors.grupo_trabajo_id}
                        >
                            {gruposTrabajo.map((g) => (
                                <SelectItem key={g.id} value={String(g.id)}>
                                    {g.descripcion}
                                </SelectItem>
                            ))}
                        </Select>
                    </FormField>
                </div>

                <FormField label="Descripción" htmlFor="pe_descripcion" error={form.errors.descripcion} required>
                    <Input
                        id="pe_descripcion"
                        value={form.data.descripcion}
                        onChange={(e) => form.setData('descripcion', e.target.value)}
                        error={!!form.errors.descripcion}
                    />
                </FormField>

                <div className="grid grid-cols-3 gap-3">
                    <FormField label="Precio" htmlFor="pe_precio" error={form.errors.precio} required>
                        <Input
                            id="pe_precio"
                            type="number"
                            step="0.01"
                            min={0}
                            value={form.data.precio}
                            onChange={(e) => form.setData('precio', Number(e.target.value))}
                            error={!!form.errors.precio}
                        />
                    </FormField>
                    {/* Fraccionable: media jornada de horas extra es medio día,
                        y redondearla a uno le regala al grupo el doble. Con
                        `any` las flechas siguen subiendo de uno en uno. */}
                    <FormField label="Días" htmlFor="pe_dias" error={form.errors.dias} required>
                        <Input
                            id="pe_dias"
                            type="number"
                            step="any"
                            min={0}
                            value={form.data.dias}
                            onChange={(e) => form.setData('dias', Number(e.target.value))}
                            error={!!form.errors.dias}
                        />
                    </FormField>
                    <FormField label="Personas" htmlFor="pe_personas" error={form.errors.personas} required>
                        <Input
                            id="pe_personas"
                            type="number"
                            min={1}
                            value={form.data.personas}
                            onChange={(e) => form.setData('personas', Number(e.target.value))}
                            error={!!form.errors.personas}
                        />
                    </FormField>
                </div>

                <div className="flex items-center justify-between">
                    <span className="text-base-content/70 text-sm">
                        Monto:{' '}
                        <span className="font-mono font-semibold">
                            ${monto.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </span>
                    </span>
                    <Button type="submit" size="sm" disabled={form.processing}>
                        {form.processing && <Loader2Icon className="size-4 animate-spin" />}
                        Agregar
                    </Button>
                </div>
            </form>
        </div>
    );
}
