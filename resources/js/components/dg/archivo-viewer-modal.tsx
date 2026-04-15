import { useEffect, useRef, useState } from 'react';
import { ChevronLeft, ChevronRight, Download, Loader2, PanelRightClose, PanelRightOpen, StickyNote, X } from 'lucide-react';
import { renderAsync as renderDocxAsync } from 'docx-preview';
import * as XLSX from 'xlsx';
import { PPTXViewer } from 'pptxviewjs';
import { SecurePdfViewer } from '@/components/intra/SecurePdfViewer';
import NotasEditor from '@/components/dg/notas-editor';
import type { DgReporteArchivo } from '@/types/models';

type Props = {
    archivo: DgReporteArchivo | null;
    onClose: () => void;
    puedeEditarNotas?: boolean;
    puedeVerNotas?: boolean;
};

type TipoViewer = 'pdf' | 'docx' | 'xlsx' | 'pptx' | 'otro';

function detectarTipo(mime: string | null, nombre: string): TipoViewer {
    const m = (mime ?? '').toLowerCase();
    const n = nombre.toLowerCase();
    if (m.includes('pdf') || n.endsWith('.pdf')) return 'pdf';
    if (m.includes('wordprocessingml') || n.endsWith('.docx')) return 'docx';
    if (m.includes('spreadsheetml') || m.includes('ms-excel') || n.endsWith('.xlsx') || n.endsWith('.xls')) return 'xlsx';
    if (m.includes('presentationml') || m.includes('ms-powerpoint') || n.endsWith('.pptx') || n.endsWith('.ppt')) return 'pptx';
    return 'otro';
}

export default function ArchivoViewerModal({ archivo, onClose, puedeEditarNotas = false, puedeVerNotas = false }: Props) {
    const puedeAccederNotas = puedeEditarNotas || puedeVerNotas;
    const containerDocx = useRef<HTMLDivElement>(null);
    const canvasPptx = useRef<HTMLCanvasElement>(null);
    const pptxViewer = useRef<PPTXViewer | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [sheets, setSheets] = useState<{ name: string; html: string }[]>([]);
    const [sheetActiva, setSheetActiva] = useState(0);
    const [pptxSlide, setPptxSlide] = useState(0);
    const [pptxTotal, setPptxTotal] = useState(0);
    const [notasAbiertas, setNotasAbiertas] = useState(false);
    const [notasAncho, setNotasAncho] = useState<number>(() => {
        if (typeof window === 'undefined') return 384;
        const guardado = Number(localStorage.getItem('dg.notasAncho'));
        return guardado > 0 ? guardado : 384;
    });
    const resizingRef = useRef(false);

    useEffect(() => {
        const onMove = (e: MouseEvent) => {
            if (!resizingRef.current) return;
            const nuevo = Math.min(Math.max(window.innerWidth - e.clientX, 280), Math.floor(window.innerWidth * 0.7));
            setNotasAncho(nuevo);
        };
        const onUp = () => {
            if (resizingRef.current) {
                resizingRef.current = false;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                localStorage.setItem('dg.notasAncho', String(notasAncho));
            }
        };
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);
        return () => {
            window.removeEventListener('mousemove', onMove);
            window.removeEventListener('mouseup', onUp);
        };
    }, [notasAncho]);

    const iniciarResize = (e: React.MouseEvent) => {
        e.preventDefault();
        resizingRef.current = true;
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
    };

    const tipo = archivo ? detectarTipo(archivo.mime, archivo.nombre_original) : 'otro';
    const streamUrl = archivo ? `/admin/dg/archivos/${archivo.id}/stream` : '';
    const downloadUrl = archivo ? `/admin/dg/archivos/${archivo.id}/descargar` : '';

    useEffect(() => {
        if (!archivo) return;
        setError(null);
        setSheets([]);
        setSheetActiva(0);
        setPptxSlide(0);
        setPptxTotal(0);

        if (tipo === 'docx' || tipo === 'xlsx' || tipo === 'pptx') {
            setLoading(true);
            fetch(streamUrl, { credentials: 'same-origin' })
                .then((r) => {
                    if (!r.ok) throw new Error('No se pudo descargar el archivo');
                    return r.arrayBuffer();
                })
                .then(async (buffer) => {
                    if (tipo === 'docx' && containerDocx.current) {
                        containerDocx.current.innerHTML = '';
                        await renderDocxAsync(buffer, containerDocx.current, undefined, {
                            className: 'docx',
                            inWrapper: true,
                            breakPages: true,
                            useBase64URL: true,
                            experimental: true,
                            renderHeaders: true,
                            renderFooters: true,
                        });
                    }
                    if (tipo === 'xlsx') {
                        const wb = XLSX.read(buffer, { type: 'array', cellStyles: true, cellHTML: true });
                        const parsed = wb.SheetNames.map((name) => {
                            const ws = wb.Sheets[name];
                            if (!ws || !ws['!ref']) {
                                return { name, html: '<p class="p-4 text-base-content/60">Hoja vacía</p>' };
                            }
                            try {
                                return {
                                    name,
                                    html: XLSX.utils.sheet_to_html(ws, { editable: false, header: '', footer: '' }),
                                };
                            } catch {
                                return { name, html: '<p class="p-4 text-error">No se pudo renderizar esta hoja</p>' };
                            }
                        });
                        setSheets(parsed);
                    }
                    if (tipo === 'pptx' && canvasPptx.current) {
                        const canvas = canvasPptx.current;
                        // pptxviewjs lee canvas.style.width/height con parseFloat — deben estar en px, no % ni auto.
                        const parent = canvas.parentElement;
                        const maxW = Math.min(parent?.clientWidth ?? 1280, 1600);
                        const w = Math.max(800, maxW - 32);
                        const h = Math.round((w * 9) / 16);
                        canvas.style.width = `${w}px`;
                        canvas.style.height = `${h}px`;
                        const dpr = window.devicePixelRatio || 1;
                        canvas.width = Math.round(w * dpr);
                        canvas.height = Math.round(h * dpr);
                        try {
                            const viewer = new PPTXViewer({ canvas });
                            viewer.on('error', (err: unknown) => {
                                console.error('pptxviewjs error:', err);
                            });
                            await viewer.loadFile(buffer);

                            // Exponer globals que la lib usa internamente para resolver charts/imágenes
                            // (lo que normalmente hace mountSimpleViewer)
                            const proc = (viewer as unknown as { processor?: Record<string, unknown> }).processor;
                            if (proc) {
                                const procAny = proc as Record<string, unknown>;
                                const inner = procAny.processor as Record<string, unknown> | undefined;
                                const zipProc = procAny.zipProcessor as Record<string, unknown> | undefined;
                                const effectiveZip = procAny.zip ?? inner?.zip ?? zipProc?.zip ?? null;
                                const effectivePackage = procAny.package ?? inner?.package ?? zipProc?.package ?? null;
                                const w = window as unknown as Record<string, unknown>;
                                w.currentProcessor = {
                                    processor: proc,
                                    zip: effectiveZip,
                                    package: effectivePackage,
                                    reRenderShape: () => {
                                        const idx = viewer.getCurrentSlideIndex();
                                        viewer.render(canvas, { slideIndex: idx }).catch(() => {});
                                    },
                                };
                                if (effectiveZip) w.currentZipData = effectiveZip;
                            }

                            const count = viewer.getSlideCount();
                            if (count === 0) {
                                throw new Error('No se pudieron leer las diapositivas de este archivo.');
                            }
                            await viewer.render(canvas, { slideIndex: 0 });
                            // Re-render tras 200ms para capturar charts asíncronos
                            setTimeout(() => {
                                const idx = viewer.getCurrentSlideIndex();
                                viewer.render(canvas, { slideIndex: idx }).catch(() => {});
                            }, 200);

                            // Listener para re-renderizar cuando la lib resuelve charts asíncronos
                            const onChartComplete = () => {
                                const idx = viewer.getCurrentSlideIndex();
                                viewer.render(canvas, { slideIndex: idx }).catch(() => {});
                            };
                            window.addEventListener('chartRenderingComplete', onChartComplete);

                            pptxViewer.current = viewer;
                            setPptxTotal(count);
                            setPptxSlide(0);
                        } catch (e) {
                            const msg = e instanceof Error ? e.message : 'No se pudo renderizar la presentación.';
                            throw new Error(msg);
                        }
                    }
                })
                .catch((e) => setError(e.message ?? 'Error al cargar'))
                .finally(() => setLoading(false));
        }

        return () => {
            if (pptxViewer.current) {
                pptxViewer.current.destroy();
                pptxViewer.current = null;
            }
        };
    }, [archivo, tipo, streamUrl]);

    const irSlideAnterior = async () => {
        if (!pptxViewer.current || !canvasPptx.current) return;
        await pptxViewer.current.previousSlide(canvasPptx.current);
        setPptxSlide(pptxViewer.current.getCurrentSlideIndex());
    };

    const irSlideSiguiente = async () => {
        if (!pptxViewer.current || !canvasPptx.current) return;
        await pptxViewer.current.nextSlide(canvasPptx.current);
        setPptxSlide(pptxViewer.current.getCurrentSlideIndex());
    };

    if (!archivo) return null;

    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-base-100">
            <div className="flex items-center justify-between px-4 py-3 border-b border-base-300 bg-base-100 shrink-0">
                <div className="flex items-center gap-3 min-w-0">
                    <button type="button" onClick={onClose} className="btn btn-sm btn-ghost">
                        <ChevronLeft className="size-4" /> Cerrar
                    </button>
                    <h3 className="font-semibold truncate">{archivo.nombre_original}</h3>
                </div>
                <div className="flex items-center gap-2">
                    {puedeAccederNotas && (
                        <button
                            type="button"
                            onClick={() => setNotasAbiertas((v) => !v)}
                            className={`btn btn-sm ${notasAbiertas ? 'btn-primary' : 'btn-ghost'}`}
                            title={notasAbiertas ? 'Ocultar notas' : 'Mostrar notas'}
                        >
                            {notasAbiertas ? <PanelRightClose className="size-4" /> : <PanelRightOpen className="size-4" />}
                            <StickyNote className="size-4" /> Notas
                        </button>
                    )}
                    <a href={downloadUrl} className="btn btn-sm btn-ghost" title="Descargar">
                        <Download className="size-4" /> Descargar
                    </a>
                    <button type="button" onClick={onClose} className="btn btn-sm btn-circle btn-ghost" title="Cerrar">
                        <X className="size-4" />
                    </button>
                </div>
            </div>

            <div className="flex-1 flex overflow-hidden">
                <div className="flex-1 overflow-hidden bg-base-200 relative">
                    {loading && (
                        <div className="absolute inset-0 flex items-center justify-center bg-base-200/80 z-10">
                            <Loader2 className="size-8 animate-spin text-base-content/60" />
                        </div>
                    )}

                    {error && !loading && (
                        <div className="flex flex-col items-center justify-center h-full gap-3">
                            <p className="text-error text-center px-6">{error}</p>
                            <a href={downloadUrl} className="btn btn-primary">
                                <Download className="size-4" /> Descargar archivo
                            </a>
                        </div>
                    )}

                    {!loading && !error && tipo === 'pdf' && (
                        <div className="h-full p-2">
                            <SecurePdfViewer url={streamUrl} title={archivo.nombre_original} />
                        </div>
                    )}

                    {!error && tipo === 'docx' && (
                        <div className="h-full overflow-auto">
                            <div className="bg-white mx-auto my-4 shadow-lg" style={{ maxWidth: '900px' }}>
                                <div ref={containerDocx} className="p-4" />
                            </div>
                        </div>
                    )}

                    {!loading && !error && tipo === 'xlsx' && sheets.length > 0 && (
                        <div className="flex flex-col h-full bg-white">
                            <div role="tablist" className="tabs tabs-bordered shrink-0 bg-base-100 sticky top-0 z-10 border-b border-base-300 overflow-x-auto">
                                {sheets.map((s, i) => (
                                    <button
                                        key={s.name}
                                        role="tab"
                                        type="button"
                                        className={`tab whitespace-nowrap ${i === sheetActiva ? 'tab-active' : ''}`}
                                        onClick={() => setSheetActiva(i)}
                                    >
                                        {s.name}
                                    </button>
                                ))}
                            </div>
                            <div
                                className="flex-1 overflow-auto p-2 text-slate-900 [&_*]:!text-slate-900 [&_table]:border-collapse [&_table]:text-sm [&_td]:border [&_td]:border-slate-300 [&_td]:px-2 [&_td]:py-1 [&_td]:min-w-[80px] [&_td]:bg-white [&_th]:border [&_th]:border-slate-400 [&_th]:bg-slate-100 [&_th]:px-2 [&_th]:py-1 [&_th]:font-semibold [&_th]:sticky [&_th]:top-0"
                                dangerouslySetInnerHTML={{ __html: sheets[sheetActiva].html }}
                            />
                        </div>
                    )}

                    <div className={`${tipo === 'pptx' && !error ? 'flex' : 'hidden'} flex-col h-full`}>
                        <div className="flex items-center justify-center gap-3 py-2 bg-base-300 shrink-0">
                            <button
                                type="button"
                                onClick={irSlideAnterior}
                                disabled={pptxSlide <= 0}
                                className="btn btn-sm btn-circle btn-ghost"
                            >
                                <ChevronLeft className="size-4" />
                            </button>
                            <span className="text-sm min-w-[5rem] text-center">
                                {pptxTotal > 0 ? `${pptxSlide + 1} / ${pptxTotal}` : '...'}
                            </span>
                            <button
                                type="button"
                                onClick={irSlideSiguiente}
                                disabled={pptxSlide >= pptxTotal - 1}
                                className="btn btn-sm btn-circle btn-ghost"
                            >
                                <ChevronRight className="size-4" />
                            </button>
                        </div>
                        <div className="flex-1 overflow-auto flex items-center justify-center p-4 bg-slate-900">
                            <canvas ref={canvasPptx} className="shadow-xl bg-white" />
                        </div>
                    </div>

                    {!loading && !error && tipo === 'otro' && (
                        <div className="flex flex-col items-center justify-center h-full gap-3">
                            <p>Formato no soportado para vista previa.</p>
                            <a href={downloadUrl} className="btn btn-primary">
                                <Download className="size-4" /> Descargar archivo
                            </a>
                        </div>
                    )}
                </div>

                {puedeAccederNotas && notasAbiertas && (
                    <>
                        <div
                            onMouseDown={iniciarResize}
                            className="w-1 shrink-0 cursor-col-resize bg-base-300 hover:bg-primary transition-colors"
                            title="Arrastra para ajustar el ancho"
                        />
                        <aside
                            className="shrink-0 border-l border-base-300 bg-base-100 flex flex-col"
                            style={{ width: `${notasAncho}px` }}
                        >
                            <div className="px-3 py-2 border-b border-base-300 flex items-center gap-2">
                                <StickyNote className="size-4 text-primary" />
                                <span className="font-semibold text-sm">Notas</span>
                            </div>
                            <div className="flex-1 overflow-hidden">
                                <NotasEditor
                                    saveUrl={`/admin/dg/archivos/${archivo.id}/notas`}
                                    payloadKey="notas"
                                    initialHtml={archivo.notas}
                                    readOnly={!puedeEditarNotas}
                                />
                            </div>
                        </aside>
                    </>
                )}
            </div>
        </div>
    );
}
