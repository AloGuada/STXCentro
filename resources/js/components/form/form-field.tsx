import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

type FormFieldProps = {
    label: string;
    htmlFor: string;
    error?: string;
    description?: string;
    required?: boolean;
    /** Control que acompaña al título, alineado a la derecha del renglón. */
    accion?: ReactNode;
    className?: string;
    children: ReactNode;
};

export function FormField({
    label,
    htmlFor,
    error,
    description,
    required,
    accion,
    className,
    children,
}: FormFieldProps) {
    const titulo = (
        <label className="label" htmlFor={htmlFor}>
            <span className="label-text">
                {label}
                {required && <span className="text-error ml-1">*</span>}
            </span>
        </label>
    );

    return (
        <div className={cn('form-control w-full', className)}>
            {/* La acción va al lado del título, nunca dentro: un control dentro
                de un <label> hereda su clic y termina activando al de abajo. */}
            {accion ? (
                <div className="flex items-center justify-between gap-2">
                    {titulo}
                    {accion}
                </div>
            ) : (
                titulo
            )}
            {children}
            {description && (
                <label className="label">
                    <span className="label-text-alt text-base-content/60">{description}</span>
                </label>
            )}
            {error && (
                <label className="label">
                    <span className="label-text-alt text-error">{error}</span>
                </label>
            )}
        </div>
    );
}
