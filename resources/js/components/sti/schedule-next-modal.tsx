import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { StiEquipo } from '@/types/models';
import { router } from '@inertiajs/react';
import { CalendarIcon, Loader2Icon } from 'lucide-react';
import { useState } from 'react';

type ScheduleNextModalProps = {
    open: boolean;
    onClose: () => void;
    equipo: StiEquipo | null;
    mantenimientoId: number;
    fechaRealizado?: string;
};

export function ScheduleNextModal({ open, onClose, equipo, mantenimientoId, fechaRealizado }: ScheduleNextModalProps) {
    const [processing, setProcessing] = useState(false);

    // Calcular fecha sugerida
    const getSuggestedDate = () => {
        const baseDate = fechaRealizado ? new Date(fechaRealizado) : new Date();
        if (equipo?.periodicidad_mantenimiento) {
            baseDate.setDate(baseDate.getDate() + equipo.periodicidad_mantenimiento);
        }
        return baseDate.toISOString().split('T')[0];
    };

    const [fechaSiguiente, setFechaSiguiente] = useState(getSuggestedDate());

    const handleConfirm = (crearSiguiente: boolean) => {
        setProcessing(true);
        router.post(
            `/admin/sti/mantenimientos/${mantenimientoId}/completar`,
            {
                crear_siguiente: crearSiguiente,
                fecha_siguiente: crearSiguiente ? fechaSiguiente : null,
            },
            {
                onFinish: () => {
                    setProcessing(false);
                    onClose();
                },
            }
        );
    };

    return (
        <Dialog open={open} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Marcar mantenimiento como realizado</DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    <p className="text-sm text-gray-600 dark:text-gray-400">
                        El mantenimiento será marcado como realizado.
                    </p>

                    {equipo?.periodicidad_mantenimiento && (
                        <div className="rounded-lg border bg-blue-50 p-4 dark:bg-blue-900/20">
                            <p className="mb-3 text-sm font-medium">
                                El equipo "{equipo.descripcion}" tiene una periodicidad de{' '}
                                <strong>{equipo.periodicidad_mantenimiento} dias</strong>.
                            </p>
                            <p className="mb-2 text-sm">¿Desea agendar el siguiente mantenimiento?</p>
                            <div className="flex items-center gap-2">
                                <CalendarIcon className="size-4 text-gray-500" />
                                <Input
                                    type="date"
                                    value={fechaSiguiente}
                                    onChange={(e) => setFechaSiguiente(e.target.value)}
                                    className="w-auto"
                                />
                            </div>
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose} disabled={processing}>
                        Cancelar
                    </Button>
                    {equipo?.periodicidad_mantenimiento ? (
                        <>
                            <Button variant="secondary" onClick={() => handleConfirm(false)} disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Solo marcar realizado
                            </Button>
                            <Button onClick={() => handleConfirm(true)} disabled={processing}>
                                {processing && <Loader2Icon className="size-4 animate-spin" />}
                                Marcar y agendar siguiente
                            </Button>
                        </>
                    ) : (
                        <Button onClick={() => handleConfirm(false)} disabled={processing}>
                            {processing && <Loader2Icon className="size-4 animate-spin" />}
                            Marcar como realizado
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
