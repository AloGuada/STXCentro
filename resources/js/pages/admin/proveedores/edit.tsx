import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento, Proveedor } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    proveedor: Proveedor;
    departamentos: Departamento[];
};

export default function ProveedoresEdit({ proveedor, departamentos }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Proveedores', href: '/admin/proveedores' },
        { title: proveedor.codigo, href: `/admin/proveedores/${proveedor.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        codigo: proveedor.codigo,
        razon_social: proveedor.razon_social,
        nombre_comercial: proveedor.nombre_comercial ?? '',
        rfc: proveedor.rfc,
        direccion: proveedor.direccion ?? '',
        telefono: proveedor.telefono ?? '',
        email: proveedor.email ?? '',
        contacto_nombre: proveedor.contacto_nombre ?? '',
        tiene_acceso_portal: proveedor.tiene_acceso_portal,
        maneja_credito: proveedor.maneja_credito,
        limite_credito: String(proveedor.limite_credito),
        dias_credito_default: String(proveedor.dias_credito_default),
        departamento_id: proveedor.departamento_id ? String(proveedor.departamento_id) : '',
        tipo_proveedor: proveedor.tipo_proveedor ?? '',
        activo: proveedor.activo,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/proveedores/${proveedor.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${proveedor.codigo}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Proveedor</h1>
                        <DeleteDialog
                            title="Eliminar proveedor"
                            description={`¿Estás seguro de eliminar el proveedor "${proveedor.razon_social}"? Esta acción no se puede deshacer.`}
                            deleteUrl={`/admin/proveedores/${proveedor.id}`}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <h2 className="text-lg font-medium">Datos Fiscales</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Código" htmlFor="codigo" error={errors.codigo} required>
                                <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} />
                            </FormField>
                            <FormField label="RFC" htmlFor="rfc" error={errors.rfc} required>
                                <Input id="rfc" value={data.rfc} onChange={(e) => setData('rfc', e.target.value)} />
                            </FormField>
                        </div>

                        <FormField label="Razón Social" htmlFor="razon_social" error={errors.razon_social} required>
                            <Input id="razon_social" value={data.razon_social} onChange={(e) => setData('razon_social', e.target.value)} />
                        </FormField>

                        <FormField label="Nombre Comercial" htmlFor="nombre_comercial" error={errors.nombre_comercial}>
                            <Input id="nombre_comercial" value={data.nombre_comercial} onChange={(e) => setData('nombre_comercial', e.target.value)} />
                        </FormField>

                        <FormField label="Dirección" htmlFor="direccion" error={errors.direccion}>
                            <textarea
                                id="direccion"
                                className="textarea textarea-bordered w-full"
                                value={data.direccion}
                                onChange={(e) => setData('direccion', e.target.value)}
                            />
                        </FormField>

                        <div className="divider" />
                        <h2 className="text-lg font-medium">Contacto</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Teléfono" htmlFor="telefono" error={errors.telefono}>
                                <Input id="telefono" value={data.telefono} onChange={(e) => setData('telefono', e.target.value)} />
                            </FormField>
                            <FormField label="Email" htmlFor="email" error={errors.email}>
                                <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                            </FormField>
                        </div>
                        <FormField label="Nombre de Contacto" htmlFor="contacto_nombre" error={errors.contacto_nombre}>
                            <Input id="contacto_nombre" value={data.contacto_nombre} onChange={(e) => setData('contacto_nombre', e.target.value)} />
                        </FormField>

                        <div className="divider" />
                        <h2 className="text-lg font-medium">Configuración</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Departamento" htmlFor="departamento_id" error={errors.departamento_id}>
                                <Select id="departamento_id" value={data.departamento_id} onValueChange={(value) => setData('departamento_id', value)}>
                                    <option value="">Sin departamento</option>
                                    {departamentos.map((d) => (
                                        <option key={d.id} value={d.id}>{d.descripcion}</option>
                                    ))}
                                </Select>
                            </FormField>
                            <FormField label="Tipo de Proveedor" htmlFor="tipo_proveedor" error={errors.tipo_proveedor}>
                                <Select id="tipo_proveedor" value={data.tipo_proveedor} onValueChange={(value) => setData('tipo_proveedor', value)}>
                                    <option value="">Seleccionar</option>
                                    <option value="materiales">Materiales</option>
                                    <option value="servicios">Servicios</option>
                                    <option value="equipos">Equipos</option>
                                    <option value="mixto">Mixto</option>
                                </Select>
                            </FormField>
                        </div>

                        <div className="divider" />
                        <h2 className="text-lg font-medium">Crédito</h2>
                        <div className="flex items-center gap-6">
                            <label className="label cursor-pointer gap-2">
                                <input type="checkbox" className="checkbox" checked={data.tiene_acceso_portal} onChange={(e) => setData('tiene_acceso_portal', e.target.checked)} />
                                <span className="label-text">Tiene acceso al portal</span>
                            </label>
                            <label className="label cursor-pointer gap-2">
                                <input type="checkbox" className="checkbox" checked={data.maneja_credito} onChange={(e) => setData('maneja_credito', e.target.checked)} />
                                <span className="label-text">Maneja crédito</span>
                            </label>
                            <label className="label cursor-pointer gap-2">
                                <input type="checkbox" className="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                                <span className="label-text">Activo</span>
                            </label>
                        </div>

                        {data.maneja_credito && (
                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Límite de Crédito" htmlFor="limite_credito" error={errors.limite_credito}>
                                    <Input id="limite_credito" type="number" step="0.01" min="0" value={data.limite_credito} onChange={(e) => setData('limite_credito', e.target.value)} />
                                </FormField>
                                <FormField label="Días de Crédito" htmlFor="dias_credito_default" error={errors.dias_credito_default}>
                                    <Input id="dias_credito_default" type="number" min="0" value={data.dias_credito_default} onChange={(e) => setData('dias_credito_default', e.target.value)} />
                                </FormField>
                            </div>
                        )}

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/proveedores">Cancelar</Link>
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
