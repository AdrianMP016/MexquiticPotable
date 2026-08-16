<?php

require_once __DIR__ . '/../Core/XlsxWriter.php';

/**
 * Exporta el padron completo de usuarios a un .xlsx con una hoja por ruta,
 * para que el administrador de servicio lleve un control tambien manual
 * ademas de la plataforma.
 */
class ExportadorPadron
{
    private PDO $db;
    private string $outputDir;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->outputDir = dirname(__DIR__, 2) . '/padron/exportados';
    }

    public function exportarExcel(): array
    {
        $filas = $this->obtenerTodo();

        if (empty($filas)) {
            throw new RuntimeException('No hay usuarios registrados para exportar.');
        }

        $porRuta = [];
        foreach ($filas as $fila) {
            $ruta = trim((string) ($fila['ruta_nombre'] ?? ''));
            if ($ruta === '') {
                $ruta = trim((string) ($fila['ruta'] ?? ''));
            }
            if ($ruta === '') {
                $ruta = 'Sin ruta asignada';
            }

            $porRuta[$ruta][] = $fila;
        }
        ksort($porRuta, SORT_NATURAL | SORT_FLAG_CASE);

        $columnas = [
            'N. Padron', 'Nombre', 'Telefono', 'WhatsApp', 'Comunidad',
            'Calle y numero', 'Colonia', 'N. Medidor', 'Estado del medidor', 'Activo',
        ];

        $writer = new XlsxWriter();
        foreach ($porRuta as $nombreRuta => $usuarios) {
            $filasHoja = [];
            foreach ($usuarios as $u) {
                $calleNumero = trim(
                    trim((string) ($u['calle'] ?? '')) . ' ' . trim((string) ($u['numero_domicilio'] ?? ''))
                );

                $filasHoja[] = [
                    $u['padron_id'] ?? '',
                    $u['nombre'] ?? '',
                    $u['telefono'] ?? '',
                    $u['whatsapp'] ?? '',
                    $u['comunidad'] ?? '',
                    $calleNumero,
                    $u['colonia'] ?? '',
                    $u['medidor'] ?? '',
                    $this->textoLegible((string) ($u['estado_medidor'] ?? '')),
                    ((int) ($u['activo'] ?? 0)) === 1 ? 'Activo' : 'Inactivo',
                ];
            }

            $writer->agregarHoja((string) $nombreRuta, $columnas, $filasHoja);
        }

        if (!is_dir($this->outputDir) && !mkdir($this->outputDir, 0755, true) && !is_dir($this->outputDir)) {
            throw new RuntimeException('No se pudo crear la carpeta de exportacion.');
        }

        $this->limpiarExportsViejos();

        $nombreArchivo = 'padron-' . date('Y-m-d-His') . '-' . bin2hex(random_bytes(8)) . '.xlsx';
        $writer->guardar($this->outputDir . '/' . $nombreArchivo);

        return [
            'url' => 'padron/exportados/' . $nombreArchivo,
            'total_usuarios' => count($filas),
            'total_rutas' => count($porRuta),
        ];
    }

    private function obtenerTodo(): array
    {
        $stmt = $this->db->query(
            "SELECT
                u.padron_id, u.nombre, u.telefono, u.whatsapp, u.activo,
                d.calle, d.numero AS numero_domicilio, d.colonia,
                COALESCE(rt.codigo, d.ruta) AS ruta,
                c.nombre AS comunidad, rt.nombre AS ruta_nombre,
                m.numero AS medidor, m.estado AS estado_medidor
             FROM usuarios_servicio u
             LEFT JOIN domicilios d ON d.usuario_id = u.id
             LEFT JOIN comunidades c ON c.id = d.comunidad_id
             LEFT JOIN rutas rt ON rt.id = u.ruta_id
             LEFT JOIN medidores m ON m.usuario_id = u.id
             ORDER BY u.activo DESC, COALESCE(u.padron_id, u.id) ASC, u.id ASC"
        );

        return $stmt->fetchAll();
    }

    private function textoLegible(string $valor): string
    {
        if ($valor === '') {
            return '';
        }

        return ucfirst(str_replace('_', ' ', $valor));
    }

    /**
     * El archivo tiene datos personales de todo el padron - no se acumulan
     * exportaciones viejas en el servidor indefinidamente. Se limpia cada
     * vez que se genera una nueva.
     */
    private function limpiarExportsViejos(): void
    {
        $archivos = glob($this->outputDir . '/padron-*.xlsx') ?: [];
        $limite = time() - 86400;

        foreach ($archivos as $archivo) {
            if (is_file($archivo) && filemtime($archivo) < $limite) {
                @unlink($archivo);
            }
        }
    }
}
