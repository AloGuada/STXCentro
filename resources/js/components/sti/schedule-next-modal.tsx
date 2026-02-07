import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { StiEquipo, StiPlan } from '@/types/models';
import { router } from '@inertiajs/react';
import { Loader2Icon } from 'lucide-react';
import { useState } from 'react';

type ScheduleNextModalProps = {
    open: boolean;
    onClose: () => void;
    equipo: StiEquipo | Pick<StiEquipo, 'id' | 'descripcion' | 'serie'> | null;
    mantenimientoId: number;
    fechaRealizado?: string;
    plan?: StiPlan | null;
};

export function ScheduleNextModal({ open, onClose, equipo, mantenimientoId, plan }: ScheduleNextModalProps) {
    const [processing, setProcessing] = useState(false);

    const handleConfirm = () => {
        setProcessing(true);
        router.post(
            `/admin/sti/mantenimientos/${mantenimientoId}/completar`,
            {},
            {
                onFinish: () => {
                    setProcessing(false);
                    onClose();
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Marcar mantenimiento como realizado</DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    <p className="text-sm text-gray-600 dark:text-gray-400">El mantenimiento sera marcado como realizado.</p>

                    {plan && (
                        <div className="rounded-lg border bg-blue-50 p-4 dark:bg-blue-900/20">
                            <p className="text-sm">
                                Este mantenimiento pertenece al plan <strong>"{plan.descripcion}"</strong>.
                            </p>
                            <p className="mt-2 text-sm text-gray-600">
                                Los siguientes mantenimientos se generan automaticamente segun la periodicidad del plan.
                            </p>
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button onClick={handleConfirm} disabled={processing}>
                        {processing && <Loader2Icon className="size-4 animate-spin" />}
                        Marcar como realizado
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
