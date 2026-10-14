import { router } from '@inertiajs/react';
import { CheckIcon, CopyIcon, LinkIcon, Loader2Icon } from 'lucide-react';
import { useState, type MouseEvent } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { StiAsignacionActivo } from '@/types/models';

type Props = {
    asignacion: Pick<StiAsignacionActivo, 'id' | 'firma_url'>;
    size?: 'xs' | 'sm' | 'md';
};

// Se copia tambien como hipervinculo: al pegarlo en un correo queda clicable
// aunque el cliente no convierta las direcciones de texto en enlaces.
async function copiarEnlace(url: string): Promise<void> {
    if (typeof ClipboardItem === 'undefined' || !navigator.clipboard.write) {
        await navigator.clipboard.writeText(url);
        return;
    }

    const ancla = document.createElement('a');
    ancla.href = url;
    ancla.textContent = url;

    await navigator.clipboard.write([
        new ClipboardItem({
            'text/plain': new Blob([url], { type: 'text/plain' }),
            'text/html': new Blob([ancla.outerHTML], { type: 'text/html' }),
        }),
    ]);
}

export function EnlaceFirma({ asignacion, size }: Props) {
    const [copiado, setCopiado] = useState(false);
    const [generando, setGenerando] = useState(false);
    const icono = size === 'xs' ? 'size-3' : 'size-4';

    // En la tabla cada celda es un enlace a la edicion; sin esto el clic navega.
    const detenerNavegacion = (e: MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const copiar = async (e: MouseEvent) => {
        detenerNavegacion(e);
        if (!asignacion.firma_url) return;

        try {
            await copiarEnlace(asignacion.firma_url);
            setCopiado(true);
            setTimeout(() => setCopiado(false), 2000);
        } catch {
            window.prompt('Copia el enlace de firma:', asignacion.firma_url);
        }
    };

    const generar = (e: MouseEvent) => {
        detenerNavegacion(e);
        setGenerando(true);
        router.post(
            `/admin/sti/asignacion-activos/${asignacion.id}/enlace-firma`,
            {},
            { preserveScroll: true, onFinish: () => setGenerando(false) },
        );
    };

    if (!asignacion.firma_url) {
        return (
            <Button
                type="button"
                variant="outline"
                size={size}
                onClick={generar}
                disabled={generando}
            >
                {generando ? (
                    <Loader2Icon className={cn(icono, 'animate-spin')} />
                ) : (
                    <LinkIcon className={icono} />
                )}
                Generar enlace de firma
            </Button>
        );
    }

    return (
        <Button
            type="button"
            variant="outline"
            size={size}
            onClick={copiar}
            title={asignacion.firma_url}
        >
            {copiado ? (
                <CheckIcon className={icono} />
            ) : (
                <CopyIcon className={icono} />
            )}
            {copiado ? 'Copiado' : 'Copiar enlace de firma'}
        </Button>
    );
}
