import { router } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState } from 'react';

export type CotizEditLockType = 'generadora' | 'tarjeta';

export type CotizEditLockState =
    | { status: 'taking' }
    | { status: 'owned' }
    | {
          status: 'blocked';
          lockedBy: { id: string; name: string } | null;
          lockedAt: string | null;
      }
    | { status: 'error'; message: string };

/** El TTL del lock en el servidor es de 15 min; refrescamos a la mitad para no perderlo. */
const HEARTBEAT_MS = 5 * 60 * 1000;

/**
 * Toma el lock de edición de una generadora al montar y lo libera al desmontar.
 * Mientras la edición sigue abierta, re-postea el lock cada 5 min (heartbeat)
 * para refrescar `locked_at` antes de que expire el TTL del servidor.
 * Si alguien más tiene el lock vigente, retorna status=blocked con el dueño.
 */
export function useCotizEditLock(
    type: CotizEditLockType,
    id: number,
): CotizEditLockState {
    const [state, setState] = useState<CotizEditLockState>({
        status: 'taking',
    });

    useEffect(() => {
        let cancelled = false;
        let owned = false;
        let released = false;
        let heartbeat: ReturnType<typeof setInterval> | undefined;

        const lockUrl = `/admin/cotiz/lock/${type}/${id}`;

        const release = () => {
            // Solo liberamos si el servidor llegó a crear NUESTRO lock, y una sola vez.
            if (!owned || released) {
                return;
            }
            released = true;
            // fetch con keepalive sobrevive a la navegación SPA y al cierre de pestaña (como
            // sendBeacon) PERO permite mandar el header CSRF. La app Inertia no expone un meta
            // csrf-token; el token va en X-XSRF-TOKEN leído de la cookie (igual que axios), si no
            // Laravel responde 419. Best-effort.
            const url = `/admin/cotiz/unlock/${type}/${id}`;
            const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
            const xsrf = match ? decodeURIComponent(match[1]) : '';

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json' },
            }).catch(() => undefined);
        };

        axios
            .post(lockUrl)
            .then(() => {
                // El servidor ya creó el lock a nuestro nombre: somos dueños aunque el
                // componente se haya desmontado mientras tanto.
                owned = true;
                if (cancelled) {
                    // Salimos antes de que resolviera el POST → liberar ahora para no dejar
                    // un lock huérfano (la carrera de "entrar y volver con atrás" enseguida).
                    release();
                    return;
                }
                setState({ status: 'owned' });
                heartbeat = setInterval(() => {
                    axios.post(lockUrl).catch(() => undefined);
                }, HEARTBEAT_MS);
            })
            .catch((error) => {
                if (cancelled) {
                    return;
                }
                if (error.response?.status === 423) {
                    setState({
                        status: 'blocked',
                        lockedBy: error.response.data.locked_by ?? null,
                        lockedAt: error.response.data.locked_at ?? null,
                    });
                } else {
                    setState({
                        status: 'error',
                        message: 'No se pudo iniciar la edición.',
                    });
                }
            });

        window.addEventListener('beforeunload', release);

        // Inertia desmonta esta página DESPUÉS de recibir la respuesta del destino, así que el
        // unlock del cleanup llegaría tarde (el índice ya se renderizó con el lock). Por eso lo
        // enviamos al INICIAR la navegación. Solo en visitas GET (back/forward/enlaces): los
        // guardados inline de la grilla son PUT/POST/DELETE y no deben soltar el lock.
        const offBefore = router.on('before', (event) => {
            if (event.detail.visit.method === 'get') {
                release();
            }
        });

        return () => {
            cancelled = true;
            if (heartbeat) {
                clearInterval(heartbeat);
            }
            window.removeEventListener('beforeunload', release);
            offBefore();
            release();
        };
    }, [type, id]);

    return state;
}
