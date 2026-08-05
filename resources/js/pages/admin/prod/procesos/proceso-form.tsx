import { FormField } from '@/components/form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { ProdProceso } from '@/types/models';
import { Link, useForm } from '@inertiajs/react';
import { Loader2Icon, PlusIcon, Trash2Icon } from 'lucide-react';
import type { FormEvent } from 'react';

export type EventoBorrador = { evento: string; descripcion: string };

type Props = {
    proceso?: ProdProceso;
};

/**
 * Alta y edición comparten forma: el proceso es un nombre y una lista de eventos
 * del export de planta que disparan su pago.
 */
export function ProcesoForm({ proceso }: Props) {
    const esEdicion = !!proceso;

    const { data, setData, post, put, processing, errors } = useForm<{
        nombre: string;
        orden: string;
        activo: boolean;
        eventos: EventoBorrador[];
    }>({
        nombre: proceso?.nombre ?? '',
        orden: String(proceso?.orden ?? 0),
        activo: proceso?.activo ?? true,
        eventos: (proceso?.eventos ?? []).map((e) => ({ evento: e.evento, descripcion: e.descripcion ?? '' })),
    });

    const editarEvento = (indice: number, cambio: Partial<EventoBorrador>) =>
        setData(
            'eventos',
            data.eventos.map((e, i) => (i === indice ? { ...e, ...cambio } : e)),
        );

    const agregarEvento = () => setData('eventos', [...data.eventos, { evento: '', descripcion: '' }]);

    const quitarEvento = (indice: number) =>
        setData(
            'eventos',
            data.eventos.filter((_, i) => i !== indice),
        );

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        esEdicion ? put(`/admin/prod/procesos/${proceso.id}`) : post('/admin/prod/procesos');
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-3 gap-4">
                <FormField label="Nombre" htmlFor="nombre" error={errors.nombre} className="col-span-2" required>
                    <Input
                        id="nombre"
                        value={data.nombre}
                        onChange={(e) => setData('nombre', e.target.value)}
                        error={!!errors.nombre}
                        placeholder="Soldadura, Pintura..."
                    />
                </FormField>

                <FormField
                    label="Orden"
                    htmlFor="orden"
                    error={errors.orden}
                    description="Cómo se listan en pantalla."
                >
                    <Input
                        id="orden"
                        type="number"
                        min="0"
                        value={data.orden}
                        onChange={(e) => setData('orden', e.target.value)}
                        error={!!errors.orden}
                    />
                </FormField>
            </div>

            <div>
                <div className="mb-2 flex items-center justify-between">
                    <div>
                        <span className="label-text font-medium">Eventos del export de planta</span>
                        <p className="text-base-content/60 text-sm">
                            Números que disparan el pago de este proceso (soldadura 75, pintura 85). Un evento sólo
                            puede estar en un proceso. Sin eventos, el proceso se captura únicamente a mano.
                        </p>
                    </div>
                    <Button type="button" size="sm" variant="outline" onClick={agregarEvento}>
                        <PlusIcon className="size-4" />
                        Agregar
                    </Button>
                </div>

                {errors.eventos && <p className="text-error mb-2 text-sm">{errors.eventos}</p>}

                <div className="rounded-box border-base-300 divide-base-300 divide-y border">
                    {data.eventos.length === 0 ? (
                        <p className="text-base-content/50 px-3 py-4 text-center text-sm">
                            Sin eventos configurados
                        </p>
                    ) : (
                        data.eventos.map((evento, i) => (
                            <div key={i} className="flex items-start gap-3 p-3">
                                <div className="w-28">
                                    <Input
                                        value={evento.evento}
                                        onChange={(e) => editarEvento(i, { evento: e.target.value })}
                                        placeholder="75"
                                        error={!!errors[`eventos.${i}.evento` as keyof typeof errors]}
                                    />
                                    {errors[`eventos.${i}.evento` as keyof typeof errors] && (
                                        <p className="text-error mt-1 text-xs">
                                            {errors[`eventos.${i}.evento` as keyof typeof errors]}
                                        </p>
                                    )}
                                </div>
                                <div className="flex-1">
                                    <Input
                                        value={evento.descripcion}
                                        onChange={(e) => editarEvento(i, { descripcion: e.target.value })}
                                        placeholder="Cómo se llama en el export (opcional)"
                                    />
                                </div>
                                <button
                                    type="button"
                                    className="btn btn-ghost btn-sm text-error"
                                    onClick={() => quitarEvento(i)}
                                    aria-label="Quitar evento"
                                >
                                    <Trash2Icon className="size-4" />
                                </button>
                            </div>
                        ))
                    )}
                </div>
            </div>

            <label className="flex cursor-pointer items-center gap-2">
                <input
                    type="checkbox"
                    className="checkbox checkbox-sm"
                    checked={data.activo}
                    onChange={(e) => setData('activo', e.target.checked)}
                />
                <span className="text-sm">Activo</span>
            </label>

            <div className="flex justify-end gap-2">
                <Button variant="outline" asChild>
                    <Link href="/admin/prod/procesos">Cancelar</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Loader2Icon className="size-4 animate-spin" />}
                    Guardar
                </Button>
            </div>
        </form>
    );
}
