<?php
require_once __DIR__ . '/../Core/ReciboQr.php';

class Pagos
{
    private PDO $db;
    private string $qrSecret;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->qrSecret = (string) (getenv('MEXQUITIC_QR_SECRET') ?: 'mexquitic-agua-qr-2026-v1');
    }

    public function usuarios(string $termino = ''): array
    {
        $params = [];
        $where = 'WHERE u.activo = 1';

        if (trim($termino) !== '') {
            $where .= " AND (
                u.nombre LIKE :termino_u
                OR d.ruta LIKE :termino_r
                OR m.numero LIKE :termino_m
            )";
            $t = '%' . trim($termino) . '%';
            $params['termino_u'] = $t;
            $params['termino_r'] = $t;
            $params['termino_m'] = $t;
        }

        $stmt = $this->db->prepare(
            "SELECT
                u.id AS usuario_id,
                u.nombre AS usuario,
                u.whatsapp,
                MAX(d.ruta) AS ruta,
                MAX(m.numero) AS medidor
             FROM usuarios_servicio u
             LEFT JOIN domicilios d ON d.usuario_id = u.id AND d.activo = 1
             LEFT JOIN medidores m ON m.usuario_id = u.id
             $where
             GROUP BY u.id, u.nombre, u.whatsapp
             ORDER BY u.nombre ASC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function recibosPorUsuario(int $usuarioId): array
    {
        $data = $this->recibos($usuarioId, 1, 0);
        return $data['recibos'] ?? [];
    }

    public function recibos(int $usuarioId = 0, int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = (int) $perPage;
        $allowAll = $perPage <= 0;
        $params = [];
        $where = '';

        if ($usuarioId > 0) {
            $where = 'WHERE r.usuario_id = :usuario_id';
            $params['usuario_id'] = $usuarioId;
        }

        $stmt = $this->db->prepare(
            "SELECT
                r.id AS recibo_id,
                r.folio,
                r.total,
                r.subtotal,
                r.multas,
                r.cooperaciones,
                r.recargos,
                r.estado AS estado_recibo,
                r.recibo_entregado,
                r.fecha_entrega,
                r.created_at,
                r.imagen_path,
                l.fecha_captura AS lectura_fecha_captura,
                u.id AS usuario_id,
                u.nombre AS usuario,
                u.whatsapp,
                d.calle,
                d.numero AS numero_domicilio,
                d.colonia,
                d.ruta,
                m.numero AS medidor,
                p.nombre AS periodo,
                p.fecha_inicio,
                p.fecha_fin,
                p.fecha_vencimiento,
                COALESCE(pg.total_pagado, 0) AS total_pagado
             FROM recibos r
             INNER JOIN usuarios_servicio u ON u.id = r.usuario_id
             INNER JOIN domicilios d ON d.id = r.domicilio_id
             INNER JOIN medidores m ON m.id = r.medidor_id
             INNER JOIN periodos_bimestrales p ON p.id = r.periodo_id
             LEFT JOIN lecturas l ON l.id = r.lectura_id
             LEFT JOIN (
                SELECT recibo_id, SUM(monto) AS total_pagado
                FROM pagos
                GROUP BY recibo_id
             ) pg ON pg.recibo_id = r.id
             $where
             ORDER BY p.fecha_fin DESC, r.id DESC"
        );
        $stmt->execute($params);

        $recibos = array_map(function (array $row): array {
            $row['saldo'] = max((float) $row['total'] - (float) $row['total_pagado'], 0);
            $row['estado_pago'] = $this->estadoPago((float) $row['total'], (float) $row['total_pagado'], (string) $row['estado_recibo']);
            $row['recibo_entregado'] = (int) ($row['recibo_entregado'] ?? 0);
            return $row;
        }, $stmt->fetchAll());

        $total = count($recibos);
        if ($allowAll) {
            $effectivePerPage = $total > 0 ? $total : 1;
        } else {
            $effectivePerPage = max(1, min($perPage, 500));
        }

        $totalPages = $total > 0 ? (int) ceil($total / $effectivePerPage) : 1;
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $effectivePerPage;
        $pagedRecibos = $allowAll ? $recibos : array_slice($recibos, $offset, $effectivePerPage);

        return [
            'recibos' => $pagedRecibos,
            'pagination' => [
                'page' => $page,
                'per_page' => $allowAll ? 0 : $effectivePerPage,
                'effective_per_page' => $effectivePerPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? $offset + count($pagedRecibos) : 0,
            ],
        ];
    }

    public function obtenerRecibo(int $reciboId): array
    {
        if ($reciboId <= 0) {
            throw new RuntimeException('No se recibio el recibo a consultar.');
        }

        $stmt = $this->db->prepare(
            "SELECT
                r.id AS recibo_id,
                r.folio,
                r.total,
                r.subtotal,
                r.multas,
                r.cooperaciones,
                r.recargos,
                r.estado AS estado_recibo,
                r.recibo_entregado,
                r.fecha_entrega,
                r.created_at,
                r.imagen_path,
                l.fecha_captura AS lectura_fecha_captura,
                u.id AS usuario_id,
                u.nombre AS usuario,
                u.whatsapp,
                d.calle,
                d.numero AS numero_domicilio,
                d.colonia,
                d.ruta,
                m.numero AS medidor,
                p.nombre AS periodo,
                p.fecha_inicio,
                p.fecha_fin,
                p.fecha_vencimiento,
                COALESCE(pg.total_pagado, 0) AS total_pagado
             FROM recibos r
             INNER JOIN usuarios_servicio u ON u.id = r.usuario_id
             INNER JOIN domicilios d ON d.id = r.domicilio_id
             INNER JOIN medidores m ON m.id = r.medidor_id
             INNER JOIN periodos_bimestrales p ON p.id = r.periodo_id
             LEFT JOIN lecturas l ON l.id = r.lectura_id
             LEFT JOIN (
                SELECT recibo_id, SUM(monto) AS total_pagado
                FROM pagos
                GROUP BY recibo_id
             ) pg ON pg.recibo_id = r.id
             WHERE r.id = :recibo_id
             LIMIT 1"
        );
        $stmt->execute(['recibo_id' => $reciboId]);
        $recibo = $stmt->fetch();

        if (!$recibo) {
            throw new RuntimeException('No se encontro el recibo solicitado.');
        }

        $recibo['saldo'] = max((float) $recibo['total'] - (float) $recibo['total_pagado'], 0);
        $recibo['estado_pago'] = $this->estadoPago((float) $recibo['total'], (float) $recibo['total_pagado'], (string) $recibo['estado_recibo']);
        $recibo['recibo_entregado'] = (int) ($recibo['recibo_entregado'] ?? 0);
        $recibo['pagos'] = $this->listarPagos($reciboId);

        return $recibo;
    }

    public function obtenerReciboPorQr(string $qrToken): array
    {
        $dataQr = $this->decodificarQrRecibo($qrToken);
        return $this->obtenerReciboPorDatosQr($dataQr);
    }

    public function actualizarEntrega(int $reciboId, bool $entregado): array
    {
        if ($reciboId <= 0) {
            throw new RuntimeException('Selecciona un recibo valido para actualizar la entrega.');
        }

        $this->db->beginTransaction();
        try {
            $this->actualizarEntregaRecibo($reciboId, $entregado);
            $this->db->commit();
            return $this->obtenerRecibo($reciboId);
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function registrarPorQr(array $input): array
    {
        $qrToken = Request::cleanString($input['qr_token'] ?? null);
        if (!$qrToken) {
            throw new RuntimeException('Escanea un QR valido antes de registrar el pago.');
        }

        $recibo = $this->obtenerReciboPorQr($qrToken);
        $input['recibo_id'] = (int) ($recibo['recibo_id'] ?? 0);

        return $this->registrar($input);
    }

    public function registrar(array $input): array
    {
        $data = $this->normalizarPago($input);
        $errors = $this->validarPago($data);

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $recibo = $this->obtenerRecibo($data['recibo_id']);

        if ($recibo['estado_recibo'] === 'cancelado') {
            throw new RuntimeException('No se pueden registrar pagos sobre un recibo cancelado.');
        }

        if ((float) $recibo['saldo'] <= 0) {
            throw new RuntimeException('Este recibo ya no tiene saldo pendiente.');
        }

        if ($data['monto'] - (float) $recibo['saldo'] > 0.009) {
            throw new RuntimeException('El monto capturado excede el saldo pendiente del recibo.');
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO pagos
                    (recibo_id, monto, fecha_pago, metodo, referencia, capturado_por_id, observaciones)
                 VALUES
                    (:recibo_id, :monto, :fecha_pago, :metodo, :referencia, :capturado_por_id, :observaciones)"
            );
            $stmt->execute([
                'recibo_id' => $data['recibo_id'],
                'monto' => $data['monto'],
                'fecha_pago' => $data['fecha_pago'],
                'metodo' => $data['metodo'],
                'referencia' => $data['referencia'],
                'capturado_por_id' => $data['capturado_por_id'] > 0 ? $data['capturado_por_id'] : null,
                'observaciones' => $data['observaciones'],
            ]);

            $this->sincronizarEstadoRecibo($data['recibo_id']);
            if ($data['recibo_entregado'] !== null) {
                $this->actualizarEntregaRecibo($data['recibo_id'], (bool) $data['recibo_entregado']);
            }
            $this->db->commit();

            return $this->obtenerRecibo($data['recibo_id']);
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function eliminar(int $pagoId): array
    {
        if ($pagoId <= 0) {
            throw new RuntimeException('No se recibio el pago a eliminar.');
        }

        $stmt = $this->db->prepare('SELECT id, recibo_id FROM pagos WHERE id = :pago_id LIMIT 1');
        $stmt->execute(['pago_id' => $pagoId]);
        $pago = $stmt->fetch();

        if (!$pago) {
            throw new RuntimeException('No se encontro el pago solicitado.');
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('DELETE FROM pagos WHERE id = :pago_id');
            $stmt->execute(['pago_id' => $pagoId]);

            $this->sincronizarEstadoRecibo((int) $pago['recibo_id']);
            $this->db->commit();

            return $this->obtenerRecibo((int) $pago['recibo_id']);
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function listarPagos(int $reciboId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                p.id AS pago_id,
                p.monto,
                p.fecha_pago,
                p.metodo,
                p.referencia,
                p.observaciones
             FROM pagos p
             WHERE p.recibo_id = :recibo_id
             ORDER BY p.fecha_pago DESC, p.id DESC"
        );
        $stmt->execute(['recibo_id' => $reciboId]);

        return $stmt->fetchAll();
    }

    private function sincronizarEstadoRecibo(int $reciboId): void
    {
        $stmt = $this->db->prepare(
            "SELECT
                r.total,
                r.estado,
                COALESCE(SUM(p.monto), 0) AS total_pagado
             FROM recibos r
             LEFT JOIN pagos p ON p.recibo_id = r.id
             WHERE r.id = :recibo_id
             GROUP BY r.id, r.total, r.estado
             LIMIT 1"
        );
        $stmt->execute(['recibo_id' => $reciboId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('No se encontro el recibo para actualizar su estado.');
        }

        if ($row['estado'] === 'cancelado') {
            return;
        }

        $nuevoEstado = (float) $row['total_pagado'] >= (float) $row['total'] ? 'pagado' : 'generado';
        $stmt = $this->db->prepare('UPDATE recibos SET estado = :estado WHERE id = :recibo_id');
        $stmt->execute([
            'estado' => $nuevoEstado,
            'recibo_id' => $reciboId,
        ]);
    }

    private function normalizarPago(array $input): array
    {
        return [
            'recibo_id' => (int) ($input['recibo_id'] ?? 0),
            'monto' => $this->numero($input['monto'] ?? 0),
            'metodo' => $this->metodoValido(Request::cleanString($input['metodo'] ?? 'efectivo')),
            'referencia' => Request::cleanString($input['referencia'] ?? null),
            'observaciones' => Request::cleanString($input['observaciones'] ?? null),
            'fecha_pago' => $this->normalizarFechaPago(Request::cleanString($input['fecha_pago'] ?? null)),
            'capturado_por_id' => (int) ($input['_usuario_sistema_id'] ?? 0),
            'recibo_entregado' => array_key_exists('recibo_entregado', $input)
                ? $this->boolNullable($input['recibo_entregado'])
                : null,
        ];
    }

    private function validarPago(array $data): array
    {
        $errors = [];

        if ($data['recibo_id'] <= 0) {
            $errors['recibo_id'] = 'Selecciona un recibo para registrar el pago.';
        }

        if ($data['monto'] <= 0) {
            $errors['monto'] = 'Captura un monto de pago valido.';
        }

        if (!$data['fecha_pago']) {
            $errors['fecha_pago'] = 'Captura la fecha y hora del pago.';
        }

        if (!in_array($data['metodo'], ['efectivo', 'transferencia', 'spei', 'tarjeta', 'otro'], true)) {
            $errors['metodo'] = 'Selecciona un metodo de pago valido.';
        }

        if ($data['referencia'] && strlen($data['referencia']) > 120) {
            $errors['referencia'] = 'La referencia es demasiado larga.';
        }

        return $errors;
    }

    private function estadoPago(float $total, float $pagado, string $estadoRecibo): string
    {
        if ($estadoRecibo === 'cancelado') {
            return 'cancelado';
        }

        if ($estadoRecibo === 'pagado' || ($pagado >= $total && $total > 0)) {
            return 'pagado';
        }

        return 'pendiente';
    }

    private function actualizarEntregaRecibo(int $reciboId, bool $entregado): void
    {
        $stmt = $this->db->prepare(
            "UPDATE recibos
             SET recibo_entregado = :recibo_entregado,
                 fecha_entrega = CASE
                    WHEN :recibo_entregado_flag = 1 THEN COALESCE(fecha_entrega, NOW())
                    ELSE NULL
                 END
             WHERE id = :recibo_id"
        );
        $stmt->execute([
            'recibo_entregado' => $entregado ? 1 : 0,
            'recibo_entregado_flag' => $entregado ? 1 : 0,
            'recibo_id' => $reciboId,
        ]);
    }

    private function decodificarQrRecibo(string $token): array
    {
        return ReciboQr::parseToken($token, $this->qrSecret);
    }

    private function obtenerReciboPorDatosQr(array $dataQr): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                r.id AS recibo_id
             FROM recibos r
             INNER JOIN lecturas l ON l.id = r.lectura_id
             WHERE r.usuario_id = :usuario_id
               AND r.lectura_id = :lectura_id
               AND DATE_FORMAT(l.fecha_captura, '%Y-%m-%d %H:%i:%s') = :fecha_registro
             LIMIT 1"
        );
        $stmt->execute([
            'usuario_id' => (int) $dataQr['usuario_id'],
            'lectura_id' => (int) $dataQr['lectura_id'],
            'fecha_registro' => (string) $dataQr['fecha_registro'],
        ]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('El QR no corresponde a un recibo vigente en la base de datos.');
        }

        return $this->obtenerRecibo((int) $row['recibo_id']);
    }

    private function boolNullable($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $clean = mb_strtolower(trim((string) $value), 'UTF-8');
        if (in_array($clean, ['1', 'true', 'si', 'on', 'yes'], true)) {
            return 1;
        }

        if (in_array($clean, ['0', 'false', 'no', 'off'], true)) {
            return 0;
        }

        return null;
    }

    private function numero($value): float
    {
        return round((float) $value, 2);
    }

    private function metodoValido(?string $value): string
    {
        $metodo = mb_strtolower(trim((string) $value), 'UTF-8');
        if ($metodo === '' || $metodo === null) {
            return 'efectivo';
        }

        if ($metodo === 'transferencia') {
            return 'spei';
        }

        return $metodo;
    }

    private function normalizarFechaPago(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $timestamp = strtotime(str_replace('T', ' ', $value));
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
