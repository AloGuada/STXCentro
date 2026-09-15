import { SearchInput } from '@/components/data-table';
import { FormField } from '@/components/form';
import { ProcesosDeObra } from '@/components/prod/procesos-de-obra';
import { Button, ButtonLink } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { etiquetaDePieza } from '@/lib/prod/piezas';
import type { BreadcrumbItem } from '@/types';
import type { Concepto, PaginatedData, ProdCatalogo, ProdPieza, ProdProceso } from '@/types/models';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ChevronDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    CopyPlusIcon,
    DownloadIcon,
    GitCompareIcon,
    Loader2Icon,
    PlusIcon,
    UploadIcon,
} from 'lucide-react';
import { type FormEvent, useCallback, useState } from 'react';

const fmt = (n: number) => Number(n).toLocaleString('es-MX', { minimumFractionDigits: 3, maximumFractionDigits: 3 });

type VersionRow = ProdCatalogo & { conceptos_count: number };
/** La marca de la tabla: sin sus piezas, que se piden al desplegarla. */
type MarcaDelCatalogo = Concepto & { piezas_count: number };
type PiezaConAvance = Pick<ProdPieza, 'id' | 'qr' | 'qs' | 'correlativo'> & {
    avance: Record<number, { capturado: number; disponible: number }>;
};

type Props = {
    catalogo: ProdCatalogo;
    marcas: PaginatedData<MarcaDelCatalogo>;
    /** Piezas ya pagadas por marca y proceso: marcaId => procesoId => pagadas. */
    avancePorMarca: Record<number, Record<number, number>>;
    /** Del catálogo completo, no de la página que se está viendo. */
    totales: { marcas: number; piezas: number; declaradas: number; peso: number };
    /** Los que paga la obra: una columna de avance por cada uno. */
    procesos: ProdProceso[];
    procesosDisponibles: ProdProceso[];
    versiones: VersionRow[];
    filters: { search?: string };
};

export default function CatalogoShow({
    catalogo,
    marcas,
    avancePorMarca,
    totales,
    procesos,
    procesosDisponibles,
    versiones,
    filters,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Produccion', href: '/admin/prod/destajos' },
        { title: 'Catalogos', href: '/admin/prod/catalogos' },
        { title: `${catalogo.nombre} v${catalogo.version}`, href: `/admin/prod/catalogos/${catalogo.id}` },
    ];

    const [abiertas, setAbiertas] = useState<number[]>([]);
    // Los QR de una marca se piden al desplegarla y se quedan cacheados: el
    // catálogo de una obra son decenas de miles y no caben en la pantalla.
    const [piezasPorMarca, setPiezasPorMarca] = useState<Record<number, PiezaConAvance[]>>({});
    const [cargando, setCargando] = useState<number[]>([]);

    const cargarPiezas = useCallback(
        (marcaId: number) => {
            if (piezasPorMarca[marcaId] || cargando.includes(marcaId)) {
                return;
            }

            setCargando((prev) => [...prev, marcaId]);

            fetch(`/admin/prod/marcas/${marcaId}/piezas`, { headers: { Accept: 'application/json' } })
                .then((res) => (res.ok ? res.json() : Promise.reject(res)))
                .then((datos) => setPiezasPorMarca((prev) => ({ ...prev, [marcaId]: datos.piezas ?? [] })))
                .catch(() => setPiezasPorMarca((prev) => ({ ...prev, [marcaId]: [] })))
                .finally(() => setCargando((prev) => prev.filter((id) => id !== marcaId)));
        },
        [piezasPorMarca, cargando],
    );

    const alternar = (marcaId: number) => {
        setAbiertas((prev) => (prev.includes(marcaId) ? prev.filter((id) => id !== marcaId) : [...prev, marcaId]));
        cargarPiezas(marcaId);
    };

    /** Piezas pagadas de la marca en un proceso, ya resumidas por el servidor. */
    const pagadasEn = (marca: MarcaDelCatalogo, procesoId: number) => avancePorMarca[marca.id]?.[procesoId] ?? 0;

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
                `¿Crear la versión ${catalogo.version + 1}? Se copian las ${totales.marcas} marcas con sus piezas y ` +
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
                    <SearchInput
                        placeholder="Buscar por marca, lote, QS, QR o descripcion..."
                        defaultValue={filters.search ?? ''}
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
                                {marcas.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={10 + procesos.length}
                                            className="text-base-content/50 py-6 text-center"
                                        >
                                            {filters.search
                                                ? 'Ninguna marca coincide con la búsqueda'
                                                : 'Este catálogo no tiene marcas todavía'}
                                        </td>
                                    </tr>
                                ) : (
                                    marcas.data.flatMap((m) => {
                                        const piezas = piezasPorMarca[m.id] ?? [];
                                        const abierta = abiertas.includes(m.id);
                                        // El layout declara cuántas piezas tiene el modelo; si no
                                        // cuadra con las piezas cargadas, el archivo vino incompleto.
                                        const descuadre = m.piezas_count !== Number(m.cantidad);

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
                                                        {m.piezas_count}
                                                    </span>
                                                    <span className="text-base-content/40"> / {m.cantidad}</span>
                                                </td>
                                                {procesos.map((p) => {
                                                    const pagadas = pagadasEn(m, p.id);
                                                    // Se paga contra la cantidad del modelo, no contra los
                                                    // QR activos: el QR cambia con la orden de trabajo.
                                                    const cantidad = Number(m.cantidad) || m.piezas_count;
                                                    const completo = cantidad > 0 && pagadas >= cantidad;

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
                                                                        / {cantidad}
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
                                                    {cargando.includes(m.id) ? (
                                                        <p className="text-base-content/50 flex items-center gap-2 text-sm">
                                                            <Loader2Icon className="size-4 animate-spin" /> Cargando
                                                            piezas...
                                                        </p>
                                                    ) : piezas.length === 0 ? (
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
                                                                    {pieza.correlativo !== null && (
                                                                        <span className="text-base-content/50 mr-1">#{pieza.correlativo}</span>
                                                                    )}
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

                    <div className="border-base-300 bg-base-200 flex flex-wrap items-center justify-between gap-2 border-t px-4 py-2 text-sm">
                        {/* Los totales son del catálogo completo, no de la página. */}
                        <span>
                            {totales.marcas} marcas ·{' '}
                            <span className="font-mono">{totales.piezas.toLocaleString('es-MX')}</span> piezas cargadas
                            de <span className="font-mono">{totales.declaradas.toLocaleString('es-MX')}</span> que
                            declara el layout
                        </span>
                        <span className="font-mono">Peso total: {fmt(totales.peso)} kg</span>
                    </div>

                    {marcas.last_page > 1 && (
                        <div className="border-base-300 flex items-center justify-between border-t px-4 py-2 text-sm">
                            <span className="text-base-content/60">
                                {marcas.from}–{marcas.to} de {marcas.total} marcas
                            </span>
                            <div className="join">
                                <Link
                                    href={marcas.prev_page_url ?? '#'}
                                    className={`join-item btn btn-sm ${marcas.prev_page_url ? '' : 'btn-disabled'}`}
                                    preserveState
                                    preserveScroll
                                >
                                    <ChevronLeftIcon className="size-4" />
                                </Link>
                                <button className="join-item btn btn-sm btn-ghost pointer-events-none">
                                    {marcas.current_page} / {marcas.last_page}
                                </button>
                                <Link
                                    href={marcas.next_page_url ?? '#'}
                                    className={`join-item btn btn-sm ${marcas.next_page_url ? '' : 'btn-disabled'}`}
                                    preserveState
                                    preserveScroll
                                >
                                    <ChevronRightIcon className="size-4" />
                                </Link>
                            </div>
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
                            Columnas: QR, Marca, Descripcion, Categoria QS, Correlativo, Cantidad, Peso Kg, Area,
                            Longitud Mm, Lote. El archivo es una <strong>lista de piezas</strong>: un renglón por pieza, repitiendo
                            marca y lote tantas veces como piezas tenga el modelo. La marca se identifica por{' '}
                            <strong>marca + lote</strong> y se paga contra su columna <strong>Cantidad</strong>. El QR
                            es de la orden de trabajo: al recargar un modelo, los QR que no vengan en el archivo se
                            desactivan y lo pagado bajo ellos sigue contando para el modelo. La categoria se crea
                            automaticamente si no existe. Los layouts viejos siguen cargando: sin QR se usa el QS,
                            Categoria entra como Categoria QS y una columna Etapa se lee como Lote.
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
            </div>
        </AppLayout>
    );
}
