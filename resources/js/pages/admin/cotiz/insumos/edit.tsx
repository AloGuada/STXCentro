import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { CotizCategoriaTarjeta, CotizCentroCosto, CotizInsumo, CotizUnidad } from '@/types/models';
import { Head, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Cotización', href: '/admin/cotiz/insumos' },
    { title: 'Insumos', href: '/admin/cotiz/insumos' },
    { title: 'Editar', href: '/admin/cotiz/insumos' },
];

type Props = {
    insumo: CotizInsumo;
    unidades: CotizUnidad[];
    centrosCosto: CotizCentroCosto[];
    categoriasTarjeta: CotizCategoriaTarjeta[];
};

export default function InsumosEdit({ insumo, unidades, centrosCosto, categoriasTarjeta }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        descripcion: insumo.descripcion,
        codigo_stumis: insumo.codigo_stumis ?? '',
        unidad_id: String(insumo.unidad_id),
        precio_unitario: insumo.precio_unitario,
        peso_lineal: insumo.peso_lineal ?? '',
        peso_default: insumo.peso_default ?? '',
        centro_costo_id: String(insumo.centro_costo_id),
        categoria_tarjeta_id: insumo.categoria_tarjeta_id ? String(insumo.categoria_tarjeta_id) : '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/cotiz/insumos/${insumo.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar — ${insumo.descripcion}`} />

            <div className="p-6">
                <div className="w-3/4">
                    <h1 className="mb-6 text-2xl font-semibold">Editar insumo</h1>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <FormField label="Descripción" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input id="descripcion" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Código STUMIS" htmlFor="codigo_stumis" error={errors.codigo_stumis}>
                                <Input id="codigo_stumis" value={data.codigo_stumis} onChange={(e) => setData('codigo_stumis', e.target.value)} />
                            </FormField>
                            <FormField label="Unidad" htmlFor="unidad_id" error={errors.unidad_id} required>
                                <Select id="unidad_id" value={data.unidad_id} onValueChange={(value) => setData('unidad_id', value)}>
                                    <option value="">Seleccionar unidad</option>
                                    {unidades.map((u) => (
                                        <option key={u.id} value={u.id}>{u.descripcion}</option>
                                    ))}
                                </Select>
                            </FormField>
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <FormField label="Precio unitario" htmlFor="precio_unitario" error={errors.precio_unitario} required>
                                <Input id="precio_unitario" type="number" step="0.0001" min="0" value={data.precio_unitario} onChange={(e) => setData('precio_unitario', e.target.value)} />
                            </FormField>
                            <FormField label="Peso ML/M²" htmlFor="peso_lineal" error={errors.peso_lineal}>
                                <Input id="peso_lineal" type="number" step="0.000001" min="0" value={data.peso_lineal} onChange={(e) => setData('peso_lineal', e.target.value)} />
                            </FormField>
                            <FormField label="Peso default" htmlFor="peso_default" error={errors.peso_default}>
                                <Input id="peso_default" type="number" step="0.000001" min="0" value={data.peso_default} onChange={(e) => setData('peso_default', e.target.value)} />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Centro de costo" htmlFor="centro_costo_id" error={errors.centro_costo_id} required>
                                <Select id="centro_costo_id" value={data.centro_costo_id} onValueChange={(value) => setData('centro_costo_id', value)}>
                                    <option value="">Seleccionar centro de costo</option>
                                    {centrosCosto.map((c) => (
                                        <option key={c.id} value={c.id}>{c.cod_coste} — {c.concepto}</option>
                                    ))}
                                </Select>
                            </FormField>
                            <FormField label="Categoría de tarjeta" htmlFor="categoria_tarjeta_id" error={errors.categoria_tarjeta_id}>
                                <Select id="categoria_tarjeta_id" value={data.categoria_tarjeta_id} onValueChange={(value) => setData('categoria_tarjeta_id', value)}>
                                    <option value="">(sin clasificar)</option>
                                    {categoriasTarjeta.map((c) => (
                                        <option key={c.id} value={c.id}>{c.descripcion}</option>
                                    ))}
                                </Select>
                            </FormField>
                        </div>

                        <div className="flex items-center gap-2 pt-2">
                            <Button type="submit" variant="primary" disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Guardar cambios
                            </Button>
                            <ButtonLink variant="ghost" href="/admin/cotiz/insumos">
                                Cancelar
                            </ButtonLink>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
