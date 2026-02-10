import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, Pieza, ProdGrupoPrecio, ProdMarcaGrupo } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, TrashIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Props = {
    pieza: Pieza & {
        obra: Obra;
        marca_grupos: (ProdMarcaGrupo & { grupo_precio: ProdGrupoPrecio })[];
    };
    obras: Obra[];
    grupoPrecios: ProdGrupoPrecio[];
};

export default function PiezasEdit({ pieza, obras, grupoPrecios }: Props) {
    const [activeTab, setActiveTab] = useState<'datos' | 'grupo-precios'>('datos');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Piezas', href: '/admin/prod/piezas' },
        { title: pieza.marca, href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        obra_id: pieza.obra_id.toString(),
        marca: pieza.marca,
        descripcion: pieza.descripcion,
        longitud: pieza.longitud?.toString() ?? '',
        peso: pieza.peso.toString(),
        cantidad: pieza.cantidad.toString(),
        version: pieza.version.toString(),
    });

    const asignarForm = useForm({
        pieza_id: pieza.id.toString(),
        grupo_precio_id: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/piezas/${pieza.id}`);
    };

    const handleAsignarGrupo = (e: FormEvent) => {
        e.preventDefault();
        asignarForm.post('/admin/prod/marca-grupo', {
            preserveScroll: true,
            onSuccess: () => asignarForm.reset('grupo_precio_id'),
        });
    };

    const handleRemoveGrupo = (marcaGrupoId: number) => {
        router.delete(`/admin/prod/marca-grupo/${marcaGrupoId}`, { preserveScroll: true });
    };

    const gruposAsignados = pieza.marca_grupos?.map((mg) => mg.grupo_precio_id) ?? [];
    const gruposDisponibles = grupoPrecios.filter((gp) => !gruposAsignados.includes(gp.id));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar Pieza: ${pieza.marca}`} />

            <div className="space-y-6 p-6">
                <div className="tabs tabs-boxed w-3/4">
                    <button type="button" className={`tab ${activeTab === 'datos' ? 'tab-active' : ''}`} onClick={() => setActiveTab('datos')}>
                        Datos de la Pieza
                    </button>
                    <button
                        type="button"
                        className={`tab ${activeTab === 'grupo-precios' ? 'tab-active' : ''}`}
                        onClick={() => setActiveTab('grupo-precios')}
                    >
                        Grupo Precios ({pieza.marca_grupos?.length ?? 0})
                    </button>
                </div>

                {activeTab === 'datos' && (
                    <div className="w-3/4">
                        <div className="mb-6 flex items-center justify-between">
                            <h1 className="text-2xl font-semibold">Editar Pieza</h1>
                            <DeleteDialog
                                title="Eliminar pieza"
                                description={`Eliminar la pieza "${pieza.marca}"? Esta accion no se puede deshacer.`}
                                deleteUrl={`/admin/prod/piezas/${pieza.id}`}
                            />
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Obra" htmlFor="obra_id" error={errors.obra_id} required>
                                <Select
                                    id="obra_id"
                                    value={data.obra_id}
                                    onValueChange={(value) => setData('obra_id', value)}
                                    placeholder="Seleccionar obra"
                                >
                                    {obras.map((obra) => (
                                        <option key={obra.id} value={obra.id}>
                                            {obra.no} - {obra.descripcion}
                                        </option>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Marca" htmlFor="marca" error={errors.marca} required>
                                <Input id="marca" value={data.marca} onChange={(e) => setData('marca', e.target.value)} />
                            </FormField>

                            <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                                <Input id="descripcion" value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
                            </FormField>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Longitud" htmlFor="longitud" error={errors.longitud}>
                                    <Input
                                        id="longitud"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.longitud}
                                        onChange={(e) => setData('longitud', e.target.value)}
                                    />
                                </FormField>

                                <FormField label="Peso (kg)" htmlFor="peso" error={errors.peso} required>
                                    <Input
                                        id="peso"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.peso}
                                        onChange={(e) => setData('peso', e.target.value)}
                                    />
                                </FormField>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <FormField label="Cantidad" htmlFor="cantidad" error={errors.cantidad} required>
                                    <Input
                                        id="cantidad"
                                        type="number"
                                        min="1"
                                        value={data.cantidad}
                                        onChange={(e) => setData('cantidad', e.target.value)}
                                    />
                                </FormField>

                                <FormField label="Version" htmlFor="version" error={errors.version}>
                                    <Input
                                        id="version"
                                        type="number"
                                        min="1"
                                        value={data.version}
                                        onChange={(e) => setData('version', e.target.value)}
                                    />
                                </FormField>
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/prod/piezas">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {activeTab === 'grupo-precios' && (
                    <div className="w-3/4 space-y-6">
                        <h2 className="text-lg font-semibold">Grupos de Precios Asignados</h2>

                        {pieza.marca_grupos && pieza.marca_grupos.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Grupo Precio</th>
                                            <th className="text-right">Precio ($/kg)</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {pieza.marca_grupos.map((mg) => (
                                            <tr key={mg.id}>
                                                <td>{mg.grupo_precio.descripcion}</td>
                                                <td className="text-right font-mono">
                                                    ${Number(mg.grupo_precio.precio).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                                                </td>
                                                <td>
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => handleRemoveGrupo(mg.id)}>
                                                        <TrashIcon className="size-4 text-error" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No hay grupos de precios asignados.</p>
                        )}

                        {gruposDisponibles.length > 0 && (
                            <>
                                <div className="divider" />
                                <h2 className="text-lg font-semibold">Asignar Grupo de Precios</h2>
                                <form onSubmit={handleAsignarGrupo} className="flex items-end gap-4">
                                    <FormField label="Grupo Precio" htmlFor="grupo_precio_id">
                                        <Select
                                            id="grupo_precio_id"
                                            value={asignarForm.data.grupo_precio_id}
                                            onValueChange={(value) => asignarForm.setData('grupo_precio_id', value)}
                                            placeholder="Seleccionar grupo"
                                        >
                                            {gruposDisponibles.map((gp) => (
                                                <option key={gp.id} value={gp.id}>
                                                    {gp.descripcion} (${Number(gp.precio).toFixed(2)}/kg)
                                                </option>
                                            ))}
                                        </Select>
                                    </FormField>
                                    <Button type="submit" disabled={asignarForm.processing || !asignarForm.data.grupo_precio_id}>
                                        {asignarForm.processing ? <Loader2Icon className="size-4 animate-spin" /> : <PlusIcon className="size-4" />}
                                        Asignar
                                    </Button>
                                </form>
                            </>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
