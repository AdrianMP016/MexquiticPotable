<?php

require_once __DIR__ . '/Activaciones.php';
require_once __DIR__ . '/../Core/XlsxWriter.php';

/**
 * Exporta el historial de encendidos y apagados de la bomba (tabla
 * bomba_activaciones) a un archivo descargable, en Excel o texto plano.
 * Uso exclusivo del usuario de sistemas - la restriccion de quien puede
 * llamar a esto vive en ajax/peticiones.php, no aqui.
 */
class ExportadorActividadBomba
{
    private Activaciones $activaciones;
    private string $outputDir;

    public function __construct(Activaciones $activaciones)
    {
        $this->activaciones = $activaciones;
        $this->outputDir = dirname(__DIR__, 2) . '/exportados';
    }

    public function exportar(string $desde, string $hasta, string $formato): array
    {
        $datos = $this->activaciones->exportarRango($desde, $hasta);
        $filas = $this->filasParaExportar($datos['registros']);

        if (!is_dir($this->outputDir) && !mkdir($this->outputDir, 0755, true) && !is_dir($this->outputDir)) {
            throw new RuntimeException('No se pudo crear la carpeta de exportacion.');
        }

        $this->limpiarExportsViejos();

        $sufijo = date('Y-m-d-His') . '-' . bin2hex(random_bytes(8));

        if ($formato === 'txt') {
            $nombreArchivo = 'actividad-bomba-' . $sufijo . '.txt';
            $this->guardarTxt($this->outputDir . '/' . $nombreArchivo, $datos['desde'], $datos['hasta'], $filas);
        } else {
            $nombreArchivo = 'actividad-bomba-' . $sufijo . '.xlsx';
            $this->guardarXlsx($this->outputDir . '/' . $nombreArchivo, $filas);
        }

        return [
            'url' => 'exportados/' . $nombreArchivo,
            'total_registros' => count($filas),
            'desde' => $datos['desde'],
            'hasta' => $datos['hasta'],
        ];
    }

    private function filasParaExportar(array $registros): array
    {
        $filas = [];

        foreach ($registros as $registro) {
            $duracionSegundos = $registro['duracion_segundos'];
            if ($duracionSegundos === null && $registro['fin_at'] === null) {
                $duracionSegundos = max(0, time() - strtotime((string) $registro['inicio_at']));
            }

            $filas[] = [
                'Origen' => $this->textoLegible((string) $registro['origen']),
                'Iniciado por' => $registro['iniciado_por_nombre'] ?? 'Sistema',
                'Inicio' => $registro['inicio_at'],
                'Fin' => $registro['fin_at'] ?? 'En curso',
                'Duracion' => $duracionSegundos !== null ? Activaciones::formatoHorasMinutos((int) $duracionSegundos) : '',
                'Motivo de fin' => $registro['fin_motivo'] !== null ? $this->textoLegible((string) $registro['fin_motivo']) : '',
            ];
        }

        return $filas;
    }

    private function guardarXlsx(string $ruta, array $filas): void
    {
        $columnas = ['Origen', 'Iniciado por', 'Inicio', 'Fin', 'Duracion', 'Motivo de fin'];

        $filasHoja = [];
        foreach ($filas as $fila) {
            $filasHoja[] = array_values($fila);
        }

        $writer = new XlsxWriter();
        $writer->agregarHoja('Actividad de la bomba', $columnas, $filasHoja);
        $writer->guardar($ruta);
    }

    private function guardarTxt(string $ruta, string $desde, string $hasta, array $filas): void
    {
        $lineas = [];
        $lineas[] = 'Exportacion de encendido y apagado de la bomba';
        $lineas[] = 'Rango: ' . $desde . ' a ' . $hasta;
        $lineas[] = 'Generado: ' . date('Y-m-d H:i:s');
        $lineas[] = '';

        if (empty($filas)) {
            $lineas[] = 'No hay registros en este rango de fechas.';
        } else {
            $encabezados = array_keys($filas[0]);
            $lineas[] = implode("\t", $encabezados);

            foreach ($filas as $fila) {
                $lineas[] = implode("\t", array_map(static fn ($v) => (string) $v, $fila));
            }
        }

        file_put_contents($ruta, implode("\r\n", $lineas) . "\r\n");
    }

    private function textoLegible(string $valor): string
    {
        if ($valor === '') {
            return '';
        }

        return ucfirst(str_replace('_', ' ', $valor));
    }

    /**
     * No se acumulan exportaciones viejas en el servidor indefinidamente.
     */
    private function limpiarExportsViejos(): void
    {
        $archivos = array_merge(
            glob($this->outputDir . '/actividad-bomba-*.xlsx') ?: [],
            glob($this->outputDir . '/actividad-bomba-*.txt') ?: []
        );
        $limite = time() - 86400;

        foreach ($archivos as $archivo) {
            if (is_file($archivo) && filemtime($archivo) < $limite) {
                @unlink($archivo);
            }
        }
    }
}
