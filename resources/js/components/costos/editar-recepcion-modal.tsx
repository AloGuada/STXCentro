import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { useEffect, type FormEvent } from 'react';

export type FacturaOpcion = { id: number; folio: string | null; total: number; estatus: string | null };

export type RecepcionEditable = {
    id: number;
    folio: string | null;
    fecha_entrega: string | null;
    recibido_por_id: string | null;
    observaciones: string | null;
    factura_id: number | null;
    completa_factura: boolean;
    facturas_disponibles: FacturaOpcion[];
};

type UsuarioOpcion = { id: string; name: string };

type Props = {
    recepcion: RecepcionEditable | null;
    usuarios: UsuarioOpcion[];
    onClose: () => void;
};

const fmt = (n: number) => `$${n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/**
 * Corrige los datos de captura de una recepción y la factura a la que se ligó.
 * Cantidades y precios quedan fuera a propósito: mueven presupuesto, y para eso
 * el camino es cancelar la recepción y volver a capturarla.
 */
export function EditarRecepcionModal({ recepcion, usuarios, onClose }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<{
        fecha_entrega: string;
        recibido_por: string;
        factura_id: string;
        completa_factura: boolean;
        observaciones: string;
        archivo: File | null;
    }>({
        fecha_entrega: '',
        recibido_por: '',
        factura_id: '',
        completa_factura: false,
        observaciones: '',
        archivo: null,
    });

    // Al abrir con otra recepción hay que recargar el formulario: el modal se
    // reutiliza para todos los renglones de la tabla.
    useEffect(() => {
        if (!recepcion) return;

        clearErrors();
        setData({
            fecha_entrega: recepcion.fecha_entrega?.slice(0, 10) ?? '',
            recibido_por: recepcion.recibido_por_id ?? '',
            factura_id: recepcion.factura_id ? String(recepcion.factura_id) : '',
            completa_factura: recepcion.completa_factura,
            observaciones: recepcion.observaciones ?? '',
            archivo: null,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [recepcion?.id]);

    // El backend manda los bloqueos (cancelada, factura pagada) bajo la llave
    // generica `error`, que no es un campo del formulario.
    const errorGeneral = (errors as Record<string, string | undefined>).error;

    const cerrar = () => {
        reset();
        onClose();
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (!recepcion) return;

        post(`/admin/costos/entregas/${recepcion.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: cerrar,
        });
    };

    return (
        <Dialog open={!!recepcion} onOpenChange={(abierto) => !abierto && cerrar()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Editar recepción {recepcion?.folio ?? ''}</DialogTitle>
                    <DialogDescription>
                        Datos de captura y factura ligada. Para corregir cantidades o precios hay que cancelar la
                        recepción y volver a registrarla, porque esos sí mueven el presupuesto y el estatus de la orden.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    {errorGeneral && <div className="alert alert-error text-sm">{errorGeneral}</div>}

                    <FormField
                        label="Fecha de entrega"
                        htmlFor="fecha_entrega"
                        error={errors.fecha_entrega}
                        required
                    >
                        <Input
                            id="fecha_entrega"
                            type="date"
                            value={data.fecha_entrega}
                            onChange={(e) => setData('fecha_entrega', e.target.value)}
                            error={!!errors.fecha_entrega}
                        />
                    </FormField>

                    <FormField label="Recibió" htmlFor="recibido_por" error={errors.recibido_por} required>
                        <Select
                            value={data.recibido_por}
                            onValueChange={(v) => setData('recibido_por', v)}
                            placeholder="Selecciona quién recibió"
                            error={!!errors.recibido_por}
                        >
                            {usuarios.map((u) => (
                                <SelectItem key={u.id} value={u.id}>
                                    {u.name}
                                </SelectItem>
                            ))}
                        </Select>
                    </FormField>

                    <FormField
                        label="Factura ligada"
                        htmlFor="factura_id"
                        error={errors.factura_id}
                        description="Opcional. Sólo facturas de la misma orden de compra que todavía no avanzan."
                    >
                        <Select
                            value={data.factura_id}
                            onValueChange={(v) =>
                                setData({ ...data, factura_id: v, completa_factura: v ? data.completa_factura : false })
                            }
                            error={!!errors.factura_id}
                        >
                            <SelectItem value="">— Sin factura —</SelectItem>
                            {(recepcion?.facturas_disponibles ?? []).map((f) => (
                                <SelectItem key={f.id} value={String(f.id)}>
                                    {f.folio ?? `#${f.id}`} · {fmt(Number(f.total))}
                                </SelectItem>
                            ))}
                        </Select>
                    </FormField>

                    <label className="label cursor-pointer justify-start gap-2">
                        <input
                            type="checkbox"
                            className="checkbox"
                            disabled={!data.factura_id}
                            checked={data.completa_factura}
                            onChange={(e) => setData('completa_factura', e.target.checked)}
                        />
                        <span className="label-text">Esta recepción completa la factura</span>
                    </label>

                    <FormField label="Observaciones" htmlFor="observaciones" error={errors.observaciones}>
                        <textarea
                            id="observaciones"
                            rows={3}
                            className="textarea textarea-bordered w-full"
                            value={data.observaciones}
                            onChange={(e) => setData('observaciones', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label="Evidencia"
                        htmlFor="archivo"
                        error={errors.archivo}
                        description="Opcional. Si subes un archivo reemplaza la evidencia actual."
                    >
                        <input
                            id="archivo"
                            type="file"
                            className="file-input file-input-bordered w-full"
                            onChange={(e) => setData('archivo', e.target.files?.[0] ?? null)}
                        />
                    </FormField>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={cerrar}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Loader2Icon className="size-4 animate-spin" />}
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
