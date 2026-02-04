import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type BadgeVariant = 'primary' | 'secondary' | 'accent' | 'ghost' | 'info' | 'success' | 'warning' | 'error' | 'outline';

type BadgeProps = ComponentProps<'span'> & {
    variant?: BadgeVariant;
};

function Badge({ className, variant, ...props }: BadgeProps) {
    return (
        <span
            className={cn(
                'badge',
                variant && `badge-${variant}`,
                className,
            )}
            {...props}
        />
    );
}

export { Badge };
