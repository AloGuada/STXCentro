import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';

export function useCan() {
    const { auth } = usePage<SharedData>().props;
    const permissions = auth.permissions ?? [];

    const can = (permission: string): boolean => permissions.includes(permission);

    const canAny = (perms: string[]): boolean => perms.some((p) => permissions.includes(p));

    return { can, canAny };
}
