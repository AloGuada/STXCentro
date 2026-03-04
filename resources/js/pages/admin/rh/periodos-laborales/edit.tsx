import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { RhPeriodoLaboral } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    periodo: RhPeriodoLaboral;
    personas: { id: number; nombre: string; apellido: string }[];
    puestos: { id: number; nombre: string }[];
    requisiciones: { id: number; folio: string; puesto_id: number }[];
};

export default function PeriodoLaboralEdit({ periodo, personas, puestos, requisiciones }: Props) {
    const personaNombre = periodo.persona ? `${periodo.persona.nombre} ${periodo.persona.apellido}` : 'Periodo';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'RH', href: '/admin/rh/skills' },
        { title: 'Periodos Laborales', href: '/admin/rh/periodos-laborales' },
        { title: personaNombre, href: `/admin/rh/periodos-laborales/${periodo.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        persona_id: String(periodo.persona_id),
        puesto_id: String(periodo.puesto_id),
        requisicion_id: periodo.requisicion_id ? String(periodo.requisicion_id) : '',
        fecha_inicio: periodo.fecha_inicio ?? '',
        fecha_fin: periodo.fecha_fin ?? '',
        salario: periodo.salario ? String(periodo.salario) : '',
        tipo_contrato: periodo.tipo_contrato ?? '',
        estado: periodo.estado,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/rh/periodos-laborales/${periodo.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar Periodo - ${personaNombre}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Periodo Laboral</h1>
                        <DeleteDialog
                            title="Eliminar periodo laboral"
                            description={`¿Estas seguro de eliminar este periodo laboral de "${personaNombre}"? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/rh/periodos-laborales/${periodo.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Persona" htmlFor="persona_id" error={errors.persona_id} required>
                            <Select value={data.persona_id} onValueChange={(v) => setData('persona_id', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar persona" />
                                </SelectTrigger>
                                <SelectContent>
                                    {personas.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.nombre} {p.apellido}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField label="Puesto" htmlFor="puesto_id" error={errors.puesto_id} required>
                            <Select value={data.puesto_id} onValueChange={(v) => setData('puesto_id', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar puesto" />
                                </SelectTrigger>
                                <SelectContent>
                                    {puestos.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField label="Requisición" htmlFor="requisicion_id" error={errors.requisicion_id}>
                            <Select value={data.requisicion_id} onValueChange={(v) => setData('requisicion_id', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Sin requisición" />
                                </SelectTrigger>
                                <SelectContent>
                                    {requisiciones.map((r) => (
                                        <SelectItem key={r.id} value={String(r.id)}>
                                            {r.folio}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Fecha de Inicio" htmlFor="fecha_inicio" error={errors.fecha_inicio} required>
                                <Input id="fecha_inicio" type="date" value={data.fecha_inicio} onChange={(e) => setData('fecha_inicio', e.target.value)} />
                            </FormField>

                            <FormField label="Fecha de Fin" htmlFor="fecha_fin" error={errors.fecha_fin}>
                                <Input id="fecha_fin" type="date" value={data.fecha_fin} onChange={(e) => setData('fecha_fin', e.target.value)} />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Salario" htmlFor="salario" error={errors.salario}>
                                <Input id="salario" type="number" step="0.01" value={data.salario} onChange={(e) => setData('salario', e.target.value)} placeholder="0.00" />
                            </FormField>

                            <FormField label="Tipo de Contrato" htmlFor="tipo_contrato" error={errors.tipo_contrato}>
                                <Input id="tipo_contrato" value={data.tipo_contrato} onChange={(e) => setData('tipo_contrato', e.target.value)} placeholder="Ej: Indefinido, Temporal" />
                            </FormField>
                        </div>

                        <FormField label="Estado" htmlFor="estado" error={errors.estado} required>
                            <Select value={data.estado} onValueChange={(v) => setData('estado', v as 'activo' | 'terminado' | 'baja')}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="activo">Activo</SelectItem>
                                    <SelectItem value="terminado">Terminado</SelectItem>
                                    <SelectItem value="baja">Baja</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/rh/periodos-laborales">Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
