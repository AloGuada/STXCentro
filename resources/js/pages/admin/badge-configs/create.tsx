import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Badge Configs', href: '/admin/badge-configs' },
    { title: 'Nueva', href: '/admin/badge-configs/create' },
];

const operadores = ['=', '!=', '>', '>=', '<', '<=', 'like'];

type Props = {
    roles: string[];
};

export default function BadgeConfigsCreate({ roles }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
        tabla: '',
        campo_estatus: '',
        operador: '=',
        valor_estatus: '',
        condiciones_extra: '',
        rol: '',
        nav_href: '',
        filter_href: '',
        activo: true,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/badge-configs');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva Badge Config" />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Nueva Badge Config</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                            <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} placeholder="Ej: Facturas sin entrega" />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Tabla" htmlFor="tabla" error={errors.tabla} required>
                                <Input id="tabla" value={data.tabla} onChange={(e) => setData('tabla', e.target.value)} placeholder="Ej: costos_facturas" />
                            </FormField>
                            <FormField label="Campo" htmlFor="campo_estatus" error={errors.campo_estatus} required>
                                <Input id="campo_estatus" value={data.campo_estatus} onChange={(e) => setData('campo_estatus', e.target.value)} placeholder="Ej: estatus" />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Operador" htmlFor="operador" error={errors.operador} required>
                                <select id="operador" className="select select-bordered w-full" value={data.operador} onChange={(e) => setData('operador', e.target.value)}>
                                    {operadores.map((op) => (
                                        <option key={op} value={op}>{op}</option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField label="Valor" htmlFor="valor_estatus" error={errors.valor_estatus} required>
                                <Input id="valor_estatus" value={data.valor_estatus} onChange={(e) => setData('valor_estatus', e.target.value)} placeholder="Ej: pendiente_entrega o -7 days" />
                            </FormField>
                        </div>

                        <FormField label="Condiciones Extra (JSON)" htmlFor="condiciones_extra" error={errors.condiciones_extra}>
                            <textarea
                                id="condiciones_extra"
                                className="textarea textarea-bordered w-full font-mono text-xs"
                                value={data.condiciones_extra}
                                onChange={(e) => setData('condiciones_extra', e.target.value)}
                                rows={3}
                                placeholder='[{"campo": "aprobada_costos", "operador": "=", "valor": true}]'
                            />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Rol" htmlFor="rol" error={errors.rol} required>
                                <select id="rol" className="select select-bordered w-full" value={data.rol} onChange={(e) => setData('rol', e.target.value)}>
                                    <option value="">Seleccionar rol...</option>
                                    {roles.map((r) => (
                                        <option key={r} value={r}>{r}</option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField label="Nav Href" htmlFor="nav_href" error={errors.nav_href} required>
                                <Input id="nav_href" value={data.nav_href} onChange={(e) => setData('nav_href', e.target.value)} placeholder="Ej: /admin/costos/facturas" />
                            </FormField>
                        </div>

                        <FormField label="Filter Href" htmlFor="filter_href" error={errors.filter_href}>
                            <Input id="filter_href" value={data.filter_href} onChange={(e) => setData('filter_href', e.target.value)} placeholder="Ej: /admin/costos/facturas?estatus=pendiente_entrega" />
                        </FormField>

                        <FormField label="Activo" htmlFor="activo" error={errors.activo}>
                            <label className="label cursor-pointer justify-start gap-3">
                                <input type="checkbox" className="toggle toggle-primary" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                                <span>{data.activo ? 'Activo' : 'Inactivo'}</span>
                            </label>
                        </FormField>

                        <div className="flex justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/badge-configs">Cancelar</Link>
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
