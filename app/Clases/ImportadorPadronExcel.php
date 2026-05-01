<?php

class ImportadorPadronExcel
{
    private PDO $db;
    private string $pythonScript;
    private bool $padronIdAgregado = false;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->pythonScript = dirname(__DIR__, 2) . '/tools/importar_padron_excel.py';
    }

    public function importarPadronExcel(string $excelPath): array
    {
        $excelPath = trim($excelPath);

        if ($excelPath === '' || !is_file($excelPath)) {
            throw new RuntimeException('No se encontro el archivo Excel indicado.');
        }

        $this->asegurarEsquema();

        $payload = $this->leerExcel($excelPath);
        $rows = $payload['rows'] ?? [];

        if (empty($rows)) {
            throw new RuntimeException('No se encontraron filas validas para importar en el Excel.');
        }

        $this->refrescarStaging($rows);

        $resultado = [
            'archivo' => $excelPath,
            'procesados' => count($rows),
            'insertados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'hojas' => $payload['meta']['used_sheets'] ?? [],
            'errores' => [],
            'schema' => [
                'padron_id_agregado' => $this->padronIdAgregado,
            ],
        ];
        $filasHistoricas = [];

        foreach ($rows as $row) {
            try {
                $fila = $this->normalizarFila($row);
                $this->validarFila($fila);
                $filasHistoricas[] = $fila;

                $this->db->beginTransaction();
                $accion = $this->upsertFila($fila);
                $this->db->commit();

                if ($accion === 'insertado') {
                    $resultado['insertados']++;
                } else {
                    $resultado['actualizados']++;
                }
            } catch (Throwable $exception) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                $resultado['omitidos']++;
                $resultado['errores'][] = [
                    'hoja' => $row['source_sheet'] ?? null,
                    'fila' => $row['source_row'] ?? null,
                    'id_excel' => $row['id_excel'] ?? null,
                    'medidor' => $row['medidor'] ?? null,
                    'motivo' => $exception->getMessage(),
                ];
            }
        }

        $resultado['historico_2026'] = $this->sincronizarHistorico2026(
            $filasHistoricas,
            basename($excelPath)
        );

        return $resultado;
    }

    private function asegurarEsquema(): void
    {
        $stmt = $this->db->query("SHOW COLUMNS FROM usuarios_servicio LIKE 'padron_id'");
        $exists = (bool) $stmt->fetch();

        if ($exists) {
            $this->padronIdAgregado = false;
            return;
        }

        $this->db->exec(
            "ALTER TABLE usuarios_servicio
             ADD COLUMN padron_id BIGINT UNSIGNED NULL AFTER id,
             ADD UNIQUE KEY uq_usuario_padron_id (padron_id)"
        );
        $this->padronIdAgregado = true;
    }

    private function leerExcel(string $excelPath): array
    {
        if (!is_file($this->pythonScript)) {
            throw new RuntimeException('No se encontro el lector del Excel para el padron.');
        }

        $python = $this->resolverPython();
        $command = escapeshellarg($python)
            . ' '
            . escapeshellarg($this->pythonScript)
            . ' '
            . escapeshellarg($excelPath)
            . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $raw = trim(implode(PHP_EOL, $output));
        $data = json_decode($raw, true);

        if ($exitCode !== 0) {
            $message = $data['error'] ?? $raw ?: 'No se pudo leer el archivo Excel.';
            throw new RuntimeException($message);
        }

        if (!is_array($data) || !isset($data['rows'])) {
            throw new RuntimeException(
                'La lectura del Excel devolvio un formato inesperado. '
                . 'JSON: '
                . json_last_error_msg()
                . '. Vista previa: '
                . substr($raw, 0, 400)
            );
        }

        return $data;
    }

    private function resolverPython(): string
    {
        $userProfile = getenv('USERPROFILE') ?: 'C:\\Users\\Acer_V';
        $candidates = [
            getenv('MEZQUITIC_PYTHON_BIN') ?: null,
            $userProfile . '\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\python\\python.exe',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('No se encontro un ejecutable de Python para leer el Excel.');
    }

    private function refrescarStaging(array $rows): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO staging_padron_excel
                (id_excel, usuario, medidor, ruta, lect_ene26, lect_mar26, calle, numero, colonia, observaciones)
             VALUES
                (:id_excel, :usuario, :medidor, :ruta, :lect_ene26, :lect_mar26, :calle, :numero, :colonia, :observaciones)"
        );

        $this->db->beginTransaction();

        try {
            $this->db->exec('DELETE FROM staging_padron_excel');

            foreach ($rows as $row) {
                $domicilio = $this->normalizarTexto($row['domicilio'] ?? null, 180, true);
                [$calle, $numero] = $this->separarDomicilio($domicilio);

                $stmt->execute([
                    'id_excel' => $this->stringOrNull($row['id_excel'] ?? null),
                    'usuario' => $this->normalizarTexto($row['usuario'] ?? null, 180, true),
                    'medidor' => $this->normalizarCodigo($row['medidor'] ?? null, 60),
                    'ruta' => $this->normalizarCodigo($row['ruta'] ?? null, 25),
                    'lect_ene26' => $this->stringOrNull($row['lect_ene26'] ?? null),
                    'lect_mar26' => $this->stringOrNull($row['lect_mar26'] ?? null),
                    'calle' => $calle,
                    'numero' => $numero,
                    'colonia' => $this->normalizarTexto($row['colonia'] ?? null, 180, true),
                    'observaciones' => $this->normalizarTexto($row['observaciones'] ?? null, 65535, true),
                ]);
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function normalizarFila(array $row): array
    {
        $padronId = $this->toInt($row['id_excel'] ?? null);
        $nombre = $this->normalizarTexto($row['usuario'] ?? null, 180, true);
        $medidorOriginal = $this->normalizarCodigo($row['medidor'] ?? null, 60);
        [$medidor, $estadoMedidor, $medidorReferencia] = $this->resolverMedidor($medidorOriginal, $padronId, $row);
        $rutaCodigo = $this->normalizarCodigo($row['ruta'] ?? null, 25);
        $domicilio = $this->normalizarTexto($row['domicilio'] ?? null, 180, true);
        [$calle, $numero] = $this->separarDomicilio($domicilio);
        $colonia = $this->normalizarTexto($row['colonia'] ?? null, 180, true);
        $observaciones = $this->normalizarTexto($row['observaciones'] ?? null, 65535, true);

        if ($medidorReferencia) {
            $prefijo = 'MEDIDOR_PADRON: ' . $medidorReferencia;
            $observaciones = $observaciones
                ? $prefijo . ' | ' . $observaciones
                : $prefijo;
        }

        $comunidadId = null;
        $rutaId = null;

        if ($rutaCodigo) {
            $comunidad = $this->resolverComunidad($rutaCodigo);
            $comunidadId = $this->asegurarComunidad($comunidad['nombre'], $comunidad['prefijo']);
            $rutaId = $this->asegurarRuta($comunidadId, $rutaCodigo);
        }

        return [
            'padron_id' => $padronId,
            'nombre' => $nombre,
            'medidor' => $medidor,
            'medidor_original' => $medidorOriginal,
            'estado_medidor' => $estadoMedidor,
            'ruta_codigo' => $rutaCodigo,
            'ruta_id' => $rutaId,
            'comunidad_id' => $comunidadId,
            'calle' => $calle,
            'numero' => $numero,
            'colonia' => $colonia,
            'observaciones' => $observaciones,
            'lect_ene26' => $this->normalizarLecturaHistorica($row['lect_ene26'] ?? null),
            'lect_mar26' => $this->normalizarLecturaHistorica($row['lect_mar26'] ?? null),
            'source_sheet' => $row['source_sheet'] ?? null,
            'source_row' => $row['source_row'] ?? null,
        ];
    }

    private function validarFila(array $fila): void
    {
        if ($fila['padron_id'] === null && !$fila['medidor']) {
            throw new RuntimeException('La fila no tiene id ni medidor para identificar al usuario.');
        }

        if (!$fila['nombre']) {
            throw new RuntimeException('La fila no tiene nombre de usuario.');
        }

        if (!$fila['ruta_codigo']) {
            throw new RuntimeException('La fila no tiene ruta.');
        }

        if (!$fila['ruta_id'] || !$fila['comunidad_id']) {
            throw new RuntimeException('No se pudo resolver la comunidad o la ruta del registro.');
        }
    }

    private function upsertFila(array $fila): string
    {
        $matchPadron = $fila['padron_id'] !== null
            ? $this->buscarPorPadronId($fila['padron_id'])
            : null;
        $matchMedidor = $fila['padron_id'] === null && $fila['medidor']
            ? $this->buscarPorMedidor($fila['medidor'])
            : null;

        if (
            $matchPadron
            && $matchMedidor
            && (int) $matchPadron['usuario_id'] !== (int) $matchMedidor['usuario_id']
        ) {
            throw new RuntimeException('El id del padron y el medidor apuntan a usuarios distintos.');
        }

        $registro = $matchPadron ?: $matchMedidor;

        if (!$registro) {
            $this->insertarFila($fila);
            return 'insertado';
        }

        $this->actualizarFila($registro, $fila);
        return 'actualizado';
    }

    private function insertarFila(array $fila): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO usuarios_servicio
                (padron_id, nombre, telefono, whatsapp, ruta_id, activo)
             VALUES
                (:padron_id, :nombre, NULL, NULL, :ruta_id, 1)"
        );
        $stmt->execute([
            'padron_id' => $fila['padron_id'],
            'nombre' => $fila['nombre'],
            'ruta_id' => $fila['ruta_id'],
        ]);

        $usuarioId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "INSERT INTO domicilios
                (usuario_id, comunidad_id, calle, numero, colonia, ruta, observaciones, modo_ubicacion, activo)
             VALUES
                (:usuario_id, :comunidad_id, :calle, :numero, :colonia, :ruta, :observaciones, 'manual', 1)"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'comunidad_id' => $fila['comunidad_id'],
            'calle' => $fila['calle'],
            'numero' => $fila['numero'],
            'colonia' => $fila['colonia'],
            'ruta' => $fila['ruta_codigo'],
            'observaciones' => $fila['observaciones'],
        ]);

        $domicilioId = (int) $this->db->lastInsertId();

        if (!$fila['medidor']) {
            throw new RuntimeException('No se puede insertar un usuario nuevo sin medidor.');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO medidores
                (usuario_id, domicilio_id, numero, estado)
             VALUES
                (:usuario_id, :domicilio_id, :numero, :estado)"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'domicilio_id' => $domicilioId,
            'numero' => $fila['medidor'],
            'estado' => $fila['estado_medidor'],
        ]);
    }

    private function actualizarFila(array $registro, array $fila): void
    {
        $usuarioId = (int) $registro['usuario_id'];
        $domicilioId = isset($registro['domicilio_id']) ? (int) $registro['domicilio_id'] : 0;
        $medidorId = isset($registro['medidor_id']) ? (int) $registro['medidor_id'] : 0;

        $stmt = $this->db->prepare(
            "UPDATE usuarios_servicio
             SET padron_id = :padron_id,
                 nombre = :nombre,
                 ruta_id = :ruta_id,
                 activo = 1
             WHERE id = :usuario_id"
        );
        $stmt->execute([
            'padron_id' => $fila['padron_id'] ?? $registro['padron_id'],
            'nombre' => $fila['nombre'],
            'ruta_id' => $fila['ruta_id'],
            'usuario_id' => $usuarioId,
        ]);

        if ($domicilioId > 0) {
            $stmt = $this->db->prepare(
                "UPDATE domicilios
                 SET comunidad_id = :comunidad_id,
                     calle = :calle,
                     numero = :numero,
                     colonia = :colonia,
                     ruta = :ruta,
                     observaciones = :observaciones,
                     activo = 1
                 WHERE id = :domicilio_id"
            );
            $stmt->execute([
                'comunidad_id' => $fila['comunidad_id'],
                'calle' => $fila['calle'] ?: $registro['calle'],
                'numero' => $fila['numero'] ?: $registro['numero'],
                'colonia' => $fila['colonia'] ?: $registro['colonia'],
                'ruta' => $fila['ruta_codigo'],
                'observaciones' => $fila['observaciones'] ?: $registro['observaciones'],
                'domicilio_id' => $domicilioId,
            ]);
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO domicilios
                    (usuario_id, comunidad_id, calle, numero, colonia, ruta, observaciones, modo_ubicacion, activo)
                 VALUES
                    (:usuario_id, :comunidad_id, :calle, :numero, :colonia, :ruta, :observaciones, 'manual', 1)"
            );
            $stmt->execute([
                'usuario_id' => $usuarioId,
                'comunidad_id' => $fila['comunidad_id'],
                'calle' => $fila['calle'],
                'numero' => $fila['numero'],
                'colonia' => $fila['colonia'],
                'ruta' => $fila['ruta_codigo'],
                'observaciones' => $fila['observaciones'],
            ]);
            $domicilioId = (int) $this->db->lastInsertId();
        }

        if ($fila['medidor']) {
            $this->asegurarMedidorDisponible($fila['medidor'], $usuarioId, $medidorId);

            if ($medidorId > 0) {
                $stmt = $this->db->prepare(
                "UPDATE medidores
                     SET usuario_id = :usuario_id,
                         domicilio_id = :domicilio_id,
                         numero = :numero,
                         estado = :estado
                     WHERE id = :medidor_id"
                );
                $stmt->execute([
                    'usuario_id' => $usuarioId,
                    'domicilio_id' => $domicilioId,
                    'numero' => $fila['medidor'],
                    'estado' => $fila['estado_medidor'],
                    'medidor_id' => $medidorId,
                ]);
            } else {
                $stmt = $this->db->prepare(
                    "INSERT INTO medidores
                        (usuario_id, domicilio_id, numero, estado)
                     VALUES
                        (:usuario_id, :domicilio_id, :numero, :estado)"
                );
                $stmt->execute([
                    'usuario_id' => $usuarioId,
                    'domicilio_id' => $domicilioId,
                    'numero' => $fila['medidor'],
                    'estado' => $fila['estado_medidor'],
                ]);
            }
        }
    }

    private function buscarPorPadronId(int $padronId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT
                u.id AS usuario_id,
                u.padron_id,
                d.id AS domicilio_id,
                d.calle,
                d.numero,
                d.colonia,
                d.observaciones,
                m.id AS medidor_id
             FROM usuarios_servicio u
             LEFT JOIN domicilios d ON d.usuario_id = u.id
             LEFT JOIN medidores m ON m.usuario_id = u.id
             WHERE u.padron_id = :padron_id
             ORDER BY d.id ASC, m.id ASC
             LIMIT 1"
        );
        $stmt->execute(['padron_id' => $padronId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function buscarPorMedidor(string $medidor): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT
                u.id AS usuario_id,
                u.padron_id,
                d.id AS domicilio_id,
                d.calle,
                d.numero,
                d.colonia,
                d.observaciones,
                m.id AS medidor_id
             FROM medidores m
             INNER JOIN usuarios_servicio u ON u.id = m.usuario_id
             LEFT JOIN domicilios d ON d.usuario_id = u.id
             WHERE m.numero = :numero
             ORDER BY d.id ASC, m.id ASC
             LIMIT 1"
        );
        $stmt->execute(['numero' => $medidor]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function asegurarMedidorDisponible(string $medidor, int $usuarioId, int $medidorIdActual): void
    {
        $stmt = $this->db->prepare(
            "SELECT id, usuario_id
             FROM medidores
             WHERE numero = :numero
             LIMIT 1"
        );
        $stmt->execute(['numero' => $medidor]);
        $row = $stmt->fetch();

        if (!$row) {
            return;
        }

        if ((int) $row['id'] === $medidorIdActual && (int) $row['usuario_id'] === $usuarioId) {
            return;
        }

        throw new RuntimeException('El medidor ya pertenece a otro usuario del sistema.');
    }

    private function sincronizarHistorico2026(array $filas, string $fuente): array
    {
        $periodo = $this->obtenerPeriodo(2026, 1);
        if (!$periodo) {
            return [
                'periodo' => '',
                'insertados' => 0,
                'actualizados' => 0,
                'omitidos' => count($filas),
                'protegidos' => 0,
                'detalle' => 'No se encontro el periodo Enero-Febrero 2026 para conectar el historico.',
            ];
        }

        $resultado = [
            'periodo' => (string) ($periodo['nombre'] ?? ''),
            'insertados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'protegidos' => 0,
            'detalle' => 'Historico conectado con el periodo actual de 2026.',
        ];

        foreach ($filas as $fila) {
            $lecturaAnterior = $fila['lect_ene26'] ?? null;
            $lecturaActual = $fila['lect_mar26'] ?? null;

            if ($lecturaAnterior === null || $lecturaActual === null) {
                $resultado['omitidos']++;
                continue;
            }

            $medidorId = $this->buscarMedidorId((string) ($fila['medidor'] ?? ''));
            if ($medidorId <= 0) {
                $resultado['omitidos']++;
                continue;
            }

            $lecturaExistente = $this->buscarLecturaPorPeriodo($medidorId, (int) $periodo['id']);
            if ($lecturaExistente && $this->lecturaProtegida($lecturaExistente)) {
                $resultado['protegidos']++;
                continue;
            }

            $payload = [
                'lectura_anterior' => round((float) $lecturaAnterior, 2),
                'lectura_actual' => round((float) $lecturaActual, 2),
                'fecha_captura' => (string) $periodo['fecha_fin'] . ' 23:59:59',
                'observaciones' => 'Historico 2026 importado desde padron de Excel.',
                'origen' => 'historico_excel',
                'fuente_historica' => $fuente,
            ];

            if ($lecturaExistente) {
                $stmt = $this->db->prepare(
                    "UPDATE lecturas
                     SET lectura_anterior = :lectura_anterior,
                         lectura_actual = :lectura_actual,
                         fecha_captura = :fecha_captura,
                         observaciones = :observaciones,
                         origen = :origen,
                         fuente_historica = :fuente_historica
                     WHERE id = :lectura_id"
                );
                $stmt->execute($payload + [
                    'lectura_id' => (int) $lecturaExistente['id'],
                ]);
                $resultado['actualizados']++;
                continue;
            }

            $stmt = $this->db->prepare(
                "INSERT INTO lecturas
                    (medidor_id, periodo_id, lectura_anterior, lectura_actual, capturado_por_id, fecha_captura,
                     latitud, longitud, observaciones, foto_medicion_path, origen, fuente_historica)
                 VALUES
                    (:medidor_id, :periodo_id, :lectura_anterior, :lectura_actual, NULL, :fecha_captura,
                     NULL, NULL, :observaciones, NULL, :origen, :fuente_historica)"
            );
            $stmt->execute($payload + [
                'medidor_id' => $medidorId,
                'periodo_id' => (int) $periodo['id'],
            ]);
            $resultado['insertados']++;
        }

        return $resultado;
    }

    private function obtenerPeriodo(int $anio, int $bimestre): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nombre, fecha_fin
             FROM periodos_bimestrales
             WHERE anio = :anio
               AND bimestre = :bimestre
             LIMIT 1"
        );
        $stmt->execute([
            'anio' => $anio,
            'bimestre' => $bimestre,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function buscarMedidorId(string $numero): int
    {
        if ($numero === '') {
            return 0;
        }

        $stmt = $this->db->prepare(
            "SELECT id
             FROM medidores
             WHERE numero = :numero
             LIMIT 1"
        );
        $stmt->execute(['numero' => $numero]);
        $row = $stmt->fetch();

        return (int) ($row['id'] ?? 0);
    }

    private function buscarLecturaPorPeriodo(int $medidorId, int $periodoId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT
                id,
                capturado_por_id,
                latitud,
                longitud,
                foto_medicion_path,
                origen
             FROM lecturas
             WHERE medidor_id = :medidor_id
               AND periodo_id = :periodo_id
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([
            'medidor_id' => $medidorId,
            'periodo_id' => $periodoId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function lecturaProtegida(array $lectura): bool
    {
        if (($lectura['origen'] ?? '') === 'historico_excel') {
            return false;
        }

        if ((int) ($lectura['capturado_por_id'] ?? 0) > 0) {
            return true;
        }

        if (trim((string) ($lectura['foto_medicion_path'] ?? '')) !== '') {
            return true;
        }

        return trim((string) ($lectura['latitud'] ?? '')) !== ''
            || trim((string) ($lectura['longitud'] ?? '')) !== '';
    }

    private function asegurarComunidad(string $nombre, string $prefijo): int
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM comunidades
             WHERE prefijo_ruta = :prefijo
             LIMIT 1"
        );
        $stmt->execute(['prefijo' => $prefijo]);
        $row = $stmt->fetch();

        if ($row) {
            $this->db->prepare(
                "UPDATE comunidades
                 SET nombre = :nombre,
                     activo = 1
                 WHERE id = :id"
            )->execute([
                'nombre' => $nombre,
                'id' => $row['id'],
            ]);

            return (int) $row['id'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO comunidades (nombre, prefijo_ruta, activo)
             VALUES (:nombre, :prefijo, 1)"
        );
        $stmt->execute([
            'nombre' => $nombre,
            'prefijo' => $prefijo,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function asegurarRuta(int $comunidadId, string $codigo): int
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM rutas
             WHERE codigo = :codigo
             LIMIT 1"
        );
        $stmt->execute(['codigo' => $codigo]);
        $row = $stmt->fetch();

        if ($row) {
            $this->db->prepare(
                "UPDATE rutas
                 SET comunidad_id = :comunidad_id,
                     nombre = :nombre,
                     activo = 1
                 WHERE id = :id"
            )->execute([
                'comunidad_id' => $comunidadId,
                'nombre' => $codigo,
                'id' => $row['id'],
            ]);

            return (int) $row['id'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO rutas
                (comunidad_id, codigo, nombre, descripcion, activo)
             VALUES
                (:comunidad_id, :codigo, :nombre, NULL, 1)"
        );
        $stmt->execute([
            'comunidad_id' => $comunidadId,
            'codigo' => $codigo,
            'nombre' => $codigo,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function resolverComunidad(?string $rutaCodigo): array
    {
        $prefix = '';

        if ($rutaCodigo) {
            $prefix = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', strtok($rutaCodigo, '-')));
        }

        $map = [
            'CENT1' => ['nombre' => 'Centro 1', 'prefijo' => 'Cent1'],
            'CENT2' => ['nombre' => 'Centro 2', 'prefijo' => 'Cent2'],
            'CENTRO' => ['nombre' => 'Centro 1', 'prefijo' => 'Cent1'],
            'PEDRE' => ['nombre' => 'Pedregal', 'prefijo' => 'Pedre'],
            'LLANI' => ['nombre' => 'Llano', 'prefijo' => 'Llani'],
            'LANI' => ['nombre' => 'Llano', 'prefijo' => 'Llani'],
            'PUENT' => ['nombre' => 'Puente', 'prefijo' => 'Puent'],
            'PLAYA' => ['nombre' => 'Playa', 'prefijo' => 'Playa'],
            'BELLAV' => ['nombre' => 'Bella Vista', 'prefijo' => 'Bellav'],
            'EJIDAL' => ['nombre' => 'Ejidal', 'prefijo' => 'Ejidal'],
            'EJIDA' => ['nombre' => 'Ejidal', 'prefijo' => 'Ejidal'],
        ];

        if (isset($map[$prefix])) {
            return $map[$prefix];
        }

        $fallbackPrefix = $prefix !== '' ? $prefix : 'GEN';
        return [
            'nombre' => ucfirst(strtolower($fallbackPrefix)),
            'prefijo' => substr($fallbackPrefix, 0, 20),
        ];
    }

    private function separarDomicilio(?string $domicilio): array
    {
        if (!$domicilio) {
            return [null, null];
        }

        $patterns = [
            '/^(.*?)(?:\\s+(?:NO\\.?|NUM\\.?|NUMERO|#)\\s*([A-Z0-9\\-\\/]+))$/u',
            '/^(.*?)(?:\\s+(S\\/N))$/u',
            '/^(.*?)(?:\\s+(LOTE\\s+[A-Z0-9\\-\\/]+))$/u',
            '/^(.*?)(?:\\s+(KM\\.?\\s*[0-9]+(?:\\.[0-9]+)?))$/u',
            '/^(.*?)(?:\\s+([0-9]+[A-Z0-9\\-\\/]*))$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $domicilio, $matches)) {
                $calle = trim($matches[1], " \t\n\r\0\x0B,.-");
                $numero = trim($matches[2], " \t\n\r\0\x0B,.-");

                return [
                    $calle !== '' ? $calle : null,
                    $numero !== '' ? $numero : null,
                ];
            }
        }

        return [$domicilio, null];
    }

    private function normalizarTexto($value, int $maxLength, bool $upper = false): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        $text = preg_replace('/\s+/u', ' ', $text);

        if ($text === '') {
            return null;
        }

        if ($upper) {
            $text = mb_strtoupper($text, 'UTF-8');
        }

        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }

    private function normalizarCodigo($value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $code = mb_strtoupper(trim((string) $value), 'UTF-8');
        $code = preg_replace('/\s+/u', '', $code);
        $code = preg_replace('/[^A-Z0-9-]/', '', $code);

        if ($code === '') {
            return null;
        }

        return substr($code, 0, $maxLength);
    }

    private function resolverMedidor(?string $medidorOriginal, ?int $padronId, array $row): array
    {
        if ($medidorOriginal === null) {
            return [
                $this->generarMedidorVirtual($padronId, $row, 'SINMED'),
                'sin_medidor',
                null,
            ];
        }

        if ($this->esMedidorPlaceholder($medidorOriginal)) {
            return [
                $this->generarMedidorVirtual($padronId, $row, $medidorOriginal),
                'sin_medidor',
                $medidorOriginal,
            ];
        }

        return [$medidorOriginal, 'activo', null];
    }

    private function esMedidorPlaceholder(string $medidor): bool
    {
        if (str_contains($medidor, 'XXXX')) {
            return true;
        }

        return (bool) preg_match('/-0{3,4}$/', $medidor);
    }

    private function generarMedidorVirtual(?int $padronId, array $row, string $base): string
    {
        $base = preg_replace('/[^A-Z0-9-]/', '', mb_strtoupper($base, 'UTF-8')) ?: 'SINMED';

        if (strlen($base) > 40) {
            $base = substr($base, 0, 40);
        }

        if ($padronId !== null) {
            return sprintf('%s-P%06d', $base, $padronId);
        }

        $sheet = $this->normalizarCodigo($row['source_sheet'] ?? null, 12) ?: 'PADRON';
        $fila = isset($row['source_row']) ? (int) $row['source_row'] : 0;

        return sprintf('%s-%s-R%05d', $base, $sheet, $fila);
    }

    private function toInt($value): ?int
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (!preg_match('/^\d+$/', $text)) {
            return null;
        }

        return (int) $text;
    }

    private function normalizarLecturaHistorica($value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $text = str_replace([',', ' '], ['', ''], $text);
        if (!is_numeric($text)) {
            return null;
        }

        return round((float) $text, 2);
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}
