import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { RhRequisicion } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    requisicion: RhRequisicion;
    puestos: { id: number; nombre: string }[];
};

export default function RequisicionEdit({ requisicion, puestos }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'RH', href: '/admin/rh/skills' },
        { title: 'Requisiciones', href: '/admin/rh/requisiciones' },
        { title: requisicion.folio, href: `/admin/rh/requisiciones/${requisicion.id}` },
        { title: 'Editar', href: `/admin/rh/requisiciones/${requisicion.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        puesto_id: String(requisicion.puesto_id),
        cantidad: String(requisicion.cantidad),
        tipo_requisicion: requisicion.tipo_requisicion,
        tipo_contrato_generado: requisicion.tipo_contrato_generado,
        estado: requisicion.estado,
        justificacion: requisicion.justificacion ?? '',
        nombre_solicitante: requisicion.nombre_solicitante ?? '',
        salario: requisicion.salario ? String(requisicion.salario) : '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/rh/requisiciones/${requisicion.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar Requisicion ${requisicion.folio}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Requisicion</h1>
                        <DeleteDialog
                            title="Eliminar requisicion"
                            description={`¿Estas seguro de eliminar la requisicion "${requisicion.folio}"? Esta accion no se puede deshacer.`}
                            deleteUrl={`/admin/rh/requisiciones/${requisicion.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
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

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Cantidad" htmlFor="cantidad" error={errors.cantidad} required>
                                <Input id="cantidad" type="number" min="1" value={data.cantidad} onChange={(e) => setData('cantidad', e.target.value)} />
                            </FormField>

                            <FormField label="Tipo de Requisicion" htmlFor="tipo_requisicion" error={errors.tipo_requisicion} required>
                                <Select value={data.tipo_requisicion} onValueChange={(v) => setData('tipo_requisicion', v as 'nueva' | 'reemplazo' | 'temporal')}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Seleccionar tipo" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="nueva">Nueva</SelectItem>
                                        <SelectItem value="reemplazo">Reemplazo</SelectItem>
                                        <SelectItem value="temporal">Temporal</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>
                        </div>

                        <FormField label="Tipo de Contrato a Generar" htmlFor="tipo_contrato_generado" error={errors.tipo_contrato_generado} required>
                            <Select value={data.tipo_contrato_generado} onValueChange={(v) => setData('tipo_contrato_generado', v as 'planta' | 'obra')}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar tipo de contrato" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="planta">Planta</SelectItem>
                                    <SelectItem value="obra">Obra</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField label="Estado" htmlFor="estado" error={errors.estado} required>
                            <Select value={data.estado} onValueChange={(v) => setData('estado', v as 'borrador' | 'abierta' | 'en_proceso' | 'cerrada' | 'cancelada')}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar estado" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="borrador">Borrador</SelectItem>
                                    <SelectItem value="abierta">Abierta</SelectItem>
                                    <SelectItem value="en_proceso">En Proceso</SelectItem>
                                    <SelectItem value="cerrada">Cerrada</SelectItem>
                                    <SelectItem value="cancelada">Cancelada</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField label="Justificacion" htmlFor="justificacion" error={errors.justificacion}>
                            <textarea id="justificacion" className="textarea textarea-bordered w-full" value={data.justificacion} onChange={(e) => setData('justificacion', e.target.value)} placeholder="Justificacion de la requisicion" />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Nombre del Solicitante" htmlFor="nombre_solicitante" error={errors.nombre_solicitante}>
                                <Input id="nombre_solicitante" value={data.nombre_solicitante} onChange={(e) => setData('nombre_solicitante', e.target.value)} placeholder="Nombre del solicitante" />
                            </FormField>

                            <FormField label="Salario" htmlFor="salario" error={errors.salario}>
                                <Input id="salario" type="number" step="0.01" value={data.salario} onChange={(e) => setData('salario', e.target.value)} placeholder="0.00" />
                            </FormField>
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/rh/requisiciones">Cancelar</Link>
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
