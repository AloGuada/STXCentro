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

type ObraWithPiezas = Obra & { piezas: Pieza[] };

type Props = {
    grupoPrecio: ProdGrupoPrecio & {
        marca_grupos: (ProdMarcaGrupo & { pieza: Pieza & { obra: Obra } })[];
    };
    obras: ObraWithPiezas[];
};

export default function GrupoPreciosEdit({ grupoPrecio, obras }: Props) {
    const [activeTab, setActiveTab] = useState<'datos' | 'piezas'>('datos');
    const [selectedObraId, setSelectedObraId] = useState('');
    const [selectedPiezaIds, setSelectedPiezaIds] = useState<number[]>([]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Grupo Precios', href: '/admin/prod/grupo-precios' },
        { title: grupoPrecio.descripcion, href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        descripcion: grupoPrecio.descripcion,
        precio: grupoPrecio.precio.toString(),
    });

    const assignForm = useForm<{ pieza_ids: number[] }>({
        pieza_ids: [],
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/prod/grupo-precios/${grupoPrecio.id}`);
    };

    const handleAssign = (e: FormEvent) => {
        e.preventDefault();
        assignForm.transform(() => ({ pieza_ids: selectedPiezaIds }));
        assignForm.post(`/admin/prod/grupo-precios/${grupoPrecio.id}/assign-piezas`, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedPiezaIds([]);
            },
        });
    };

    const handleRemovePieza = (marcaGrupoId: number) => {
        router.delete(`/admin/prod/marca-grupo/${marcaGrupoId}`, { preserveScroll: true });
    };

    const togglePieza = (piezaId: number) => {
        setSelectedPiezaIds((prev) => (prev.includes(piezaId) ? prev.filter((id) => id !== piezaId) : [...prev, piezaId]));
    };

    const toggleAllPiezas = (piezas: Pieza[]) => {
        const available = piezas.filter((p) => !assignedPiezaIds.includes(p.id));
        const allSelected = available.every((p) => selectedPiezaIds.includes(p.id));
        if (allSelected) {
            setSelectedPiezaIds((prev) => prev.filter((id) => !available.some((p) => p.id === id)));
        } else {
            setSelectedPiezaIds((prev) => [...new Set([...prev, ...available.map((p) => p.id)])]);
        }
    };

    const assignedPiezaIds = grupoPrecio.marca_grupos?.map((mg) => mg.pieza_id) ?? [];
    const selectedObra = obras.find((o) => o.id.toString() === selectedObraId);
    const obraPiezas = selectedObra?.piezas ?? [];
    const availablePiezas = obraPiezas.filter((p) => !assignedPiezaIds.includes(p.id));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar: ${grupoPrecio.descripcion}`} />

            <div className="space-y-6 p-6">
                <div className="tabs tabs-boxed w-3/4">
                    <button type="button" className={`tab ${activeTab === 'datos' ? 'tab-active' : ''}`} onClick={() => setActiveTab('datos')}>
                        Datos
                    </button>
                    <button type="button" className={`tab ${activeTab === 'piezas' ? 'tab-active' : ''}`} onClick={() => setActiveTab('piezas')}>
                        Piezas ({grupoPrecio.marca_grupos?.length ?? 0})
                    </button>
                </div>

                {activeTab === 'datos' && (
                    <div className="w-3/4">
                        <div className="mb-6 flex items-center justify-between">
                            <h1 className="text-2xl font-semibold">Editar Grupo de Precios</h1>
                            <DeleteDialog
                                title="Eliminar grupo de precios"
                                description={`Eliminar "${grupoPrecio.descripcion}"? Esta accion no se puede deshacer.`}
                                deleteUrl={`/admin/prod/grupo-precios/${grupoPrecio.id}`}
                            />
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Descripcion" htmlFor="descripcion" error={errors.descripcion} required>
                                <Input
                                    id="descripcion"
                                    value={data.descripcion}
                                    onChange={(e) => setData('descripcion', e.target.value)}
                                    placeholder="Nombre del grupo de precios"
                                />
                            </FormField>

                            <FormField label="Precio ($/kg)" htmlFor="precio" error={errors.precio} required>
                                <Input
                                    id="precio"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.precio}
                                    onChange={(e) => setData('precio', e.target.value)}
                                    placeholder="0.00"
                                />
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/prod/grupo-precios">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {activeTab === 'piezas' && (
                    <div className="space-y-6">
                        {/* Piezas asignadas */}
                        <h2 className="text-lg font-semibold">Piezas Asignadas</h2>

                        {grupoPrecio.marca_grupos && grupoPrecio.marca_grupos.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Marca</th>
                                            <th>Descripcion</th>
                                            <th>Obra</th>
                                            <th className="text-right">Peso Unit. (kg)</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {grupoPrecio.marca_grupos.map((mg) => (
                                            <tr key={mg.id}>
                                                <td className="font-medium">{mg.pieza?.marca}</td>
                                                <td>{mg.pieza?.descripcion}</td>
                                                <td className="text-sm">{mg.pieza?.obra?.no}</td>
                                                <td className="text-right font-mono text-sm">
                                                    {Number(mg.pieza?.peso).toFixed(2)}
                                                </td>
                                                <td>
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => handleRemovePieza(mg.id)}>
                                                        <TrashIcon className="size-4 text-error" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No hay piezas asignadas a este grupo.</p>
                        )}

                        <div className="divider" />

                        {/* Asignar piezas por obra */}
                        <h2 className="text-lg font-semibold">Asignar Piezas por Obra</h2>

                        <div className="w-64">
                            <FormField label="Obra" htmlFor="obra_select">
                                <Select
                                    id="obra_select"
                                    value={selectedObraId}
                                    onValueChange={(value) => {
                                        setSelectedObraId(value);
                                        setSelectedPiezaIds([]);
                                    }}
                                    placeholder="Seleccionar obra"
                                >
                                    {obras.map((obra) => (
                                        <option key={obra.id} value={obra.id}>
                                            {obra.no} - {obra.descripcion}
                                        </option>
                                    ))}
                                </Select>
                            </FormField>
                        </div>

                        {selectedObra && (
                            <>
                                {availablePiezas.length > 0 ? (
                                    <form onSubmit={handleAssign}>
                                        <div className="overflow-x-auto">
                                            <table className="table w-full">
                                                <thead>
                                                    <tr>
                                                        <th>
                                                            <input
                                                                type="checkbox"
                                                                className="checkbox checkbox-sm"
                                                                checked={availablePiezas.length > 0 && availablePiezas.every((p) => selectedPiezaIds.includes(p.id))}
                                                                onChange={() => toggleAllPiezas(obraPiezas)}
                                                            />
                                                        </th>
                                                        <th>Marca</th>
                                                        <th>Descripcion</th>
                                                        <th className="text-right">Peso (kg)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {availablePiezas.map((pieza) => (
                                                        <tr key={pieza.id} className="hover cursor-pointer" onClick={() => togglePieza(pieza.id)}>
                                                            <td>
                                                                <input
                                                                    type="checkbox"
                                                                    className="checkbox checkbox-sm"
                                                                    checked={selectedPiezaIds.includes(pieza.id)}
                                                                    onChange={() => togglePieza(pieza.id)}
                                                                />
                                                            </td>
                                                            <td className="font-medium">{pieza.marca}</td>
                                                            <td>{pieza.descripcion}</td>
                                                            <td className="text-right font-mono text-sm">{Number(pieza.peso_unitario).toFixed(2)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>

                                        <div className="mt-4">
                                            <Button type="submit" disabled={assignForm.processing || selectedPiezaIds.length === 0}>
                                                {assignForm.processing ? <Loader2Icon className="size-4 animate-spin" /> : <PlusIcon className="size-4" />}
                                                Asignar {selectedPiezaIds.length} pieza{selectedPiezaIds.length !== 1 ? 's' : ''}
                                            </Button>
                                        </div>
                                    </form>
                                ) : (
                                    <p className="text-sm text-gray-500">
                                        Todas las piezas de esta obra ya estan asignadas a este grupo.
                                    </p>
                                )}
                            </>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
