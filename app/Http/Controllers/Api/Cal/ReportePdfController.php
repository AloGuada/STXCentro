<?php

namespace App\Http\Controllers\Api\Cal;

use App\Helpers\PdfWithShapes;
use App\Http\Controllers\Controller;
use App\Models\Cal\Flecha;
use App\Models\Cal\Reporte;
use Illuminate\Support\Facades\Storage;

class ReportePdfController extends Controller
{
    private function calcularEscalaYOrientacion($pageSize)
    {
        // Tamaño carta en mm
        $cartaAncho = 215.9;
        $cartaAlto = 279.4;

        $pdfAncho = $pageSize['width'];
        $pdfAlto = $pageSize['height'];

        // PRIMERO: Determinar si el PDF es horizontal o vertical
        $isLandscape = $pdfAncho > $pdfAlto;

        // SEGUNDO: Definir tamaño objetivo según orientación
        if ($isLandscape) {
            // PDF horizontal -> hoja horizontal (279.4 x 215.9)
            $targetAncho = $cartaAlto; // 279.4
            $targetAlto = $cartaAncho; // 215.9
        } else {
            // PDF vertical -> hoja vertical (215.9 x 279.4)
            $targetAncho = $cartaAncho; // 215.9
            $targetAlto = $cartaAlto; // 279.4
        }

        // TERCERO: Calcular escala para que entre en la hoja
        $escalaAncho = $targetAncho / $pdfAncho;
        $escalaAlto = $targetAlto / $pdfAlto;
        $escala = min($escalaAncho, $escalaAlto, 1.0); // No agrandar, solo reducir

        return [
            'escala' => $escala,
            'isLandscape' => $isLandscape,
            'anchoFinal' => $pdfAncho * $escala,
            'altoFinal' => $pdfAlto * $escala,
            'targetAncho' => $targetAncho,
            'targetAlto' => $targetAlto,
        ];
    }

    public function generarReporte($reporteId)
    {
        try {
            $reporte = Reporte::with(['plano.pieza.etapa.obra', 'inspector', 'soldador'])
                ->findOrFail($reporteId);

            $flechas = Flecha::where('reporte_id', $reporteId)
                ->orderBy('created_at')
                ->get();

            if ($flechas->isEmpty()) {
                return response()->json(['error' => 'No hay flechas en este reporte'], 400);
            }

            $pdfPath = Storage::disk('local')->path($reporte->plano->pdf_path);

            if (! file_exists($pdfPath)) {
                return response()->json(['error' => 'PDF no encontrado'], 404);
            }

            $pdf = new PdfWithShapes;
            $pdf->SetMargins(10, 10, 10); // izquierda, arriba, derecha
            $pdf->SetAutoPageBreak(true, 10);

            $pageCount = $pdf->setSourceFile($pdfPath);

            $numeroJunta = 1;
            $juntasPorPagina = [];

            foreach ($flechas as $flecha) {
                $pagina = $flecha->pagina ?? 1;
                if (! isset($juntasPorPagina[$pagina])) {
                    $juntasPorPagina[$pagina] = [];
                }
                $juntasPorPagina[$pagina][] = [
                    'flecha' => $flecha,
                    'numero' => $numeroJunta,
                ];
                $numeroJunta += $flecha->esdoble ? 2 : 1;
            }

            // PRIMERO: Tabla resumen
            $this->agregarPaginaResumen($pdf, $reporte, $flechas);

            // DESPUÉS: Plano con flechas
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplId = $pdf->importPage($pageNo);
                $pageSize = $pdf->getTemplateSize($tplId);
                $config = $this->calcularEscalaYOrientacion($pageSize);

                $pdf->AddPage($config['isLandscape'] ? 'L' : 'P');

                // Aplicar template con escala
                if ($config['escala'] < 1.0) {
                    // Centrar el PDF escalado en la página
                    $offsetX = $config['targetAncho'] - $config['anchoFinal'];
                    $offsetY = $config['targetAlto'] - $config['altoFinal'];

                    $pdf->useTemplate($tplId, $offsetX / 2, $offsetY / 2, $config['anchoFinal'], $config['altoFinal']);
                } else {
                    $pdf->useTemplate($tplId);
                }

                if (isset($juntasPorPagina[$pageNo])) {
                    foreach ($juntasPorPagina[$pageNo] as $juntaData) {
                        $this->dibujarFlecha($pdf, $juntaData['flecha'], $juntaData['numero'], $tplId, $config);
                    }
                }
            }

            $folio = $reporte->folio;
            if (empty($folio) || $folio === 'N/A') {
                $folio = $this->calcularFolio($reporte);
            }
            $filename = $folio.'.pdf';
            $outputPath = storage_path('app/temp/'.$filename);

            if (! file_exists(dirname($outputPath))) {
                mkdir(dirname($outputPath), 0755, true);
            }

            $pdf->Output('F', $outputPath);

            return response()->download($outputPath, $filename)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            logger('error del pdf:'.$e->getMessage());

            return response()->json([
                'error' => 'Error al generar reporte',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function dibujarFlecha($pdf, $flecha, $numeroJunta, $tplId, $config)
    {
        $pdf->SetDrawColor(255, 0, 0);
        $pdf->SetLineWidth(0.8);

        // Obtener tamaño de la página del template
        $pageSize = $pdf->getTemplateSize($tplId);
        $pageWidth = $pageSize['width'];
        $pageHeight = $pageSize['height'];

        // Coordenadas con escala y offset aplicados
        $offsetX = $config['targetAncho'] - $config['anchoFinal'];
        $offsetY = $config['targetAlto'] - $config['altoFinal'];

        $x1 = ($flecha->inicio_x * $pageWidth * $config['escala']) + ($offsetX / 2);
        $y1 = ($flecha->inicio_y * $pageHeight * $config['escala']) + ($offsetY / 2);
        $x2 = ($flecha->fin_x * $pageWidth * $config['escala']) + ($offsetX / 2);
        $y2 = ($flecha->fin_y * $pageHeight * $config['escala']) + ($offsetY / 2);

        // Dibujar línea
        $pdf->Line($x1, $y1, $x2, $y2);

        // Dibujar número de junta con rectángulo redondeado
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFillColor(255, 0, 0);

        if ($flecha->esdoble) {
            // Flecha doble: dos líneas (número impar = Frente, número par = Atrás)
            $texto1 = "{$numeroJunta}F";
            $texto2 = ($numeroJunta + 1).'A';

            // Calcular dimensiones del rectángulo
            $anchoTexto1 = $pdf->GetStringWidth($texto1);
            $anchoTexto2 = $pdf->GetStringWidth($texto2);
            $anchoMax = max($anchoTexto1, $anchoTexto2) + 4; // +4mm de padding
            $altoLinea = 4; // Altura de cada línea
            $altoTotal = $altoLinea * 2; // Dos líneas
            $radio = 0.8; // Radio de esquinas redondeadas

            $x = $x2 - $anchoMax / 2;
            $y = $y2 - $altoTotal / 2;

            $pdf->SetLineWidth(0.1);
            $pdf->SetDrawColor(255, 0, 0);

            // Líneas superiores e inferiores
            $pdf->Line($x + $radio, $y, $x + $anchoMax - $radio, $y);
            $pdf->Line($x + $radio, $y + $altoTotal, $x + $anchoMax - $radio, $y + $altoTotal);

            // Líneas izquierda y derecha
            $pdf->Line($x, $y + $radio, $x, $y + $altoTotal - $radio);
            $pdf->Line($x + $anchoMax, $y + $radio, $x + $anchoMax, $y + $altoTotal - $radio);

            // Arcos en las esquinas
            $pdf->Ellipse($x + $radio, $y + $radio, $radio, $radio, 'D');
            $pdf->Ellipse($x + $anchoMax - $radio, $y + $radio, $radio, $radio, 'D');
            $pdf->Ellipse($x + $radio, $y + $altoTotal - $radio, $radio, $radio, 'D');
            $pdf->Ellipse($x + $anchoMax - $radio, $y + $altoTotal - $radio, $radio, $radio, 'D');

            // Rellenar el rectángulo
            $pdf->Rect($x, $y, $anchoMax, $altoTotal, 'F');

            // Dibujar primera línea (Frente)
            $pdf->SetXY($x, $y);
            $pdf->Cell($anchoMax, $altoLinea, $texto1, 0, 0, 'C');

            // Dibujar segunda línea (Atrás)
            $pdf->SetXY($x, $y + $altoLinea);
            $pdf->Cell($anchoMax, $altoLinea, $texto2, 0, 0, 'C');
        } else {
            // Flecha simple: una línea
            $texto = "{$numeroJunta}";

            // Calcular dimensiones del rectángulo
            $anchoTexto = $pdf->GetStringWidth($texto) + 4; // +4mm de padding
            $altoTexto = 4; // Altura
            $radio = 0.8; // Radio de esquinas redondeadas

            $x = $x2 - $anchoTexto / 2;
            $y = $y2 - $altoTexto / 2;

            // Rellenar el rectángulo
            $pdf->Rect($x, $y, $anchoTexto, $altoTexto, 'F');

            // Dibujar texto
            $pdf->SetXY($x, $y);
            $pdf->Cell($anchoTexto, $altoTexto, $texto, 0, 0, 'C');
        }
    }

    private function agregarPaginaResumen($pdf, $reporte, $flechas)
    {
        $pdf->AddPage('L'); // Landscape
        $pdf->SetAutoPageBreak(false);

        // Configurar colores
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->SetTextColor(0, 0, 0);

        $pageWidth = $pdf->GetPageWidth();
        $pageHeight = $pdf->GetPageHeight();

        // Márgenes más pequeños
        $margenIzq = 5;
        $margenDer = 5;
        $margenTop = 5;
        $margenBottom = 5;

        $pdf->SetLeftMargin($margenIzq);
        $pdf->SetRightMargin($margenDer);
        $pdf->SetY($margenTop);

        $anchoUtil = $pageWidth - $margenIzq - $margenDer;

        // Encabezado más compacto
        $this->dibujarEncabezado($pdf, $reporte, $anchoUtil, $margenIzq);

        $pdf->Ln(2);

        // Contar el total de FILAS
        $totalFilas = 0;
        foreach ($flechas as $flecha) {
            $totalFilas += $flecha->esdoble ? 2 : 1;
        }

        // Dividir en páginas de 25 filas
        $filasPorPagina = 25;
        $totalPaginas = ceil($totalFilas / $filasPorPagina);

        $numeroJuntaGlobal = 1;
        $filasEnPaginaActual = 0;
        $paginaActual = 1;
        $indiceFlechaActual = 0;

        while ($indiceFlechaActual < count($flechas)) {
            if ($paginaActual > 1) {
                $pdf->AddPage('L');
                $pdf->SetY($margenTop);
                $this->dibujarEncabezado($pdf, $reporte, $anchoUtil, $margenIzq);
                $pdf->Ln(2);
                $filasEnPaginaActual = 0;
            }

            // Calcular espacio disponible para la tabla
            $yInicioTabla = $pdf->GetY();
            $espacioParaFirmas = 18;
            $espacioDisponible = $pageHeight - $yInicioTabla - $espacioParaFirmas - $margenBottom;

            // Dibujar encabezados de tabla
            $titulos = [
                'Junta', 'Clave de soldador', 'Tipo de junta', 'Material Correcto',
                'Prep. para junta de filete', 'Prep. para junta de ranura', 'Placa de respaldo',
                'Radios de acceso', 'Corte sin muescas', 'Precalentamiento', 'Limpieza entre pasadas',
                'Grieta', 'F/Fusion', 'Traslape', 'Soldadura insuficiente', 'Porososidad',
                'Socavado', 'Perfil de soldadura', 'Crater', 'Retiro de punto de soldadura',
                'Material base dañado',
            ];

            $numColumns = count($titulos);
            $colWidth = $anchoUtil / $numColumns;
            $w = array_fill(0, $numColumns, $colWidth);

            $this->dibujarEncabezadosTabla($pdf, $titulos, $w, $margenIzq);

            // Dibujar filas de datos
            $y = $pdf->GetY();
            $rowHeight = 5;

            while ($indiceFlechaActual < count($flechas) && $filasEnPaginaActual < $filasPorPagina) {
                $flecha = $flechas[$indiceFlechaActual];

                $fillColor = ($numeroJuntaGlobal % 2 == 0) ? [245, 245, 245] : [255, 255, 255];
                $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);

                if ($flecha->esdoble) {
                    if ($filasEnPaginaActual + 2 <= $filasPorPagina) {
                        $this->dibujarFilaJunta($pdf, "J{$numeroJuntaGlobal}(F)", $flecha, $reporte->soldador, $w, $margenIzq, $y, $rowHeight);
                        $y += $rowHeight;
                        $filasEnPaginaActual++;

                        $this->dibujarFilaJunta($pdf, 'J'.($numeroJuntaGlobal + 1).'(A)', $flecha, $reporte->soldador, $w, $margenIzq, $y, $rowHeight);
                        $y += $rowHeight;
                        $filasEnPaginaActual++;

                        $numeroJuntaGlobal += 2;
                        $indiceFlechaActual++;
                    } else {
                        break;
                    }
                } else {
                    $this->dibujarFilaJunta($pdf, "J{$numeroJuntaGlobal}", $flecha, $reporte->soldador, $w, $margenIzq, $y, $rowHeight);
                    $y += $rowHeight;
                    $filasEnPaginaActual++;
                    $numeroJuntaGlobal++;
                    $indiceFlechaActual++;
                }
            }

            $pdf->SetY($y);

            $this->dibujarFooter($pdf, $reporte, $anchoUtil, $margenIzq, $paginaActual, $totalPaginas);

            $paginaActual++;
        }
    }

    private function dibujarEncabezado($pdf, $reporte, $anchoUtil, $margenIzq)
    {
        $y = $pdf->GetY();

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(220, 220, 220);

        $logoPath = public_path('logo_steelex.png');

        if (file_exists($logoPath)) {
            $pdf->Rect($margenIzq, $y, $anchoUtil / 3, 6, 'D');
            $pdf->Image($logoPath, $margenIzq + 2, $y + 0.5, 15, 5);
        } else {
            $pdf->Cell($anchoUtil / 3, 6, 'Steelex', 'LTB', 0, 'C', true);
        }

        $pdf->SetXY($margenIzq + $anchoUtil / 3, $y);
        $pdf->Cell($anchoUtil / 3, 6, 'TIM DEL MAYAB S.A. DE C.V.', 'TB', 0, 'C', true);
        $pdf->Cell($anchoUtil / 3, 6, utf8_decode('INSPECCIÓN VISUAL DE SOLDADURA'), 'TRB', 1, 'C', true);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(200, 200, 200);
        $pdf->Cell($anchoUtil, 5, 'DATOS GENERALES', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 7);
        $pdf->SetFillColor(255, 255, 255);

        $obra = ($reporte->plano->pieza->etapa->obra->no ?? 'N/A').' - '.($reporte->plano->pieza->etapa->obra->descripcion ?? '');

        $plano = $reporte->plano->pieza->marca ?? 'N/A';
        $linea = ($reporte->linea ?? 'N').'.'.($reporte->modulo ?? 'N');
        $lugar = utf8_decode('Mérida, Yucatán');
        $norma = 'AWS D1.1';
        $consecutivo = $reporte->consecutivo ?? 'N/A';
        $folio = $reporte->folio;
        if (empty($folio) || $folio === 'N/A') {
            $folio = $this->calcularFolio($reporte);
        }
        $fecha = $reporte->created_at ? $reporte->created_at->format('d/m/Y') : 'N/A';

        $colWidth = $anchoUtil / 2;
        $rowHeight = 4;
        $yStart = $pdf->GetY();

        // Columna Izquierda
        $pdf->SetXY($margenIzq, $yStart);
        $pdf->Cell($colWidth, $rowHeight, 'OBRA: '.utf8_decode($obra), 'LT', 1, 'L');

        $pdf->SetX($margenIzq);
        $pdf->Cell($colWidth, $rowHeight, 'PLANO: '.utf8_decode($plano), 'L', 1, 'L');

        $pdf->SetX($margenIzq);
        $pdf->Cell($colWidth, $rowHeight, utf8_decode('LÍNEA: ').$linea, 'L', 1, 'L');

        $pdf->SetX($margenIzq);
        $pdf->Cell($colWidth, $rowHeight, 'Consecutivo: '.$consecutivo, 'LB', 1, 'L');

        // Columna Derecha
        $pdf->SetXY($margenIzq + $colWidth, $yStart);
        $pdf->Cell($colWidth, $rowHeight, 'Fecha: '.$fecha, 'TR', 1, 'L');

        $pdf->SetX($margenIzq + $colWidth);
        $pdf->Cell($colWidth, $rowHeight, utf8_decode('Lugar de verificación: ').$lugar, 'R', 1, 'L');

        $pdf->SetX($margenIzq + $colWidth);
        $pdf->Cell($colWidth, $rowHeight, 'Norma aplicable: '.$norma, 'R', 1, 'L');

        $pdf->SetX($margenIzq + $colWidth);
        $pdf->Cell($colWidth, $rowHeight, 'Folio: '.$folio, 'RB', 1, 'L');
    }

    private function dibujarEncabezadosTabla($pdf, $titulos, $w, $margenIzq)
    {
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);

        $x = $margenIzq;
        $y = $pdf->GetY();

        $pdf->SetFont('Arial', 'B', 5);
        $pdf->SetFillColor(220, 220, 220);

        $titulosCompletos = array_map('utf8_decode', [
            'Junta', 'Clave de soldador', 'Tipo de junta', 'Material correcto',
            'Prep. para junta de filete', 'Prep. para junta de ranura', 'Placa de respaldo',
            'Radios de acceso', 'Corte sin muescas', 'Precalentamiento', 'Limpieza entre pasadas',
            'Grieta', 'F/Fusión', 'Traslape', 'Soldadura insuficiente', 'Porosidad',
            'Socavado', 'Perfil de soldadura', 'Cráter', 'Retiro de puntos de soldadura',
            'Material base dañado',
        ]);

        $maxTextLength = 0;
        foreach ($titulosCompletos as $titulo) {
            $palabras = explode(' ', $titulo);

            if (count($palabras) <= 2) {
                $lineas = [$titulo];
            } elseif (count($palabras) <= 4) {
                $mitad = ceil(count($palabras) / 2);
                $lineas[] = implode(' ', array_slice($palabras, 0, $mitad));
                $lineas[] = implode(' ', array_slice($palabras, $mitad));
            } else {
                $tercio = ceil(count($palabras) / 3);
                $lineas[] = implode(' ', array_slice($palabras, 0, $tercio));
                $lineas[] = implode(' ', array_slice($palabras, $tercio, $tercio));
                $lineas[] = implode(' ', array_slice($palabras, $tercio * 2));
            }

            foreach ($lineas as $linea) {
                $textLength = strlen($linea) * 0.55;
                $maxTextLength = max($maxTextLength, $textLength);
            }
        }

        $cellHeight = $maxTextLength + 6;

        foreach ($titulosCompletos as $i => $titulo) {
            $pdf->Rect($x, $y, $w[$i], $cellHeight, 'FD');

            $palabras = explode(' ', $titulo);
            $lineas = [];

            if ($titulo === 'Soldadura insuficiente') {
                $lineas = ['Soldadura', 'insuficiente'];
            } elseif (count($palabras) <= 2) {
                $lineas = [$titulo];
            } elseif (count($palabras) <= 4) {
                $mitad = ceil(count($palabras) / 2);
                $lineas[] = implode(' ', array_slice($palabras, 0, $mitad));
                $lineas[] = implode(' ', array_slice($palabras, $mitad));
            } else {
                $tercio = ceil(count($palabras) / 3);
                $lineas[] = implode(' ', array_slice($palabras, 0, $tercio));
                $lineas[] = implode(' ', array_slice($palabras, $tercio, $tercio));
                $lineas[] = implode(' ', array_slice($palabras, $tercio * 2));
            }

            $centerX = $x + ($w[$i] / 2);
            $centerY = $y + ($cellHeight / 2) + 6;

            $pdf->Rotate(90, $centerX, $centerY);

            $lineSpacing = 1.5;
            $numLineas = count($lineas);

            $startOffset = -($numLineas - 1) * $lineSpacing / 2;

            foreach ($lineas as $idx => $linea) {
                $textX = $centerX - 0.8;
                $textY = $centerY + $startOffset + ($idx * $lineSpacing);

                $pdf->Text($textX, $textY, $linea);
            }

            $pdf->Rotate(0);

            $x += $w[$i];
        }

        $pdf->SetY($y + $cellHeight);
    }

    private function dibujarFilaJunta($pdf, $numeroTexto, $flecha, $soldador, $w, $x_start, $y, $rowHeight)
    {
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);

        $x = $x_start;

        $tipo = $flecha->tipo ?? '';

        $datos = [
            $numeroTexto,
            utf8_decode($soldador->certificacion ?? ''),
            utf8_decode(ucfirst($tipo)),
            'A',
            $tipo == 'junta' ? 'A' : 'N/A',
            $tipo == 'ranura' ? 'A' : 'N/A',
            $tipo == 'ranura' ? 'DN' : 'N/A',
            $tipo == 'ranura' ? 'DN' : 'N/A',
            'DN',
            'A',
            $tipo == 'ranura' ? 'DN' : 'N/A',
            'N/A',
            'N/A',
            'N/A',
            'DN',
            'DN',
            'DN',
            'DN',
            'N/A',
            'N/A',
            'N/A',
        ];

        for ($i = 0; $i < count($w); $i++) {
            $pdf->SetXY($x, $y);
            $pdf->Cell($w[$i], $rowHeight, $datos[$i], 1, 0, 'C', true);
            $x += $w[$i];
        }
    }

    private function calcularFolio($reporte)
    {
        $fecha = $reporte->created_at;
        $year = $fecha->format('Y');
        $month = $fecha->format('m');

        $reportesDelMes = Reporte::where('es_plantilla', false)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('created_at')
            ->pluck('id')
            ->values();

        $posicion = $reportesDelMes->search($reporte->id);
        $consecutivo = $posicion !== false ? $posicion + 1 : $reportesDelMes->count() + 1;

        return sprintf('IV%s%s%02d', $year, $month, $consecutivo);
    }

    private function dibujarFooter($pdf, $reporte, $anchoUtil, $margenIzq, $paginaActual, $totalPaginas)
    {
        $y = $pdf->GetY();
        $footerHeight = 15;

        $pdf->Rect($margenIzq, $y, $anchoUtil, $footerHeight);

        $seccionWidth = $anchoUtil / 4;

        // Sección 1: Numeración de página
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetXY($margenIzq, $y + 5);
        $pdf->Cell($seccionWidth, 4, "{$paginaActual} / {$totalPaginas}", 0, 0, 'C');

        $pdf->Line($margenIzq + $seccionWidth, $y, $margenIzq + $seccionWidth, $y + $footerHeight);

        // Sección 2: Elaboró
        $pdf->SetFont('Arial', '', 5);
        $pdf->SetXY($margenIzq + $seccionWidth, $y + 1);
        $pdf->Cell($seccionWidth, 3, 'Elaboro:', 0, 1, 'C');

        $pdf->SetFont('Arial', 'B', 6);
        $pdf->SetXY($margenIzq + $seccionWidth, $y + 4);
        $pdf->Cell($seccionWidth, 3, 'INSPECTOR VISUAL DE', 0, 1, 'C');
        $pdf->SetXY($margenIzq + $seccionWidth, $y + 7);
        $pdf->Cell($seccionWidth, 3, 'SOLDADURA', 0, 1, 'C');

        $pdf->SetFont('Arial', '', 5);
        $pdf->SetXY($margenIzq + $seccionWidth, $y + 11);
        $pdf->Cell($seccionWidth, 3, utf8_decode($reporte->inspector->name ?? 'N/A'), 0, 1, 'C');

        $pdf->Line($margenIzq + ($seccionWidth * 2), $y, $margenIzq + ($seccionWidth * 2), $y + $footerHeight);

        // Sección 3: Revisó
        $pdf->SetFont('Arial', '', 5);
        $pdf->SetXY($margenIzq + ($seccionWidth * 2), $y + 1);
        $pdf->Cell($seccionWidth, 3, 'Reviso:', 0, 1, 'C');

        $pdf->SetFont('Arial', 'B', 6);
        $pdf->SetXY($margenIzq + ($seccionWidth * 2), $y + 4);
        $pdf->Cell($seccionWidth, 3, 'ING.PEDRO DUARTE ORTIZ', 0, 1, 'C');
        $pdf->SetXY($margenIzq + ($seccionWidth * 2), $y + 7);
        $pdf->Cell($seccionWidth, 3, 'INSPECTOR VISUAL DE', 0, 1, 'C');
        $pdf->SetXY($margenIzq + ($seccionWidth * 2), $y + 10);
        $pdf->Cell($seccionWidth, 3, 'SOLDADURA NIVEL II', 0, 0, 'C');

        $pdf->Line($margenIzq + ($seccionWidth * 3), $y, $margenIzq + ($seccionWidth * 3), $y + $footerHeight);

        // Sección 4: F-STX-CA-04 y Revisión
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetXY($margenIzq + ($seccionWidth * 3), $y + 4);
        $pdf->Cell($seccionWidth, 3, 'F-STX-CA-04', 0, 1, 'C');

        $pdf->SetFont('Arial', '', 6);
        $pdf->SetXY($margenIzq + ($seccionWidth * 3), $y + 8);
        $pdf->Cell($seccionWidth, 3, 'Revision: 01', 0, 0, 'C');
    }
}
