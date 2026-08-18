import { BotonPdf } from '@/components/alm/boton-pdf';
import { FormField } from '@/components/form';
import { Button, ButtonLink } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { ESTATUS_TRANSFERENCIA, TRANSFERENCIAS_DEMO, USUARIOS_DEMO, resumenTransferencia } from '@/lib/alm/demo';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { ArrowLeftIcon, ArrowRightIcon, TriangleAlertIcon, TruckIcon } from 'lucide-react';
import { useState } from 'react';

const numero = (n: number) => n.toLocaleString('es-MX', { maximumFractionDigits: 3 });

type Props = {
    /** Id del documento. La maqueta lo lee de la URL y busca en los datos demo. */
    transferenciaId?: number;
};

/**
 * Segundo tiempo de la transferencia: la recepción.
 *
 * Es el mismo documento que firmó el origen, no uno nuevo. El destino captura
 * **lo que de verdad llegó** y el sistema saca la diferencia contra lo enviado;
 * pedirle que teclee el faltante sería pedirle que decida si hubo faltante.
 *
 * Mientras esta firma no exista, el material está en tránsito: ya salió del
 * origen y no es existencia del destino. Ese es el punto de partirlo en dos —si
 * se cerrara de un golpe, lo que se cae del camión desaparecería del kardex sin
 * que nadie lo hubiera recibido nunca.
 */
export default function TransferenciaShow({ transferenciaId }: Props) {
    const transferencia = TRANSFERENCIAS_DEMO.find((t) => t.id === transferenciaId) ?? TRANSFERENCIAS_DEMO[0];
    const enTransito = transferencia.estatus === 'en_transito';

    const [recibidos, setRecibidos] = useState<Record<number, string>>(() =>
        Object.fromEntries(
            transferencia.renglones
                .filter((r) => r.cantidad_recibida !== null)
                .map((r) => [r.producto_id, String(r.cantidad_recibida)]),
        ),
    );
    const [responsable, setResponsable] = useState(transferencia.faltante_responsable ?? '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Inventarios', href: '/admin/almacen/existencias' },
        { title: 'Transferencias', href: '/admin/almacen/transferencias' },
        { title: transferencia.folio, href: `/admin/almacen/transferencias/${transferencia.id}` },
    ];

    const filas = transferencia.renglones.map((renglon) => {
        const capturado = recibidos[renglon.producto_id];
        const recibido = capturado === undefined || capturado === '' ? null : Number(capturado);
        const faltante = recibido === null ? null : renglon.cantidad_enviada - recibido;

        return { renglon, recibido, faltante };
    });

    const pendientes = filas.filter((f) => f.recibido === null).length;
    const conFaltante = filas.filter((f) => f.faltante !== null && f.faltante > 0);
    const conSobrante = filas.filter((f) => f.faltante !== null && f.faltante < 0);
    // Ya cerrada, el resumen sale del documento; en tránsito, de lo capturado.
    const resumen = resumenTransferencia(transferencia);
    const estatus = ESTATUS_TRANSFERENCIA[transferencia.estatus];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Transferencia ${transferencia.folio}`} />

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-mono text-2xl font-semibold">{transferencia.folio}</h1>
                            <span className={`badge badge-sm ${estatus.clase}`}>{estatus.etiqueta}</span>
                            {!enTransito && resumen.faltante > 0 && (
                                <span className="badge badge-sm badge-error">
                                    Faltó {numero(resumen.faltante)}
                                </span>
                            )}
                            <span className="flex items-center gap-2">
                                <span className="badge badge-sm badge-ghost font-mono">{transferencia.origen}</span>
                                <ArrowRightIcon className="text-base-content/40 size-4" />
                                <span className="badge badge-sm badge-info font-mono">{transferencia.destino}</span>
                            </span>
                        </div>
                        <p className="text-base-content/60 mt-1 text-sm">
                            Enviado el {transferencia.fecha_envio} por {transferencia.envio} · autorizó{' '}
                            {transferencia.autorizo}
                            {transferencia.pedido_folio && (
                                <>
                                    {' '}
                                    · surte <span className="font-mono">{transferencia.pedido_folio}</span>
                                </>
                            )}
                            {transferencia.fecha_recepcion && (
                                <>
                                    {' '}
                                    · recibió {transferencia.recibio} el {transferencia.fecha_recepcion}
                                </>
                            )}
                        </p>
                        {transferencia.observaciones && (
                            <p className="text-base-content/50 mt-1 text-sm italic">{transferencia.observaciones}</p>
                        )}
                    </div>

                    <div className="flex gap-2">
                        <ButtonLink href="/admin/almacen/transferencias" variant="outline">
                            <ArrowLeftIcon className="size-4" />
                            Volver
                        </ButtonLink>
                        <BotonPdf folio={transferencia.folio} etiqueta="PDF" />
                    </div>
                </div>

                <div className="alert alert-warning mb-4">
                    <span>Vista de maqueta: la recepción todavía no guarda nada.</span>
                </div>

                {enTransito && (
                    <div className="alert alert-info mb-4">
                        <TruckIcon className="size-5" />
                        <span>
                            El material va en el camino: salió de {transferencia.origen} y todavía no es existencia de{' '}
                            {transferencia.destino}. Captura <strong>lo que llegó</strong>; el faltante lo calcula el
                            sistema.
                        </span>
                    </div>
                )}

                <div className="rounded-box border-base-300 overflow-x-auto border">
                    <table className="table table-sm">
                        <thead className="bg-base-200">
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th className="text-right">Enviado</th>
                                <th className="w-36 text-right">Recibido</th>
                                <th className="text-right">Faltante</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.map(({ renglon, recibido, faltante }) => (
                                <tr key={renglon.producto_id} className="hover">
                                    <td className="font-mono text-xs">{renglon.codigo}</td>
                                    <td>{renglon.descripcion}</td>
                                    <td className="text-right font-mono">
                                        {numero(renglon.cantidad_enviada)}{' '}
                                        <span className="text-base-content/40 text-xs">{renglon.unidad}</span>
                                    </td>
                                    <td>
                                        {enTransito ? (
                                            <div className="flex items-center justify-end gap-1">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.001"
                                                    className="input-sm w-24 text-right font-mono"
                                                    value={recibidos[renglon.producto_id] ?? ''}
                                                    onChange={(e) =>
                                                        setRecibidos((prev) => ({
                                                            ...prev,
                                                            [renglon.producto_id]: e.target.value,
                                                        }))
                                                    }
                                                    aria-label={`Recibido de ${renglon.codigo}`}
                                                />
                                                <span className="text-base-content/40 text-xs">{renglon.unidad}</span>
                                            </div>
                                        ) : (
                                            <div className="text-right font-mono">
                                                {recibido === null ? (
                                                    <span className="text-base-content/30">—</span>
                                                ) : (
                                                    numero(recibido)
                                                )}
                                            </div>
                                        )}
                                    </td>
                                    <td className="text-right font-mono">
                                        {faltante === null ? (
                                            <span className="text-base-content/30">—</span>
                                        ) : faltante === 0 ? (
                                            <span className="text-success">0</span>
                                        ) : faltante > 0 ? (
                                            <span className="text-error font-semibold">
                                                <TriangleAlertIcon className="mr-1 inline size-3" />
                                                {numero(faltante)}
                                            </span>
                                        ) : (
                                            <span className="text-warning font-semibold">
                                                +{numero(Math.abs(faltante))}
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {conSobrante.length > 0 && (
                    <div className="alert alert-warning mt-4">
                        <span>
                            Hay renglones donde llegó <strong>más</strong> de lo enviado. Eso no lo arregla la
                            recepción: se confirma lo enviado y la diferencia se corrige con un ajuste en el destino,
                            para que quede con folio y con quién lo autorizó.
                        </span>
                    </div>
                )}

                {enTransito ? (
                    <div className="mt-4 space-y-4">
                        {conFaltante.length > 0 && (
                            <div className="rounded-box border-error/40 bg-error/5 border p-4">
                                <p className="mb-3 text-sm">
                                    <TriangleAlertIcon className="text-error mr-1 inline size-4" />
                                    {conFaltante.length === 1
                                        ? 'Un renglón llegó incompleto.'
                                        : `${conFaltante.length} renglones llegaron incompletos.`}{' '}
                                    El faltante no se perdona: se queda registrado con dueño y con la fecha de esta
                                    recepción.
                                </p>
                                <FormField
                                    label="Responsable del faltante"
                                    htmlFor="faltante_responsable"
                                    description="Quién responde por lo que no llegó."
                                    required
                                >
                                    <Select
                                        id="faltante_responsable"
                                        value={responsable}
                                        onValueChange={setResponsable}
                                        placeholder="¿A quién se le carga?"
                                    >
                                        {USUARIOS_DEMO.map((u) => (
                                            <SelectItem key={u.id} value={u.nombre}>
                                                {u.nombre} — {u.puesto}
                                            </SelectItem>
                                        ))}
                                    </Select>
                                </FormField>
                            </div>
                        )}

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-base-content/60 max-w-2xl text-sm">
                                {pendientes === 0 ? (
                                    <>Todos los renglones tienen cantidad recibida.</>
                                ) : (
                                    <>
                                        Faltan <strong>{pendientes}</strong> de {filas.length} renglones por confirmar.
                                    </>
                                )}{' '}
                                Al confirmar, lo recibido entra al almacén {transferencia.destino} y el tránsito se
                                cierra.
                            </p>
                            <Button
                                disabled
                                title={pendientes > 0 ? 'Faltan renglones por confirmar' : undefined}
                            >
                                Confirmar recepción
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="mt-4 space-y-2">
                        <p className="text-base-content/60 text-sm">
                            Recepción cerrada: entraron {numero(resumen.recibido)} de {numero(resumen.enviado)} a{' '}
                            {transferencia.destino}.
                        </p>
                        {transferencia.faltante_responsable && (
                            <div className="alert alert-error">
                                <span>
                                    Se quedaron {numero(resumen.faltante)} en el camino, a cargo de{' '}
                                    <strong>{transferencia.faltante_responsable}</strong> desde el{' '}
                                    {transferencia.fecha_recepcion}.
                                </span>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
