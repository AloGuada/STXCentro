import { useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

type Props = {
    url: string;
    title?: string;
};

export function PdfViewer({ url, title }: Props) {
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);

    // Note: Basic PDF viewing with iframe doesn't support page navigation
    // For full page control, consider using pdf.js or react-pdf library

    return (
        <div className="flex flex-col h-full">
            {/* PDF Embed */}
            <div className="flex-1 bg-white rounded-lg overflow-hidden">
                <iframe
                    src={url}
                    title={title ?? 'PDF Viewer'}
                    className="w-full h-full min-h-[70vh]"
                />
            </div>

            {/* Pagination controls */}
            <div className="flex items-center justify-center gap-4 py-4">
                <button
                    onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                    className="btn btn-circle btn-sm btn-ghost text-white"
                    disabled={currentPage <= 1}
                >
                    <ChevronLeft className="size-5" />
                </button>
                <span className="text-white">
                    {currentPage} / {totalPages}
                </span>
                <button
                    onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                    className="btn btn-circle btn-sm btn-ghost text-white"
                    disabled={currentPage >= totalPages}
                >
                    <ChevronRight className="size-5" />
                </button>
            </div>
        </div>
    );
}
