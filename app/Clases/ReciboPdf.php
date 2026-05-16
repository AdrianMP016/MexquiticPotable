<?php

class ReciboPdf
{
    private const PAGE_W   = 139.7;   // mm — 5.5 in
    private const PAGE_H   = 215.9;   // mm — 8.5 in
    private const CANVAS_W = 1648;
    private const CANVAS_H = 2550;

    public function generarPdfMasivo(array $recibosData, string $outputPath): void
    {
        require_once dirname(__DIR__) . '/Libs/fpdf/fpdf.php';
        require_once dirname(__DIR__) . '/Libs/phpqrcode/qrcode.php';

        $pdf = new FPDF('P', 'mm', [self::PAGE_W, self::PAGE_H]);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        foreach ($recibosData as $impresion) {
            $this->dibujarPagina($pdf, $impresion);
        }

        $pdf->Output('F', $outputPath);
    }

    private function dibujarPagina(FPDF $pdf, array $imp): void
    {
        $pdf->AddPage();

        $cw        = (int)   ($imp['canvas']['width']  ?? self::CANVAS_W);
        $ch        = (int)   ($imp['canvas']['height'] ?? self::CANVAS_H);
        $offsetX   = (float) ($imp['print']['offsetX'] ?? 0);
        $offsetY   = (float) ($imp['print']['offsetY'] ?? 0);
        $fontScale = (float) ($imp['print']['fontScale'] ?? 2.08);

        // $imp['fields'] => ['field_name' => ['x','y','width','fontSize',...], ...]
        // $imp['values'] => ['field_name' => 'texto', ...]
        foreach ($imp['fields'] as $nombre => $field) {
            $valor = (string) ($imp['values'][$nombre] ?? '');
            if ($valor === '') {
                continue;
            }
            $this->dibujarCampo($pdf, $field, $valor, $cw, $ch, $offsetX, $offsetY, $fontScale);
        }

        $this->dibujarQr($pdf, $imp, $cw, $ch, $offsetX, $offsetY);
    }

    private function dibujarCampo(
        FPDF $pdf, array $field, string $valor,
        int $cw, int $ch, float $ox, float $oy, float $fontScale
    ): void {
        $x = ((float)($field['x'] ?? 0) + $ox) / $cw * self::PAGE_W;
        $y = ((float)($field['y'] ?? 0) + $oy) / $ch * self::PAGE_H;
        $w = max(1.0, (float)($field['width'] ?? 200) / $cw * self::PAGE_W);

        $fsPt  = max(4.0, (float)($field['fontSize'] ?? 10) * $fontScale * 72.0 / 300.0);
        $bold  = !empty($field['bold']) ? 'B' : '';
        $align = $this->fpdfAlign((string)($field['align'] ?? 'left'));

        [$r, $g, $b] = $this->hexRgb((string)($field['color'] ?? '#111111'));
        $pdf->SetFont('Helvetica', $bold, $fsPt);
        $pdf->SetTextColor($r, $g, $b);

        $lineH = (float)($field['lineHeight'] ?? 0) / $ch * self::PAGE_H;
        if ($lineH <= 0) {
            $lineH = $fsPt * 25.4 / 72.0 * 1.35;
        }

        $pdf->SetXY($x, $y);
        if (!empty($field['multiline'])) {
            $pdf->MultiCell($w, $lineH, $valor, 0, $align);
        } else {
            $pdf->Cell($w, $lineH, $valor, 0, 0, $align);
        }
    }

    private function dibujarQr(FPDF $pdf, array $imp, int $cw, int $ch, float $ox, float $oy): void
    {
        $qrConfig = $imp['qr'] ?? [];
        $qrToken  = (string)($imp['qr_token'] ?? '');

        if ($qrToken === '' || empty($qrConfig['x'])) {
            return;
        }

        $qrX    = ((float)($qrConfig['x'] ?? 0) + $ox) / $cw * self::PAGE_W;
        $qrY    = ((float)($qrConfig['y'] ?? 0) + $oy) / $ch * self::PAGE_H;
        $qrSzMm = max(5.0, (float)($qrConfig['size'] ?? 120) / $cw * self::PAGE_W);

        $tmpPng = $this->qrTempPng($qrToken);
        if ($tmpPng !== null) {
            $pdf->Image($tmpPng, $qrX, $qrY, $qrSzMm, $qrSzMm, 'PNG');
            @unlink($tmpPng);
        }
    }

    private function qrTempPng(string $token): ?string
    {
        try {
            $qr    = QRCode::getMinimumQRCode($token, QR_ERROR_CORRECT_LEVEL_H);
            $image = $qr->createImage(5, 2);
            $path  = sys_get_temp_dir() . '/mexq_qr_' . md5($token) . '.png';
            imagepng($image, $path);
            imagedestroy($image);
            return $path;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function fpdfAlign(string $align): string
    {
        $a = strtolower(substr($align, 0, 1));
        if ($a === 'c') return 'C';
        if ($a === 'r') return 'R';
        return 'L';
    }

    private function hexRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
