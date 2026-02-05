import { CostosManager } from '@/components/sti/costos-manager';
import { RatingSlider } from '@/components/sti/rating-slider';
import { DeleteDialog } from '@/components/delete-dialog';
import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Departamento, StiCostoMantenimiento, StiEquipo, StiStatus, StiTecnico, StiTicket, StiTicketHistorial } from '@/types/models';
import { Head, Link, useForm } from '@inertiajs/react';
import { ClockIcon, Loader2Icon, PenLineIcon } from 'lucide-react';
import { useEffect, useRef, type FormEvent } from 'react';
import SignaturePad from 'signature_pad';

type Props = {
    ticket: StiTicket & {
        historial: (StiTicketHistorial & { status: StiStatus })[];
        costos: StiCostoMantenimiento[];
    };
    tecnicos: Pick<StiTecnico, 'id' | 'descripcion'>[];
    equipos: Pick<StiEquipo, 'id' | 'descripcion' | 'serie'>[];
    departamentos: Pick<Departamento, 'id' | 'descripcion'>[];
    statuses: (Pick<StiStatus, 'id' | 'descripcion'> & { orden: number })[];
};

export default function TicketsEdit({ ticket, tecnicos, equipos, departamentos, statuses }: Props) {
    const signaturePadRef = useRef<SignaturePad | null>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'STI', href: '/admin/sti/equipos' },
        { title: 'Tickets', href: '/admin/sti/tickets' },
        { title: `Ticket #${ticket.id}`, href: `/admin/sti/tickets/${ticket.id}/edit` },
    ];

    const currentStatusId = ticket.historial?.[0]?.status_id?.toString() ?? '';
    const currentStatus = statuses.find((s) => s.id.toString() === currentStatusId);
    const isCompletado = currentStatus && currentStatus.orden >= 8;

    const { data, setData, put, processing, errors } = useForm({
        nombre_solicitante: ticket.nombre_solicitante,
        comentario: ticket.comentario,
        tecnico_id: ticket.tecnico_id?.toString() ?? '',
        equipo_id: ticket.equipo_id?.toString() ?? '',
        departamento_id: ticket.departamento_id.toString(),
        status_id: currentStatusId,
        firma_completado: ticket.firma_completado ?? '',
        calificacion: ticket.calificacion ?? 0,
    });

    useEffect(() => {
        if (canvasRef.current && !ticket.firma_completado) {
            signaturePadRef.current = new SignaturePad(canvasRef.current, {
                backgroundColor: 'rgb(255, 255, 255)',
            });
        }
    }, [ticket.firma_completado]);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        // Get signature if available
        if (signaturePadRef.current && !signaturePadRef.current.isEmpty()) {
            setData('firma_completado', signaturePadRef.current.toDataURL());
        }

        put(`/admin/sti/tickets/${ticket.id}`);
    };

    const clearSignature = () => {
        if (signaturePadRef.current) {
            signaturePadRef.current.clear();
        }
    };

    // Get selected status to check if it's completado
    const selectedStatus = statuses.find((s) => s.id.toString() === data.status_id);
    const showFirmaSection = selectedStatus && selectedStatus.orden >= 8;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar Ticket #${ticket.id}`} />

            <div className="mx-auto max-w-4xl space-y-6 p-6">
                {/* Card principal */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Editar Ticket #{ticket.id}</CardTitle>
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <Link href={`/admin/sti/tickets/${ticket.id}`}>Ver Historial</Link>
                            </Button>
                            <DeleteDialog
                                title="Eliminar ticket"
                                description={`¿Estas seguro de eliminar el ticket #${ticket.id}? Esta accion no se puede deshacer.`}
                                deleteUrl={`/admin/sti/tickets/${ticket.id}`}
                            />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <FormField label="Nombre del Solicitante" htmlFor="nombre_solicitante" error={errors.nombre_solicitante} required>
                                <Input
                                    id="nombre_solicitante"
                                    value={data.nombre_solicitante}
                                    onChange={(e) => setData('nombre_solicitante', e.target.value)}
                                    placeholder="Nombre de quien reporta"
                                />
                            </FormField>

                            <FormField label="Departamento" htmlFor="departamento_id" error={errors.departamento_id} required>
                                <Select
                                    id="departamento_id"
                                    value={data.departamento_id}
                                    onValueChange={(value) => setData('departamento_id', value)}
                                >
                                    <option value="">Seleccionar departamento</option>
                                    {departamentos.map((depto) => (
                                        <option key={depto.id} value={depto.id}>
                                            {depto.descripcion}
                                        </option>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Comentario / Descripcion del problema" htmlFor="comentario" error={errors.comentario} required>
                                <textarea
                                    id="comentario"
                                    className="textarea textarea-bordered min-h-24 w-full"
                                    value={data.comentario}
                                    onChange={(e) => setData('comentario', e.target.value)}
                                    placeholder="Describe el problema o solicitud"
                                />
                            </FormField>

                            <FormField label="Equipo (opcional)" htmlFor="equipo_id" error={errors.equipo_id}>
                                <Select
                                    id="equipo_id"
                                    value={data.equipo_id}
                                    onValueChange={(value) => setData('equipo_id', value)}
                                >
                                    <option value="">Sin equipo asociado</option>
                                    {equipos.map((equipo) => (
                                        <option key={equipo.id} value={equipo.id}>
                                            {equipo.descripcion} {equipo.serie ? `(${equipo.serie})` : ''}
                                        </option>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Tecnico asignado" htmlFor="tecnico_id" error={errors.tecnico_id}>
                                <Select
                                    id="tecnico_id"
                                    value={data.tecnico_id}
                                    onValueChange={(value) => setData('tecnico_id', value)}
                                >
                                    <option value="">Sin asignar</option>
                                    {tecnicos.map((tecnico) => (
                                        <option key={tecnico.id} value={tecnico.id}>
                                            {tecnico.descripcion}
                                        </option>
                                    ))}
                                </Select>
                            </FormField>

                            <FormField label="Estado" htmlFor="status_id" error={errors.status_id}>
                                <Select
                                    id="status_id"
                                    value={data.status_id}
                                    onValueChange={(value) => setData('status_id', value)}
                                >
                                    <option value="">Sin estado</option>
                                    {statuses.map((status) => (
                                        <option key={status.id} value={status.id}>
                                            {status.descripcion}
                                        </option>
                                    ))}
                                </Select>
                            </FormField>

                            {/* Seccion de firma y calificacion (solo si estado es Completado o superior) */}
                            {showFirmaSection && (
                                <div className="space-y-4 rounded-lg border bg-green-50 p-4 dark:bg-green-900/20">
                                    <h3 className="font-medium">Cierre del Ticket</h3>

                                    {/* Calificacion */}
                                    <FormField label="Calificacion del servicio" htmlFor="calificacion">
                                        <RatingSlider
                                            value={data.calificacion || null}
                                            onChange={(value) => setData('calificacion', value)}
                                            disabled={isCompletado && !!ticket.calificacion}
                                        />
                                    </FormField>

                                    {/* Firma */}
                                    <FormField label="Firma de conformidad" htmlFor="firma">
                                        {ticket.firma_completado ? (
                                            <div className="rounded border bg-white p-2">
                                                <img src={ticket.firma_completado} alt="Firma" className="max-h-32" />
                                            </div>
                                        ) : (
                                            <div className="space-y-2">
                                                <div className="rounded border bg-white">
                                                    <canvas ref={canvasRef} width={400} height={150} className="w-full" />
                                                </div>
                                                <Button type="button" variant="outline" size="sm" onClick={clearSignature}>
                                                    <PenLineIcon className="size-4" />
                                                    Limpiar firma
                                                </Button>
                                            </div>
                                        )}
                                    </FormField>
                                </div>
                            )}

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/sti/tickets">Cancelar</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Historial de estados */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ClockIcon className="size-5" />
                            Historial de Estados
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {ticket.historial && ticket.historial.length > 0 ? (
                            <div className="space-y-3">
                                {ticket.historial.map((h, index) => (
                                    <div
                                        key={h.id}
                                        className={`flex items-center gap-3 rounded-lg border p-3 ${index === 0 ? 'border-primary bg-primary/5' : ''}`}
                                    >
                                        <div className={`size-3 rounded-full ${index === 0 ? 'bg-primary' : 'bg-gray-300'}`} />
                                        <div className="flex-1">
                                            <p className="font-medium">{h.status?.descripcion}</p>
                                            <p className="text-sm text-gray-500">
                                                {new Date(h.created_at).toLocaleString('es-MX', {
                                                    day: 'numeric',
                                                    month: 'short',
                                                    year: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No hay historial de estados.</p>
                        )}
                    </CardContent>
                </Card>

                {/* Costos */}
                <Card>
                    <CardHeader>
                        <CardTitle>Costos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <CostosManager
                            costos={ticket.costos}
                            storeUrl={`/admin/sti/tickets/${ticket.id}/costos`}
                            destroyUrlPrefix={`/admin/sti/tickets/${ticket.id}/costos`}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
