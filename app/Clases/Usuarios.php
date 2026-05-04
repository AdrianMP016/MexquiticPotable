<?php

class Usuarios
{
    private PDO $db;
    private string $uploadDir;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->uploadDir = dirname(__DIR__, 2) . '/fachadas';
    }

    public function guardar(array $input, ?array $fachada = null): array
    {
        $data = $this->normalizar($input);
        $errors = $this->validar($data);

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $this->validarDuplicados($data);

        $fachadaPath = null;
        $this->db->beginTransaction();

        try {
            $data['ruta'] = $this->obtenerCodigoRuta($data['ruta_id']);
            $usuarioId = $this->crearUsuario($data);
            $fachadaPath = $this->guardarFachada($fachada, $usuarioId);
            $comunidadId = $this->obtenerComunidadId($data['comunidad']);
            $domicilioId = $this->crearDomicilio($usuarioId, $comunidadId, $data, $fachadaPath);
            $medidorId = $this->crearMedidor($usuarioId, $domicilioId, $data);

            $this->db->commit();

            return [
                'usuario_id' => $usuarioId,
                'domicilio_id' => $domicilioId,
                'medidor_id' => $medidorId,
                'nombre' => $data['nombre'],
                'ruta' => $data['ruta'],
                'medidor' => $data['medidor'],
                'fachada_path' => $fachadaPath,
            ];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            if (!empty($fachadaPath)) {
                $absolutePath = dirname(__DIR__, 2) . '/' . $fachadaPath;
                if (is_file($absolutePath)) {
                    unlink($absolutePath);
                }
            }
            throw $exception;
        }
    }

    public function buscarDuplicados(string $termino): array
    {
        $termino = trim($termino);

        if ($termino === '') {
            return [];
        }

        $like = '%' . $termino . '%';

        $stmt = $this->db->prepare(
            "SELECT
                u.id AS usuario_id,
                u.padron_id,
                u.nombre,
                u.telefono,
                u.whatsapp,
                u.ruta_id,
                COALESCE(rt.codigo, d.ruta) AS ruta,
                m.numero AS medidor
             FROM usuarios_servicio u
             LEFT JOIN domicilios d ON d.usuario_id = u.id
             LEFT JOIN rutas rt ON rt.id = u.ruta_id
             LEFT JOIN medidores m ON m.usuario_id = u.id
             WHERE u.nombre LIKE :nombre
                OR d.ruta LIKE :ruta
                OR m.numero LIKE :medidor
                OR u.telefono LIKE :telefono
                OR u.whatsapp LIKE :whatsapp
             ORDER BY u.id DESC
             LIMIT 10"
        );
        $stmt->execute([
            'nombre' => $like,
            'ruta' => $like,
            'medidor' => $like,
            'telefono' => $like,
            'whatsapp' => $like,
        ]);

        return $stmt->fetchAll();
    }

    public function listar(string $nombre = '', int $page = 1, int $perPage = 25): array
    {
        $params = [];
        $where = '';

        if (trim($nombre) !== '') {
            $where = 'WHERE u.nombre LIKE :nombre';
            $params['nombre'] = '%' . mb_strtoupper(trim($nombre), 'UTF-8') . '%';
        }

        $page = max(1, $page);
        $perPage = (int) $perPage;
        $allowAll = $perPage <= 0;

        $stmtCount = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM usuarios_servicio u
             LEFT JOIN domicilios d ON d.usuario_id = u.id
             LEFT JOIN comunidades c ON c.id = d.comunidad_id
             LEFT JOIN rutas rt ON rt.id = u.ruta_id
             LEFT JOIN medidores m ON m.usuario_id = u.id
             $where"
        );
        $stmtCount->execute($params);
        $total = (int) ($stmtCount->fetch()['total'] ?? 0);

        if ($allowAll) {
            $perPage = $total > 0 ? $total : 1;
        } else {
            $perPage = max(1, min($perPage, 500));
        }

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            "SELECT
                u.id AS usuario_id,
                u.padron_id,
                u.nombre,
                u.telefono,
                u.whatsapp,
                u.activo,
                u.ruta_id,
                d.id AS domicilio_id,
                d.calle,
                d.numero AS numero_domicilio,
                d.colonia,
                COALESCE(rt.codigo, d.ruta) AS ruta,
                d.google_place_id,
                d.modo_ubicacion,
                d.referencia_ubicacion,
                d.fachada_path,
                c.nombre AS comunidad,
                rt.nombre AS ruta_nombre,
                m.id AS medidor_id,
                m.numero AS medidor,
                m.estado AS estado_medidor
             FROM usuarios_servicio u
             LEFT JOIN domicilios d ON d.usuario_id = u.id
             LEFT JOIN comunidades c ON c.id = d.comunidad_id
             LEFT JOIN rutas rt ON rt.id = u.ruta_id
             LEFT JOIN medidores m ON m.usuario_id = u.id
             $where
             ORDER BY u.activo DESC, COALESCE(u.padron_id, u.id) ASC, u.id ASC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $usuarios = array_map(function (array $usuario): array {
            $usuario['fachada_path'] = $usuario['fachada_path'] ?: $this->buscarFachadaUsuario((int) $usuario['usuario_id']);
            return $usuario;
        }, $stmt->fetchAll());

        return [
            'usuarios' => $usuarios,
            'pagination' => [
                'page' => $page,
                'per_page' => $allowAll ? 0 : $perPage,
                'effective_per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? $offset + count($usuarios) : 0,
            ],
        ];
    }

    public function obtener(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                u.id AS usuario_id,
                u.padron_id,
                u.nombre,
                u.telefono,
                u.whatsapp,
                u.activo,
                u.ruta_id,
                d.id AS domicilio_id,
                d.calle,
                d.numero AS numero_domicilio,
                d.colonia,
                d.latitud,
                d.longitud,
                d.google_place_id,
                d.modo_ubicacion,
                d.referencia_ubicacion,
                d.fachada_path,
                d.ruta,
                c.nombre AS comunidad,
                rt.nombre AS ruta_nombre,
                m.id AS medidor_id,
                m.numero AS medidor,
                m.estado AS estado_medidor
             FROM usuarios_servicio u
             LEFT JOIN domicilios d ON d.usuario_id = u.id
             LEFT JOIN comunidades c ON c.id = d.comunidad_id
             LEFT JOIN rutas rt ON rt.id = u.ruta_id
             LEFT JOIN medidores m ON m.usuario_id = u.id
             WHERE u.id = :usuario_id
             LIMIT 1"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            throw new RuntimeException('No se encontro el usuario solicitado.');
        }

        $usuario['fachada_path'] = $usuario['fachada_path'] ?: $this->buscarFachadaUsuario((int) $usuario['usuario_id']);

        return $usuario;
    }

    public function actualizar(array $input, ?array $fachada): array
    {
        $usuarioId = (int) ($input['usuario_id'] ?? 0);
        $domicilioId = (int) ($input['domicilio_id'] ?? 0);
        $medidorAnteriorId = (int) ($input['medidor_id'] ?? 0);
        $medidorId = (int) ($input['medidor_id_asignado'] ?? $medidorAnteriorId);

        if ($usuarioId <= 0 || $domicilioId <= 0 || $medidorId <= 0) {
            throw new InvalidArgumentException(json_encode([
                'general' => 'No se recibieron los identificadores del registro.',
            ], JSON_UNESCAPED_UNICODE));
        }

        $input['medidor'] = $this->obtenerNumeroMedidor($medidorId);
        $data = $this->normalizar($input);
        $errors = $this->validar($data);

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $this->validarDuplicados($data, $usuarioId, $medidorId);

        $this->db->beginTransaction();

        try {
            $data['ruta'] = $this->obtenerCodigoRuta($data['ruta_id']);
            $comunidadId = $this->obtenerComunidadId($data['comunidad']);
            $fachadaPath = $this->guardarFachada($fachada, $usuarioId);

            $stmt = $this->db->prepare(
                "UPDATE usuarios_servicio
                 SET nombre = :nombre,
                     telefono = :telefono,
                     whatsapp = :whatsapp,
                     ruta_id = :ruta_id,
                     activo = :activo
                 WHERE id = :usuario_id"
            );
            $stmt->execute([
                'nombre' => $data['nombre'],
                'telefono' => $data['telefono'],
                'whatsapp' => $data['whatsapp'],
                'ruta_id' => $data['ruta_id'],
                'activo' => $data['estado_usuario'] === 'Inactivo' ? 0 : 1,
                'usuario_id' => $usuarioId,
            ]);

            $sqlDomicilio = "UPDATE domicilios
                SET comunidad_id = :comunidad_id,
                    calle = :calle,
                    numero = :numero,
                    colonia = :colonia,
                    latitud = :latitud,
                    longitud = :longitud,
                    google_place_id = :google_place_id,
                    modo_ubicacion = :modo_ubicacion,
                    referencia_ubicacion = :referencia_ubicacion,
                    ruta = :ruta";
            $paramsDomicilio = [
                'comunidad_id' => $comunidadId,
                'calle' => $data['calle'],
                'numero' => $data['numero_domicilio'],
                'colonia' => $data['colonia'],
                'latitud' => $data['latitud'],
                'longitud' => $data['longitud'],
                'google_place_id' => $data['google_place_id'],
                'modo_ubicacion' => $data['modo_ubicacion'],
                'referencia_ubicacion' => $data['referencia_ubicacion'],
                'ruta' => $data['ruta'],
                'domicilio_id' => $domicilioId,
            ];

            if ($fachadaPath !== null) {
                $sqlDomicilio .= ", fachada_path = :fachada_path";
                $paramsDomicilio['fachada_path'] = $fachadaPath;
            }

            $sqlDomicilio .= " WHERE id = :domicilio_id";
            $stmt = $this->db->prepare($sqlDomicilio);
            $stmt->execute($paramsDomicilio);

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
                'numero' => $data['medidor'],
                'estado' => $this->mapearEstadoMedidor($data['estado_medidor']),
                'medidor_id' => $medidorId,
            ]);

            $this->db->commit();

            return $this->obtener($usuarioId);
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function darDeBaja(int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            throw new RuntimeException('No se recibio el usuario a dar de baja.');
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('UPDATE usuarios_servicio SET activo = 0 WHERE id = :usuario_id');
            $stmt->execute(['usuario_id' => $usuarioId]);

            $stmt = $this->db->prepare(
                "UPDATE domicilios d
                 JOIN medidores m ON m.domicilio_id = d.id
                 SET d.activo = 0,
                     m.estado = 'inactivo'
                 WHERE d.usuario_id = :usuario_id"
            );
            $stmt->execute(['usuario_id' => $usuarioId]);

            $this->db->commit();

            return ['usuario_id' => $usuarioId];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function normalizar(array $input): array
    {
        $data = [
            'nombre' => $this->mayusculas(Request::cleanString($input['nombre'] ?? null)),
            'telefono' => $this->soloDigitos(Request::cleanString($input['telefono'] ?? null)),
            'whatsapp' => $this->soloDigitos(Request::cleanString($input['whatsapp'] ?? null)),
            'estado_usuario' => Request::cleanString($input['estado_usuario'] ?? 'Activo'),
            'calle' => $this->mayusculas(Request::cleanString($input['calle'] ?? null)),
            'numero_domicilio' => $this->mayusculas(Request::cleanString($input['numero_domicilio'] ?? null)),
            'colonia' => $this->mayusculas(Request::cleanString($input['colonia'] ?? null)),
            'latitud' => Request::cleanString($input['latitud'] ?? null),
            'longitud' => Request::cleanString($input['longitud'] ?? null),
            'google_place_id' => Request::cleanString($input['google_place_id'] ?? null),
            'modo_ubicacion' => Request::cleanString($input['modo_ubicacion'] ?? 'manual'),
            'referencia_ubicacion' => $this->mayusculas(Request::cleanString($input['referencia_ubicacion'] ?? null)),
            'comunidad' => Request::cleanString($input['comunidad'] ?? null),
            'ruta_id' => (int) ($input['ruta_id'] ?? 0),
            'ruta' => null,
            'medidor' => $this->limpiarCodigo(Request::cleanString($input['medidor'] ?? null), 60),
            'lectura_inicial' => $input['lectura_inicial'] ?? null,
            'estado_medidor' => Request::cleanString($input['estado_medidor'] ?? 'Activo'),
        ];

        if ($data['modo_ubicacion'] !== 'google_maps') {
            $data['google_place_id'] = null;
        }

        return $data;
    }

    private function validar(array $data): array
    {
        $errors = [];

        if (!$data['nombre']) {
            $errors['nombre'] = 'Captura el nombre completo.';
        }

        if (!$data['whatsapp']) {
            $errors['whatsapp'] = 'Captura el WhatsApp donde se enviaran los recibos.';
        } elseif (strlen($data['whatsapp']) !== 10) {
            $errors['whatsapp'] = 'El WhatsApp debe tener 10 digitos.';
        }

        if ($data['telefono'] && strlen($data['telefono']) !== 10) {
            $errors['telefono'] = 'El telefono alternativo debe tener 10 digitos.';
        }

        if ($data['ruta_id'] <= 0) {
            $errors['ruta_id'] = 'Selecciona la ruta del usuario.';
        }

        if (!$data['comunidad']) {
            $errors['comunidad'] = 'Selecciona la comunidad.';
        }

        if (!$data['medidor']) {
            $errors['medidor'] = 'Captura el numero de medidor.';
        } elseif (!preg_match('/^[A-Z0-9-]+$/', $data['medidor'])) {
            $errors['medidor'] = 'El medidor solo puede llevar letras, numeros y guiones.';
        }

        if ($data['lectura_inicial'] !== null && $data['lectura_inicial'] !== '' && !is_numeric($data['lectura_inicial'])) {
            $errors['lectura_inicial'] = 'La lectura inicial debe ser numerica.';
        }

        if ($data['latitud'] && !is_numeric($data['latitud'])) {
            $errors['latitud'] = 'La latitud debe ser numerica.';
        }

        if ($data['longitud'] && !is_numeric($data['longitud'])) {
            $errors['longitud'] = 'La longitud debe ser numerica.';
        }

        if ($data['google_place_id'] && strlen($data['google_place_id']) > 191) {
            $errors['google_place_id'] = 'El identificador de Google Maps es demasiado largo.';
        }

        if (!in_array($data['modo_ubicacion'], ['google_maps', 'manual', 'aproximada'], true)) {
            $errors['modo_ubicacion'] = 'Selecciona un modo de ubicacion valido.';
        }

        if ($data['referencia_ubicacion'] && strlen($data['referencia_ubicacion']) > 255) {
            $errors['referencia_ubicacion'] = 'La referencia de ubicacion es demasiado larga.';
        }

        if ($data['modo_ubicacion'] === 'aproximada' && !$data['referencia_ubicacion']) {
            $errors['referencia_ubicacion'] = 'Agrega una referencia cuando la ubicacion sea aproximada.';
        }

        return $errors;
    }

    private function validarDuplicados(array $data, int $usuarioIdExcluir = 0, int $medidorIdExcluir = 0): void
    {
        $stmt = $this->db->prepare('SELECT id FROM usuarios_servicio WHERE whatsapp = :whatsapp AND id <> :id LIMIT 1');
        $stmt->execute([
            'whatsapp' => $data['whatsapp'],
            'id' => $usuarioIdExcluir,
        ]);

        if ($stmt->fetch()) {
            throw new RuntimeException('Ya existe un usuario registrado con ese numero de WhatsApp.');
        }

        $stmt = $this->db->prepare(
            'SELECT id FROM usuarios_servicio WHERE nombre = :nombre AND ruta_id = :ruta_id AND id <> :id LIMIT 1'
        );
        $stmt->execute([
            'nombre' => $data['nombre'],
            'ruta_id' => $data['ruta_id'],
            'id' => $usuarioIdExcluir,
        ]);

        if ($stmt->fetch()) {
            throw new RuntimeException('Ya existe un usuario registrado con el mismo nombre en esa ruta.');
        }

        $stmt = $this->db->prepare('SELECT id FROM medidores WHERE numero = :numero AND id <> :id LIMIT 1');
        $stmt->execute([
            'numero' => $data['medidor'],
            'id' => $medidorIdExcluir,
        ]);

        if ($stmt->fetch()) {
            throw new RuntimeException('Ya existe un medidor registrado con ese numero.');
        }
    }

    private function crearUsuario(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO usuarios_servicio
                (nombre, telefono, whatsapp, ruta_id, activo)
             VALUES
                (:nombre, :telefono, :whatsapp, :ruta_id, :activo)"
        );

        $stmt->execute([
            'nombre' => $data['nombre'],
            'telefono' => $data['telefono'],
            'whatsapp' => $data['whatsapp'],
            'ruta_id' => $data['ruta_id'],
            'activo' => $data['estado_usuario'] === 'Inactivo' ? 0 : 1,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function obtenerComunidadId(string $nombre): int
    {
        $stmt = $this->db->prepare('SELECT id FROM comunidades WHERE nombre = :nombre LIMIT 1');
        $stmt->execute(['nombre' => $nombre]);
        $row = $stmt->fetch();

        if ($row) {
            return (int) $row['id'];
        }

        $prefijo = substr(preg_replace('/[^A-Za-z0-9]/', '', $nombre), 0, 20);
        $stmt = $this->db->prepare('INSERT INTO comunidades (nombre, prefijo_ruta) VALUES (:nombre, :prefijo)');
        $stmt->execute([
            'nombre' => $nombre,
            'prefijo' => $prefijo ?: 'GEN',
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function crearDomicilio(int $usuarioId, int $comunidadId, array $data, ?string $fachadaPath = null): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO domicilios
                (usuario_id, comunidad_id, calle, numero, colonia, latitud, longitud, google_place_id, modo_ubicacion, referencia_ubicacion, fachada_path, ruta, observaciones)
             VALUES
                (:usuario_id, :comunidad_id, :calle, :numero, :colonia, :latitud, :longitud, :google_place_id, :modo_ubicacion, :referencia_ubicacion, :fachada_path, :ruta, :observaciones)"
        );

        $stmt->execute([
            'usuario_id' => $usuarioId,
            'comunidad_id' => $comunidadId,
            'calle' => $data['calle'],
            'numero' => $data['numero_domicilio'],
            'colonia' => $data['colonia'],
            'latitud' => $data['latitud'],
            'longitud' => $data['longitud'],
            'google_place_id' => $data['google_place_id'],
            'modo_ubicacion' => $data['modo_ubicacion'],
            'referencia_ubicacion' => $data['referencia_ubicacion'],
            'fachada_path' => $fachadaPath,
            'ruta' => $data['ruta'],
            'observaciones' => null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function obtenerCodigoRuta(int $rutaId): string
    {
        $stmt = $this->db->prepare('SELECT codigo FROM rutas WHERE id = :ruta_id AND activo = 1 LIMIT 1');
        $stmt->execute(['ruta_id' => $rutaId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('La ruta seleccionada no existe o esta inactiva.');
        }

        return $row['codigo'];
    }

    private function crearMedidor(int $usuarioId, int $domicilioId, array $data): int
    {
        $estado = $this->mapearEstadoMedidor($data['estado_medidor']);

        $stmt = $this->db->prepare(
            "INSERT INTO medidores
                (usuario_id, domicilio_id, numero, estado)
             VALUES
                (:usuario_id, :domicilio_id, :numero, :estado)"
        );

        $stmt->execute([
            'usuario_id' => $usuarioId,
            'domicilio_id' => $domicilioId,
            'numero' => $data['medidor'],
            'estado' => $estado,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function obtenerNumeroMedidor(int $medidorId): string
    {
        $stmt = $this->db->prepare('SELECT numero FROM medidores WHERE id = :medidor_id LIMIT 1');
        $stmt->execute(['medidor_id' => $medidorId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('El medidor seleccionado no existe.');
        }

        return $row['numero'];
    }

    private function mapearEstadoMedidor(?string $estado): string
    {
        $estado = mb_strtolower($estado ?? 'activo', 'UTF-8');

        switch ($estado) {
            case 'sin medidor':
                return 'sin_medidor';
            case 'reemplazado':
                return 'reemplazado';
            case 'inactivo':
                return 'inactivo';
            default:
                return 'activo';
        }
    }

    private function mayusculas(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strtoupper($value, 'UTF-8');
    }

    private function soloDigitos(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);
        return $digits === '' ? null : substr($digits, 0, 10);
    }

    private function limpiarCodigo(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $code = preg_replace('/[^A-Z0-9-]/', '', mb_strtoupper($value, 'UTF-8'));
        return $code === '' ? null : substr($code, 0, $maxLength);
    }

    private function guardarFachada(?array $file, int $usuarioId): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo subir la foto de fachada.');
        }

        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new RuntimeException('La foto de fachada no debe pesar mas de 10 MB.');
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $imageInfo = getimagesize($file['tmp_name']);
        $mime = $imageInfo['mime'] ?? null;

        if (!isset($allowed[$mime])) {
            throw new RuntimeException('La foto de fachada debe ser JPG, PNG o WEBP.');
        }

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true);
        }

        foreach (glob($this->uploadDir . '/usuario_' . $usuarioId . '_fachada.*') ?: [] as $oldFile) {
            if (is_file($oldFile)) {
                unlink($oldFile);
            }
        }

        $filename = 'usuario_' . $usuarioId . '_fachada.' . $allowed[$mime];
        $destination = $this->uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('No se pudo guardar la foto de fachada.');
        }

        return 'fachadas/' . $filename;
    }

    private function buscarFachadaUsuario(int $usuarioId): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $relativePath = 'fachadas/usuario_' . $usuarioId . '_fachada.' . $extension;
            $absolutePath = dirname(__DIR__, 2) . '/' . $relativePath;

            if (is_file($absolutePath)) {
                return $relativePath;
            }
        }

        return null;
    }
}
