import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, CheckIcon, FileTextIcon, LockIcon, PrinterIcon, SaveIcon, TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';
import { ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type Renglon = {
    id: number;
    orden: number;
    articulo_id: number;
    codigo: string | null;
    descripcion: string | null;
    unidad: string | null;
    clasificacion: 'A' | 'B' | 'C' | null;
    ubicacion: string | null;
    cantidad_sistema: number | null;
    cantidad_contada: number | null;
    observaciones: string | null;
};

type Conteo = {
    id: number;
    folio: string;
    origen: 'programado' | 'manual';
    origen_etiqueta: string;
    almacen: string | null;
    almacen_nombre: string | null;
    programa_id: number | null;
    fecha_programada: string;
    fecha_cierre: string | null;
    responsable: string | null;
    estatus: 'pendiente' | 'contando' | 'cerrado' | 'cancelado';
    estatus_etiqueta: string;
    vencido: boolean;
    ajuste_id: number | null;
    ajuste_folio: string | null;
    observaciones: string | null;
    /** La hoja firmada escaneada, si la subieron al cerrar. */
    firmado_url: string | null;
    completa: boolean;
    puede_capturar: boolean;
    puede_cerrar: boolean;
    saldo_visible: boolean;
    renglones: Renglon[];
};

type Props = { conteo: Conteo };

type Captura = Record<string, { cantidad_contada: string; observaciones: string }>;

const ESTATUS_CLASE: Record<Conteo['estatus'], string> = {
    pendiente: 'badge-ghost',
    contando: 'badge-info',
    cerrado: 'badge-success',
    cancelado: 'badge-error',
};

const CLASE_ABC: Record<'A' | 'B' | 'C', string> = {
    A: 'badge-error',
    B: 'badge-warning',
    C: 'badge-ghost',
};

/**
 * La hoja de conteo: qué toca contar ese día, en qué orden, y lo que se
 * encontró.
 *
 * Mientras está abierta se captura lo contado, a medias si hace falta, y el
 * saldo del sistema no se enseña: un número a la vista es una respuesta
 * sugerida. Cuando todo está contado, quien puede cerrar ve las diferencias y
 * cierra; eso levanta el ajuste, que es el único documento que mueve el saldo.
 */
export default function ConteoShow({ conteo }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/existencias' },
        { title: 'Inventarios cíclicos', href: '/admin/almacen/conteos' },
        { title: conteo.folio, href: `/admin/almacen/conteos/${conteo.id}` },
    ];

    const abierta = conteo.estatus === 'pendiente' || conteo.estatus === 'contando';
    const capturando = abierta && conteo.puede_capturar;
    const contados = conteo.renglones.filter((r) => r.cantidad_contada !== null).length;
    const [confirmando, setConfirmando] = useState(false);

    const captura = useForm({
        renglones: Object.fromEntries(
            conteo.renglones.map((r) => [
                r.id,
                {
                    cantidad_contada: r.cantidad_contada === null ? '' : String(r.cantidad_contada),
                    observaciones: r.observaciones ?? '',
                },
            ]),
        ) as Captura,
    });

    const cierre = useForm<{ observaciones: string; firmado: File | null; cierre?: string }>({
        observaciones: '',
        firmado: null,
    });

    const setRenglon = (id: number, campo: 'cantidad_contada' | 'observaciones', valor: string) =>
        captura.setData('renglones', {
            ...captura.data.renglones,
            [id]: { ...captura.data.renglones[id], [campo]: valor },
        });

    const guardarCaptura = () => {
        captura.transform((data) => ({
            renglones: Object.entries(data.renglones).map(([id, r]) => ({
                id: Number(id),
                cantidad_contada: r.cantidad_contada === '' ? null : r.cantidad_contada,
                observaciones: r.observaciones || null,
            })),
        }));
        captura.patch(`/admin/almacen/conteos/${conteo.id}/captura`, { preserveScroll: true });
    };

    const cerrarConteo = () =>
        cierre.post(`/admin/almacen/conteos/${conteo.id}/cerrar`, {
            preserveScroll: true,
            onSuccess: () => setConfirmando(false),
        });

    const diferencias = conteo.saldo_visible
        ? conteo.renglones.filter(
              (r) => r.cantidad_contada !== null && r.cantidad_sistema !== null && r.cantidad_contada !== r.cantidad_sistema,
          )
        : [];

    const mostrarSistema = conteo.saldo_visible;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Conteo ${conteo.folio}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-mono text-2xl font-semibold">{conteo.folio}</h1>
                            <span className={`badge badge-sm ${ESTATUS_CLASE[conteo.estatus]}`}>
                                {conteo.estatus_etiqueta}
                            </span>
                            <span className="badge badge-sm badge-ghost">{conteo.origen_etiqueta}</span>
                        </div>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Almacén {conteo.almacen}
                            {conteo.almacen_nombre ? ` — ${conteo.almacen_nombre}` : ''} · programado el{' '}
                            {conteo.fecha_programada}
                            {conteo.responsable ? ` · cuenta ${conteo.responsable}` : ''}
                            {conteo.fecha_cierre ? ` · cerrado el ${conteo.fecha_cierre}` : ''}
                            {conteo.vencido && <span className="text-error"> · vencido</span>}
                        </p>
                    </div>

                    <div className="flex gap-2">
                        <ButtonLink
                            href={conteo.programa_id ? `/admin/almacen/conteos?programa_id=${conteo.programa_id}` : '/admin/almacen/conteos'}
                            variant="outline"
                        >
                            <ArrowLeftIcon className="size-4" />
                            Volver
                        </ButtonLink>
                        {conteo.estatus === 'cerrado' ? (
                            <a
                                href={`/admin/almacen/conteos/${conteo.id}/reporte`}
                                target="_blank"
                                rel="noopener"
                                className="btn btn-primary"
                            >
                                <FileTextIcon className="size-4" />
                                Reporte
                            </a>
                        ) : (
                            <a
                                href={`/admin/almacen/conteos/${conteo.id}/pdf`}
                                target="_blank"
                                rel="noopener"
                                className={`btn ${capturando ? 'btn-outline' : 'btn-primary'}`}
                            >
                                <PrinterIcon className="size-4" />
                                Imprimir hoja
                            </a>
                        )}
                    </div>
                </div>

                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div className="text-base-content/70 text-sm">
                        <strong>{conteo.renglones.length}</strong> artículos por contar
                        {contados > 0 && (
                            <span>
                                {' '}
                                · <strong>{contados}</strong> capturados
                                {contados < conteo.renglones.length && ` · faltan ${conteo.renglones.length - contados}`}
                            </span>
                        )}
                    </div>
                    {abierta && !conteo.puede_capturar && (
                        <span className="text-base-content/50 text-xs">
                            Puedes ver la hoja pero no capturar: eso pide el permiso de captura.
                        </span>
                    )}
                </div>

                {captura.errors.renglones && (
                    <div className="alert alert-error mb-4">
                        <span>{captura.errors.renglones}</span>
                    </div>
                )}

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (capturando) {
                            guardarCaptura();
                        }
                    }}
                >
                    <div className="rounded-box border-base-300 overflow-x-auto border">
                        <table className="table table-sm">
                            <thead className="bg-base-200">
                                <tr>
                                    <th className="w-12 text-right">#</th>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th className="w-16">Clase</th>
                                    <th>Ubicación</th>
                                    {mostrarSistema && <th className="w-32 text-right">Sistema</th>}
                                    <th className="w-36 text-right">Contado</th>
                                    {mostrarSistema && <th className="w-32 text-right">Diferencia</th>}
                                    {capturando && <th className="w-56">Nota</th>}
                                    {!capturando && <th>Nota</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {conteo.renglones.map((r) => {
                                    const diferencia =
                                        r.cantidad_contada !== null && r.cantidad_sistema !== null
                                            ? r.cantidad_contada - r.cantidad_sistema
                                            : null;
                                    const descuadra = diferencia !== null && diferencia !== 0;

                                    return (
                                        <tr key={r.id} className={descuadra ? 'bg-warning/10' : 'hover'}>
                                            <td className="text-base-content/50 text-right font-mono text-xs">{r.orden}</td>
                                            <td className="font-mono text-xs">{r.codigo}</td>
                                            <td>{r.descripcion}</td>
                                            <td>
                                                {r.clasificacion && (
                                                    <span className={`badge badge-xs ${CLASE_ABC[r.clasificacion]}`}>
                                                        {r.clasificacion}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-base-content/60 text-sm">
                                                {r.ubicacion ?? <span className="text-base-content/40">—</span>}
                                            </td>
                                            {mostrarSistema && (
                                                <td className="text-right font-mono">
                                                    {r.cantidad_sistema === null ? (
                                                        <span className="text-base-content/30">—</span>
                                                    ) : (
                                                        numero(r.cantidad_sistema)
                                                    )}
                                                </td>
                                            )}
                                            <td className="text-right font-mono">
                                                {capturando ? (
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="0.001"
                                                        inputMode="decimal"
                                                        className="input-sm text-right"
                                                        placeholder="—"
                                                        value={captura.data.renglones[r.id]?.cantidad_contada ?? ''}
                                                        onChange={(e) => setRenglon(r.id, 'cantidad_contada', e.target.value)}
                                                        error={Boolean(captura.errors[`renglones.${r.id}.cantidad_contada` as never])}
                                                    />
                                                ) : r.cantidad_contada === null ? (
                                                    <span className="text-base-content/30">—</span>
                                                ) : (
                                                    <>
                                                        {numero(r.cantidad_contada)}{' '}
                                                        <span className="text-base-content/40 text-xs">{r.unidad}</span>
                                                    </>
                                                )}
                                            </td>
                                            {mostrarSistema && (
                                                <td className="text-right font-mono">
                                                    {diferencia === null ? (
                                                        <span className="text-base-content/30">—</span>
                                                    ) : diferencia === 0 ? (
                                                        <span className="text-success">
                                                            <CheckIcon className="inline size-4" />
                                                        </span>
                                                    ) : (
                                                        <span className={diferencia > 0 ? 'text-info font-semibold' : 'text-error font-semibold'}>
                                                            {diferencia > 0 ? '+' : ''}
                                                            {numero(diferencia)}
                                                        </span>
                                                    )}
                                                </td>
                                            )}
                                            <td className="text-base-content/70 text-sm">
                                                {capturando ? (
                                                    <Input
                                                        type="text"
                                                        maxLength={500}
                                                        className="input-sm"
                                                        placeholder="Opcional"
                                                        value={captura.data.renglones[r.id]?.observaciones ?? ''}
                                                        onChange={(e) => setRenglon(r.id, 'observaciones', e.target.value)}
                                                    />
                                                ) : (
                                                    (r.observaciones ?? <span className="text-base-content/30">—</span>)
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {capturando && (
                        <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <p className="text-base-content/60 text-sm">
                                Se puede guardar a medias y seguir después. Lo que no se encontró se captura en cero;
                                dejarlo vacío es no haberlo contado todavía.
                            </p>
                            <button type="submit" className="btn btn-primary" disabled={captura.processing}>
                                <SaveIcon className="size-4" />
                                Guardar captura
                            </button>
                        </div>
                    )}
                </form>

                {abierta && conteo.puede_cerrar && (
                    <div className="rounded-box border-base-300 mt-6 border">
                        <div className="border-base-300 border-b px-4 py-3">
                            <h2 className="font-medium">Cerrar el conteo</h2>
                            <p className="text-base-content/60 text-sm">
                                {conteo.completa
                                    ? diferencias.length === 0
                                        ? 'Todo cuadra. Cerrar deja el acta del conteo sin mover el saldo.'
                                        : `${diferencias.length} ${diferencias.length === 1 ? 'renglón descuadra' : 'renglones descuadran'}. Cerrar genera el ajuste que corrige el saldo a lo contado.`
                                    : `Faltan ${conteo.renglones.length - contados} renglones por contar. Se cierra cuando la hoja esté completa.`}
                            </p>
                        </div>

                        {conteo.completa && diferencias.length > 0 && (
                            <ul className="divide-base-300 divide-y px-4 text-sm">
                                {diferencias.map((r) => {
                                    const d = (r.cantidad_contada ?? 0) - (r.cantidad_sistema ?? 0);

                                    return (
                                        <li key={r.id} className="flex items-center justify-between gap-3 py-2">
                                            <span>
                                                <span className="font-mono text-xs">{r.codigo}</span> {r.descripcion}
                                            </span>
                                            <span className="font-mono">
                                                {numero(r.cantidad_sistema ?? 0)} → {numero(r.cantidad_contada ?? 0)}{' '}
                                                <span className={d > 0 ? 'text-info' : 'text-error'}>
                                                    ({d > 0 ? '+' : ''}
                                                    {numero(d)})
                                                </span>
                                            </span>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}

                        <div className="border-base-300 space-y-3 border-t p-4">
                            {cierre.errors.cierre && <p className="text-error text-sm">{cierre.errors.cierre}</p>}

                            {confirmando ? (
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        cerrarConteo();
                                    }}
                                    className="space-y-3"
                                >
                                    <div>
                                        <label className="label label-text" htmlFor="cierre-observaciones">
                                            Observaciones del cierre (opcional)
                                        </label>
                                        <textarea
                                            id="cierre-observaciones"
                                            className="textarea textarea-bordered w-full"
                                            rows={2}
                                            maxLength={1000}
                                            value={cierre.data.observaciones}
                                            onChange={(e) => cierre.setData('observaciones', e.target.value)}
                                        />
                                    </div>
                                    <div>
                                        <label className="label label-text" htmlFor="cierre-firmado">
                                            Hoja firmada (opcional)
                                        </label>
                                        <input
                                            id="cierre-firmado"
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                            className="file-input file-input-bordered file-input-sm w-full"
                                            onChange={(e) => cierre.setData('firmado', e.target.files?.[0] ?? null)}
                                        />
                                        <p className="text-base-content/60 mt-1 text-xs">
                                            El escaneo o la foto de la hoja con las firmas. PDF, JPG o PNG, hasta 10 MB.
                                            El acta con valor contable es el ajuste; esto es el papel que lo respalda.
                                        </p>
                                        {cierre.errors.firmado && <p className="text-error text-sm">{cierre.errors.firmado}</p>}
                                    </div>
                                    <div className="flex justify-end gap-2">
                                        <button type="button" className="btn btn-ghost" onClick={() => setConfirmando(false)}>
                                            Cancelar
                                        </button>
                                        <button type="submit" className="btn btn-primary" disabled={cierre.processing}>
                                            <LockIcon className="size-4" />
                                            {diferencias.length === 0 ? 'Cerrar sin diferencias' : 'Cerrar y generar ajuste'}
                                        </button>
                                    </div>
                                </form>
                            ) : (
                                <div className="flex justify-end">
                                    <button
                                        type="button"
                                        className="btn btn-primary"
                                        disabled={!conteo.completa}
                                        onClick={() => setConfirmando(true)}
                                    >
                                        <LockIcon className="size-4" />
                                        Cerrar conteo
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {conteo.ajuste_folio && (
                    <div className="alert alert-success mt-4">
                        <span>
                            Este conteo cerró y generó el ajuste{' '}
                            {conteo.ajuste_id ? (
                                <Link href={`/admin/almacen/ajustes/${conteo.ajuste_id}`} className="link font-mono font-semibold">
                                    {conteo.ajuste_folio}
                                </Link>
                            ) : (
                                <strong className="font-mono">{conteo.ajuste_folio}</strong>
                            )}
                            , que es el que movió el saldo.
                            {conteo.observaciones && <span className="block text-sm">{conteo.observaciones}</span>}
                            {conteo.firmado_url ? (
                                <a
                                    href={conteo.firmado_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="link mt-1 block text-sm"
                                >
                                    Ver la hoja firmada
                                </a>
                            ) : (
                                <span className="text-base-content/60 block text-sm">
                                    Se cerró sin subir la hoja firmada.
                                </span>
                            )}
                        </span>
                    </div>
                )}

                {conteo.vencido && (
                    <div className="alert alert-warning mt-4">
                        <TriangleAlertIcon className="size-4" />
                        <span>Esta hoja tenía fecha {conteo.fecha_programada} y sigue sin contarse.</span>
                    </div>
                )}

                <p className="text-base-content/60 mt-4 text-sm">
                    La hoja impresa no trae el saldo del sistema a propósito: se anota lo que se encontró, no lo que
                    debería haber. Las diferencias se corrigen con un ajuste, nunca en esta hoja.
                </p>
            </div>
        </AppLayout>
    );
}
