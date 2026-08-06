import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { etiquetaDePieza } from '@/lib/prod/piezas';
import type { Concepto, ProdCatalogo, ProdCategoria } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

type Props = {
    concepto: Concepto & { catalogo: ProdCatalogo };
    categorias: ProdCategoria[];
};

export default function ConceptosEdit({ concepto, categorias }: Props) {
    const catalogo = concepto.catalogo;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Catalogos', href: '/admin/prod/catalogos' },
        { title: `${catalogo.nombre} v${catalogo.version}`, href: `/admin/prod/catalogos/${catalogo.id}` },
        {
            title: etiquetaDePieza(concepto.marca, concepto.lote),
            href: `/admin/prod/conceptos/${concepto.id}/edit`,
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        marca: concepto.marca,
        lote: concepto.lote ?? '',
        descripcion: concepto.descripcion,
        cantidad: String(concepto.cantidad),
        peso_unitario: String(concepto.peso_unitario),
        longitud: concepto.longitud != null ? String(concepto.longitud) : '',
        categoria_id: concepto.categoria_id != null ? String(concepto.categoria_id) : '',
        version: String(concepto.version),
        activo: concepto.activo,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/conceptos/${concepto.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${etiquetaDePieza(concepto.marca, concepto.lote)}`} />

            <div className="p-6">
                <div className="w-full max-w-2xl">
                    <h1 className="text-2xl font-semibold">Editar marca</h1>
                    <p className="mb-6 mt-1 text-sm text-base-content/60">
                        {catalogo.nombre} v{catalogo.version}
                        {catalogo.obra ? ` · Obra ${catalogo.obra.no} - ${catalogo.obra.descripcion}` : ''}
                    </p>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Marca" htmlFor="marca" error={errors.marca} required>
                                <Input
                                    id="marca"
                                    value={data.marca}
                                    onChange={(e) => setData('marca', e.target.value)}
                                    error={!!errors.marca}
                                />
                            </FormField>

                            <FormField
                                label="Lote"
                                htmlFor="lote"
                                error={errors.lote}
                                description="Junto con la marca identifica el modelo. Dejala vacia si la obra no maneja lotes."
                            >
                                <Input
                                    id="lote"
                                    value={data.lote}
                                    onChange={(e) => setData('lote', e.target.value)}
                                    error={!!errors.lote}
                                    placeholder="Sin lote"
                                />
                            </FormField>
                        </div>

                        <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                            <Input
                                id="descripcion"
                                value={data.descripcion}
                                onChange={(e) => setData('descripcion', e.target.value)}
                                error={!!errors.descripcion}
                            />
                        </FormField>

                        <FormField label="Categoria" htmlFor="categoria_id" error={errors.categoria_id} required>
                            <Select
                                value={data.categoria_id}
                                onValueChange={(v) => setData('categoria_id', v)}
                                placeholder="Selecciona categoria"
                                error={!!errors.categoria_id}
                            >
                                {categorias.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.nombre}
                                    </SelectItem>
                                ))}
                            </Select>
                        </FormField>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Cantidad (piezas)" htmlFor="cantidad" error={errors.cantidad}>
                                <Input
                                    id="cantidad"
                                    type="number"
                                    min="0"
                                    value={data.cantidad}
                                    onChange={(e) => setData('cantidad', e.target.value)}
                                    error={!!errors.cantidad}
                                />
                            </FormField>

                            <FormField label="Longitud (mm)" htmlFor="longitud" error={errors.longitud} required>
                                <Input
                                    id="longitud"
                                    type="number"
                                    min="0"
                                    value={data.longitud}
                                    onChange={(e) => setData('longitud', e.target.value)}
                                    error={!!errors.longitud}
                                />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Peso Unitario (kg)" htmlFor="peso_unitario" error={errors.peso_unitario} required>
                                <Input
                                    id="peso_unitario"
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    value={data.peso_unitario}
                                    onChange={(e) => setData('peso_unitario', e.target.value)}
                                    error={!!errors.peso_unitario}
                                />
                            </FormField>

                            <FormField label="Version" htmlFor="version" error={errors.version}>
                                <Input
                                    id="version"
                                    type="number"
                                    min="1"
                                    value={data.version}
                                    onChange={(e) => setData('version', e.target.value)}
                                    error={!!errors.version}
                                />
                            </FormField>
                        </div>

                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                className="checkbox checkbox-sm"
                                checked={data.activo}
                                onChange={(e) => setData('activo', e.target.checked)}
                            />
                            <span className="text-sm">Activo</span>
                        </label>

                        <div className="flex items-center justify-between">
                            <DeleteDialog
                                title="Eliminar pieza"
                                description={`¿Eliminar la pieza "${etiquetaDePieza(concepto.marca, concepto.lote)}"? Esta acción no se puede deshacer.`}
                                deleteUrl={`/admin/prod/conceptos/${concepto.id}`}
                            />
                            <div className="flex gap-2">
                                <Button variant="outline" asChild>
                                    <Link href={`/admin/prod/catalogos/${catalogo.id}`}>Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
