import { useCallback, useEffect, useRef, useState } from 'react';
import { Document, Page, pdfjs } from 'react-pdf';
import { ChevronLeft, ChevronRight, Loader2, ZoomIn, ZoomOut } from 'lucide-react';

import 'react-pdf/dist/Page/AnnotationLayer.css';
import 'react-pdf/dist/Page/TextLayer.css';

// Configure PDF.js worker (local to avoid CDN/certificate issues)
pdfjs.GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url).toString();

const ZOOM_MIN = 0.5;
const ZOOM_MAX = 5.0;
const ZOOM_STEP = 0.2;
const WHEEL_COMMIT_DELAY_MS = 250;

type Props = {
    url: string;
    title?: string;
};

export function SecurePdfViewer({ url, title }: Props) {
    const [numPages, setNumPages] = useState<number>(0);
    const [pageNumber, setPageNumber] = useState<number>(1);
    // scale = resolución a la que pdf.js dibuja el canvas (re-renderiza).
    // displayScale = lo que ve el usuario; durante un gesto se aplica como CSS
    // transform sobre el canvas existente para evitar re-renders intermedios.
    const [scale, setScale] = useState<number>(1.0);
    const [displayScale, setDisplayScale] = useState<number>(1.0);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    const scrollRef = useRef<HTMLDivElement>(null);
    const pageWrapperRef = useRef<HTMLDivElement>(null);
    const pinchRef = useRef<{ initialDist: number; initialScale: number } | null>(null);
    const scaleRef = useRef(scale);
    scaleRef.current = scale;
    const displayScaleRef = useRef(displayScale);
    displayScaleRef.current = displayScale;
    const wheelCommitTimerRef = useRef<number | null>(null);

    const clampScale = (s: number) => Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, s));

    // Preview rápido: solo CSS transform, sin pedir nuevo render del PDF.
    const previewScale = (target: number) => {
        setDisplayScale(target);
        const wrapper = pageWrapperRef.current;
        if (wrapper) {
            const ratio = target / scaleRef.current;
            wrapper.style.transform = ratio === 1 ? '' : `scale(${ratio})`;
            wrapper.style.transformOrigin = 'top center';
        }
    };

    // Commit: pide a pdf.js que re-renderice el canvas a la nueva resolución.
    // El transform se limpia cuando el nuevo render termina (onRenderSuccess).
    const commitScale = (target: number) => {
        setDisplayScale(target);
        setScale(target);
    };

    const onDocumentLoadSuccess = useCallback(({ numPages }: { numPages: number }) => {
        setNumPages(numPages);
        setLoading(false);
    }, []);

    const onDocumentLoadError = useCallback((error: Error) => {
        setError('Error al cargar el documento PDF');
        setLoading(false);
        console.error('PDF load error:', error);
    }, []);

    const onPageRenderSuccess = useCallback(() => {
        const wrapper = pageWrapperRef.current;
        if (wrapper && scaleRef.current === displayScaleRef.current) {
            wrapper.style.transform = '';
        }
    }, []);

    const goToPrevPage = () => setPageNumber((prev) => Math.max(1, prev - 1));
    const goToNextPage = () => setPageNumber((prev) => Math.min(numPages, prev + 1));
    const zoomIn = () => commitScale(clampScale(scaleRef.current + ZOOM_STEP));
    const zoomOut = () => commitScale(clampScale(scaleRef.current - ZOOM_STEP));

    useEffect(() => {
        const el = scrollRef.current;
        if (!el) return;

        const distancia = (t1: Touch, t2: Touch) => {
            const dx = t1.clientX - t2.clientX;
            const dy = t1.clientY - t2.clientY;
            return Math.hypot(dx, dy);
        };

        const onTouchStart = (e: TouchEvent) => {
            if (e.touches.length === 2) {
                e.preventDefault();
                pinchRef.current = {
                    initialDist: distancia(e.touches[0], e.touches[1]),
                    initialScale: displayScaleRef.current,
                };
            }
        };

        const onTouchMove = (e: TouchEvent) => {
            if (e.touches.length === 2 && pinchRef.current) {
                e.preventDefault();
                const dist = distancia(e.touches[0], e.touches[1]);
                const ratio = dist / pinchRef.current.initialDist;
                previewScale(clampScale(pinchRef.current.initialScale * ratio));
            }
        };

        const onTouchEnd = (e: TouchEvent) => {
            if (e.touches.length < 2 && pinchRef.current) {
                pinchRef.current = null;
                commitScale(displayScaleRef.current);
            }
        };

        // Ctrl/Cmd + rueda (incluye pinch de trackpad).
        const onWheel = (e: WheelEvent) => {
            if (!e.ctrlKey && !e.metaKey) return;
            e.preventDefault();
            const delta = -e.deltaY * 0.005;
            previewScale(clampScale(displayScaleRef.current + delta));
            if (wheelCommitTimerRef.current) window.clearTimeout(wheelCommitTimerRef.current);
            wheelCommitTimerRef.current = window.setTimeout(() => {
                commitScale(displayScaleRef.current);
            }, WHEEL_COMMIT_DELAY_MS);
        };

        el.addEventListener('touchstart', onTouchStart, { passive: false });
        el.addEventListener('touchmove', onTouchMove, { passive: false });
        el.addEventListener('touchend', onTouchEnd);
        el.addEventListener('touchcancel', onTouchEnd);
        el.addEventListener('wheel', onWheel, { passive: false });

        return () => {
            el.removeEventListener('touchstart', onTouchStart);
            el.removeEventListener('touchmove', onTouchMove);
            el.removeEventListener('touchend', onTouchEnd);
            el.removeEventListener('touchcancel', onTouchEnd);
            el.removeEventListener('wheel', onWheel);
            if (wheelCommitTimerRef.current) window.clearTimeout(wheelCommitTimerRef.current);
        };
    }, []);

    // Prevent right-click context menu
    const handleContextMenu = (e: React.MouseEvent) => {
        e.preventDefault();
        return false;
    };

    // Prevent keyboard shortcuts for copying/saving
    const handleKeyDown = (e: React.KeyboardEvent) => {
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'p' || e.key === 'c')) {
            e.preventDefault();
            return false;
        }
    };

    return (
        <div
            className="flex flex-col h-full select-none"
            onContextMenu={handleContextMenu}
            onKeyDown={handleKeyDown}
            tabIndex={0}
        >
            {/* Controls */}
            <div className="flex items-center justify-center gap-4 py-3 bg-slate-800/50 rounded-t-lg">
                {/* Zoom controls */}
                <div className="flex items-center gap-2">
                    <button
                        onClick={zoomOut}
                        className="btn btn-circle btn-sm btn-ghost text-white"
                        disabled={displayScale <= ZOOM_MIN}
                        title="Reducir"
                    >
                        <ZoomOut className="size-4" />
                    </button>
                    <span className="text-white text-sm min-w-[4rem] text-center">
                        {Math.round(displayScale * 100)}%
                    </span>
                    <button
                        onClick={zoomIn}
                        className="btn btn-circle btn-sm btn-ghost text-white"
                        disabled={displayScale >= ZOOM_MAX}
                        title="Ampliar"
                    >
                        <ZoomIn className="size-4" />
                    </button>
                </div>

                <div className="w-px h-6 bg-slate-600" />

                {/* Page navigation */}
                <div className="flex items-center gap-2">
                    <button
                        onClick={goToPrevPage}
                        className="btn btn-circle btn-sm btn-ghost text-white"
                        disabled={pageNumber <= 1}
                        title="Página anterior"
                    >
                        <ChevronLeft className="size-5" />
                    </button>
                    <span className="text-white text-sm min-w-[5rem] text-center">
                        {pageNumber} / {numPages || '...'}
                    </span>
                    <button
                        onClick={goToNextPage}
                        className="btn btn-circle btn-sm btn-ghost text-white"
                        disabled={pageNumber >= numPages}
                        title="Página siguiente"
                    >
                        <ChevronRight className="size-5" />
                    </button>
                </div>
            </div>

            {/* PDF Viewer */}
            <div
                ref={scrollRef}
                className="flex-1 overflow-auto bg-slate-900 rounded-b-lg flex justify-center touch-pan-y"
                style={{ minHeight: '70vh' }}
            >
                {loading && (
                    <div className="flex items-center justify-center h-full">
                        <Loader2 className="size-8 animate-spin text-slate-400" />
                    </div>
                )}

                {error && (
                    <div className="flex items-center justify-center h-full">
                        <p className="text-red-400">{error}</p>
                    </div>
                )}

                <div ref={pageWrapperRef} className="will-change-transform">
                    <Document
                        file={url}
                        onLoadSuccess={onDocumentLoadSuccess}
                        onLoadError={onDocumentLoadError}
                        loading={null}
                        className="py-4"
                    >
                        <Page
                            pageNumber={pageNumber}
                            scale={scale}
                            renderTextLayer={false}
                            renderAnnotationLayer={false}
                            className="shadow-xl"
                            onRenderSuccess={onPageRenderSuccess}
                            loading={null}
                        />
                    </Document>
                </div>
            </div>

            {/* Hidden title for accessibility */}
            {title && <span className="sr-only">{title}</span>}
        </div>
    );
}
