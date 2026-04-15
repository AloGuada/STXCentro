import * as pdfjs from 'pdfjs-dist';
import workerSrc from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

pdfjs.GlobalWorkerOptions.workerSrc = workerSrc;

export type PdfTextCheck = {
    status: 'ok' | 'empty' | 'error';
    charCount: number;
    pages: number;
    message: string;
};

const MIN_CHARS = 100;

export async function checkPdfHasText(file: File): Promise<PdfTextCheck> {
    try {
        const buffer = await file.arrayBuffer();
        const pdf = await pdfjs.getDocument({ data: buffer }).promise;

        let text = '';
        for (let i = 1; i <= pdf.numPages; i++) {
            const page = await pdf.getPage(i);
            const content = await page.getTextContent();
            text += content.items.map((it) => ('str' in it ? it.str : '')).join(' ');
            if (text.length >= MIN_CHARS) break;
        }

        const charCount = text.replace(/\s+/g, '').length;

        if (charCount < MIN_CHARS) {
            return {
                status: 'empty',
                charCount,
                pages: pdf.numPages,
                message: 'El PDF parece ser una imagen escaneada. La IA no podrá analizarlo.',
            };
        }

        return {
            status: 'ok',
            charCount,
            pages: pdf.numPages,
            message: 'El CV tiene texto extraíble. La IA podrá analizarlo.',
        };
    } catch {
        return {
            status: 'error',
            charCount: 0,
            pages: 0,
            message: 'No se pudo leer el PDF.',
        };
    }
}
