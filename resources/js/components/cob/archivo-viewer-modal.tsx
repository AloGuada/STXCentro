import { SecurePdfViewer } from '@/components/intra/SecurePdfViewer';
import { renderAsync as renderDocxAsync } from 'docx-preview';
import { ChevronLeft, ChevronRight, Download, Loader2, X } from 'lucide-react';
import { PPTXViewer } from 'pptxviewjs';
import { useEffect, useRef, useState } from 'react';
import * as XLSX from 'xlsx';

type Props = {
    nombre: string;
    mime: string | null;
    streamUrl: string;
    downloadUrl: string;
    onClose: () => void;
};

type TipoViewer = 'pdf' | 'docx' | 'xlsx' | 'pptx' | 'image' | 'otro';

function detectarTipo(mime: string | null, nombre: string): TipoViewer {
    const m = (mime ?? '').toLowerCase();
    const n = nombre.toLowerCase();
    if (m.includes('pdf') || n.endsWith('.pdf')) return 'pdf';
    if (m.includes('wordprocessingml') || n.endsWith('.docx')) return 'docx';
    if (m.includes('spreadsheetml') || m.includes('ms-excel') || n.endsWith('.xlsx') || n.endsWith('.xls')) return 'xlsx';
    if (m.includes('presentationml') || m.includes('ms-powerpoint') || n.endsWith('.pptx') || n.endsWith('.ppt')) return 'pptx';
    if (m.startsWith('image/') || /\.(jpe?g|png|gif|webp|bmp)$/.test(n)) return 'image';
    return 'otro';
}

export default function ArchivoViewerModal({ nombre, mime, streamUrl, downloadUrl, onClose }: Props) {
    const containerDocx = useRef<HTMLDivElement>(null);
    const canvasPptx = useRef<HTMLCanvasElement>(null);
    const pptxViewer = useRef<PPTXViewer | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [sheets, setSheets] = useState<{ name: string; html: string }[]>([]);
    const [sheetActiva, setSheetActiva] = useState(0);
    const [pptxSlide, setPptxSlide] = useState(0);
    const [pptxTotal, setPptxTotal] = useState(0);

    const tipo = detectarTipo(mime, nombre);

    useEffect(() => {
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
                                return { name, html: XLSX.utils.sheet_to_html(ws, { editable: false, header: '', footer: '' }) };
                            } catch {
                                return { name, html: '<p class="p-4 text-error">No se pudo renderizar esta hoja</p>' };
                            }
                        });
                        setSheets(parsed);
                    }
                    if (tipo === 'pptx' && canvasPptx.current) {
                        const canvas = canvasPptx.current;
                        const parent = canvas.parentElement;
                        const maxW = Math.min(parent?.clientWidth ?? 1280, 1600);
                        const w = Math.max(800, maxW - 32);
                        const h = Math.round((w * 9) / 16);
                        canvas.style.width = `${w}px`;
                        canvas.style.height = `${h}px`;
                        const dpr = window.devicePixelRatio || 1;
                        canvas.width = Math.round(w * dpr);
                        canvas.height = Math.round(h * dpr);
                        const viewer = new PPTXViewer({ canvas });
                        viewer.on('error', (err: unknown) => console.error('pptxviewjs error:', err));
                        await viewer.loadFile(buffer);
                        const count = viewer.getSlideCount();
                        if (count === 0) {
                            throw new Error('No se pudieron leer las diapositivas de este archivo.');
                        }
                        await viewer.render(canvas, { slideIndex: 0 });
                        setTimeout(() => {
                            const idx = viewer.getCurrentSlideIndex();
                            viewer.render(canvas, { slideIndex: idx }).catch(() => {});
                        }, 200);
                        pptxViewer.current = viewer;
                        setPptxTotal(count);
                        setPptxSlide(0);
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
    }, [tipo, streamUrl]);

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

    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-base-100">
            <div className="flex shrink-0 items-center justify-between border-b border-base-300 bg-base-100 px-4 py-3">
                <div className="flex min-w-0 items-center gap-3">
                    <button type="button" onClick={onClose} className="btn btn-sm btn-ghost">
                        <ChevronLeft className="size-4" /> Cerrar
                    </button>
                    <h3 className="truncate font-semibold">{nombre}</h3>
                </div>
                <div className="flex items-center gap-2">
                    <a href={downloadUrl} className="btn btn-sm btn-ghost" title="Descargar">
                        <Download className="size-4" /> Descargar
                    </a>
                    <button type="button" onClick={onClose} className="btn btn-circle btn-sm btn-ghost" title="Cerrar">
                        <X className="size-4" />
                    </button>
                </div>
            </div>

            <div className="relative flex-1 overflow-hidden bg-base-200">
                {loading && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center bg-base-200/80">
                        <Loader2 className="size-8 animate-spin text-base-content/60" />
                    </div>
                )}

                {error && !loading && (
                    <div className="flex h-full flex-col items-center justify-center gap-3">
                        <p className="px-6 text-center text-error">{error}</p>
                        <a href={downloadUrl} className="btn btn-primary">
                            <Download className="size-4" /> Descargar archivo
                        </a>
                    </div>
                )}

                {!loading && !error && tipo === 'pdf' && (
                    <div className="h-full p-2">
                        <SecurePdfViewer url={streamUrl} title={nombre} />
                    </div>
                )}

                {!loading && !error && tipo === 'image' && (
                    <div className="flex h-full items-center justify-center overflow-auto p-4">
                        <img src={streamUrl} alt={nombre} className="max-h-full max-w-full object-contain" />
                    </div>
                )}

                {!error && tipo === 'docx' && (
                    <div className="h-full overflow-auto">
                        <div className="mx-auto my-4 bg-white shadow-lg" style={{ maxWidth: '900px' }}>
                            <div ref={containerDocx} className="p-4" />
                        </div>
                    </div>
                )}

                {!loading && !error && tipo === 'xlsx' && sheets.length > 0 && (
                    <div className="flex h-full flex-col bg-white">
                        <div role="tablist" className="tabs tabs-bordered sticky top-0 z-10 shrink-0 overflow-x-auto border-b border-base-300 bg-base-100">
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
                            className="flex-1 overflow-auto p-2 text-slate-900 [&_*]:!text-slate-900 [&_table]:border-collapse [&_table]:text-sm [&_td]:min-w-[80px] [&_td]:border [&_td]:border-slate-300 [&_td]:bg-white [&_td]:px-2 [&_td]:py-1 [&_th]:sticky [&_th]:top-0 [&_th]:border [&_th]:border-slate-400 [&_th]:bg-slate-100 [&_th]:px-2 [&_th]:py-1 [&_th]:font-semibold"
                            dangerouslySetInnerHTML={{ __html: sheets[sheetActiva].html }}
                        />
                    </div>
                )}

                <div className={`${tipo === 'pptx' && !error ? 'flex' : 'hidden'} h-full flex-col`}>
                    <div className="flex shrink-0 items-center justify-center gap-3 bg-base-300 py-2">
                        <button type="button" onClick={irSlideAnterior} disabled={pptxSlide <= 0} className="btn btn-circle btn-sm btn-ghost">
                            <ChevronLeft className="size-4" />
                        </button>
                        <span className="min-w-[5rem] text-center text-sm">{pptxTotal > 0 ? `${pptxSlide + 1} / ${pptxTotal}` : '...'}</span>
                        <button type="button" onClick={irSlideSiguiente} disabled={pptxSlide >= pptxTotal - 1} className="btn btn-circle btn-sm btn-ghost">
                            <ChevronRight className="size-4" />
                        </button>
                    </div>
                    <div className="flex flex-1 items-center justify-center overflow-auto bg-slate-900 p-4">
                        <canvas ref={canvasPptx} className="bg-white shadow-xl" />
                    </div>
                </div>

                {!loading && !error && tipo === 'otro' && (
                    <div className="flex h-full flex-col items-center justify-center gap-3">
                        <p>Formato no soportado para vista previa.</p>
                        <a href={downloadUrl} className="btn btn-primary">
                            <Download className="size-4" /> Descargar archivo
                        </a>
                    </div>
                )}
            </div>
        </div>
    );
}
