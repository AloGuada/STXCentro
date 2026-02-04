import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

type FormFieldProps = {
    label: string;
    htmlFor: string;
    error?: string;
    description?: string;
    required?: boolean;
    className?: string;
    children: ReactNode;
};

export function FormField({
    label,
    htmlFor,
    error,
    description,
    required,
    className,
    children,
}: FormFieldProps) {
    return (
        <div className={cn('form-control w-full', className)}>
            <label className="label" htmlFor={htmlFor}>
                <span className="label-text">
                    {label}
                    {required && <span className="text-error ml-1">*</span>}
                </span>
            </label>
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
