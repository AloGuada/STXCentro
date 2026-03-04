import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { StiEquipo, StiMantenimiento, StiPlan } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeftIcon, ChevronRightIcon, DownloadIcon, ListIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Mantenimientos', href: '/admin/sti/mantenimientos' },
    { title: 'Vista Gantt Anual', href: '/admin/sti/mantenimientos-gantt-anual' },
];

const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

type Props = {
    mantenimientos: (StiMantenimiento & { equipo?: StiEquipo; plan?: StiPlan })[];
    equipos: Pick<StiEquipo, 'id' | 'descripcion'>[];
    planes: Pick<StiPlan, 'id' | 'descripcion'>[];
    year: number;
    filters: {
        year?: string;
        equipo_id?: string;
        plan_id?: string;
    };
};

export default function MantenimientosGanttAnual({ mantenimientos, equipos, planes, year, filters }: Props) {
    const handleFilterChange = (key: string, value: string) => {
        router.get(
            '/admin/sti/mantenimientos-gantt-anual',
            { ...filters, [key]: value || undefined },
            { preserveState: true, preserveScroll: true },
        );
    };

    const navigateYear = (direction: number) => {
        handleFilterChange('year', String(year + direction));
    };

    const handleExport = () => {
        const params = new URLSearchParams();
        params.set('year', String(year));
        if (filters.equipo_id) params.set('equipo_id', filters.equipo_id);
        if (filters.plan_id) params.set('plan_id', filters.plan_id);
        window.open(`/admin/sti/mantenimientos-gantt-anual/exportar?${params.toString()}`, '_blank');
    };

    // Agrupar mantenimientos por equipo
    const mantenimientosPorEquipo = mantenimientos.reduce(
        (acc, mant) => {
            const equipoId = mant.equipo_id;
            if (!acc[equipoId]) {
                acc[equipoId] = {
                    equipo: mant.equipo,
                    mantenimientos: [],
                };
            }
            acc[equipoId].mantenimientos.push(mant);
            return acc;
        },
        {} as Record<number, { equipo?: StiEquipo; mantenimientos: StiMantenimiento[] }>,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Vista Gantt Anual ${year}`} />

            <div className="space-y-6 p-6">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Vista Gantt Anual</CardTitle>
                        <div className="flex gap-2">
                            <Button variant="outline" onClick={handleExport}>
                                <DownloadIcon className="size-4" />
                                Exportar PDF
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href="/admin/sti/mantenimientos">
                                    <ListIcon className="size-4" />
                                    Vista Lista
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-center gap-4">
                            <div className="flex items-center gap-2">
                                <Button variant="outline" size="sm" onClick={() => navigateYear(-1)}>
                                    <ChevronLeftIcon className="size-4" />
                                </Button>
                                <span className="min-w-20 text-center text-lg font-semibold">{year}</span>
                                <Button variant="outline" size="sm" onClick={() => navigateYear(1)}>
                                    <ChevronRightIcon className="size-4" />
                                </Button>
                            </div>

                            <Select value={filters.equipo_id ?? ''} onValueChange={(value) => handleFilterChange('equipo_id', value)}>
                                <option value="">Todos los equipos</option>
                                {equipos.map((equipo) => (
                                    <option key={equipo.id} value={equipo.id}>
                                        {equipo.descripcion}
                                    </option>
                                ))}
                            </Select>

                            <Select value={filters.plan_id ?? ''} onValueChange={(value) => handleFilterChange('plan_id', value)}>
                                <option value="">Todos los planes</option>
                                {planes.map((plan) => (
                                    <option key={plan.id} value={plan.id}>
                                        {plan.descripcion}
                                    </option>
                                ))}
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1000px]">
                                <thead>
                                    <tr className="border-b bg-gray-50 dark:bg-gray-800">
                                        <th className="sticky left-0 z-10 min-w-48 bg-gray-50 p-3 text-left dark:bg-gray-800">Equipo</th>
                                        <th className="min-w-36 p-3 text-left text-sm font-medium">Asignado a</th>
                                        <th className="min-w-32 p-3 text-left text-sm font-medium">Departamento</th>
                                        {MESES.map((mes) => (
                                            <th key={mes} className="min-w-20 p-2 text-center text-sm font-medium">
                                                {mes}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {Object.entries(mantenimientosPorEquipo).map(([equipoId, { equipo, mantenimientos: mants }]) => {
                                        const asignacion = equipo?.asignaciones?.[0];
                                        return (
                                            <tr key={equipoId} className="border-b hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                <td className="sticky left-0 z-10 bg-white p-3 dark:bg-gray-900">
                                                    <div className="font-medium">{equipo?.descripcion ?? 'Equipo'}</div>
                                                    <div className="text-xs text-gray-500">{mants.length} mantenimientos</div>
                                                </td>
                                                <td className="p-3 text-sm">{asignacion?.empleado ?? '-'}</td>
                                                <td className="p-3 text-sm">{asignacion?.departamento?.descripcion ?? '-'}</td>
                                                {MESES.map((_, mesIndex) => {
                                                    const mantsDelMes = mants.filter((m) => {
                                                        const fecha = new Date(m.fecha_programada);
                                                        return fecha.getMonth() === mesIndex;
                                                    });
                                                    return (
                                                        <td key={mesIndex} className="p-1 text-center">
                                                            <div className="flex flex-wrap justify-center gap-1">
                                                                {mantsDelMes.map((mant) => {
                                                                    const fecha = new Date(mant.fecha_programada);
                                                                    const dia = fecha.getDate();
                                                                    return (
                                                                        <Link
                                                                            key={mant.id}
                                                                            href={`/admin/sti/mantenimientos/${mant.id}/edit`}
                                                                            className={`flex size-7 items-center justify-center rounded text-xs font-medium transition hover:scale-110 ${
                                                                                mant.status === 'realizado'
                                                                                    ? 'bg-green-500 text-white'
                                                                                    : 'bg-orange-400 text-white'
                                                                            }`}
                                                                            title={`${mant.descripcion ?? 'Mantenimiento'} - ${fecha.toLocaleDateString('es-MX')}`}
                                                                        >
                                                                            {dia}
                                                                        </Link>
                                                                    );
                                                                })}
                                                            </div>
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        );
                                    })}
                                    {Object.keys(mantenimientosPorEquipo).length === 0 && (
                                        <tr>
                                            <td colSpan={15} className="p-8 text-center text-gray-500">
                                                No hay mantenimientos programados para este año.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex items-center gap-4 text-sm">
                    <div className="flex items-center gap-2">
                        <div className="size-4 rounded bg-orange-400"></div>
                        <span>Pendiente</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="size-4 rounded bg-green-500"></div>
                        <span>Realizado</span>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
