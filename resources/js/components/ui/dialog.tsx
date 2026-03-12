import { createContext, useContext, useEffect, useRef, type ComponentProps, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

type DialogContextType = {
    dialogRef: React.RefObject<HTMLDialogElement | null>;
    open: () => void;
    close: () => void;
};

const DialogContext = createContext<DialogContextType | null>(null);

function useDialog() {
    const context = useContext(DialogContext);
    if (!context) {
        throw new Error('useDialog must be used within a Dialog');
    }
    return context;
}

type DialogProps = {
    children: ReactNode;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
};

function Dialog({ children, open: isOpen, onOpenChange }: DialogProps) {
    const dialogRef = useRef<HTMLDialogElement>(null);

    const openDialog = () => {
        dialogRef.current?.showModal();
        onOpenChange?.(true);
    };

    const closeDialog = () => {
        dialogRef.current?.close();
        onOpenChange?.(false);
    };

    // Sincronizar el estado controlado con el diálogo nativo
    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;

        if (isOpen && !dialog.open) {
            dialog.showModal();
        } else if (!isOpen && dialog.open) {
            dialog.close();
        }
    }, [isOpen]);

    return (
        <DialogContext.Provider value={{ dialogRef, open: openDialog, close: closeDialog }}>
            {children}
        </DialogContext.Provider>
    );
}

type DialogTriggerProps = ComponentProps<'button'>;

function DialogTrigger({ children, onClick, ...props }: DialogTriggerProps) {
    const { open } = useDialog();

    return (
        <button
            type="button"
            onClick={(e) => {
                open();
                onClick?.(e);
            }}
            {...props}
        >
            {children}
        </button>
    );
}

type DialogContentProps = ComponentProps<'dialog'>;

function DialogContent({ className, children, ...props }: DialogContentProps) {
    const { dialogRef, close } = useDialog();

    return (
        <dialog
            ref={dialogRef}
            className="modal"
            onClick={(e) => {
                // Cerrar al hacer clic en el backdrop
                if (e.target === dialogRef.current) {
                    close();
                }
            }}
            {...props}
        >
            <div className={cn('modal-box', className)}>
                {children}
            </div>
            <form method="dialog" className="modal-backdrop">
                <button type="button" onClick={close}>close</button>
            </form>
        </dialog>
    );
}

function DialogHeader({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn('mb-4', className)}
            {...props}
        />
    );
}

function DialogFooter({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn('modal-action', className)}
            {...props}
        />
    );
}

function DialogTitle({ className, ...props }: ComponentProps<'h3'>) {
    return (
        <h3
            className={cn('text-lg font-bold', className)}
            {...props}
        />
    );
}

function DialogDescription({ className, ...props }: ComponentProps<'p'>) {
    return (
        <p
            className={cn('py-4', className)}
            {...props}
        />
    );
}

function DialogClose({ className, children, ...props }: ComponentProps<'button'>) {
    const { close } = useDialog();

    return (
        <button
            type="button"
            className={cn('btn', className)}
            onClick={close}
            {...props}
        >
            {children ?? 'Cerrar'}
        </button>
    );
}

export {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    useDialog,
};
