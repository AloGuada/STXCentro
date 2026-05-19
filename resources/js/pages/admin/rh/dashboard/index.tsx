import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento } from '@/types/models';
import { Head } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RH', href: '/admin/rh/puestos' },
    { title: 'Organigrama', href: '/admin/rh/dashboard' },
];

type Empleado = {
    nombre: string;
    apellido: string;
};

type OrgNodo = {
    id: number;
    nombre: string;
    codigo: string | null;
    departamento: string;
    departamento_id: number | null;
    empleados_activos: number;
    empleados: Empleado[];
    children: OrgNodo[];
};

type Kpis = {
    total_puestos: number;
    total_empleados: number;
    puestos_vacantes: number;
    total_departamentos: number;
};

type Props = {
    tree: OrgNodo[];
    kpis: Kpis;
    departamentos: Departamento[];
};

const DEPT_BG = [
    'bg-sky-100 dark:bg-sky-900/40',
    'bg-amber-100 dark:bg-amber-900/40',
    'bg-emerald-100 dark:bg-emerald-900/40',
    'bg-violet-100 dark:bg-violet-900/40',
    'bg-rose-100 dark:bg-rose-900/40',
    'bg-cyan-100 dark:bg-cyan-900/40',
    'bg-orange-100 dark:bg-orange-900/40',
    'bg-teal-100 dark:bg-teal-900/40',
    'bg-indigo-100 dark:bg-indigo-900/40',
    'bg-lime-100 dark:bg-lime-900/40',
];

function buildDeptColorMap(departamentos: Departamento[]): Record<number, string> {
    const map: Record<number, string> = {};
    departamentos.forEach((d, i) => {
        map[d.id] = DEPT_BG[i % DEPT_BG.length];
    });
    return map;
}

function OrgTreeNode({
    node,
    deptColors,
    level = 0,
}: {
    node: OrgNodo;
    deptColors: Record<number, string>;
    level?: number;
}) {
    const [expanded, setExpanded] = useState(level < 2);
    const [showEmpleados, setShowEmpleados] = useState(false);
    const hasChildren = node.children.length > 0;
    const bg = node.departamento_id ? (deptColors[node.departamento_id] ?? 'bg-base-100') : 'bg-base-100';

    return (
        <div className="flex flex-col items-center">
            <div
                className={`border-base-300 ${bg} w-52 cursor-pointer rounded border`}
                onClick={() => node.empleados.length > 0 && setShowEmpleados(!showEmpleados)}
            >
                <div className="p-2.5">
                    <div className="flex items-start justify-between gap-1">
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium leading-tight">{node.nombre}</p>
                            {node.codigo && <p className="text-base-content/40 text-xs">{node.codigo}</p>}
                        </div>
                        {hasChildren && (
                            <button
                                className="btn btn-ghost btn-xs shrink-0"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    setExpanded(!expanded);
                                }}
                            >
                                {expanded ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
                            </button>
                        )}
                    </div>

                    <p className="text-base-content/50 mt-1 text-xs">{node.departamento}</p>

                    <p className={`mt-1 text-xs ${node.empleados_activos === 0 ? 'text-base-content/30' : 'text-base-content/60'}`}>
                        {node.empleados_activos} {node.empleados_activos === 1 ? 'persona' : 'personas'}
                    </p>

                    {showEmpleados && node.empleados.length > 0 && (
                        <div className="border-base-200 mt-2 border-t pt-1.5">
                            {node.empleados.map((e, i) => (
                                <p key={i} className="text-base-content/50 text-xs leading-relaxed">
                                    {e.nombre} {e.apellido}
                                </p>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {hasChildren && expanded && (
                <>
                    <div className="bg-base-content/30 h-5 w-0.5" />
                    <div className="relative flex items-start">
                        {node.children.length > 1 && (
                            <div
                                className="bg-base-content/30 absolute top-0 h-0.5"
                                style={{
                                    left: `${100 / (node.children.length * 2)}%`,
                                    right: `${100 / (node.children.length * 2)}%`,
                                }}
                            />
                        )}
                        {node.children.map((child) => (
                            <div key={child.id} className="flex flex-col items-center px-2">
                                <div className="bg-base-content/30 mx-auto h-5 w-0.5" />
                                <OrgTreeNode node={child} deptColors={deptColors} level={level + 1} />
                            </div>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

export default function DashboardIndex({ tree, kpis, departamentos }: Props) {
    const deptColors = buildDeptColorMap(departamentos);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organigrama - RH" />

            <div className="space-y-4 p-4">
                {/* KPIs */}
                <div className="text-base-content/60 flex flex-wrap gap-6 text-sm">
                    <span><strong className="text-base-content">{kpis.total_puestos}</strong> puestos</span>
                    <span><strong className="text-base-content">{kpis.total_empleados}</strong> empleados activos</span>
                    <span><strong className="text-base-content">{kpis.puestos_vacantes}</strong> vacantes</span>
                    <span><strong className="text-base-content">{kpis.total_departamentos}</strong> departamentos</span>
                </div>

                {/* Legend */}
                {departamentos.length > 1 && (
                    <div className="flex flex-wrap gap-4 text-xs">
                        {departamentos.map((dept) => (
                            <div key={dept.id} className="flex items-center gap-1.5">
                                <span className={`inline-block h-3 w-3 rounded ${deptColors[dept.id]}`} />
                                <span className="text-base-content/60">{dept.descripcion}</span>
                            </div>
                        ))}
                    </div>
                )}

                {/* Org Chart */}
                <Card>
                    <CardContent>
                        <div className="overflow-x-auto py-4">
                            {tree.length === 0 ? (
                                <p className="text-base-content/40 py-12 text-center text-sm">No hay puestos registrados</p>
                            ) : (
                                <div className="inline-flex gap-8">
                                    {tree.map((root) => (
                                        <OrgTreeNode key={root.id} node={root} deptColors={deptColors} />
                                    ))}
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
