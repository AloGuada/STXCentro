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
import { Trash2Icon } from 'lucide-react';
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
            <DialogTrigger className="btn btn-error">
                {trigger ?? (
                    <>
                        <Trash2Icon className="size-4" />
                        Eliminar
                    </>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose className="btn btn-ghost">
                        Cancelar
                    </DialogClose>
                    <Button variant="error" onClick={handleDelete} loading={processing}>
                        Eliminar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
