import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type LabelProps = ComponentProps<'label'>;

function Label({ className, ...props }: LabelProps) {
    return (
        <label
            className={cn('label-text font-medium', className)}
            {...props}
        />
    );
}

export { Label };
