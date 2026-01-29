import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { router } from '@inertiajs/react';
import { Loader2Icon, Trash2Icon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

type DeleteDialogProps = {
    title?: string;
    description?: string;
    deleteUrl: string;
    trigger?: ReactNode;
    onSuccess?: () => void;
};

export function DeleteDialog({
    title = 'Eliminar registro',
    description = '¿Estás seguro de que deseas eliminar este registro? Esta acción no se puede deshacer.',
    deleteUrl,
    trigger,
    onSuccess,
}: DeleteDialogProps) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const handleDelete = () => {
        setProcessing(true);
        router.delete(deleteUrl, {
            onSuccess: () => {
                setOpen(false);
                onSuccess?.();
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button variant="destructive">
                        <Trash2Icon className="size-4" />
                        Eliminar
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline" disabled={processing}>
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button variant="destructive" onClick={handleDelete} disabled={processing}>
                        {processing && <Loader2Icon className="size-4 animate-spin" />}
                        Eliminar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
