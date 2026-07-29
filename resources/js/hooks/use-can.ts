import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';

export function useCan() {
    const { auth } = usePage<SharedData>().props;
    const permissions = auth.permissions ?? [];
    const roles = auth.roles ?? [];

    const can = (permission: string): boolean => permissions.includes(permission);

    const canAny = (perms: string[]): boolean => perms.some((p) => permissions.includes(p));

    /** ¿Tiene algún permiso del módulo, sin importar cuál? Ej: canModulo('costos'). */
    const canModulo = (modulo: string): boolean => permissions.some((p) => p.startsWith(`${modulo}.`));

    const hasRole = (role: string): boolean => roles.includes(role);

    return { can, canAny, canModulo, hasRole };
}
