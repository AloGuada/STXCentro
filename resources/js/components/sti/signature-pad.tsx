import SignaturePadLib from 'signature_pad';
import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Eraser } from 'lucide-react';

type SignaturePadProps = {
    value?: string | null;
    onChange: (dataUrl: string | null) => void;
    width?: number;
    height?: number;
    disabled?: boolean;
    className?: string;
};

export type SignaturePadRef = {
    clear: () => void;
};

export const SignaturePad = forwardRef<SignaturePadRef, SignaturePadProps>(
    ({ value, onChange, width = 400, height = 200, disabled = false, className }, ref) => {
        const canvasRef = useRef<HTMLCanvasElement>(null);
        const padRef = useRef<SignaturePadLib | null>(null);

        const clear = useCallback(() => {
            if (padRef.current) {
                padRef.current.clear();
                onChange(null);
            }
        }, [onChange]);

        useImperativeHandle(ref, () => ({
            clear,
        }));

        useEffect(() => {
            if (!canvasRef.current) return;

            const canvas = canvasRef.current;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);

            canvas.width = width * ratio;
            canvas.height = height * ratio;
            canvas.style.width = `${width}px`;
            canvas.style.height = `${height}px`;

            const context = canvas.getContext('2d');
            if (context) {
                context.scale(ratio, ratio);
            }

            padRef.current = new SignaturePadLib(canvas, {
                backgroundColor: 'rgb(255, 255, 255)',
                penColor: 'rgb(0, 0, 0)',
            });

            if (disabled) {
                padRef.current.off();
            }

            padRef.current.addEventListener('endStroke', () => {
                if (padRef.current && !padRef.current.isEmpty()) {
                    onChange(padRef.current.toDataURL('image/png'));
                }
            });

            return () => {
                if (padRef.current) {
                    padRef.current.off();
                }
            };
        }, [width, height, disabled, onChange]);

        useEffect(() => {
            if (!padRef.current || !value) return;

            if (value && padRef.current.isEmpty()) {
                const img = new Image();
                img.onload = () => {
                    if (padRef.current && canvasRef.current) {
                        const ctx = canvasRef.current.getContext('2d');
                        if (ctx) {
                            const ratio = Math.max(window.devicePixelRatio || 1, 1);
                            ctx.clearRect(0, 0, width, height);
                            ctx.drawImage(img, 0, 0, width, height);
                        }
                    }
                };
                img.src = value;
            }
        }, [value, width, height]);

        useEffect(() => {
            if (padRef.current) {
                if (disabled) {
                    padRef.current.off();
                } else {
                    padRef.current.on();
                }
            }
        }, [disabled]);

        return (
            <div className={className}>
                <div className="relative inline-block rounded-md border border-gray-300 dark:border-gray-600">
                    <canvas
                        ref={canvasRef}
                        className="cursor-crosshair rounded-md"
                        style={{ touchAction: 'none' }}
                    />
                    {!disabled && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="absolute right-2 top-2"
                            onClick={clear}
                        >
                            <Eraser className="size-4" />
                        </Button>
                    )}
                </div>
            </div>
        );
    }
);

SignaturePad.displayName = 'SignaturePad';
