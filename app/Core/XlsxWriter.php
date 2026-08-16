<?php

/**
 * Generador minimo de archivos .xlsx (varias hojas, encabezado en negritas)
 * usando solo ZipArchive - sin Composer, sin PhpSpreadsheet, sin Python. Un
 * .xlsx es, por dentro, un ZIP con archivos XML (formato OOXML); esta clase
 * arma esos XML a mano siguiendo el estandar, usando texto en linea
 * ("inlineStr") en vez de una tabla de cadenas compartidas, para mantener
 * el codigo simple y facil de verificar.
 */
class XlsxWriter
{
    /** @var array<int, array{nombre:string, columnas:string[], filas:array[]}> */
    private array $hojas = [];

    public function agregarHoja(string $nombre, array $columnas, array $filas): void
    {
        $this->hojas[] = [
            'nombre' => $this->nombreHojaValido($nombre),
            'columnas' => $columnas,
            'filas' => $filas,
        ];
    }

    public function guardar(string $rutaDestino): void
    {
        if (empty($this->hojas)) {
            throw new RuntimeException('No hay ninguna hoja que exportar.');
        }

        $zip = new ZipArchive();
        if ($zip->open($rutaDestino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo Excel en el servidor.');
        }

        $zip->addEmptyDir('_rels');
        $zip->addEmptyDir('xl');
        $zip->addEmptyDir('xl/_rels');
        $zip->addEmptyDir('xl/worksheets');

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->relsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach ($this->hojas as $indice => $hoja) {
            $zip->addFromString(
                'xl/worksheets/sheet' . ($indice + 1) . '.xml',
                $this->sheetXml($hoja['columnas'], $hoja['filas'])
            );
        }

        $zip->close();
    }

    private function nombreHojaValido(string $nombre): string
    {
        // Excel prohibe estos caracteres en el nombre de hoja, y limita a 31.
        $limpio = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $nombre);
        $limpio = trim((string) $limpio);

        return $limpio !== '' ? mb_substr($limpio, 0, 31, 'UTF-8') : 'Hoja';
    }

    private function columnaLetra(int $indiceBase0): string
    {
        $letra = '';
        $n = $indiceBase0 + 1;

        while ($n > 0) {
            $resto = ($n - 1) % 26;
            $letra = chr(65 + $resto) . $letra;
            $n = intdiv($n - 1, 26);
        }

        return $letra;
    }

    private function escaparTexto($valor): string
    {
        $texto = $valor === null ? '' : (string) $valor;

        return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        $overrides = '';
        foreach ($this->hojas as $indice => $hoja) {
            $n = $indice + 1;
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" '
                . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides
            . '</Types>';
    }

    private function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        $sheets = '';
        foreach ($this->hojas as $indice => $hoja) {
            $n = $indice + 1;
            $sheets .= '<sheet name="' . $this->escaparTexto($hoja['nombre']) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheets . '</sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        $rels = '';
        foreach ($this->hojas as $indice => $hoja) {
            $n = $indice + 1;
            $rels .= '<Relationship Id="rId' . $n . '" '
                . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                . 'Target="worksheets/sheet' . $n . '.xml"/>';
        }

        $idStyles = count($this->hojas) + 1;
        $rels .= '<Relationship Id="rId' . $idStyles . '" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" '
            . 'Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        // Estilo 0 = normal, estilo 1 = negritas (para el encabezado de cada hoja).
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="2">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';
    }

    private function sheetXml(array $columnas, array $filas): string
    {
        $cols = '';
        foreach ($columnas as $indice => $encabezado) {
            $ancho = max(12, min(45, mb_strlen((string) $encabezado, 'UTF-8') + 6));
            $cols .= '<col min="' . ($indice + 1) . '" max="' . ($indice + 1) . '" width="' . $ancho . '" customWidth="1"/>';
        }

        $filasXml = '<row r="1">';
        foreach ($columnas as $indice => $encabezado) {
            $ref = $this->columnaLetra($indice) . '1';
            $filasXml .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t xml:space="preserve">'
                . $this->escaparTexto($encabezado) . '</t></is></c>';
        }
        $filasXml .= '</row>';

        $numeroFila = 2;
        foreach ($filas as $fila) {
            $filasXml .= '<row r="' . $numeroFila . '">';
            foreach (array_values($fila) as $indice => $valor) {
                $ref = $this->columnaLetra($indice) . $numeroFila;
                $filasXml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                    . $this->escaparTexto($valor) . '</t></is></c>';
            }
            $filasXml .= '</row>';
            $numeroFila++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<cols>' . $cols . '</cols>'
            . '<sheetData>' . $filasXml . '</sheetData>'
            . '</worksheet>';
    }
}
