import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { BadgeConfig } from '@/types/models';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { Loader2Icon, Trash2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const operadores = ['=', '!=', '>', '>=', '<', '<=', 'like'];

type Props = {
    badgeConfig: BadgeConfig;
    roles: string[];
};

export default function BadgeConfigsEdit({ badgeConfig, roles }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Badge Configs', href: '/admin/badge-configs' },
        { title: badgeConfig.nombre, href: `/admin/badge-configs/${badgeConfig.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        nombre: badgeConfig.nombre,
        tabla: badgeConfig.tabla,
        campo_estatus: badgeConfig.campo_estatus,
        operador: badgeConfig.operador,
        valor_estatus: badgeConfig.valor_estatus,
        condiciones_extra: badgeConfig.condiciones_extra ? JSON.stringify(badgeConfig.condiciones_extra, null, 2) : '',
        rol: badgeConfig.rol,
        nav_href: badgeConfig.nav_href,
        filter_href: badgeConfig.filter_href ?? '',
        activo: badgeConfig.activo,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/badge-configs/${badgeConfig.id}`);
    };

    const handleDelete = () => {
        if (confirm('Eliminar esta badge config?')) {
            router.delete(`/admin/badge-configs/${badgeConfig.id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar: ${badgeConfig.nombre}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <div className="mb-6 flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Editar Badge Config</h1>
                        <Button variant="destructive" size="sm" onClick={handleDelete}>
                            <Trash2Icon className="size-4" /> Eliminar
                        </Button>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} required>
                            <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Tabla" htmlFor="tabla" error={errors.tabla} required>
                                <Input id="tabla" value={data.tabla} onChange={(e) => setData('tabla', e.target.value)} />
                            </FormField>
                            <FormField label="Campo" htmlFor="campo_estatus" error={errors.campo_estatus} required>
                                <Input id="campo_estatus" value={data.campo_estatus} onChange={(e) => setData('campo_estatus', e.target.value)} />
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
                                <Input id="valor_estatus" value={data.valor_estatus} onChange={(e) => setData('valor_estatus', e.target.value)} />
                            </FormField>
                        </div>

                        <FormField label="Condiciones Extra (JSON)" htmlFor="condiciones_extra" error={errors.condiciones_extra}>
                            <textarea
                                id="condiciones_extra"
                                className="textarea textarea-bordered w-full font-mono text-xs"
                                value={data.condiciones_extra}
                                onChange={(e) => setData('condiciones_extra', e.target.value)}
                                rows={3}
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
                                <Input id="nav_href" value={data.nav_href} onChange={(e) => setData('nav_href', e.target.value)} />
                            </FormField>
                        </div>

                        <FormField label="Filter Href" htmlFor="filter_href" error={errors.filter_href}>
                            <Input id="filter_href" value={data.filter_href} onChange={(e) => setData('filter_href', e.target.value)} />
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
