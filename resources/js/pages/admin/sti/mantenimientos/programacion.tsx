import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { CRITICIDAD_COLORS, CRITICIDAD_LABELS, type StiCriticidad, type StiEquipo, type StiPlan } from '@/types/models';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeftIcon, ChevronRightIcon, ListIcon, Loader2Icon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Mantenimientos', href: '/admin/sti/mantenimientos' },
    { title: 'Programacion', href: '/admin/sti/mantenimientos/programacion' },
];

type EquipoConCount = StiEquipo & { mantenimientos_count: number };

type Props = {
    equipos: EquipoConCount[];
    planes: Pick<StiPlan, 'id' | 'descripcion' | 'periodicidad'>[];
    year: number;
};

export default function MantenimientosProgramacion({ equipos, planes, year }: Props) {
    const { flash } = usePage<{ flash: { success?: string } }>().props;
    const [selectedPlanId, setSelectedPlanId] = useState('');
    const [selectedEquipoId, setSelectedEquipoId] = useState('');
    const [fechaInicial, setFechaInicial] = useState(new Date().toISOString().split('T')[0]);
    const [processing, setProcessing] = useState(false);

    const navigateYear = (direction: number) => {
        router.get(
            '/admin/sti/mantenimientos/programacion',
            { year: year + direction },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleGenerar = () => {
        if (!selectedPlanId || !selectedEquipoId || !fechaInicial) return;

        setProcessing(true);
        router.post(
            '/admin/sti/mantenimientos/generar',
            {
                plan_id: selectedPlanId,
                equipo_id: selectedEquipoId,
                fecha_inicial: fechaInicial,
                year,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const canGenerar = selectedPlanId && selectedEquipoId && fechaInicial && !processing;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Programacion ${year}`} />

            <div className="space-y-6 p-6">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Programacion de Mantenimientos</CardTitle>
                        <Button variant="outline" asChild>
                            <Link href="/admin/sti/mantenimientos">
                                <ListIcon className="size-4" />
                                Vista Lista
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="flex items-center gap-2">
                                <Button variant="outline" size="sm" onClick={() => navigateYear(-1)}>
                                    <ChevronLeftIcon className="size-4" />
                                </Button>
                                <span className="min-w-20 text-center text-lg font-semibold">{year}</span>
                                <Button variant="outline" size="sm" onClick={() => navigateYear(1)}>
                                    <ChevronRightIcon className="size-4" />
                                </Button>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium">Plan</label>
                                <Select value={selectedPlanId} onValueChange={setSelectedPlanId} className="w-72">
                                    <option value="">Seleccionar plan...</option>
                                    {planes.map((plan) => (
                                        <option key={plan.id} value={plan.id}>
                                            {plan.descripcion} (cada {plan.periodicidad} dias)
                                        </option>
                                    ))}
                                </Select>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium">Equipo</label>
                                <Select value={selectedEquipoId} onValueChange={setSelectedEquipoId} className="w-72">
                                    <option value="">Seleccionar equipo...</option>
                                    {equipos.map((equipo) => (
                                        <option key={equipo.id} value={equipo.id}>
                                            {equipo.descripcion} {equipo.serie ? `(${equipo.serie})` : ''}
                                        </option>
                                    ))}
                                </Select>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium">Fecha Inicial</label>
                                <Input
                                    type="date"
                                    value={fechaInicial}
                                    onChange={(e) => setFechaInicial(e.target.value)}
                                    className="w-44"
                                />
                            </div>

                            <Button onClick={handleGenerar} disabled={!canGenerar}>
                                {processing && <Loader2Icon className="mr-1 size-4 animate-spin" />}
                                Generar
                            </Button>
                        </div>

                        {flash?.success && (
                            <div className="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">
                                {flash.success}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b bg-gray-50 dark:bg-gray-800">
                                        <th className="p-3 text-left">Equipo</th>
                                        <th className="p-3 text-left">Serie</th>
                                        <th className="p-3 text-center">Criticidad</th>
                                        <th className="p-3 text-center">Mant. en {year}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {equipos.map((equipo) => {
                                        const sinMant = equipo.mantenimientos_count === 0;
                                        return (
                                            <tr
                                                key={equipo.id}
                                                className={`border-b transition hover:bg-gray-50 dark:hover:bg-gray-800/50 ${sinMant ? 'bg-red-50 dark:bg-red-900/10' : ''}`}
                                            >
                                                <td className="p-3 font-medium">{equipo.descripcion}</td>
                                                <td className="p-3 text-sm text-gray-500">{equipo.serie ?? '-'}</td>
                                                <td className="p-3 text-center">
                                                    <span className={`badge badge-sm ${CRITICIDAD_COLORS[equipo.factor_criticidad as StiCriticidad]}`}>
                                                        {CRITICIDAD_LABELS[equipo.factor_criticidad as StiCriticidad]}
                                                    </span>
                                                </td>
                                                <td className="p-3 text-center">
                                                    <span className={`font-mono text-sm ${sinMant ? 'font-bold text-red-600' : ''}`}>
                                                        {equipo.mantenimientos_count}
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {equipos.length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="p-8 text-center text-gray-500">
                                                No hay equipos registrados.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
