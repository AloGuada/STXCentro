import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type CheckboxProps = Omit<ComponentProps<'input'>, 'type' | 'checked' | 'onChange'> & {
    checked?: boolean;
    onCheckedChange?: (checked: boolean) => void;
};

function Checkbox({ className, checked, onCheckedChange, ...props }: CheckboxProps) {
    return (
        <input
            type="checkbox"
            className={cn('checkbox', className)}
            checked={checked}
            onChange={(e) => onCheckedChange?.(e.target.checked)}
            {...props}
        />
    );
}

export { Checkbox };
