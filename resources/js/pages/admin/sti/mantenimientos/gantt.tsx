import { GanttChart } from '@/components/sti/gantt-chart';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { StiEquipo, StiMantenimiento, StiTecnico } from '@/types/models';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeftIcon, ChevronRightIcon, ListIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'STI', href: '/admin/sti/equipos' },
    { title: 'Mantenimientos', href: '/admin/sti/mantenimientos' },
    { title: 'Vista Gantt', href: '/admin/sti/mantenimientos/gantt' },
];

type Props = {
    mantenimientos: StiMantenimiento[];
    equipos: Pick<StiEquipo, 'id' | 'descripcion'>[];
    tecnicos: Pick<StiTecnico, 'id' | 'descripcion'>[];
    filters: {
        mes: string;
        equipo_id?: string;
        tecnico_id?: string;
    };
};

export default function MantenimientosGantt({ mantenimientos, equipos, tecnicos, filters }: Props) {
    const currentMonth = filters.mes || new Date().toISOString().slice(0, 7);

    const handleFilterChange = (key: string, value: string) => {
        router.get(
            '/admin/sti/mantenimientos/gantt',
            { ...filters, [key]: value || undefined },
            { preserveState: true, preserveScroll: true }
        );
    };

    const navigateMonth = (direction: number) => {
        const [year, month] = currentMonth.split('-').map(Number);
        const date = new Date(year, month - 1 + direction, 1);
        const newMonth = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        handleFilterChange('mes', newMonth);
    };

    const handleItemClick = (id: number) => {
        router.visit(`/admin/sti/mantenimientos/${id}/edit`);
    };

    const monthName = new Date(currentMonth + '-01').toLocaleDateString('es-MX', {
        month: 'long',
        year: 'numeric',
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vista Gantt - Mantenimientos" />

            <div className="space-y-6 p-6">
                {/* Header con filtros */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Vista Gantt de Mantenimientos</CardTitle>
                        <Button variant="outline" asChild>
                            <Link href="/admin/sti/mantenimientos">
                                <ListIcon className="size-4" />
                                Vista Lista
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-center gap-4">
                            {/* Navegacion de mes */}
                            <div className="flex items-center gap-2">
                                <Button variant="outline" size="sm" onClick={() => navigateMonth(-1)}>
                                    <ChevronLeftIcon className="size-4" />
                                </Button>
                                <Input
                                    type="month"
                                    value={currentMonth}
                                    onChange={(e) => handleFilterChange('mes', e.target.value)}
                                    className="w-auto"
                                />
                                <Button variant="outline" size="sm" onClick={() => navigateMonth(1)}>
                                    <ChevronRightIcon className="size-4" />
                                </Button>
                            </div>

                            {/* Filtro por equipo */}
                            <Select
                                value={filters.equipo_id ?? ''}
                                onValueChange={(value) => handleFilterChange('equipo_id', value)}
                            >
                                <option value="">Todos los equipos</option>
                                {equipos.map((equipo) => (
                                    <option key={equipo.id} value={equipo.id}>
                                        {equipo.descripcion}
                                    </option>
                                ))}
                            </Select>

                            {/* Filtro por tecnico */}
                            <Select
                                value={filters.tecnico_id ?? ''}
                                onValueChange={(value) => handleFilterChange('tecnico_id', value)}
                            >
                                <option value="">Todos los tecnicos</option>
                                {tecnicos.map((tecnico) => (
                                    <option key={tecnico.id} value={tecnico.id}>
                                        {tecnico.descripcion}
                                    </option>
                                ))}
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                {/* Gantt Chart */}
                <Card>
                    <CardHeader>
                        <CardTitle className="capitalize">{monthName}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <GanttChart mantenimientos={mantenimientos} mes={currentMonth} onItemClick={handleItemClick} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
