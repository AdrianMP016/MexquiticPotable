<?php

class Verificador
{
    private PDO $db;
    private string $uploadDir;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->uploadDir = dirname(__DIR__, 2) . '/mediciones';
    }

    public function obtenerUsuario(int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            throw new RuntimeException('No se recibio el usuario a consultar.');
        }

        $stmt = $this->db->prepare(
            "SELECT
                u.id AS usuario_id,
                u.nombre,
                u.telefono,
                u.whatsapp,
                u.activo,
                d.id AS domicilio_id,
                d.calle,
                d.numero AS numero_domicilio,
                d.colonia,
                d.fachada_path,
                d.ruta,
                m.id AS medidor_id,
                m.numero AS medidor,
                m.estado AS estado_medidor
             FROM usuarios_servicio u
             LEFT JOIN domicilios d ON d.usuario_id = u.id
             LEFT JOIN medidores m ON m.usuario_id = u.id
             WHERE u.id = :usuario_id
               AND u.activo = 1
             LIMIT 1"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            throw new RuntimeException('No se encontro un usuario activo con ese registro.');
        }

        if (empty($usuario['medidor_id'])) {
            throw new RuntimeException('El usuario seleccionado no tiene medidor asignado.');
        }

        if (strtolower((string) ($usuario['estado_medidor'] ?? 'activo')) !== 'activo') {
            throw new RuntimeException('El medidor de este usuario no esta activo para capturar lecturas.');
        }

        $periodo = $this->obtenerPeriodoActual();
        $lecturaActual = $this->obtenerLecturaDelPeriodo((int) $usuario['medidor_id'], (int) $periodo['periodo_id']);
        $lecturaAnterior = $lecturaActual
            ? (float) $lecturaActual['lectura_anterior']
            : $this->obtenerUltimaLecturaAnterior((int) $usuario['medidor_id'], (int) $periodo['periodo_id']);

        $usuario['periodo_id'] = (int) $periodo['periodo_id'];
        $usuario['periodo_nombre'] = $periodo['nombre'];
        $usuario['periodo_fecha_inicio'] = $periodo['fecha_inicio'];
        $usuario['periodo_fecha_fin'] = $periodo['fecha_fin'];
        $usuario['periodo_fecha_vencimiento'] = $periodo['fecha_vencimiento'];
        $usuario['lectura_anterior'] = $lecturaAnterior;
        $usuario['lectura_actual_guardada'] = $lecturaActual ? (float) $lecturaActual['lectura_actual'] : null;
        $usuario['lectura_id_actual'] = $lecturaActual ? (int) $lecturaActual['id'] : null;

        return $usuario;
    }

    public function buscarUsuarios(string $termino): array
    {
        $termino = trim($termino);

        if ($termino === '') {
            return [];
        }

        $like = '%' . $termino . '%';
        $stmt = $this->db->prepare(
            "SELECT
                u.id AS usuario_id,
                u.nombre,
                u.telefono,
                u.whatsapp,
                u.activo,
                COALESCE(rt.codigo, d.ruta) AS ruta,
                rt.nombre AS ruta_nombre,
                m.numero AS medidor,
                m.estado AS estado_medidor
             FROM usuarios_servicio u
             LEFT JOIN domicilios d ON d.usuario_id = u.id
             LEFT JOIN rutas rt ON rt.id = u.ruta_id
             LEFT JOIN medidores m ON m.usuario_id = u.id
             WHERE u.activo = 1
               AND LOWER(COALESCE(m.estado, 'activo')) = 'activo'
               AND (
                    COALESCE(rt.codigo, d.ruta) LIKE :ruta
                    OR COALESCE(rt.nombre, '') LIKE :ruta_nombre
               )
             ORDER BY COALESCE(rt.codigo, d.ruta) ASC, u.nombre ASC, u.id ASC"
        );
        $stmt->execute([
            'ruta' => $like,
            'ruta_nombre' => $like,
        ]);

        return $stmt->fetchAll();
    }

    public function guardarMedicion(array $input, ?array $foto): array
    {
        $data = $this->normalizar($input);
        $errors = $this->validar($data, $foto);

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $periodo = $this->obtenerPeriodoActual();
        $periodoId = (int) $periodo['periodo_id'];

        if (!empty($data['periodo_id']) && (int) $data['periodo_id'] !== $periodoId) {
            throw new RuntimeException('El periodo visible ya cambio. Recarga el usuario antes de guardar la lectura.');
        }

        $this->db->beginTransaction();

        try {
            $this->validarMedidorAsignado($data);
            $lecturaId = $this->guardarLectura($data, $periodoId);
            $fotoPath = $this->guardarFotoMedidor($foto, $lecturaId);

            $stmt = $this->db->prepare('UPDATE lecturas SET foto_medicion_path = :foto WHERE id = :id');
            $stmt->execute([
                'foto' => $fotoPath,
                'id' => $lecturaId,
            ]);

            $this->db->commit();

            return [
                'lectura_id' => $lecturaId,
                'periodo_id' => $periodoId,
                'periodo_nombre' => $periodo['nombre'],
                'foto_medicion_path' => $fotoPath,
                'consumo_m3' => $data['consumo_m3'],
            ];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function normalizar(array $input): array
    {
        $medicion = Request::cleanString($input['medicion'] ?? null);
        $lecturaAnterior = Request::cleanString($input['lectura_anterior'] ?? '0') ?? '0';

        return [
            'usuario_id' => (int) ($input['usuario_id'] ?? 0),
            'domicilio_id' => (int) ($input['domicilio_id'] ?? 0),
            'medidor_id' => (int) ($input['medidor_id'] ?? 0),
            'periodo_id' => (int) ($input['periodo_id'] ?? 0),
            'capturado_por_id' => (int) ($input['_usuario_sistema_id'] ?? 0),
            'lectura_anterior' => is_numeric($lecturaAnterior) ? (float) $lecturaAnterior : 0,
            'medicion' => is_numeric($medicion) ? (float) $medicion : null,
            'latitud' => Request::cleanString($input['latitud'] ?? null),
            'longitud' => Request::cleanString($input['longitud'] ?? null),
            'observaciones' => Request::cleanString($input['observaciones'] ?? null),
        ];
    }

    private function validar(array &$data, ?array $foto): array
    {
        $errors = [];

        foreach (['usuario_id', 'domicilio_id', 'medidor_id'] as $field) {
            if ($data[$field] <= 0) {
                $errors[$field] = 'No se recibieron los datos completos del usuario.';
            }
        }

        if ($data['medicion'] === null) {
            $errors['medicion'] = 'Captura la medicion actual.';
        }

        if (!$data['latitud'] || !is_numeric($data['latitud'])) {
            $errors['latitud'] = 'Captura la latitud de la lectura.';
        }

        if (!$data['longitud'] || !is_numeric($data['longitud'])) {
            $errors['longitud'] = 'Captura la longitud de la lectura.';
        }

        if (!$foto || ($foto['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors['foto_medidor'] = 'Toma la foto del medidor.';
        }

        $data['consumo_m3'] = max($data['medicion'] - $data['lectura_anterior'], 0);

        return $errors;
    }

    private function obtenerPeriodoActual(): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                id AS periodo_id,
                nombre,
                fecha_inicio,
                fecha_fin,
                fecha_vencimiento
             FROM periodos_bimestrales
             WHERE estado <> 'cancelado'
               AND fecha_fin < CURDATE()
             ORDER BY fecha_fin DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch();

        if (!$row) {
            $stmt = $this->db->prepare(
                "SELECT
                    id AS periodo_id,
                    nombre,
                    fecha_inicio,
                    fecha_fin,
                    fecha_vencimiento
                 FROM periodos_bimestrales
                 WHERE estado <> 'cancelado'
                   AND CURDATE() BETWEEN fecha_inicio AND fecha_fin
                 ORDER BY fecha_inicio ASC, id ASC
                 LIMIT 1"
            );
            $stmt->execute();
            $row = $stmt->fetch();
        }

        if (!$row) {
            $stmt = $this->db->prepare(
                "SELECT
                    id AS periodo_id,
                    nombre,
                    fecha_inicio,
                    fecha_fin,
                    fecha_vencimiento
                 FROM periodos_bimestrales
                 WHERE estado <> 'cancelado'
                 ORDER BY fecha_inicio ASC, id ASC
                 LIMIT 1"
            );
            $stmt->execute();
            $row = $stmt->fetch();
        }

        if (!$row) {
            throw new RuntimeException('No existe un periodo disponible para guardar la medicion.');
        }

        return $row;
    }

    private function obtenerLecturaDelPeriodo(int $medidorId, int $periodoId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, lectura_anterior, lectura_actual
             FROM lecturas
             WHERE medidor_id = :medidor_id
               AND periodo_id = :periodo_id
             LIMIT 1"
        );
        $stmt->execute([
            'medidor_id' => $medidorId,
            'periodo_id' => $periodoId,
        ]);

        return $stmt->fetch() ?: null;
    }

    private function obtenerUltimaLecturaAnterior(int $medidorId, int $periodoId): float
    {
        $stmt = $this->db->prepare(
            "SELECT lectura_actual
             FROM lecturas
             WHERE medidor_id = :medidor_id
               AND periodo_id <> :periodo_id
             ORDER BY fecha_captura DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([
            'medidor_id' => $medidorId,
            'periodo_id' => $periodoId,
        ]);
        $row = $stmt->fetch();

        return $row ? (float) $row['lectura_actual'] : 0;
    }

    private function validarMedidorAsignado(array $data): void
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM medidores
             WHERE id = :medidor_id
               AND usuario_id = :usuario_id
               AND domicilio_id = :domicilio_id
             LIMIT 1"
        );
        $stmt->execute([
            'medidor_id' => $data['medidor_id'],
            'usuario_id' => $data['usuario_id'],
            'domicilio_id' => $data['domicilio_id'],
        ]);

        if (!$stmt->fetch()) {
            throw new RuntimeException('El medidor seleccionado no corresponde al usuario o domicilio.');
        }
    }

    private function guardarLectura(array $data, int $periodoId): int
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM lecturas
             WHERE medidor_id = :medidor_id
               AND periodo_id = :periodo_id
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([
            'medidor_id' => $data['medidor_id'],
            'periodo_id' => $periodoId,
        ]);
        $row = $stmt->fetch();

        if ($row) {
            $lecturaId = (int) $row['id'];
            $stmt = $this->db->prepare(
                "UPDATE lecturas
                 SET lectura_anterior = :lectura_anterior,
                     lectura_actual = :lectura_actual,
                     capturado_por_id = :capturado_por_id,
                     latitud = :latitud,
                     longitud = :longitud,
                     fecha_captura = NOW(),
                     observaciones = :observaciones
                 WHERE id = :id"
            );
            $stmt->execute([
                'lectura_anterior' => $data['lectura_anterior'],
                'lectura_actual' => $data['medicion'],
                'capturado_por_id' => $data['capturado_por_id'] > 0 ? $data['capturado_por_id'] : null,
                'latitud' => $data['latitud'],
                'longitud' => $data['longitud'],
                'observaciones' => $data['observaciones'],
                'id' => $lecturaId,
            ]);

            return $lecturaId;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO lecturas
                (medidor_id, periodo_id, lectura_anterior, lectura_actual, capturado_por_id,
                 latitud, longitud, fecha_captura, observaciones, foto_medicion_path)
             VALUES
                (:medidor_id, :periodo_id, :lectura_anterior, :lectura_actual, :capturado_por_id,
                 :latitud, :longitud, NOW(), :observaciones, NULL)"
        );
        $stmt->execute([
            'medidor_id' => $data['medidor_id'],
            'periodo_id' => $periodoId,
            'lectura_anterior' => $data['lectura_anterior'],
            'lectura_actual' => $data['medicion'],
            'capturado_por_id' => $data['capturado_por_id'] > 0 ? $data['capturado_por_id'] : null,
            'latitud' => $data['latitud'],
            'longitud' => $data['longitud'],
            'observaciones' => $data['observaciones'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function guardarFotoMedidor(array $file, int $lecturaId): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo subir la foto del medidor.');
        }

        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new RuntimeException('La foto del medidor no debe pesar mas de 5 MB.');
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $imageInfo = getimagesize($file['tmp_name']);
        $mime = $imageInfo['mime'] ?? null;

        if (!isset($allowed[$mime])) {
            throw new RuntimeException('La foto del medidor debe ser JPG, PNG o WEBP.');
        }

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true);
        }

        $filename = 'lectura_' . $lecturaId . '.' . $allowed[$mime];
        $destination = $this->uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('No se pudo guardar la foto del medidor.');
        }

        foreach (glob($this->uploadDir . '/lectura_' . $lecturaId . '.*') ?: [] as $previousFile) {
            if (is_file($previousFile) && $previousFile !== $destination) {
                unlink($previousFile);
            }
        }

        return 'mediciones/' . $filename;
    }
}
