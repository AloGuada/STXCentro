import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
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
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={htmlFor}>
                {label}
                {required && <span className="text-destructive ml-1">*</span>}
            </Label>
            {children}
            {description && <p className="text-muted-foreground text-sm">{description}</p>}
            <InputError message={error} />
        </div>
    );
}
