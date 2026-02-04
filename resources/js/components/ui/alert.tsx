import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type AlertVariant = 'info' | 'success' | 'warning' | 'error' | 'destructive' | 'default';

type AlertProps = ComponentProps<'div'> & {
    variant?: AlertVariant;
};

function Alert({ className, variant = 'info', ...props }: AlertProps) {
    // Mapear variantes de shadcn a DaisyUI
    const variantMap: Record<string, string> = {
        destructive: 'alert-error',
        default: '',
    };
    const variantClass = variantMap[variant] ?? `alert-${variant}`;

    return (
        <div
            role="alert"
            className={cn('alert', variantClass, className)}
            {...props}
        />
    );
}

function AlertTitle({ className, ...props }: ComponentProps<'h3'>) {
    return (
        <h3
            className={cn('font-bold', className)}
            {...props}
        />
    );
}

function AlertDescription({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            className={cn('text-sm', className)}
            {...props}
        />
    );
}

export { Alert, AlertTitle, AlertDescription };
