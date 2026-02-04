import { useCallback, useState } from 'react';
import { Document, Page, pdfjs } from 'react-pdf';
import { ChevronLeft, ChevronRight, Loader2, ZoomIn, ZoomOut } from 'lucide-react';

import 'react-pdf/dist/Page/AnnotationLayer.css';
import 'react-pdf/dist/Page/TextLayer.css';

// Configure PDF.js worker
pdfjs.GlobalWorkerOptions.workerSrc = `//unpkg.com/pdfjs-dist@${pdfjs.version}/build/pdf.worker.min.mjs`;

type Props = {
    url: string;
    title?: string;
};

export function SecurePdfViewer({ url, title }: Props) {
    const [numPages, setNumPages] = useState<number>(0);
    const [pageNumber, setPageNumber] = useState<number>(1);
    const [scale, setScale] = useState<number>(1.0);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    const onDocumentLoadSuccess = useCallback(({ numPages }: { numPages: number }) => {
        setNumPages(numPages);
        setLoading(false);
    }, []);

    const onDocumentLoadError = useCallback((error: Error) => {
        setError('Error al cargar el documento PDF');
        setLoading(false);
        console.error('PDF load error:', error);
    }, []);

    const goToPrevPage = () => setPageNumber((prev) => Math.max(1, prev - 1));
    const goToNextPage = () => setPageNumber((prev) => Math.min(numPages, prev + 1));
    const zoomIn = () => setScale((prev) => Math.min(2.0, prev + 0.2));
    const zoomOut = () => setScale((prev) => Math.max(0.5, prev - 0.2));

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
                        disabled={scale <= 0.5}
                        title="Reducir"
                    >
                        <ZoomOut className="size-4" />
                    </button>
                    <span className="text-white text-sm min-w-[4rem] text-center">
                        {Math.round(scale * 100)}%
                    </span>
                    <button
                        onClick={zoomIn}
                        className="btn btn-circle btn-sm btn-ghost text-white"
                        disabled={scale >= 2.0}
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
                className="flex-1 overflow-auto bg-slate-900 rounded-b-lg flex justify-center"
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
                        loading={
                            <div className="flex items-center justify-center p-8">
                                <Loader2 className="size-6 animate-spin text-slate-400" />
                            </div>
                        }
                    />
                </Document>
            </div>

            {/* Hidden title for accessibility */}
            {title && <span className="sr-only">{title}</span>}
        </div>
    );
}
