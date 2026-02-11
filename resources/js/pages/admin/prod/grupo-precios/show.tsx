import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Concepto, Obra, ProdGrupoPrecio, ProdGrupoPrecioConcepto } from '@/types/models';
import { Head, router } from '@inertiajs/react';
import { TrashIcon } from 'lucide-react';
import { useState } from 'react';

type Props = {
    obra: Obra & { conceptos: Concepto[] };
    grupoPrecios: (ProdGrupoPrecio & { grupo_precio_conceptos_count: number })[];
    grupoPrecioConceptos: (ProdGrupoPrecioConcepto & { concepto: Concepto; grupo_precio: ProdGrupoPrecio })[];
};

export default function GrupoPreciosShow({ obra, grupoPrecios, grupoPrecioConceptos }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/cortes' },
        { title: 'Grupo Precios', href: '/admin/prod/grupo-precios' },
        { title: `Obra ${obra.no}`, href: `/admin/prod/grupo-precios/obra/${obra.id}` },
    ];

    const [selectedConcepto, setSelectedConcepto] = useState('');
    const [selectedGrupo, setSelectedGrupo] = useState('');

    const assignedConceptoIds = new Set(grupoPrecioConceptos.map((gpc) => gpc.concepto_id));
    const unassignedConceptos = obra.conceptos.filter((c) => !assignedConceptoIds.has(c.id));

    const handleAssign = () => {
        if (!selectedConcepto || !selectedGrupo) return;
        router.post('/admin/prod/grupo-precio-conceptos', {
            concepto_id: selectedConcepto,
            grupo_precio_id: selectedGrupo,
        }, {
            preserveScroll: true,
            onSuccess: () => { setSelectedConcepto(''); setSelectedGrupo(''); },
        });
    };

    const handleRemove = (id: number) => {
        router.delete(`/admin/prod/grupo-precio-conceptos/${id}`, { preserveScroll: true });
    };

    const fmt = (n: number) => Number(n).toLocaleString('es-MX', { minimumFractionDigits: 4, maximumFractionDigits: 4 });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Precios Obra ${obra.no}`} />

            <div className="p-6">
                <h1 className="mb-2 text-2xl font-semibold">Obra {obra.no} - {obra.descripcion}</h1>
                <p className="mb-6 text-sm text-gray-500">{obra.conceptos.length} conceptos</p>

                {/* Grupos de precio de esta obra */}
                <div className="mb-6">
                    <h2 className="mb-2 text-lg font-medium">Grupos de Precio</h2>
                    {grupoPrecios.length === 0 ? (
                        <p className="text-sm text-gray-500">No hay grupos de precio para esta obra.</p>
                    ) : (
                        <div className="space-y-1">
                            {grupoPrecios.map((gp) => (
                                <div key={gp.id} className="flex items-center justify-between rounded border p-2">
                                    <span className="font-medium">{gp.descripcion}</span>
                                    <div className="flex items-center gap-4 text-sm">
                                        <span className="font-mono">${fmt(gp.precio_kilo)}/kg</span>
                                        <span className="text-gray-500">{gp.grupo_precio_conceptos_count} conceptos</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Asignaciones */}
                <div className="mb-6">
                    <h2 className="mb-2 text-lg font-medium">Asignaciones</h2>
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="py-2">Concepto</th>
                                <th className="py-2">Grupo Precio</th>
                                <th className="py-2 text-right">$/kg</th>
                                <th className="py-2 w-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {grupoPrecioConceptos.map((gpc) => (
                                <tr key={gpc.id} className="border-b">
                                    <td className="py-2">{gpc.concepto?.marca} - {gpc.concepto?.descripcion}</td>
                                    <td className="py-2">{gpc.grupo_precio?.descripcion}</td>
                                    <td className="py-2 text-right font-mono">${fmt(gpc.grupo_precio?.precio_kilo ?? 0)}</td>
                                    <td className="py-2">
                                        <Button type="button" variant="ghost" size="icon" className="h-6 w-6" onClick={() => handleRemove(gpc.id)}>
                                            <TrashIcon className="size-3 text-red-500" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {grupoPrecioConceptos.length === 0 && (
                                <tr><td colSpan={4} className="py-4 text-center text-gray-500">Sin asignaciones</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Nueva asignacion */}
                {unassignedConceptos.length > 0 && grupoPrecios.length > 0 && (
                    <div>
                        <h3 className="mb-2 text-sm font-medium">Nueva Asignacion</h3>
                        <div className="flex items-end gap-2">
                            <div className="flex-1">
                                <Select
                                    value={selectedConcepto}
                                    onValueChange={setSelectedConcepto}
                                    placeholder="Concepto"
                                >
                                    {unassignedConceptos.map((c) => (
                                        <option key={c.id} value={c.id}>{c.marca} - {c.descripcion}</option>
                                    ))}
                                </Select>
                            </div>
                            <div className="w-48">
                                <Select
                                    value={selectedGrupo}
                                    onValueChange={setSelectedGrupo}
                                    placeholder="Grupo precio"
                                >
                                    {grupoPrecios.map((gp) => (
                                        <option key={gp.id} value={gp.id}>{gp.descripcion}</option>
                                    ))}
                                </Select>
                            </div>
                            <Button onClick={handleAssign} disabled={!selectedConcepto || !selectedGrupo}>
                                Asignar
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
