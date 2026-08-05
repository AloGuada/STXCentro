import { Button } from '@/components/ui/button';
import { FormattedDate } from '@/components/ui/formatted-date';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    CheckCircle2Icon,
    Loader2Icon,
    SaveIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';

type Empleado = {
    id: number;
    nombre: string;
    no_empleado: string | null;
    porcentaje: number;
};
type Grupo = { id: number; descripcion: string; empleados: Empleado[] };
type Dia = { fecha: string; label: string };
type Destajo = {
    id: number;
    anio: number;
    semana: number;
    fecha_inicio: string;
    fecha_fin: string;
    cerrado: boolean;
};

type Estado =
    | 'asistencia'
    | 'falta'
    | 'vacaciones'
    | 'incapacidad'
    | 'no_aplica';

type Props = {
    destajo: Destajo;
    grupos: Grupo[];
    /** Grupos con producción o pagos extra esta semana: son los que bloquean el cierre. */
    participantes: number[];
    dias: Dia[];
    /** Marcas ya guardadas, indexadas por "empleadoId:fecha". */
    marcas: Record<string, Estado>;
};

const ESTADOS: Estado[] = [
    'asistencia',
    'falta',
    'vacaciones',
    'incapacidad',
    'no_aplica',
];

const META: Record<Estado, { letra: string; label: string; cls: string }> = {
    asistencia: {
        letra: 'A',
        label: 'Asistencia',
        cls: 'bg-emerald-500 text-white',
    },
    falta: { letra: 'F', label: 'Falta', cls: 'bg-red-500 text-white' },
    vacaciones: {
        letra: 'V',
        label: 'Vacaciones',
        cls: 'bg-sky-500 text-white',
    },
    incapacidad: {
        letra: 'I',
        label: 'Incapacidad',
        cls: 'bg-amber-500 text-white',
    },
    no_aplica: {
        letra: 'N',
        label: 'No aplica',
        cls: 'bg-base-300 text-base-content/50',
    },
};

/** Cada día cubierto vale 7/6: el séptimo día va prorrateado en los seis de trabajo. */
const FACTOR_SEPTIMO_DIA = 7 / 6;

const PAGAN: Estado[] = ['asistencia', 'vacaciones', 'incapacidad'];

export default function DestajoAsistencia({
    destajo,
    grupos,
    participantes,
    dias,
    marcas: guardadas,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Destajos', href: '/admin/prod/destajos' },
        {
            title: `Semana ${destajo.semana}`,
            href: `/admin/prod/destajos/${destajo.id}`,
        },
        {
            title: 'Asistencia',
            href: `/admin/prod/destajos/${destajo.id}/asistencia`,
        },
    ];

    const [marcas, setMarcas] = useState<Record<string, Estado>>(guardadas);
    const [sucio, setSucio] = useState(false);
    const form = useForm({});

    const get = (empId: number, fecha: string): Estado =>
        marcas[`${empId}:${fecha}`] ?? 'asistencia';

    const cycle = (empId: number, fecha: string) => {
        if (destajo.cerrado) return;

        const key = `${empId}:${fecha}`;
        const actual = marcas[key] ?? 'asistencia';
        const next = ESTADOS[(ESTADOS.indexOf(actual) + 1) % ESTADOS.length];
        setMarcas((m) => ({ ...m, [key]: next }));
        setSucio(true);
    };

    /**
     * Se manda la cuadrícula completa: las celdas que nadie tocó valen
     * "asistencia", que es justo lo que la pantalla está mostrando.
     */
    const guardar = () => {
        const payload = grupos.flatMap((g) =>
            g.empleados.flatMap((emp) =>
                dias.map((d) => ({
                    grupo_empleado_id: emp.id,
                    fecha: d.fecha,
                    estado: get(emp.id, d.fecha),
                })),
            ),
        );

        form.transform(() => ({ marcas: payload }));
        form.post(`/admin/prod/destajos/${destajo.id}/asistencia`, {
            preserveScroll: true,
            onSuccess: () => setSucio(false),
        });
    };

    // El cierre sólo exige la asistencia de los grupos que cobran algo esta
    // semana; los demás se pueden capturar, pero no son obligatorios.
    const obligatorios = useMemo(
        () => grupos.filter((g) => participantes.includes(g.id)),
        [grupos, participantes],
    );

    // Obligatorias: las que bloquean el cierre. Miden el avance, no lo que se
    // puede guardar.
    const totalCeldas = obligatorios.reduce(
        (acc, g) => acc + g.empleados.length * dias.length,
        0,
    );

    // Lo que realmente se manda al guardar: toda la cuadricula. Un grupo puede
    // no participar todavia —su produccion no se ha capturado, o estuvo parado—
    // y aun asi hay que poder registrarle la asistencia.
    const celdasCapturables = grupos.reduce(
        (acc, g) => acc + g.empleados.length * dias.length,
        0,
    );

    const capturadas = useMemo(
        () =>
            obligatorios.reduce(
                (acc, g) =>
                    acc +
                    g.empleados.reduce(
                        (a, emp) =>
                            a +
                            dias.filter(
                                (d) =>
                                    guardadas[`${emp.id}:${d.fecha}`] !==
                                    undefined,
                            ).length,
                        0,
                    ),
                0,
            ),
        [obligatorios, dias, guardadas],
    );

    const completa = totalCeldas > 0 && capturadas === totalCeldas;

    const totales = (empId: number): Record<Estado, number> => {
        const t: Record<Estado, number> = {
            asistencia: 0,
            falta: 0,
            vacaciones: 0,
            incapacidad: 0,
            no_aplica: 0,
        };
        for (const d of dias) t[get(empId, d.fecha)]++;
        return t;
    };

    /** Los mismos días que pagará la liquidación, para que no haya sorpresas al cerrar. */
    const diasPagados = (t: Record<Estado, number>): number =>
        t.no_aplica > 0
            ? 0
            : PAGAN.reduce((acc, e) => acc + t[e], 0) * FACTOR_SEPTIMO_DIA;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Asistencia · Semana ${destajo.semana}`} />

            <div className="p-6">
                <div className="mb-4 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Asistencia · Semana {destajo.semana}
                        </h1>
                        <p className="text-sm text-base-content/60">
                            Año {destajo.anio} ·{' '}
                            <FormattedDate value={destajo.fecha_inicio} /> —{' '}
                            <FormattedDate value={destajo.fecha_fin} />. Clic en
                            cada celda para cambiar el estado.
                        </p>
                        {/* Los cambios sin guardar se avisan siempre; lo del cierre
                            solo cuando hay grupos que lo bloqueen. */}
                        {sucio ? (
                            <p className="mt-1 text-sm text-warning">
                                Hay cambios sin guardar.
                            </p>
                        ) : totalCeldas === 0 ? null : completa ? (
                            <p className="mt-1 flex items-center gap-1 text-sm text-success">
                                <CheckCircle2Icon className="size-4" />{' '}
                                Asistencia completa de la semana
                            </p>
                        ) : (
                            <p className="mt-1 text-sm text-warning">
                                Faltan {totalCeldas - capturadas} de{' '}
                                {totalCeldas} celdas por guardar. El destajo no
                                se puede cerrar sin la asistencia completa.
                            </p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/admin/prod/destajos/${destajo.id}`}>
                                <ArrowLeftIcon className="size-4" /> Volver al
                                destajo
                            </Link>
                        </Button>
                        <Button
                            onClick={guardar}
                            disabled={
                                form.processing ||
                                destajo.cerrado ||
                                celdasCapturables === 0
                            }
                        >
                            {form.processing ? (
                                <Loader2Icon className="size-4 animate-spin" />
                            ) : (
                                <SaveIcon className="size-4" />
                            )}
                            Guardar
                        </Button>
                    </div>
                </div>

                {/* Leyenda */}
                <div className="mb-2 flex flex-wrap items-center gap-3 text-sm">
                    {ESTADOS.map((e) => (
                        <span
                            key={e}
                            className="inline-flex items-center gap-1.5"
                        >
                            <span
                                className={`inline-flex size-5 items-center justify-center rounded text-xs font-bold ${META[e].cls}`}
                            >
                                {META[e].letra}
                            </span>
                            {META[e].label}
                        </span>
                    ))}
                </div>

                <p className="mb-4 text-xs text-base-content/60">
                    Asistencia, vacaciones e incapacidad cuentan como día
                    cubierto; la falta no. Cada día cubierto vale 7/6 de día
                    porque el séptimo día va prorrateado, así que la semana
                    completa paga 7. Un solo <strong>No aplica</strong> deja al
                    trabajador sin sueldo base esa semana: sólo cobra el destajo
                    que le toque. El domingo no se captura.
                </p>

                {grupos.length === 0 ? (
                    <div className="rounded-box border border-dashed border-base-300 p-8 text-center">
                        <p className="text-base-content/60">
                            No hay grupos de trabajo activos con empleados. Da
                            de alta el grupo y sus integrantes para poder
                            capturar la asistencia.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {grupos.map((grupo) => (
                            <div
                                key={grupo.id}
                                className="overflow-hidden rounded-box border border-base-300"
                            >
                                <div className="border-b border-base-300 bg-base-200 px-4 py-2 font-semibold">
                                    {grupo.descripcion}
                                    {!participantes.includes(grupo.id) && (
                                        <span className="ml-2 badge badge-ghost badge-sm font-normal">
                                            Sin producción esta semana
                                        </span>
                                    )}
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="table table-sm">
                                        <thead>
                                            <tr>
                                                <th className="min-w-[180px]">
                                                    Empleado
                                                </th>
                                                {dias.map((d) => (
                                                    <th
                                                        key={d.fecha}
                                                        className="text-center whitespace-nowrap"
                                                    >
                                                        {d.label}
                                                    </th>
                                                ))}
                                                {ESTADOS.map((e) => (
                                                    <th
                                                        key={e}
                                                        className="text-center"
                                                        title={META[e].label}
                                                    >
                                                        {META[e].letra}
                                                    </th>
                                                ))}
                                                <th
                                                    className="text-center whitespace-nowrap"
                                                    title="Días que se pagarán, con el séptimo día prorrateado"
                                                >
                                                    Días pag.
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {grupo.empleados.length === 0 ? (
                                                <tr>
                                                    <td
                                                        colSpan={
                                                            dias.length +
                                                            ESTADOS.length +
                                                            2
                                                        }
                                                        className="py-4 text-center text-base-content/50"
                                                    >
                                                        Sin empleados en el
                                                        grupo
                                                    </td>
                                                </tr>
                                            ) : (
                                                grupo.empleados.map((emp) => {
                                                    const t = totales(emp.id);
                                                    return (
                                                        <tr
                                                            key={emp.id}
                                                            className="hover"
                                                        >
                                                            <td>
                                                                <span className="font-medium">
                                                                    {emp.nombre}
                                                                </span>{' '}
                                                                <span className="text-xs text-base-content/50">
                                                                    (
                                                                    {emp.no_empleado ||
                                                                        's/n'}
                                                                    )
                                                                </span>
                                                            </td>
                                                            {dias.map((d) => {
                                                                const estado =
                                                                    get(
                                                                        emp.id,
                                                                        d.fecha,
                                                                    );
                                                                return (
                                                                    <td
                                                                        key={
                                                                            d.fecha
                                                                        }
                                                                        className="text-center"
                                                                    >
                                                                        <button
                                                                            type="button"
                                                                            onClick={() =>
                                                                                cycle(
                                                                                    emp.id,
                                                                                    d.fecha,
                                                                                )
                                                                            }
                                                                            title={
                                                                                META[
                                                                                    estado
                                                                                ]
                                                                                    .label
                                                                            }
                                                                            className={`inline-flex size-7 items-center justify-center rounded text-xs font-bold ${META[estado].cls}`}
                                                                        >
                                                                            {
                                                                                META[
                                                                                    estado
                                                                                ]
                                                                                    .letra
                                                                            }
                                                                        </button>
                                                                    </td>
                                                                );
                                                            })}
                                                            {ESTADOS.map(
                                                                (e) => (
                                                                    <td
                                                                        key={e}
                                                                        className="text-center font-mono"
                                                                    >
                                                                        {t[e]}
                                                                    </td>
                                                                ),
                                                            )}
                                                            <td
                                                                className={`text-center font-mono font-semibold ${
                                                                    t.no_aplica >
                                                                    0
                                                                        ? 'text-base-content/40'
                                                                        : ''
                                                                }`}
                                                            >
                                                                {diasPagados(t)
                                                                    .toFixed(2)
                                                                    .replace(
                                                                        /\.?0+$/,
                                                                        '',
                                                                    )}
                                                            </td>
                                                        </tr>
                                                    );
                                                })
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
