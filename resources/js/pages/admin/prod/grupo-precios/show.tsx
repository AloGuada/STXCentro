import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Obra, Pieza, ProdGrupoPrecio, ProdMarcaGrupo } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, TrashIcon } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Props = {
    obra: Obra & { piezas: Pieza[] };
    grupoPrecios: (ProdGrupoPrecio & { marca_grupos_count: number })[];
    marcaGrupos: (ProdMarcaGrupo & { pieza: Pieza; grupo_precio: ProdGrupoPrecio })[];
};

export default function GrupoPreciosShow({ obra, grupoPrecios, marcaGrupos }: Props) {
    const [selectedGrupoId, setSelectedGrupoId] = useState('');
    const [selectedPiezaIds, setSelectedPiezaIds] = useState<number[]>([]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Grupo Precios', href: '/admin/prod/grupo-precios' },
        { title: `Obra ${obra.no}`, href: '#' },
    ];

    const assignForm = useForm<{ pieza_ids: number[] }>({
        pieza_ids: [],
    });

    const handleAssign = (e: FormEvent) => {
        e.preventDefault();
        if (!selectedGrupoId || selectedPiezaIds.length === 0) return;

        assignForm.transform(() => ({ pieza_ids: selectedPiezaIds }));
        assignForm.post(`/admin/prod/grupo-precios/${selectedGrupoId}/assign-piezas`, {
            preserveScroll: true,
            onSuccess: () => setSelectedPiezaIds([]),
        });
    };

    const handleRemoveMarcaGrupo = (marcaGrupoId: number) => {
        router.delete(`/admin/prod/marca-grupo/${marcaGrupoId}`, { preserveScroll: true });
    };

    const assignedPiezaIds = marcaGrupos
        .filter((mg) => mg.grupo_precio_id.toString() === selectedGrupoId)
        .map((mg) => mg.pieza_id);

    const availablePiezas = obra.piezas?.filter((p) => !assignedPiezaIds.includes(p.id)) ?? [];

    const togglePieza = (piezaId: number) => {
        setSelectedPiezaIds((prev) => (prev.includes(piezaId) ? prev.filter((id) => id !== piezaId) : [...prev, piezaId]));
    };

    const toggleAllPiezas = () => {
        const allSelected = availablePiezas.every((p) => selectedPiezaIds.includes(p.id));
        if (allSelected) {
            setSelectedPiezaIds((prev) => prev.filter((id) => !availablePiezas.some((p) => p.id === id)));
        } else {
            setSelectedPiezaIds((prev) => [...new Set([...prev, ...availablePiezas.map((p) => p.id)])]);
        }
    };

    // Agrupar marcaGrupos por grupo_precio
    const marcaGruposByGrupo = grupoPrecios
        .map((gp) => ({
            grupoPrecio: gp,
            items: marcaGrupos.filter((mg) => mg.grupo_precio_id === gp.id),
        }))
        .filter((g) => g.items.length > 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Grupo Precios - Obra ${obra.no}`} />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Obra {obra.no}</h1>
                    <p className="text-sm text-gray-500">{obra.descripcion}</p>
                </div>

                {/* Asignaciones existentes */}
                {marcaGruposByGrupo.length > 0 ? (
                    marcaGruposByGrupo.map((group) => (
                        <div key={group.grupoPrecio.id}>
                            <h3 className="mb-2 text-sm font-semibold text-gray-500">
                                {group.grupoPrecio.descripcion} - ${Number(group.grupoPrecio.precio).toFixed(2)}/kg
                            </h3>
                            <div className="overflow-x-auto">
                                <table className="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Marca</th>
                                            <th>Descripcion</th>
                                            <th className="text-right">Peso (kg)</th>
                                            <th className="text-right">Cantidad</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {group.items.map((mg) => (
                                            <tr key={mg.id}>
                                                <td className="font-medium">{mg.pieza?.marca}</td>
                                                <td>{mg.pieza?.descripcion}</td>
                                                <td className="text-right font-mono text-sm">{Number(mg.pieza?.peso).toFixed(2)}</td>
                                                <td className="text-right font-mono text-sm">{mg.pieza?.cantidad}</td>
                                                <td>
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => handleRemoveMarcaGrupo(mg.id)}>
                                                        <TrashIcon className="size-4 text-error" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ))
                ) : (
                    <p className="text-sm text-gray-500">No hay piezas asignadas a grupos de precios en esta obra.</p>
                )}

                {/* Asignar piezas */}
                <div className="divider" />
                <h2 className="text-lg font-semibold">Asignar Piezas a Grupo de Precios</h2>

                <div className="w-64">
                    <FormField label="Grupo de Precios" htmlFor="gp_select">
                        <Select
                            id="gp_select"
                            value={selectedGrupoId}
                            onValueChange={(value) => {
                                setSelectedGrupoId(value);
                                setSelectedPiezaIds([]);
                            }}
                            placeholder="Seleccionar grupo"
                        >
                            {grupoPrecios.map((gp) => (
                                <option key={gp.id} value={gp.id}>
                                    {gp.descripcion} (${Number(gp.precio).toFixed(2)}/kg)
                                </option>
                            ))}
                        </Select>
                    </FormField>
                </div>

                {selectedGrupoId && (
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
                                                        onChange={toggleAllPiezas}
                                                    />
                                                </th>
                                                <th>Marca</th>
                                                <th>Descripcion</th>
                                                <th className="text-right">Peso (kg)</th>
                                                <th className="text-right">Cantidad</th>
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
                                                    <td className="text-right font-mono text-sm">{Number(pieza.peso).toFixed(2)}</td>
                                                    <td className="text-right font-mono text-sm">{pieza.cantidad}</td>
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
                            <p className="text-sm text-gray-500">Todas las piezas de esta obra ya estan asignadas a este grupo.</p>
                        )}
                    </>
                )}

                <div className="flex justify-start pt-4">
                    <Button variant="outline" asChild>
                        <Link href="/admin/prod/grupo-precios">Volver</Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
