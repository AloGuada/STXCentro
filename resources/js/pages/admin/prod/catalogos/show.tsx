import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ChevronDownIcon,
    ChevronRightIcon,
    CopyPlusIcon,
    DownloadIcon,
    GitCompareIcon,
    PlusIcon,
    UploadIcon,
} from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';
import { FormField } from '@/components/form';
import { ProcesosDeObra } from '@/components/prod/procesos-de-obra';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDePieza } from '@/lib/prod/piezas';
import type { BreadcrumbItem } from '@/types';
import type { Concepto, ProdCatalogo, ProdPieza, ProdProceso } from '@/types/models';

const fmt = (n: number) => Number(n).toLocaleString('es-MX', { minimumFractionDigits: 3, maximumFractionDigits: 3 });

type VersionRow = ProdCatalogo & { conceptos_count: number };
type MarcaConPiezas = Concepto & { piezas?: ProdPieza[] };

type Props = {
    catalogo: ProdCatalogo;
    marcas: MarcaConPiezas[];
    /** Los que paga la obra: una columna de avance por cada uno. */
    procesos: ProdProceso[];
    procesosDisponibles: ProdProceso[];
    versiones: VersionRow[];
    filters: { search?: string };
    /** El modelo 3D de la obra, que se sube aquí y convierte Calidad. */
    calidad: {
        obra_id: number;
        modelo: {
            id: number;
            version: number;
            estatus: 'pendiente' | 'procesando' | 'listo' | 'error';
            nombre_original: string;
            error: string | null;
            procesado_at: string | null;
            resumen: { marcas?: number; cordones?: number } | null;
        } | null;
    };
};

const ESTATUS_MODELO: Record<string, string> = {
    pendiente: 'En cola',
    procesando: 'Procesando',
    listo: 'Listo',
    error: 'Error',
};

export default function CatalogoShow({
    catalogo,
    marcas,
    procesos,
    procesosDisponibles,
    versiones,
    filters,
    calidad,
}: Props) {
    const { can } = useCan();
    const ifcForm = useForm<{ obra_id: number; archivo: File | null }>({ obra_id: calidad.obra_id, archivo: null });

    const subirIfc = (e: FormEvent) => {
        e.preventDefault();
        if (!ifcForm.data.archivo) return;

        ifcForm.post('/admin/calidad/modelos', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => ifcForm.reset('archivo'),
        });
    };
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Catalogos', href: '/admin/prod/catalogos' },
        { title: `${catalogo.nombre} v${catalogo.version}`, href: `/admin/prod/catalogos/${catalogo.id}` },
    ];

    const [search, setSearch] = useState(filters.search ?? '');
    const [abiertas, setAbiertas] = useState<number[]>([]);

    const alternar = (marcaId: number) =>
        setAbiertas((prev) => (prev.includes(marcaId) ? prev.filter((id) => id !== marcaId) : [...prev, marcaId]));

    const filtered = useMemo(() => {
        if (!search) return marcas;
        const s = search.toLowerCase();
        return marcas.filter(
            (m) =>
                [m.marca, m.lote, m.descripcion].some((campo) => (campo ?? '').toLowerCase().includes(s)) ||
                (m.piezas ?? []).some(
                    (p) =>
                        (p.qs ?? '').toLowerCase().includes(s) || p.qr.toLowerCase().includes(s),
                ),
        );
    }, [marcas, search]);

    /** Piezas pagadas de la marca en un proceso: la suma del avance de sus piezas. */
    const pagadasEn = (marca: MarcaConPiezas, procesoId: number) =>
        (marca.piezas ?? []).reduce((sum, p) => sum + (p.avance?.[procesoId]?.capturado ?? 0), 0);

    const totals = useMemo(
        () => ({
            marcas: filtered.length,
            piezas: filtered.reduce((sum, m) => sum + (m.piezas?.length ?? 0), 0),
            declaradas: filtered.reduce((sum, m) => sum + Number(m.cantidad), 0),
            pesoTotal: filtered.reduce((sum, m) => sum + (m.piezas?.length ?? 0) * Number(m.peso_unitario), 0),
        }),
        [filtered],
    );

    const csvForm = useForm<{ csv_file: File | null }>({ csv_file: null });

    const handleCsvImport = (e: FormEvent) => {
        e.preventDefault();
        if (!csvForm.data.csv_file) return;

        csvForm.post(`/admin/prod/catalogos/${catalogo.id}/import-csv`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => csvForm.reset('csv_file'),
        });
    };

    const nuevaVersion = () => {
        if (
            confirm(
                `¿Crear la versión ${catalogo.version + 1}? Se copian las ${totals.marcas} marcas con sus piezas y ` +
                    `precios; la v${catalogo.version} queda congelada como histórico.`,
            )
        ) {
            router.post(`/admin/prod/catalogos/${catalogo.id}/nueva-version`);
        }
    };

    const versionAnterior = versiones.find((v) => v.version === catalogo.version - 1);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${catalogo.nombre} v${catalogo.version}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            {catalogo.nombre}
                            <span className="badge badge-ghost font-mono">v{catalogo.version}</span>
                            <span className={`badge ${catalogo.vigente ? 'badge-success' : 'badge-neutral'}`}>
                                {catalogo.vigente ? 'Vigente' : 'Histórico'}
                            </span>
                        </h1>
                        <p className="text-base-content/60 mt-1 text-sm">
                            {catalogo.obra ? `Obra ${catalogo.obra.no} — ${catalogo.obra.descripcion}` : 'Sin obra'}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {versionAnterior && (
                            <ButtonLink
                                href={`/admin/prod/catalogos/${versionAnterior.id}/comparar/${catalogo.id}`}
                                variant="outline"
                            >
                                <GitCompareIcon className="size-4" />
                                Comparar con v{versionAnterior.version}
                            </ButtonLink>
                        )}
                        {catalogo.vigente && (
                            <>
                                <Button variant="outline" onClick={nuevaVersion}>
                                    <CopyPlusIcon className="size-4" />
                                    Nueva versión
                                </Button>
                                <ButtonLink
                                    href={`/admin/prod/conceptos/create?catalogo_id=${catalogo.id}`}
                                    variant="primary"
                                >
                                    <PlusIcon className="size-4" />
                                    Nueva marca
                                </ButtonLink>
                            </>
                        )}
                    </div>
                </div>

                {!catalogo.vigente && (
                    <div className="alert alert-info mb-4">
                        <span>
                            Esta es una versión histórica: se conserva para consulta y para sostener la producción que
                            se capturó con ella, pero ya no se edita.
                        </span>
                    </div>
                )}

                {catalogo.obra && catalogo.vigente && (
                    <div className="mb-4">
                        <ProcesosDeObra
                            obra={catalogo.obra}
                            procesosDeLaObra={procesos}
                            procesosDisponibles={procesosDisponibles}
                        />
                    </div>
                )}

                {versiones.length > 1 && (
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <span className="text-base-content/60 text-sm">Versiones:</span>
                        {versiones.map((v) => (
                            <Link
                                key={v.id}
                                href={`/admin/prod/catalogos/${v.id}`}
                                className={`badge gap-1 ${v.id === catalogo.id ? 'badge-primary' : 'badge-ghost'}`}
                            >
                                v{v.version}
                                <span className="opacity-60">({v.conceptos_count})</span>
                            </Link>
                        ))}
                    </div>
                )}

                <div className="mb-4 w-full max-w-xs">
                    <Input
                        placeholder="Buscar por marca, lote, QS, QR o descripcion..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="rounded-box border-base-300 overflow-hidden border">
                    <div className="max-h-[60vh] overflow-auto">
                        <table className="table table-sm">
                            <thead className="bg-base-200 sticky top-0 z-10">
                                <tr>
                                    <th></th>
                                    <th>Marca</th>
                                    <th>Lote</th>
                                    <th>Descripcion</th>
                                    <th>Categoria</th>
                                    <th className="text-right">Piezas</th>
                                    {procesos.map((p) => (
                                        <th key={p.id} className="text-right">
                                            {p.nombre}
                                        </th>
                                    ))}
                                    <th className="text-right">Longitud (mm)</th>
                                    <th className="text-right">Peso Unit. (kg)</th>
                                    <th className="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={10 + procesos.length}
                                            className="text-base-content/50 py-6 text-center"
                                        >
                                            Este catálogo no tiene marcas todavía
                                        </td>
                                    </tr>
                                ) : (
                                    filtered.flatMap((m) => {
                                        const piezas = m.piezas ?? [];
                                        const abierta = abiertas.includes(m.id);
                                        // El layout declara cuántas piezas tiene el modelo; si no
                                        // cuadra con las piezas cargadas, el archivo vino incompleto.
                                        const descuadre = piezas.length !== Number(m.cantidad);

                                        const fila = (
                                            <tr key={m.id} className="hover">
                                                <td className="w-8">
                                                    <button
                                                        className="btn btn-ghost btn-xs"
                                                        onClick={() => alternar(m.id)}
                                                        aria-label={abierta ? 'Ocultar piezas' : 'Ver piezas'}
                                                    >
                                                        {abierta ? (
                                                            <ChevronDownIcon className="size-4" />
                                                        ) : (
                                                            <ChevronRightIcon className="size-4" />
                                                        )}
                                                    </button>
                                                </td>
                                                <td className="font-medium">
                                                    <Link
                                                        href={`/admin/prod/conceptos/${m.id}/edit`}
                                                        className="hover:underline"
                                                    >
                                                        {m.marca}
                                                    </Link>
                                                </td>
                                                <td>
                                                    {m.lote ? (
                                                        <span className="badge badge-sm badge-ghost">{m.lote}</span>
                                                    ) : (
                                                        <span className="text-base-content/40">—</span>
                                                    )}
                                                </td>
                                                <td>{m.descripcion}</td>
                                                <td>
                                                    {m.categoria ? (
                                                        <span className="badge badge-sm badge-ghost">
                                                            {m.categoria.nombre}
                                                        </span>
                                                    ) : (
                                                        <span className="text-base-content/40">—</span>
                                                    )}
                                                </td>
                                                <td className="text-right font-mono">
                                                    <span className={descuadre ? 'text-warning font-semibold' : ''}>
                                                        {piezas.length}
                                                    </span>
                                                    <span className="text-base-content/40"> / {m.cantidad}</span>
                                                </td>
                                                {procesos.map((p) => {
                                                    const pagadas = pagadasEn(m, p.id);
                                                    const completo = piezas.length > 0 && pagadas >= piezas.length;

                                                    return (
                                                        <td key={p.id} className="text-right font-mono">
                                                            {completo ? (
                                                                <span className="badge badge-sm badge-success">
                                                                    Completa
                                                                </span>
                                                            ) : (
                                                                <>
                                                                    {pagadas.toLocaleString('es-MX', {
                                                                        maximumFractionDigits: 2,
                                                                    })}
                                                                    <span className="text-base-content/40">
                                                                        {' '}
                                                                        / {piezas.length}
                                                                    </span>
                                                                </>
                                                            )}
                                                        </td>
                                                    );
                                                })}
                                                <td className="text-right font-mono">
                                                    {m.longitud != null ? (
                                                        Number(m.longitud).toLocaleString('es-MX')
                                                    ) : (
                                                        <span className="text-base-content/40">—</span>
                                                    )}
                                                </td>
                                                <td className="text-right font-mono">{fmt(m.peso_unitario)}</td>
                                                <td className="text-center">
                                                    <span
                                                        className={`badge badge-sm ${m.activo ? 'badge-success' : 'badge-ghost'}`}
                                                    >
                                                        {m.activo ? 'Activa' : 'Inactiva'}
                                                    </span>
                                                </td>
                                            </tr>
                                        );

                                        if (!abierta) {
                                            return [fila];
                                        }

                                        return [
                                            fila,
                                            <tr key={`${m.id}-piezas`} className="bg-base-100">
                                                <td></td>
                                                <td colSpan={9 + procesos.length} className="py-3">
                                                    <div className="mb-1 text-xs font-medium">
                                                        Piezas de {etiquetaDePieza(m.marca, m.lote)}
                                                    </div>
                                                    {piezas.length === 0 ? (
                                                        <p className="text-base-content/50 text-sm">
                                                            Esta marca no tiene piezas cargadas: vuelve a subir el layout.
                                                        </p>
                                                    ) : (
                                                        <div className="flex flex-wrap gap-1">
                                                            {piezas.map((pieza) => (
                                                                <span
                                                                    key={pieza.id}
                                                                    className="badge badge-sm badge-ghost font-mono"
                                                                    title={[
                                                                        `QR ${pieza.qr}`,
                                                                        ...procesos.map(
                                                                            (p) =>
                                                                                `${p.nombre}: ${Math.round((pieza.avance?.[p.id]?.capturado ?? 0) * 100)}%`,
                                                                        ),
                                                                    ].join(' · ')}
                                                                >
                                                                    {pieza.qs ?? pieza.qr}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>,
                                        ];
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {filtered.length > 0 && (
                        <div className="border-base-300 bg-base-200 flex items-center justify-between border-t px-4 py-2 text-sm">
                            <span>
                                {totals.marcas} marcas ·{' '}
                                <span className="font-mono">{totals.piezas.toLocaleString('es-MX')}</span> piezas
                                cargadas de{' '}
                                <span className="font-mono">{totals.declaradas.toLocaleString('es-MX')}</span> que
                                declara el layout
                            </span>
                            <span className="font-mono">Peso total: {fmt(totals.pesoTotal)} kg</span>
                        </div>
                    )}
                </div>

                {catalogo.vigente && (
                    <div className="mt-8 space-y-3">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold">Importar piezas desde CSV</h2>
                            <a href="/admin/prod/conceptos/layout" className="btn btn-sm btn-outline">
                                <DownloadIcon className="size-4" />
                                Descargar layout
                            </a>
                        </div>
                        <p className="text-base-content/60 text-sm">
                            Columnas: QR, Marca, Descripcion, Categoria, QS, Cantidad, Peso Kg, Area, Longitud Mm,
                            Lote. El archivo es una <strong>lista de piezas</strong>: un renglón por pieza, repitiendo
                            marca y lote tantas veces como piezas tenga el modelo. La marca se identifica por{' '}
                            <strong>marca + lote</strong> y la pieza por su <strong>QR</strong>; si ya existen, se
                            sobrescriben sus datos. La cantidad de la marca sale de{' '}
                            <strong>cuántas piezas traiga el archivo</strong>: la columna Cantidad sólo se usa para
                            avisar si no cuadran. La categoria se crea automaticamente si no existe. Los layouts
                            viejos siguen cargando: sin QR se usa el QS, y una columna Etapa se lee como Lote.
                        </p>

                        <form onSubmit={handleCsvImport} className="flex items-end gap-4">
                            <FormField
                                label="Archivo CSV"
                                htmlFor="csv_file"
                                error={csvForm.errors.csv_file}
                                className="max-w-md"
                            >
                                <input
                                    id="csv_file"
                                    type="file"
                                    accept=".csv,.txt"
                                    className="file-input file-input-bordered w-full"
                                    onChange={(e) => csvForm.setData('csv_file', e.target.files?.[0] ?? null)}
                                />
                            </FormField>
                            <Button
                                type="submit"
                                disabled={csvForm.processing || !csvForm.data.csv_file}
                                loading={csvForm.processing}
                            >
                                {!csvForm.processing && <UploadIcon className="size-4" />}
                                Importar CSV
                            </Button>
                        </form>
                    </div>
                )}

                {can('qal.modelos.crear') && (
                    <div className="mt-8 space-y-3">
                        <h2 className="text-lg font-semibold">Modelo 3D (IFC)</h2>
                        <p className="text-base-content/60 text-sm">
                            El IFC de la obra exportado de Tekla. Calidad lo convierte en una marca por archivo con sus
                            cordones de soldadura, y con eso el inspector reporta cada junta sobre el modelo. Cada IFC
                            que se sube es una <strong>versión nueva</strong>: la anterior se conserva con lo que se
                            capturó sobre ella.
                        </p>

                        {calidad.modelo && (
                            <div className="border-base-300 bg-base-100 flex flex-wrap items-center gap-3 rounded-xl border px-4 py-3 text-sm">
                                <span className="font-semibold">v{calidad.modelo.version}</span>
                                <span className="text-base-content/70">{calidad.modelo.nombre_original}</span>
                                <span
                                    className={`badge badge-sm ${
                                        calidad.modelo.estatus === 'listo'
                                            ? 'badge-success'
                                            : calidad.modelo.estatus === 'error'
                                              ? 'badge-error'
                                              : 'badge-warning'
                                    }`}
                                >
                                    {ESTATUS_MODELO[calidad.modelo.estatus]}
                                </span>
                                {calidad.modelo.resumen?.marcas !== undefined && (
                                    <span className="text-base-content/70">
                                        {calidad.modelo.resumen.marcas} marcas · {calidad.modelo.resumen.cordones ?? 0} cordones
                                    </span>
                                )}
                                {calidad.modelo.error && <span className="text-error">{calidad.modelo.error}</span>}
                                {can('qal.modelos.ver') && (
                                    <Link href={`/admin/calidad/modelos/${calidad.modelo.id}`} className="link ml-auto">
                                        Ver en Calidad
                                    </Link>
                                )}
                            </div>
                        )}

                        <form onSubmit={subirIfc} className="flex items-end gap-4">
                            <FormField label="Archivo IFC" htmlFor="ifc_file" error={ifcForm.errors.archivo} className="max-w-md">
                                <input
                                    id="ifc_file"
                                    type="file"
                                    accept=".ifc"
                                    className="file-input file-input-bordered w-full"
                                    onChange={(e) => ifcForm.setData('archivo', e.target.files?.[0] ?? null)}
                                />
                            </FormField>
                            <Button type="submit" disabled={ifcForm.processing || !ifcForm.data.archivo} loading={ifcForm.processing}>
                                {!ifcForm.processing && <UploadIcon className="size-4" />}
                                Añadir IFC
                            </Button>
                        </form>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
