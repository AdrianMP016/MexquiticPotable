<?php

class Rutas
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listar(int $page = 1, int $perPage = 25, string $buscar = '', string $campo = 'todos'): array
    {
        $page = max(1, $page);
        $perPage = (int) $perPage;
        $allowAll = $perPage <= 0;
        $buscar = trim($buscar);

        [$whereSql, $params] = $this->construirFiltroBusqueda($buscar, $campo);

        $stmtCount = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM rutas r
             LEFT JOIN comunidades c ON c.id = r.comunidad_id
             $whereSql"
        );
        foreach ($params as $key => $value) {
            $stmtCount->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmtCount->execute();
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
                r.id AS ruta_id,
                r.codigo,
                r.nombre,
                r.descripcion,
                r.activo,
                r.created_at,
                c.id AS comunidad_id,
                c.nombre AS comunidad
             FROM rutas r
             LEFT JOIN comunidades c ON c.id = r.comunidad_id
             $whereSql
             ORDER BY r.activo DESC, r.codigo ASC, r.id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rutas = $stmt->fetchAll();

        return [
            'rutas' => $rutas,
            'pagination' => [
                'page' => $page,
                'per_page' => $allowAll ? 0 : $perPage,
                'effective_per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? $offset + count($rutas) : 0,
            ],
        ];
    }

    private function construirFiltroBusqueda(string $buscar, string $campo): array
    {
        if ($buscar === '') {
            return ['', []];
        }

        $campo = trim(mb_strtolower($campo, 'UTF-8'));
        $params = [];

        switch ($campo) {
            case 'id':
                $where = "WHERE CAST(r.id AS CHAR) LIKE :buscar";
                break;
            case 'codigo':
                $where = "WHERE r.codigo LIKE :buscar";
                break;
            case 'nombre':
                $where = "WHERE r.nombre LIKE :buscar";
                break;
            case 'comunidad':
                $where = "WHERE c.nombre LIKE :buscar";
                break;
            case 'descripcion':
                $where = "WHERE r.descripcion LIKE :buscar";
                break;
            case 'estado':
                $where = "WHERE CASE WHEN r.activo = 1 THEN 'activa' ELSE 'baja' END LIKE :buscar";
                break;
            default:
                $where = "WHERE (
                    CAST(r.id AS CHAR) LIKE :buscar
                    OR r.codigo LIKE :buscar
                    OR r.nombre LIKE :buscar
                    OR c.nombre LIKE :buscar
                    OR r.descripcion LIKE :buscar
                    OR CASE WHEN r.activo = 1 THEN 'activa' ELSE 'baja' END LIKE :buscar
                )";
                break;
        }

        $params['buscar'] = '%' . $buscar . '%';

        return [$where, $params];
    }

    public function estadoVerificacion(int $periodoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                r.id AS ruta_id,
                r.codigo,
                r.nombre,
                COUNT(DISTINCT m.id) AS total_medidores,
                COUNT(DISTINCT CASE WHEN l.id IS NOT NULL THEN m.id END) AS con_lectura
             FROM rutas r
             LEFT JOIN usuarios_servicio u ON u.ruta_id = r.id AND u.estado = 'activo'
             LEFT JOIN medidores m ON m.usuario_id = u.id AND m.estado IN ('activo', 'sin_medidor')
             LEFT JOIN lecturas l ON l.medidor_id = m.id AND l.periodo_id = :periodo_id
             WHERE r.activo = 1
             GROUP BY r.id, r.codigo, r.nombre
             ORDER BY r.codigo ASC"
        );
        $stmt->execute(['periodo_id' => $periodoId]);
        $rutas = $stmt->fetchAll();

        $totalMedidores = 0;
        $totalConLectura = 0;

        foreach ($rutas as &$ruta) {
            $ruta['total_medidores'] = (int) $ruta['total_medidores'];
            $ruta['con_lectura'] = (int) $ruta['con_lectura'];
            $ruta['sin_lectura'] = $ruta['total_medidores'] - $ruta['con_lectura'];
            $ruta['porcentaje'] = $ruta['total_medidores'] > 0
                ? round($ruta['con_lectura'] / $ruta['total_medidores'] * 100, 1)
                : 0.0;
            $totalMedidores += $ruta['total_medidores'];
            $totalConLectura += $ruta['con_lectura'];
        }
        unset($ruta);

        return [
            'rutas' => $rutas,
            'resumen' => [
                'total_medidores' => $totalMedidores,
                'con_lectura' => $totalConLectura,
                'sin_lectura' => $totalMedidores - $totalConLectura,
                'porcentaje' => $totalMedidores > 0
                    ? round($totalConLectura / $totalMedidores * 100, 1)
                    : 0.0,
            ],
        ];
    }

    public function faltantesPorRuta(int $periodoId, int $rutaId = 0): array
    {
        $params = ['periodo_id' => $periodoId];
        $whereRuta = '';

        if ($rutaId > 0) {
            $whereRuta = 'AND u.ruta_id = :ruta_id';
            $params['ruta_id'] = $rutaId;
        }

        $stmt = $this->db->prepare(
            "SELECT
                u.padron_id,
                u.nombre AS usuario,
                r.codigo AS ruta_codigo,
                r.nombre AS ruta_nombre,
                d.calle,
                d.numero AS numero_domicilio,
                d.colonia,
                m.numero AS medidor
             FROM usuarios_servicio u
             LEFT JOIN rutas r ON r.id = u.ruta_id
             LEFT JOIN medidores m ON m.usuario_id = u.id AND m.estado IN ('activo', 'sin_medidor')
             LEFT JOIN domicilios d ON d.id = m.domicilio_id
             LEFT JOIN lecturas l ON l.medidor_id = m.id AND l.periodo_id = :periodo_id
             WHERE u.estado = 'activo'
               $whereRuta
               AND l.id IS NULL
               AND m.id IS NOT NULL
             ORDER BY r.codigo ASC, u.nombre ASC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function catalogo(int $comunidadId = 0): array
    {
        $sql = "SELECT
                    r.id AS ruta_id,
                    r.codigo,
                    r.nombre,
                    r.descripcion,
                    r.comunidad_id,
                    c.nombre AS comunidad
                FROM rutas r
                LEFT JOIN comunidades c ON c.id = r.comunidad_id
                WHERE r.activo = 1";
        $params = [];

        if ($comunidadId > 0) {
            $sql .= " AND r.comunidad_id = :comunidad_id";
            $params['comunidad_id'] = $comunidadId;
        }

        $sql .= " ORDER BY r.codigo ASC, r.nombre ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function obtener(int $rutaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                r.id AS ruta_id,
                r.codigo,
                r.nombre,
                r.descripcion,
                r.activo,
                r.comunidad_id
             FROM rutas r
             WHERE r.id = :ruta_id
             LIMIT 1"
        );
        $stmt->execute(['ruta_id' => $rutaId]);
        $ruta = $stmt->fetch();

        if (!$ruta) {
            throw new RuntimeException('No se encontro la ruta solicitada.');
        }

        return $ruta;
    }

    public function guardar(array $input): array
    {
        $data = $this->normalizar($input);
        $errors = $this->validar($data);

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $this->validarCodigoUnico($data['codigo']);

        $stmt = $this->db->prepare(
            "INSERT INTO rutas
                (comunidad_id, codigo, nombre, descripcion, activo)
             VALUES
                (:comunidad_id, :codigo, :nombre, :descripcion, 1)"
        );
        $stmt->execute([
            'comunidad_id' => $data['comunidad_id'],
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'],
        ]);

        return $this->obtener((int) $this->db->lastInsertId());
    }

    public function actualizar(array $input): array
    {
        $rutaId = (int) ($input['ruta_id'] ?? 0);
        $data = $this->normalizar($input);
        $errors = $this->validar($data);

        if ($rutaId <= 0) {
            $errors['ruta_id'] = 'No se recibio la ruta a editar.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $this->validarCodigoUnico($data['codigo'], $rutaId);

        $stmt = $this->db->prepare(
            "UPDATE rutas
             SET comunidad_id = :comunidad_id,
                 codigo = :codigo,
                 nombre = :nombre,
                 descripcion = :descripcion
             WHERE id = :ruta_id"
        );
        $stmt->execute([
            'comunidad_id' => $data['comunidad_id'],
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'],
            'ruta_id' => $rutaId,
        ]);

        $this->sincronizarCodigoEnDomicilios($rutaId, $data['codigo']);

        return $this->obtener($rutaId);
    }

    public function darDeBaja(int $rutaId): array
    {
        if ($rutaId <= 0) {
            throw new RuntimeException('No se recibio la ruta a dar de baja.');
        }

        $stmt = $this->db->prepare(
            "UPDATE rutas
             SET activo = 0
             WHERE id = :ruta_id"
        );
        $stmt->execute(['ruta_id' => $rutaId]);

        return $this->obtener($rutaId);
    }

    private function normalizar(array $input): array
    {
        return [
            'comunidad_id' => (int) ($input['comunidad_id'] ?? 0),
            'codigo' => $this->limpiarCodigo(Request::cleanString($input['codigo'] ?? null), 25),
            'nombre' => $this->mayusculas(Request::cleanString($input['nombre'] ?? null)),
            'descripcion' => $this->mayusculas(Request::cleanString($input['descripcion'] ?? null)),
        ];
    }

    private function validar(array $data): array
    {
        $errors = [];

        if ($data['comunidad_id'] <= 0) {
            $errors['comunidad_id'] = 'Selecciona la comunidad de la ruta.';
        }

        if (!$data['codigo']) {
            $errors['codigo'] = 'Captura el codigo de la ruta.';
        } elseif (!preg_match('/^[A-Z0-9-]+$/', $data['codigo'])) {
            $errors['codigo'] = 'La ruta solo puede llevar letras, numeros y guiones.';
        }

        if (!$data['nombre']) {
            $errors['nombre'] = 'Captura el nombre de la ruta.';
        }

        return $errors;
    }

    private function validarCodigoUnico(string $codigo, int $rutaIdExcluir = 0): void
    {
        $stmt = $this->db->prepare('SELECT id FROM rutas WHERE codigo = :codigo AND id <> :id LIMIT 1');
        $stmt->execute([
            'codigo' => $codigo,
            'id' => $rutaIdExcluir,
        ]);

        if ($stmt->fetch()) {
            throw new RuntimeException('Ya existe una ruta con ese codigo.');
        }
    }

    private function limpiarCodigo(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $code = preg_replace('/[^A-Z0-9-]/', '', mb_strtoupper($value, 'UTF-8'));
        return $code === '' ? null : substr($code, 0, $maxLength);
    }

    private function sincronizarCodigoEnDomicilios(int $rutaId, string $codigo): void
    {
        $stmt = $this->db->prepare(
            "UPDATE domicilios d
             INNER JOIN usuarios_servicio u ON u.id = d.usuario_id
             SET d.ruta = :codigo
             WHERE u.ruta_id = :ruta_id"
        );
        $stmt->execute([
            'codigo' => $codigo,
            'ruta_id' => $rutaId,
        ]);
    }

    private function mayusculas(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strtoupper($value, 'UTF-8');
    }
}
