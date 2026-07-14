import { Link } from '@inertiajs/react';
import { cloneElement, isValidElement, type ComponentProps, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

type ButtonVariant = 'primary' | 'secondary' | 'accent' | 'ghost' | 'link' | 'outline' | 'error' | 'destructive' | 'default';
type ButtonSize = 'xs' | 'sm' | 'md' | 'lg' | 'icon';

type ButtonProps = ComponentProps<'button'> & {
    variant?: ButtonVariant;
    size?: ButtonSize;
    loading?: boolean;
    asChild?: boolean;
    children?: ReactNode;
};

function Button({
    variant,
    size,
    loading = false,
    asChild = false,
    className,
    disabled,
    children,
    ...props
}: ButtonProps) {
    // Mapear variantes de shadcn a DaisyUI
    const variantMap: Record<string, string> = {
        destructive: 'btn-error',
        default: '',
    };

    const sizeMap: Record<string, string> = {
        icon: 'btn-square btn-sm',
    };

    const variantClass = variant ? (variantMap[variant] ?? `btn-${variant}`) : '';
    const sizeClass = size ? (sizeMap[size] ?? `btn-${size}`) : '';

    const classes = cn('btn', variantClass, sizeClass, className);

    if (asChild) {
        // asChild: renderiza el hijo (Link, <a>, etc.) COMO botón, inyectándole
        // las clases del botón para que se vea como tal (patrón tipo Slot).
        if (isValidElement<{ className?: string }>(children)) {
            return cloneElement(children, {
                className: cn(classes, children.props.className),
            });
        }
        return children;
    }

    return (
        <button className={classes} disabled={disabled || loading} {...props}>
            {loading && <span className="loading loading-spinner loading-sm" />}
            {children}
        </button>
    );
}

// Componente para usar Button como Link
type ButtonLinkProps = ComponentProps<typeof Link> & {
    variant?: ButtonVariant;
    size?: ButtonSize;
};

function ButtonLink({ variant, size, className, children, ...props }: ButtonLinkProps) {
    return (
        <Link
            className={cn('btn', variant && `btn-${variant}`, size && `btn-${size}`, className)}
            {...props}
        >
            {children}
        </Link>
    );
}

export { Button, ButtonLink };
