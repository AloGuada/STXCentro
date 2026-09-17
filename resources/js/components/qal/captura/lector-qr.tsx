/**
 * Lector de QR con la cámara de la tablet.
 *
 * Usa `BarcodeDetector`, que Chrome en Android trae de fábrica: las tablets de
 * planta son ésas, así que no se carga ninguna librería. Donde el navegador no
 * lo tiene, el botón de cámara no aparece y queda la captura a mano del código.
 */

import { useEffect, useRef, useState } from 'react';

type Detector = { detect: (fuente: HTMLVideoElement) => Promise<{ rawValue: string }[]> };
type ConstructorDetector = new (opciones: { formats: string[] }) => Detector;

function constructorDetector(): ConstructorDetector | null {
    return (window as unknown as { BarcodeDetector?: ConstructorDetector }).BarcodeDetector ?? null;
}

export function lectorDisponible(): boolean {
    return typeof window !== 'undefined' && constructorDetector() !== null && !!navigator.mediaDevices?.getUserMedia;
}

/** Cada cuánto se revisa un cuadro del video. Más seguido sólo gasta batería. */
const INTERVALO_MS = 250;

export function LectorQr({ onLeido, onCerrar }: { onLeido: (codigo: string) => void; onCerrar: () => void }) {
    const video = useRef<HTMLVideoElement>(null);
    const alLeer = useRef(onLeido);
    const [error, setError] = useState<string | null>(() =>
        constructorDetector() ? null : 'Este navegador no lee QR. Teclea el código de la etiqueta.',
    );

    useEffect(() => {
        alLeer.current = onLeido;
    }, [onLeido]);

    useEffect(() => {
        const Detector = constructorDetector();
        if (!Detector) {
            return;
        }

        const detector = new Detector({ formats: ['qr_code'] });
        let flujo: MediaStream | null = null;
        let reloj: number | undefined;
        let vivo = true;

        const buscar = async () => {
            if (!vivo || !video.current) {
                return;
            }
            try {
                const [codigo] = await detector.detect(video.current);
                if (vivo && codigo?.rawValue) {
                    vivo = false;
                    alLeer.current(codigo.rawValue.trim());
                    return;
                }
            } catch {
                /* Un cuadro que no se pudo leer: se prueba con el siguiente. */
            }
            reloj = window.setTimeout(buscar, INTERVALO_MS);
        };

        navigator.mediaDevices
            .getUserMedia({ video: { facingMode: 'environment' }, audio: false })
            .then(async (stream) => {
                if (!vivo || !video.current) {
                    stream.getTracks().forEach((pista) => pista.stop());
                    return;
                }
                flujo = stream;
                video.current.srcObject = stream;
                await video.current.play();
                buscar();
            })
            .catch(() => setError('No se pudo abrir la cámara. Revisa el permiso del navegador o teclea el código.'));

        return () => {
            vivo = false;
            window.clearTimeout(reloj);
            flujo?.getTracks().forEach((pista) => pista.stop());
        };
    }, []);

    return (
        <div className="mt-3 rounded-box border border-base-300 bg-base-200 p-3">
            {error ? (
                <p className="text-sm text-error">{error}</p>
            ) : (
                <div className="relative overflow-hidden rounded-box bg-black">
                    <video ref={video} muted playsInline className="aspect-[4/3] w-full object-cover" />
                    <div className="pointer-events-none absolute inset-[18%] rounded-box border-4 border-primary/80" />
                </div>
            )}
            <div className="mt-2 flex items-center justify-between gap-2">
                <span className="text-xs text-base-content/60">Apunta al QR de la etiqueta de la pieza.</span>
                <button type="button" onClick={onCerrar} className="btn btn-ghost btn-sm border border-base-300">
                    Cerrar cámara
                </button>
            </div>
        </div>
    );
}
