<?php
require_once __DIR__ . '/../Core/ReciboQr.php';
require_once __DIR__ . '/CobroAgua.php';

class Recibos
{
    private PDO $db;
    private string $rootDir;
    private string $templatePath;
    private string $coordsPath;
    private string $outputDir;
    private string $qrSecret;
    private ?CobroAgua $cobroAgua = null;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->rootDir = dirname(__DIR__, 2);
        $this->templatePath = $this->rootDir . '/recibos/plantillas/recibo-opcion2-vacio.png';
        $this->coordsPath = $this->rootDir . '/recibos/plantillas/opcion2-coordenadas.json';
        $this->outputDir = $this->rootDir . '/recibos/generados';
        $this->qrSecret = (string) (getenv('MEXQUITIC_QR_SECRET') ?: 'mexquitic-agua-qr-2026-v1');
    }

    public function listarLecturas(string $termino = '', string $estadoCobro = '', int $periodoId = 0, int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = (int) $perPage;
        $allowAll = $perPage <= 0;
        $params = [];
        $conditions = [];

        if (trim($termino) !== '') {
            $conditions[] = "(u.nombre LIKE :termino
                OR d.ruta LIKE :termino
                OR m.numero LIKE :termino
                OR p.nombre LIKE :termino)";
            $params['termino'] = '%' . trim($termino) . '%';
        }

        if ($periodoId > 0) {
            $conditions[] = 'p.id = :periodo_id';
            $params['periodo_id'] = $periodoId;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $stmt = $this->db->prepare(
            "SELECT
                l.id AS lectura_id,
                l.lectura_anterior,
                l.lectura_actual,
                l.consumo_m3,
                l.fecha_captura,
                l.latitud,
                l.longitud,
                l.observaciones,
                l.foto_medicion_path,
                u.id AS usuario_id,
                u.nombre AS usuario,
                u.whatsapp,
                d.id AS domicilio_id,
                d.calle,
                d.numero AS numero_domicilio,
                d.colonia,
                d.ruta,
                c.nombre AS comunidad,
                m.id AS medidor_id,
                m.numero AS medidor,
                p.id AS periodo_id,
                p.nombre AS periodo,
                p.fecha_inicio,
                p.fecha_fin,
                p.fecha_vencimiento,
                r.id AS recibo_id,
                r.folio,
                r.total,
                r.estado AS estado_recibo,
                r.recibo_entregado,
                r.fecha_entrega,
                r.imagen_path,
                COALESCE(pg.total_pagado, 0) AS total_pagado
             FROM lecturas l
             JOIN medidores m ON m.id = l.medidor_id
             JOIN usuarios_servicio u ON u.id = m.usuario_id
             JOIN domicilios d ON d.id = m.domicilio_id
             JOIN comunidades c ON c.id = d.comunidad_id
             JOIN periodos_bimestrales p ON p.id = l.periodo_id
             LEFT JOIN recibos r ON r.lectura_id = l.id
                OR (r.lectura_id IS NULL AND r.medidor_id = l.medidor_id AND r.periodo_id = l.periodo_id)
             LEFT JOIN (
                SELECT recibo_id, SUM(monto) AS total_pagado
                FROM pagos
                GROUP BY recibo_id
             ) pg ON pg.recibo_id = r.id
             $where
             ORDER BY l.fecha_captura DESC, l.id DESC"
        );
        $stmt->execute($params);

        $estadoCobro = $this->normalizarFiltroEstadoCobro($estadoCobro);
        $rows = array_map(function (array $row): array {
            $row = $this->enriquecerEstadoCobro($row);
            $row['imagen_disponible'] = $this->imagenReciboDisponible($row);
            if (!$row['imagen_disponible']) {
                $row['imagen_path'] = '';
            }
            return $row;
        }, $stmt->fetchAll());

        if ($estadoCobro !== '') {
            $rows = array_values(array_filter($rows, function (array $row) use ($estadoCobro): bool {
            return ($row['estado_cobro'] ?? '') === $estadoCobro;
            }));
        }

        $summary = [
            'adeudo' => 0,
            'pendiente' => 0,
            'parcial' => 0,
            'pagado' => 0,
            'sin_recibo' => 0,
        ];

        foreach ($rows as $row) {
            $estado = (string) ($row['estado_cobro'] ?? 'sin_recibo');
            if (!array_key_exists($estado, $summary)) {
                $summary[$estado] = 0;
            }
            $summary[$estado]++;
        }

        $total = count($rows);
        if ($allowAll) {
            $effectivePerPage = $total > 0 ? $total : 1;
        } else {
            $effectivePerPage = max(1, min($perPage, 500));
        }

        $totalPages = $total > 0 ? (int) ceil($total / $effectivePerPage) : 1;
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $effectivePerPage;
        $lecturas = $allowAll ? $rows : array_slice($rows, $offset, $effectivePerPage);

        return [
            'lecturas' => $lecturas,
            'summary' => $summary,
            'pagination' => [
                'page' => $page,
                'per_page' => $allowAll ? 0 : $effectivePerPage,
                'effective_per_page' => $effectivePerPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? $offset + count($lecturas) : 0,
            ],
        ];
    }

    public function obtenerLectura(int $lecturaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                l.id AS lectura_id,
                l.medidor_id,
                l.periodo_id,
                l.lectura_anterior,
                l.lectura_actual,
                l.consumo_m3,
                l.fecha_captura,
                l.latitud,
                l.longitud,
                l.observaciones,
                u.id AS usuario_id,
                u.nombre AS usuario,
                u.whatsapp,
                d.id AS domicilio_id,
                d.calle,
                d.numero AS numero_domicilio,
                d.colonia,
                d.ruta,
                c.nombre AS comunidad,
                m.numero AS medidor,
                p.nombre AS periodo,
                p.fecha_inicio,
                p.fecha_fin,
                p.fecha_vencimiento,
                r.id AS recibo_id,
                r.folio,
                r.subtotal,
                r.multas,
                r.cooperaciones,
                r.recargos,
                r.total,
                r.tarifa_nombre,
                r.tarifa_parametros_json,
                r.recibo_entregado,
                r.fecha_entrega,
                r.imagen_path
             FROM lecturas l
             JOIN medidores m ON m.id = l.medidor_id
             JOIN usuarios_servicio u ON u.id = m.usuario_id
             JOIN domicilios d ON d.id = m.domicilio_id
             JOIN comunidades c ON c.id = d.comunidad_id
             JOIN periodos_bimestrales p ON p.id = l.periodo_id
             LEFT JOIN recibos r ON r.lectura_id = l.id
                OR (r.lectura_id IS NULL AND r.medidor_id = l.medidor_id AND r.periodo_id = l.periodo_id)
             WHERE l.id = :lectura_id
             LIMIT 1"
        );
        $stmt->execute(['lectura_id' => $lecturaId]);
        $lectura = $stmt->fetch();

        if (!$lectura) {
            throw new RuntimeException('No se encontro la lectura solicitada.');
        }

        $lectura['imagen_disponible'] = $this->imagenReciboDisponible($lectura);
        if (!$lectura['imagen_disponible']) {
            $lectura['imagen_path'] = '';
        }

        return $lectura;
    }

    public function generar(array $input): array
    {
        $data = $this->normalizarRecibo($input);
        $errors = $this->validarRecibo($data);

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $lectura = $this->obtenerLectura($data['lectura_id']);
        $cobro = $this->calcularCobroRecibo($lectura, $data);
        $subtotal = $cobro['subtotal'];
        $total = $cobro['total'];

        $this->db->beginTransaction();

        try {
            $recibo = $this->guardarRecibo($lectura, $data, $subtotal, $total, $cobro['parametros']);
            $qrToken = $this->generarTokenQr($lectura);
            $imagenPath = $this->generarImagen($lectura, $recibo, $data, $subtotal, $total, $qrToken, $cobro);

            $stmt = $this->db->prepare('UPDATE recibos SET imagen_path = :imagen_path WHERE id = :recibo_id');
            $stmt->execute([
                'imagen_path' => $imagenPath,
                'recibo_id' => $recibo['recibo_id'],
            ]);

            $this->guardarDetalles($recibo['recibo_id'], $lectura, $data, $cobro);
            $this->db->commit();

            return [
                'recibo_id' => $recibo['recibo_id'],
                'folio' => $recibo['folio'],
                'imagen_path' => $imagenPath,
                'total' => $total,
                'qr_token' => $qrToken,
                'impresion' => $this->crearPayloadImpresion($lectura, $recibo, $data, $subtotal, $total, $qrToken, $cobro),
            ];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function previsualizarPeriodo(array $input): array
    {
        $periodoId = (int) ($input['periodo_id'] ?? 0);
        $data = $this->normalizarRecibo($input);
        $errors = $this->validarRecibo($data, false);

        if ($periodoId <= 0) {
            $errors['periodo_id'] = 'Selecciona un periodo para preparar la impresion.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $lecturas = $this->obtenerLecturasPorPeriodo($periodoId);

        if (empty($lecturas)) {
            return [
                'periodo_id' => $periodoId,
                'periodo' => '',
                'total' => 0,
                'insertados' => 0,
                'actualizados' => 0,
                'omitidos' => 0,
                'recibos' => [],
                'errores' => [],
            ];
        }

        $insertados = 0;
        $actualizados = 0;
        $omitidos = 0;
        $errores = [];
        $recibos = [];

        foreach ($lecturas as $lectura) {
            $payload = $data;
            $payload['lectura_id'] = (int) ($lectura['lectura_id'] ?? 0);
            $teniaRecibo = !empty($lectura['recibo_id']);

            try {
                $resultado = $this->generar($payload);
                $lecturaActualizada = $this->obtenerLectura((int) $lectura['lectura_id']);
                $lecturaActualizada['recibo_id'] = $resultado['recibo_id'];
                $lecturaActualizada['folio'] = $resultado['folio'];
                $lecturaActualizada['imagen_path'] = $resultado['imagen_path'];
                $lecturaActualizada['total'] = $resultado['total'];
                $recibos[] = $this->formatearReciboPreview(
                    $lecturaActualizada,
                    $resultado['impresion'] ?? null
                );

                if ($teniaRecibo) {
                    $actualizados++;
                } else {
                    $insertados++;
                }
            } catch (Throwable $exception) {
                $omitidos++;
                $errores[] = [
                    'lectura_id' => (int) ($lectura['lectura_id'] ?? 0),
                    'usuario' => (string) ($lectura['usuario'] ?? 'Sin usuario'),
                    'detalle' => $exception->getMessage(),
                ];
            }
        }

        usort($recibos, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['usuario'] ?? ''), (string) ($b['usuario'] ?? ''));
        });

        return [
            'periodo_id' => $periodoId,
            'periodo' => (string) ($lecturas[0]['periodo'] ?? ''),
            'total' => count($recibos),
            'insertados' => $insertados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
            'recibos' => $recibos,
            'errores' => $errores,
        ];
    }

    public function prepararImpresion(array $input): array
    {
        $lecturaId = (int) ($input['lectura_id'] ?? 0);

        if ($lecturaId <= 0) {
            throw new InvalidArgumentException(json_encode([
                'lectura_id' => 'Selecciona una lectura para preparar la impresion.',
            ], JSON_UNESCAPED_UNICODE));
        }

        $lectura = $this->asegurarImagenRecibo($lecturaId);
        $data = $this->resolverConfiguracionImpresion($lectura, $input);
        $cobro = $this->calcularCobroRecibo($lectura, $data, $this->resolverTarifaSnapshotRecibo($lectura));
        $subtotal = $cobro['subtotal'];
        $total = $cobro['total'];
        $recibo = [
            'recibo_id' => (int) ($lectura['recibo_id'] ?? 0),
            'folio' => (string) (($lectura['folio'] ?? '') ?: $this->generarFolio()),
        ];
        $payload = $this->crearPayloadImpresion(
            $lectura,
            $recibo,
            $data,
            $subtotal,
            $total,
            $this->generarTokenQr($lectura),
            $cobro
        );

        return $this->formatearReciboPreview([
            'lectura_id' => $lectura['lectura_id'],
            'recibo_id' => $recibo['recibo_id'],
            'folio' => $recibo['folio'],
            'usuario' => $lectura['usuario'],
            'medidor' => $lectura['medidor'],
            'ruta' => $lectura['ruta'],
            'periodo' => $lectura['periodo'],
            'total' => $total,
            'imagen_path' => $lectura['imagen_path'] ?? '',
        ], $payload);
    }

    public function obtenerImagen(array $input): array
    {
        $lecturaId = (int) ($input['lectura_id'] ?? 0);

        if ($lecturaId <= 0) {
            throw new InvalidArgumentException(json_encode([
                'lectura_id' => 'Selecciona una lectura para localizar el recibo.',
            ], JSON_UNESCAPED_UNICODE));
        }

        $lectura = $this->asegurarImagenRecibo($lecturaId);

        return [
            'lectura_id' => (int) ($lectura['lectura_id'] ?? 0),
            'recibo_id' => (int) ($lectura['recibo_id'] ?? 0),
            'folio' => (string) ($lectura['folio'] ?? ''),
            'usuario' => (string) ($lectura['usuario'] ?? ''),
            'imagen_path' => (string) ($lectura['imagen_path'] ?? ''),
        ];
    }

    public function enviarWhatsApp(int $reciboId, WhatsApp $whatsApp): array
    {
        if ($reciboId <= 0) {
            throw new InvalidArgumentException(json_encode([
                'recibo_id' => 'Selecciona un recibo para enviar por WhatsApp.',
            ], JSON_UNESCAPED_UNICODE));
        }

        $recibo = $this->obtenerReciboParaEnvio($reciboId);

        if (empty($recibo['imagen_path'])) {
            throw new RuntimeException('Primero genera la imagen del recibo antes de enviarla por WhatsApp.');
        }

        $relativePath = str_replace('\\', '/', (string) $recibo['imagen_path']);
        $absolutePath = $this->rootDir . '/' . ltrim($relativePath, '/');

        if (!is_file($absolutePath)) {
            throw new RuntimeException('No se encontro la imagen generada del recibo.');
        }

        $caption = implode("\n", array_filter([
            'Recibo de agua ' . ($recibo['folio'] ?: ''),
            'Usuario: ' . ($recibo['usuario'] ?: 'Sin usuario'),
            'Periodo: ' . ($recibo['periodo'] ?: 'Sin periodo'),
            'Total a pagar: ' . $this->moneda((float) $recibo['total']),
            'Favor de conservar este recibo para cualquier aclaracion.',
        ]));

        $envio = $whatsApp->enviarImagen((string) $recibo['whatsapp'], $absolutePath, $caption);

        return [
            'recibo_id' => (int) $recibo['recibo_id'],
            'folio' => $recibo['folio'],
            'usuario' => $recibo['usuario'],
            'whatsapp' => $envio['to'],
            'imagen_path' => $relativePath,
            'ultramsg' => $envio['response'],
        ];
    }

    public function listarNotificaciones(array $input): array
    {
        $tipoMensaje = $this->normalizarTipoMensaje(Request::cleanString($input['tipo_mensaje'] ?? 'recordatorio'));
        $estadoObjetivo = $this->resolverEstadoObjetivo(
            $tipoMensaje,
            $this->normalizarEstadoObjetivo(Request::cleanString($input['estado_cobro'] ?? 'adeudo'))
        );
        $limit = max(1, min(200, (int) ($input['limit'] ?? 40)));
        $periodoId = (int) ($input['periodo_id'] ?? 0);

        $stmt = $this->db->prepare(
            "SELECT
                r.id AS recibo_id,
                r.folio,
                r.total,
                r.estado AS estado_recibo,
                r.recibo_entregado,
                r.fecha_entrega,
                r.imagen_path,
                r.periodo_id,
                u.id AS usuario_id,
                u.nombre AS usuario,
                u.telefono,
                u.whatsapp,
                d.ruta,
                m.numero AS medidor,
                p.nombre AS periodo,
                p.fecha_vencimiento,
                COALESCE(pg.total_pagado, 0) AS total_pagado
             FROM recibos r
             INNER JOIN usuarios_servicio u ON u.id = r.usuario_id
             INNER JOIN domicilios d ON d.id = r.domicilio_id
             INNER JOIN medidores m ON m.id = r.medidor_id
             INNER JOIN periodos_bimestrales p ON p.id = r.periodo_id
             LEFT JOIN (
                SELECT recibo_id, SUM(monto) AS total_pagado
                FROM pagos
                GROUP BY recibo_id
             ) pg ON pg.recibo_id = r.id
             WHERE u.activo = 1
             ORDER BY p.fecha_vencimiento ASC, r.id DESC
             LIMIT 400"
        );
        $stmt->execute();

        $recibos = [];
        foreach ($stmt->fetchAll() as $row) {
            $row = $this->enriquecerEstadoCobro($row);

            if ($periodoId > 0 && (int) ($row['periodo_id'] ?? 0) !== $periodoId) {
                continue;
            }

            if ($estadoObjetivo !== 'todos' && $row['estado_cobro'] !== $estadoObjetivo) {
                continue;
            }

            $whatsApp = trim((string) ($row['whatsapp'] ?? ''));
            $telefono = trim((string) ($row['telefono'] ?? ''));
            $destino = $whatsApp !== '' ? $whatsApp : $telefono;
            $puedeEnviar = $whatsApp !== '';

            $row['telefono'] = $telefono;
            $row['whatsapp'] = $whatsApp;
            $row['destino_contacto'] = $destino;
            $row['puede_enviar'] = $puedeEnviar;
            $row['detalle_preparacion'] = $puedeEnviar
                ? 'Listo para enviar.'
                : ($telefono !== ''
                    ? 'Tiene telefono alterno, pero falta confirmar el WhatsApp.'
                    : 'Falta capturar WhatsApp para este usuario.');

            $row['mensaje_sugerido'] = $this->construirMensajeNotificacion($row, $tipoMensaje);
            $recibos[] = $row;

            if (count($recibos) >= $limit) {
                break;
            }
        }

        return $recibos;
    }

    public function notificarWhatsApp(int $reciboId, string $tipoMensaje, WhatsApp $whatsApp): array
    {
        if ($reciboId <= 0) {
            throw new InvalidArgumentException(json_encode([
                'recibo_id' => 'Selecciona un recibo para notificar.',
            ], JSON_UNESCAPED_UNICODE));
        }

        $tipoMensaje = $this->normalizarTipoMensaje($tipoMensaje);
        $recibo = $this->obtenerReciboParaEnvio($reciboId);
        $recibo = $this->enriquecerEstadoCobro($recibo);
        $mensaje = $this->construirMensajeNotificacion($recibo, $tipoMensaje);
        $envio = $whatsApp->enviarTexto((string) $recibo['whatsapp'], $mensaje);

        return [
            'recibo_id' => (int) $recibo['recibo_id'],
            'folio' => $recibo['folio'],
            'usuario' => $recibo['usuario'],
            'whatsapp' => $envio['to'],
            'estado_cobro' => $recibo['estado_cobro'],
            'mensaje' => $mensaje,
            'ultramsg' => $envio['response'],
        ];
    }

    private function obtenerReciboParaEnvio(int $reciboId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                r.id AS recibo_id,
                r.folio,
                r.total,
                r.estado AS estado_recibo,
                r.recibo_entregado,
                r.fecha_entrega,
                r.imagen_path,
                u.nombre AS usuario,
                u.whatsapp,
                d.ruta,
                m.numero AS medidor,
                p.nombre AS periodo,
                p.fecha_vencimiento,
                COALESCE(pg.total_pagado, 0) AS total_pagado
             FROM recibos r
             JOIN usuarios_servicio u ON u.id = r.usuario_id
             JOIN domicilios d ON d.id = r.domicilio_id
             JOIN medidores m ON m.id = r.medidor_id
             LEFT JOIN periodos_bimestrales p ON p.id = r.periodo_id
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

        return $this->enriquecerEstadoCobro($recibo);
    }

    private function enriquecerEstadoCobro(array $row): array
    {
        $total = (float) ($row['total'] ?? 0);
        $pagado = (float) ($row['total_pagado'] ?? 0);
        $estadoRecibo = (string) ($row['estado_recibo'] ?? '');
        $saldo = max($total - $pagado, 0);
        $estadoPago = $this->estadoPago($total, $pagado, $estadoRecibo);

        $row['total_pagado'] = $pagado;
        $row['saldo'] = $saldo;
        $row['estado_pago'] = $estadoPago;
        $row['estado_cobro'] = $this->estadoCobro($estadoPago, (string) ($row['fecha_vencimiento'] ?? ''));
        $row['recibo_entregado'] = (int) ($row['recibo_entregado'] ?? 0);

        return $row;
    }

    private function estadoPago(float $total, float $pagado, string $estadoRecibo): string
    {
        if ($estadoRecibo === '' || $estadoRecibo === null) {
            return 'sin_recibo';
        }

        if ($estadoRecibo === 'cancelado') {
            return 'cancelado';
        }

        if ($estadoRecibo === 'pagado' || ($pagado >= $total && $total > 0)) {
            return 'pagado';
        }

        return 'pendiente';
    }

    private function estadoCobro(string $estadoPago, string $fechaVencimiento): string
    {
        if ($estadoPago === 'sin_recibo' || $estadoPago === 'cancelado' || $estadoPago === 'pagado') {
            return $estadoPago;
        }

        if ($this->estaVencido($fechaVencimiento)) {
            return 'adeudo';
        }

        return 'pendiente';
    }

    private function estaVencido(string $fechaVencimiento): bool
    {
        if (!$this->fechaValida($fechaVencimiento)) {
            return false;
        }

        return $fechaVencimiento < date('Y-m-d');
    }

    private function normalizarEstadoObjetivo(?string $estado): string
    {
        $estado = mb_strtolower(trim((string) $estado), 'UTF-8');
        $permitidos = ['todos', 'adeudo', 'pendiente', 'parcial', 'pagado'];

        return in_array($estado, $permitidos, true) ? $estado : 'adeudo';
    }

    private function normalizarFiltroEstadoCobro(?string $estado): string
    {
        $estado = mb_strtolower(trim((string) $estado), 'UTF-8');
        $permitidos = ['', 'adeudo', 'pendiente', 'parcial', 'pagado', 'sin_recibo', 'cancelado'];

        return in_array($estado, $permitidos, true) ? $estado : '';
    }

    private function normalizarTipoMensaje(?string $tipo): string
    {
        $tipo = mb_strtolower(trim((string) $tipo), 'UTF-8');
        $permitidos = ['recordatorio', 'adeudo', 'agradecimiento', 'aviso'];

        return in_array($tipo, $permitidos, true) ? $tipo : 'recordatorio';
    }

    private function resolverEstadoObjetivo(string $tipoMensaje, string $estadoObjetivo): string
    {
        $permitidos = [
            'recordatorio' => ['pendiente', 'adeudo', 'todos'],
            'adeudo' => ['adeudo'],
            'agradecimiento' => ['pagado'],
            'aviso' => ['todos', 'pendiente', 'adeudo', 'pagado'],
        ];

        $opciones = $permitidos[$tipoMensaje] ?? $permitidos['recordatorio'];

        return in_array($estadoObjetivo, $opciones, true) ? $estadoObjetivo : $opciones[0];
    }

    private function construirMensajeNotificacion(array $recibo, string $tipoMensaje): string
    {
        $usuario = trim((string) ($recibo['usuario'] ?? 'usuario'));
        $folio = trim((string) ($recibo['folio'] ?? 'sin folio'));
        $periodo = trim((string) ($recibo['periodo'] ?? 'sin periodo'));
        $saldo = $this->moneda((float) ($recibo['saldo'] ?? 0));
        $total = $this->moneda((float) ($recibo['total'] ?? 0));
        $fechaVencimiento = $this->fechaValida((string) ($recibo['fecha_vencimiento'] ?? ''))
            ? date('d/m/Y', strtotime((string) $recibo['fecha_vencimiento']))
            : 'sin fecha';

        switch ($tipoMensaje) {
            case 'adeudo':
                return "Hola {$usuario}, te informamos que tu recibo {$folio} del periodo {$periodo} presenta adeudo por {$saldo}. La fecha limite fue {$fechaVencimiento}. Favor de ponerte al corriente con el sistema de agua de Mexquitic.";
            case 'agradecimiento':
                return "Hola {$usuario}, registramos tu pago del recibo {$folio} correspondiente al periodo {$periodo}. Gracias por mantenerte al corriente con el sistema de agua de Mexquitic.";
            case 'aviso':
                return "Hola {$usuario}, este es un aviso del sistema de agua de Mexquitic sobre tu recibo {$folio} del periodo {$periodo}. Total del recibo: {$total}. Saldo actual: {$saldo}.";
            case 'recordatorio':
            default:
                return "Hola {$usuario}, te recordamos que tu recibo {$folio} del periodo {$periodo} sigue pendiente por {$saldo}. Fecha limite de pago: {$fechaVencimiento}. Gracias por apoyar al sistema de agua de Mexquitic.";
        }
    }

    private function normalizarRecibo(array $input): array
    {
        $parametros = $this->obtenerCobroAgua()->parametros();

        return [
            'lectura_id' => (int) ($input['lectura_id'] ?? 0),
            'precio_m3' => $this->numero($input['precio_m3'] ?? 0),
            'cooperaciones' => $this->numero($input['cooperaciones'] ?? $parametros['cooperacion_default']),
            'multas' => $this->numero($input['multas'] ?? $parametros['multa_default']),
            'recargos' => $this->numero($input['recargos'] ?? $parametros['recargo_default']),
            'fecha_limite_pago' => Request::cleanString($input['fecha_limite_pago'] ?? null),
            'metodo_pago_caja' => Request::cleanString($input['metodo_pago_caja'] ?? 'Caja de cobro del sistema de agua'),
            'referencia_pago' => Request::cleanString($input['referencia_pago'] ?? 'Presentar este recibo al realizar el pago'),
        ];
    }

    private function validarRecibo(array $data, bool $requiereLectura = true): array
    {
        $errors = [];

        if ($requiereLectura && $data['lectura_id'] <= 0) {
            $errors['lectura_id'] = 'Selecciona una lectura para generar el recibo.';
        }

        foreach (['cooperaciones', 'multas', 'recargos'] as $field) {
            if ($data[$field] < 0) {
                $errors[$field] = 'El importe no puede ser negativo.';
            }
        }

        if (!$data['fecha_limite_pago'] || !$this->fechaValida($data['fecha_limite_pago'])) {
            $errors['fecha_limite_pago'] = 'Captura una fecha limite de pago valida.';
        }

        return $errors;
    }

    private function obtenerLecturasPorPeriodo(int $periodoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                l.id AS lectura_id,
                l.lectura_anterior,
                l.lectura_actual,
                l.consumo_m3,
                l.fecha_captura,
                l.latitud,
                l.longitud,
                u.id AS usuario_id,
                u.nombre AS usuario,
                u.whatsapp,
                d.id AS domicilio_id,
                d.ruta,
                m.id AS medidor_id,
                m.numero AS medidor,
                p.id AS periodo_id,
                p.nombre AS periodo,
                p.fecha_inicio,
                p.fecha_fin,
                p.fecha_vencimiento,
                r.id AS recibo_id,
                r.folio,
                r.imagen_path
             FROM lecturas l
             JOIN medidores m ON m.id = l.medidor_id
             JOIN usuarios_servicio u ON u.id = m.usuario_id
             JOIN domicilios d ON d.id = m.domicilio_id
             JOIN periodos_bimestrales p ON p.id = l.periodo_id
             LEFT JOIN recibos r ON r.lectura_id = l.id
                OR (r.lectura_id IS NULL AND r.medidor_id = l.medidor_id AND r.periodo_id = l.periodo_id)
             WHERE p.id = :periodo_id
             ORDER BY d.ruta ASC, u.nombre ASC, l.id ASC"
        );
        $stmt->execute(['periodo_id' => $periodoId]);

        return $stmt->fetchAll() ?: [];
    }

    private function formatearReciboPreview(array $lectura, ?array $impresion = null): array
    {
        return [
            'lectura_id' => (int) ($lectura['lectura_id'] ?? 0),
            'recibo_id' => (int) ($lectura['recibo_id'] ?? 0),
            'folio' => (string) ($lectura['folio'] ?? ''),
            'usuario' => (string) ($lectura['usuario'] ?? ''),
            'medidor' => (string) ($lectura['medidor'] ?? ''),
            'ruta' => (string) ($lectura['ruta'] ?? ''),
            'periodo' => (string) ($lectura['periodo'] ?? ''),
            'total' => (float) ($lectura['total'] ?? 0),
            'imagen_path' => (string) ($lectura['imagen_path'] ?? ''),
            'impresion' => $impresion,
        ];
    }

    private function guardarRecibo(array $lectura, array $data, float $subtotal, float $total, array $tarifaParametros): array
    {
        $folio = $lectura['folio'] ?: $this->generarFolio();
        $tarifaNombre = (string) ($tarifaParametros['nombre'] ?? 'DOMESTICA');
        $tarifaJson = json_encode($tarifaParametros, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($lectura['recibo_id']) {
            $stmt = $this->db->prepare(
                "UPDATE recibos
                 SET lectura_id = :lectura_id,
                     consumo_m3 = :consumo_m3,
                     subtotal = :subtotal,
                     multas = :multas,
                     cooperaciones = :cooperaciones,
                     recargos = :recargos,
                     total = :total,
                     tarifa_nombre = :tarifa_nombre,
                     tarifa_parametros_json = :tarifa_parametros_json
                 WHERE id = :recibo_id"
            );
            $stmt->execute([
                'lectura_id' => $lectura['lectura_id'],
                'consumo_m3' => $lectura['consumo_m3'],
                'subtotal' => $subtotal,
                'multas' => $data['multas'],
                'cooperaciones' => $data['cooperaciones'],
                'recargos' => $data['recargos'],
                'total' => $total,
                'tarifa_nombre' => $tarifaNombre,
                'tarifa_parametros_json' => $tarifaJson,
                'recibo_id' => $lectura['recibo_id'],
            ]);

            return [
                'recibo_id' => (int) $lectura['recibo_id'],
                'folio' => $folio,
            ];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO recibos
                (folio, usuario_id, domicilio_id, medidor_id, periodo_id, lectura_id,
                 consumo_m3, subtotal, multas, cooperaciones, recargos, total, tarifa_nombre, tarifa_parametros_json, estado)
             VALUES
                (:folio, :usuario_id, :domicilio_id, :medidor_id, :periodo_id, :lectura_id,
                 :consumo_m3, :subtotal, :multas, :cooperaciones, :recargos, :total, :tarifa_nombre, :tarifa_parametros_json, 'generado')"
        );
        $stmt->execute([
            'folio' => $folio,
            'usuario_id' => $lectura['usuario_id'],
            'domicilio_id' => $lectura['domicilio_id'],
            'medidor_id' => $lectura['medidor_id'],
            'periodo_id' => $lectura['periodo_id'],
            'lectura_id' => $lectura['lectura_id'],
            'consumo_m3' => $lectura['consumo_m3'],
            'subtotal' => $subtotal,
            'multas' => $data['multas'],
            'cooperaciones' => $data['cooperaciones'],
            'recargos' => $data['recargos'],
            'total' => $total,
            'tarifa_nombre' => $tarifaNombre,
            'tarifa_parametros_json' => $tarifaJson,
        ]);

        return [
            'recibo_id' => (int) $this->db->lastInsertId(),
            'folio' => $folio,
        ];
    }

    private function guardarDetalles(int $reciboId, array $lectura, array $data, array $cobro): void
    {
        $stmt = $this->db->prepare('DELETE FROM recibo_detalles WHERE recibo_id = :recibo_id');
        $stmt->execute(['recibo_id' => $reciboId]);

        $detalles = [];
        foreach ((array) ($cobro['detalle'] ?? []) as $detalle) {
            $detalles[] = [
                (string) ($detalle['descripcion'] ?? 'Consumo de agua'),
                (float) ($detalle['cantidad'] ?? 0),
                (float) ($detalle['precio_unitario'] ?? 0),
            ];
        }

        if ($data['cooperaciones'] > 0) {
            $detalles[] = ['Cooperaciones', 1, $data['cooperaciones']];
        }
        if ($data['multas'] > 0) {
            $detalles[] = ['Multas', 1, $data['multas']];
        }
        if ($data['recargos'] > 0) {
            $detalles[] = ['Recargos', 1, $data['recargos']];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO recibo_detalles
                (recibo_id, concepto_id, descripcion, cantidad, precio_unitario)
             VALUES
                (:recibo_id, NULL, :descripcion, :cantidad, :precio_unitario)"
        );

        foreach ($detalles as $detalle) {
            $stmt->execute([
                'recibo_id' => $reciboId,
                'descripcion' => $detalle[0],
                'cantidad' => $detalle[1],
                'precio_unitario' => $detalle[2],
            ]);
        }
    }

    private function generarImagen(array $lectura, array $recibo, array $data, float $subtotal, float $total, string $qrToken, array $cobro): string
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('La extension GD de PHP no esta habilitada.');
        }

        if (!is_file($this->templatePath) || !is_file($this->coordsPath)) {
            throw new RuntimeException('No se encontro la plantilla del recibo.');
        }

        if (!is_dir($this->outputDir) && !mkdir($this->outputDir, 0775, true) && !is_dir($this->outputDir)) {
            throw new RuntimeException('No se pudo crear la carpeta de recibos generados.');
        }

        $coords = json_decode(file_get_contents($this->coordsPath), true);
        $fields = $coords['fields'] ?? [];
        $image = imagecreatefrompng($this->templatePath);

        if (!$image) {
            throw new RuntimeException('No se pudo abrir la plantilla del recibo.');
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $font = $this->obtenerFuente();
        $valores = $this->construirValoresRecibo($lectura, $recibo, $data, $subtotal, $total, $cobro);

        foreach ($valores as $field => $value) {
            if (isset($fields[$field])) {
                $this->dibujarTexto($image, $font, $fields[$field], (string) $value);
            }
        }

        $principalQr = $this->dibujarQr($image, (array) ($coords['qr']['principal'] ?? []), $qrToken);
        $talonQr = $this->dibujarQr($image, (array) ($coords['qr']['talon'] ?? []), $qrToken);

        if (!$principalQr && !$talonQr) {
            throw new RuntimeException('No se pudo generar el QR del recibo. Verifica la conexion a internet e intenta de nuevo.');
        }

        $filename = 'recibo_' . $recibo['recibo_id'] . '.png';
        $destination = $this->outputDir . '/' . $filename;
        $saved = imagepng($image, $destination);
        imagedestroy($image);

        if (!$saved || !is_file($destination) || filesize($destination) <= 0) {
            throw new RuntimeException('No se pudo guardar la imagen generada del recibo.');
        }

        return 'recibos/generados/' . $filename;
    }

    private function asegurarImagenRecibo(int $lecturaId): array
    {
        $lectura = $this->obtenerLectura($lecturaId);

        if ($this->imagenReciboDisponible($lectura)) {
            return $lectura;
        }

        if (empty($lectura['recibo_id'])) {
            throw new RuntimeException('Primero genera el recibo antes de intentar verlo o imprimirlo.');
        }

        $data = $this->resolverConfiguracionImpresion($lectura, [
            'lectura_id' => $lecturaId,
            'cooperaciones' => (float) ($lectura['cooperaciones'] ?? 0),
            'multas' => (float) ($lectura['multas'] ?? 0),
            'recargos' => (float) ($lectura['recargos'] ?? 0),
            'fecha_limite_pago' => (string) ($lectura['fecha_vencimiento'] ?? ''),
        ]);
        $cobro = $this->calcularCobroRecibo($lectura, $data, $this->resolverTarifaSnapshotRecibo($lectura));
        $recibo = [
            'recibo_id' => (int) ($lectura['recibo_id'] ?? 0),
            'folio' => (string) (($lectura['folio'] ?? '') ?: $this->generarFolio()),
        ];
        $imagenPath = $this->generarImagen(
            $lectura,
            $recibo,
            $data,
            $cobro['subtotal'],
            $cobro['total'],
            $this->generarTokenQr($lectura),
            $cobro
        );

        $stmt = $this->db->prepare('UPDATE recibos SET imagen_path = :imagen_path WHERE id = :recibo_id');
        $stmt->execute([
            'imagen_path' => $imagenPath,
            'recibo_id' => $recibo['recibo_id'],
        ]);

        $lectura['imagen_path'] = $imagenPath;
        $lectura['folio'] = $recibo['folio'];
        $lectura['total'] = $cobro['total'];

        return $lectura;
    }

    private function imagenReciboDisponible(array $lectura): bool
    {
        $relativePath = trim((string) ($lectura['imagen_path'] ?? ''));

        if ($relativePath === '') {
            return false;
        }

        return is_file($this->rutaAbsolutaRecibo($relativePath));
    }

    private function rutaAbsolutaRecibo(string $relativePath): string
    {
        return $this->rootDir . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    private function crearPayloadImpresion(
        array $lectura,
        array $recibo,
        array $data,
        float $subtotal,
        float $total,
        string $qrToken,
        array $cobro
    ): array {
        if (!is_file($this->coordsPath)) {
            throw new RuntimeException('No se encontro la configuracion de coordenadas del recibo.');
        }

        $coords = json_decode(file_get_contents($this->coordsPath), true);
        $fields = $coords['fields'] ?? [];
        $printConfig = (array) ($coords['print'] ?? []);
        $printFieldOverrides = (array) ($printConfig['fields'] ?? []);
        $valores = $this->construirValoresRecibo($lectura, $recibo, $data, $subtotal, $total, $cobro);
        $variables = $this->camposVariablesImpresion();
        $camposImpresion = [];
        $valoresImpresion = [];

        foreach ($variables as $field) {
            if (!isset($fields[$field], $valores[$field])) {
                continue;
            }

            $camposImpresion[$field] = array_merge(
                (array) $fields[$field],
                (array) ($printFieldOverrides[$field] ?? [])
            );
            $valoresImpresion[$field] = (string) $valores[$field];
        }

        $qrConfig = array_merge(
            (array) (($coords['qr']['talon'] ?? [])),
            (array) ($printConfig['qr'] ?? [])
        );

        return [
            'canvas' => $coords['canvas'] ?? ['width' => 1648, 'height' => 2550],
            'fields' => $camposImpresion,
            'values' => $valoresImpresion,
            'qr' => $qrConfig,
            'qr_token' => $qrToken,
            'print' => [
                'fontScale' => (float) ($printConfig['fontScale'] ?? 2.1),
                'lineHeightScale' => (float) ($printConfig['lineHeightScale'] ?? 2.0),
                'fontFamily' => (string) ($printConfig['fontFamily'] ?? 'Arial, sans-serif'),
                'offsetX' => (float) ($printConfig['offsetX'] ?? 0),
                'offsetY' => (float) ($printConfig['offsetY'] ?? 0),
            ],
        ];
    }

    private function construirValoresRecibo(array $lectura, array $recibo, array $data, float $subtotal, float $total, array $cobro): array
    {
        $direccionLineas = $this->construirDireccionRecibo($lectura);
        $extras = $data['cooperaciones'] + $data['multas'] + $data['recargos'];
        // m³ facturados (puede ser mayor al consumo real cuando aplica mínimo facturable)
        $consumoBilledM3 = 0.0;
        foreach ((array) ($cobro['detalle'] ?? []) as $d) {
            $consumoBilledM3 += (float) ($d['cantidad'] ?? 0);
        }
        $cooperacionFugas = (float) $data['cooperaciones'];
        $multaRecargo = (float) $data['multas'] + (float) $data['recargos'];
        $periodoConsumo = $this->periodoConsumoTexto(
            (string) ($lectura['fecha_inicio'] ?? ''),
            (string) ($lectura['fecha_fin'] ?? ''),
            (string) ($lectura['periodo'] ?? '')
        );
        $periodoCorto = $this->periodoCorto($lectura['periodo'] ?? '');
        $contextoCobro = $this->obtenerContextoHistoricoCobro($lectura, (int) ($recibo['recibo_id'] ?? 0));
        $saldoAnterior = (float) ($contextoCobro['saldo_anterior'] ?? 0);
        $pagoPeriodoAnterior = (float) ($contextoCobro['pago_periodo_anterior'] ?? 0);
        $periodoPagoAnterior = trim((string) ($contextoCobro['periodo_anterior'] ?? ''));
        $avisoRecibo = (string) ($lectura['lectura_id'] ?? '');
        $rutaObservacion = trim((string) ($lectura['ruta'] ?? ''));
        $rutaObservacion = $rutaObservacion !== '' ? 'RUTA=' . $rutaObservacion : '';
        $periodoMovimiento = $this->periodoMovimiento($periodoCorto);
        $movimientoConsumo = '(+) Consumo ' . $periodoMovimiento;
        $movimientoPagoAnterior = $periodoPagoAnterior !== ''
            ? '(-) Su pago en ' . $this->periodoMovimiento($periodoPagoAnterior)
            : '(-) Su pago en periodo anterior';
        $tarifaDescripcion = (string) ($cobro['descripcion_corta'] ?? '');
        $movimientoMultaRecargo = '(+) COP. ENSOLVACION';
        if ((float) $data['multas'] > 0 && (float) $data['recargos'] <= 0) {
            $movimientoMultaRecargo = '(+) Multa pago tardio';
        } elseif ((float) $data['multas'] <= 0 && (float) $data['recargos'] > 0) {
            $movimientoMultaRecargo = '(+) Recargo';
        }

        return [
            'label_aviso_recibo' => 'AVISO RECIBO',
            'aviso_recibo' => $avisoRecibo,
            'label_fecha_limite_pago' => 'FECHA LIMITE DE PAGO',
            'label_total_top' => 'TOTAL A PAGAR',
            'total_top' => $this->moneda($total),
            'periodo_consumo' => $periodoConsumo,
            'tarifa' => $tarifaDescripcion !== '' ? $tarifaDescripcion : 'DOMESTICA',
            'usuario' => $lectura['usuario'],
            'direccion_1' => $direccionLineas[0] ?? 'Sin direccion',
            'direccion_2' => $direccionLineas[1] ?? '',
            'direccion_3' => $direccionLineas[2] ?? '',
            'direccion_4' => $direccionLineas[3] ?? '',
            'ruta' => $lectura['ruta'],
            'label_medidor' => 'MEDIDOR',
            'medidor' => $lectura['medidor'],
            'periodo' => $periodoCorto,
            'label_lectura_anterior' => 'LEC. ANT.',
            'lectura_anterior' => $this->formatoNumero($lectura['lectura_anterior']),
            'label_lectura_actual' => 'LEC. ACT.',
            'lectura_actual' => $this->formatoNumero($lectura['lectura_actual']),
            'label_consumo_m3' => 'CONSUMO M3',
            'consumo_m3' => $this->formatoNumero($lectura['consumo_m3']),
            'label_tarifa' => 'TARIFA',
            'fecha_emision' => date('d/m/Y'),
            'fecha_captura' => date('d/m/Y', strtotime($lectura['fecha_captura'])),
            'ubicacion' => ($lectura['latitud'] && $lectura['longitud']) ? 'GPS CAPTURADO' : 'SIN GPS',
            'concepto_consumo_cantidad' => $this->formatoNumero($consumoBilledM3) . ' m3',
            'concepto_consumo_importe' => $this->moneda($subtotal),
            'concepto_extra_descripcion' => $extras > 0 ? 'Extras del periodo' : '',
            'concepto_extra_cantidad' => $extras > 0 ? '1' : '',
            'concepto_extra_importe' => $extras > 0 ? $this->moneda($extras) : '',
            'total' => $this->moneda($total),
            'fecha_limite_pago' => $this->fechaCortaRecibo($data['fecha_limite_pago']),
            'metodo_pago_caja' => $data['metodo_pago_caja'],
            'referencia_pago' => $data['referencia_pago'],
            'detalle_movimientos_titulo' => 'DETALLE DE MOVIMIENTOS',
            'mov1_desc' => 'Saldo bimestre anterior',
            'mov1_signo' => '',
            'mov1_importe' => $this->moneda($saldoAnterior),
            'mov2_desc' => $movimientoPagoAnterior,
            'mov2_signo' => '-',
            'mov2_importe' => $this->moneda($pagoPeriodoAnterior),
            'mov3_desc' => '(+) COOP. FUGAS',
            'mov3_signo' => '+',
            'mov3_importe' => $this->moneda($cooperacionFugas),
            'mov4_desc' => $movimientoMultaRecargo,
            'mov4_signo' => '+',
            'mov4_importe' => $this->moneda($multaRecargo),
            'mov5_desc' => $movimientoConsumo,
            'mov5_signo' => '+',
            'mov5_importe' => $this->moneda($subtotal),
            'mov_total_desc' => '(=) TOTAL A PAGAR',
            'mov_total_importe' => $this->moneda($total),
            'aviso_fugas' => 'Por favor reportar fugas al 444 131 5689',
            'observaciones_titulo' => 'OBSERVACIONES:',
            'observaciones' => $rutaObservacion,
            'avisos_importantes_titulo' => 'AVISOS IMPORTANTES',
            'aviso_importante_izq' => "Si aun no esta en el grupo de WhatsApp\nmandar un mensaje para agregarlos.",
            'aviso_importante_der' => "Seguimos recomendando limpiar sus\nmedidores y sus tinacos o aljibes.",
            'lema' => 'SERVIR CON HONESTIDAD Y TRANSPARENCIA ES EL PRINCIPIO QUE NOS DISTINGUE',
            'stub_usuario' => $lectura['usuario'],
            'stub_medidor' => $lectura['medidor'],
            'stub_aviso' => $avisoRecibo,
            'stub_ruta' => $rutaObservacion,
            'stub_mov1_desc' => 'Saldo bimestre anterior',
            'stub_mov1_signo' => '',
            'stub_mov1_importe' => $this->moneda($saldoAnterior),
            'stub_mov2_desc' => $movimientoPagoAnterior,
            'stub_mov2_signo' => '-',
            'stub_mov2_importe' => $this->moneda($pagoPeriodoAnterior),
            'stub_mov3_desc' => '(+) COOP. FUGAS',
            'stub_mov3_signo' => '+',
            'stub_mov3_importe' => $this->moneda($cooperacionFugas),
            'stub_mov4_desc' => $movimientoMultaRecargo,
            'stub_mov4_signo' => '+',
            'stub_mov4_importe' => $this->moneda($multaRecargo),
            'stub_mov5_desc' => $movimientoConsumo,
            'stub_mov5_signo' => '+',
            'stub_mov5_importe' => $this->moneda($subtotal),
            'stub_total_desc' => '(=) TOTAL A PAGAR',
            'stub_total_importe' => $this->moneda($total),
        ];
    }

    private function resolverConfiguracionImpresion(array $lectura, array $input): array
    {
        $data = $this->normalizarRecibo($input);
        $parametros = $this->obtenerCobroAgua()->parametros();

        foreach (['cooperaciones', 'multas', 'recargos'] as $field) {
            if (!array_key_exists($field, $input) || trim((string) ($input[$field] ?? '')) === '') {
                $valorLectura = (float) ($lectura[$field] ?? 0);
                $default = (float) ($parametros[$field === 'cooperaciones'
                    ? 'cooperacion_default'
                    : ($field === 'multas' ? 'multa_default' : 'recargo_default')] ?? 0);
                $data[$field] = $valorLectura > 0 ? $valorLectura : $default;
            }
        }

        if (!$data['fecha_limite_pago'] || !$this->fechaValida($data['fecha_limite_pago'])) {
            $fechaLimite = (string) ($lectura['fecha_vencimiento'] ?? '');
            $data['fecha_limite_pago'] = $this->fechaValida($fechaLimite)
                ? $fechaLimite
                : date('Y-m-d', strtotime('+7 days'));
        }

        if (!array_key_exists('metodo_pago_caja', $input) || trim((string) ($input['metodo_pago_caja'] ?? '')) === '') {
            $data['metodo_pago_caja'] = 'Caja de cobro del sistema de agua';
        }

        if (!array_key_exists('referencia_pago', $input) || trim((string) ($input['referencia_pago'] ?? '')) === '') {
            $data['referencia_pago'] = 'Presentar este recibo al realizar el pago';
        }

        return $data;
    }

    private function calcularCobroRecibo(array $lectura, array $data, ?array $tarifaSnapshot = null): array
    {
        $calculo = $this->obtenerCobroAgua()->calcular((float) ($lectura['consumo_m3'] ?? 0), $tarifaSnapshot);
        $subtotal = (float) ($calculo['subtotal'] ?? 0);
        $extras = (float) $data['cooperaciones'] + (float) $data['multas'] + (float) $data['recargos'];

        return [
            'parametros' => $calculo['parametros'],
            'detalle' => $calculo['detalle'],
            'descripcion' => $calculo['descripcion'],
            'descripcion_corta' => $calculo['descripcion_corta'],
            'subtotal' => round($subtotal, 2),
            'total' => round($subtotal + $extras, 2),
        ];
    }

    private function resolverTarifaSnapshotRecibo(array $lectura): ?array
    {
        return $this->obtenerCobroAgua()->desdeSnapshot((string) ($lectura['tarifa_parametros_json'] ?? ''));
    }

    private function obtenerCobroAgua(): CobroAgua
    {
        if ($this->cobroAgua instanceof CobroAgua) {
            return $this->cobroAgua;
        }

        $this->cobroAgua = new CobroAgua($this->db);
        return $this->cobroAgua;
    }

    private function camposVariablesImpresion(): array
    {
        return [
            'aviso_recibo',
            'fecha_limite_pago',
            'total_top',
            'periodo_consumo',
            'usuario',
            'direccion_1',
            'direccion_2',
            'direccion_3',
            'direccion_4',
            'medidor',
            'lectura_anterior',
            'lectura_actual',
            'consumo_m3',
            'tarifa',
            'mov1_desc',
            'mov1_signo',
            'mov1_importe',
            'mov2_desc',
            'mov2_signo',
            'mov2_importe',
            'mov3_desc',
            'mov3_signo',
            'mov3_importe',
            'mov4_desc',
            'mov4_signo',
            'mov4_importe',
            'mov5_desc',
            'mov5_signo',
            'mov5_importe',
            'mov_total_desc',
            'mov_total_importe',
            'observaciones',
            'stub_usuario',
            'stub_medidor',
            'stub_aviso',
            'stub_ruta',
            'stub_mov1_desc',
            'stub_mov1_signo',
            'stub_mov1_importe',
            'stub_mov2_desc',
            'stub_mov2_signo',
            'stub_mov2_importe',
            'stub_mov3_desc',
            'stub_mov3_signo',
            'stub_mov3_importe',
            'stub_mov4_desc',
            'stub_mov4_signo',
            'stub_mov4_importe',
            'stub_mov5_desc',
            'stub_mov5_signo',
            'stub_mov5_importe',
            'stub_total_desc',
            'stub_total_importe',
        ];
    }

    private function dibujarTexto($image, string $font, array $config, string $text): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        $size = (int) ($config['fontSize'] ?? 22);
        $x = (int) ($config['x'] ?? 0);
        $y = (int) ($config['y'] ?? 0);
        $width = (int) ($config['width'] ?? 300);
        $lineHeight = (int) ($config['lineHeight'] ?? $size + 8);
        $color = $this->color($image, $config['color'] ?? '#17365D');
        $maxLines = max(1, (int) ($config['maxLines'] ?? 2));
        $renderFont = !empty($config['bold']) ? $this->obtenerFuente(true) : $font;

        if (!empty($config['background'])) {
            $padding = (int) ($config['backgroundPadding'] ?? 4);
            $height = (int) ($config['backgroundHeight'] ?? ($lineHeight * $maxLines));
            imagefilledrectangle(
                $image,
                max(0, $x - $padding),
                max(0, $y - $padding),
                $x + $width + $padding,
                $y + $height + $padding,
                $this->color($image, (string) $config['background'])
            );
        }

        $lines = !empty($config['multiline'])
            ? $this->wrapTexto($text, $renderFont, $size, $width, $maxLines)
            : [$this->ajustarTexto($text, $renderFont, $size, $width)];

        foreach ($lines as $index => $line) {
            $lineX = $this->alinearX($line, $renderFont, $size, $x, $width, $config['align'] ?? 'left');
            imagettftext($image, $size, 0, $lineX, $y + $size + ($index * $lineHeight), $color, $renderFont, $line);
        }
    }

    private function wrapTexto(string $text, string $font, int $size, int $width, int $maxLines = 2): array
    {
        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $paragraph) {
            $words = preg_split('/\s+/', trim($paragraph)) ?: [];
            $line = '';

            foreach ($words as $word) {
                $test = trim($line . ' ' . $word);
                if ($line !== '' && $this->textoAncho($test, $font, $size) > $width) {
                    $lines[] = $line;
                    $line = $word;
                    continue;
                }
                $line = $test;
            }

            if ($line !== '') {
                $lines[] = $line;
            }

            if (count($lines) >= $maxLines) {
                break;
            }
        }

        return array_slice($lines, 0, max(1, $maxLines));
    }

    private function ajustarTexto(string $text, string $font, int $size, int $width): string
    {
        if ($this->textoAncho($text, $font, $size) <= $width) {
            return $text;
        }

        while (mb_strlen($text, 'UTF-8') > 3 && $this->textoAncho($text . '...', $font, $size) > $width) {
            $text = mb_substr($text, 0, -1, 'UTF-8');
        }

        return rtrim($text) . '...';
    }

    private function alinearX(string $text, string $font, int $size, int $x, int $width, string $align): int
    {
        $textWidth = $this->textoAncho($text, $font, $size);

        if ($align === 'right') {
            return max($x, $x + $width - $textWidth);
        }

        if ($align === 'center') {
            return max($x, (int) ($x + (($width - $textWidth) / 2)));
        }

        return $x;
    }

    private function textoAncho(string $text, string $font, int $size): int
    {
        $box = imagettfbbox($size, 0, $font, $text);
        return abs(($box[2] ?? 0) - ($box[0] ?? 0));
    }

    private function color($image, string $hex): int
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return imagecolorallocate($image, $r, $g, $b);
    }

    private function obtenerFuente(bool $bold = false): string
    {
        $fonts = $bold
            ? [
                $this->rootDir . '/assets/fonts/arialbd.ttf',
                $this->rootDir . '/assets/fonts/arial.ttf',
                'C:/Windows/Fonts/arialbd.ttf',
                'C:/Windows/Fonts/calibrib.ttf',
                'C:/Windows/Fonts/segoeuib.ttf',
                'C:/Windows/Fonts/arial.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
                '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
            ]
            : [
                $this->rootDir . '/assets/fonts/arial.ttf',
                $this->rootDir . '/assets/fonts/arialbd.ttf',
                'C:/Windows/Fonts/arial.ttf',
                'C:/Windows/Fonts/calibri.ttf',
                'C:/Windows/Fonts/segoeui.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                '/usr/share/fonts/TTF/DejaVuSans.ttf',
            ];

        foreach ($fonts as $font) {
            if (is_file($font)) {
                return $font;
            }
        }

        throw new RuntimeException('No se encontro una fuente TTF para generar el recibo.');
    }

    private function generarTokenQr(array $lectura): string
    {
        return ReciboQr::buildToken(
            (int) ($lectura['usuario_id'] ?? 0),
            (int) ($lectura['lectura_id'] ?? 0),
            (string) ($lectura['fecha_captura'] ?? date('Y-m-d H:i:s')),
            $this->qrSecret
        );
    }

    private function dibujarQr($image, array $config, string $token): bool
    {
        if (array_key_exists('enabled', $config) && !$config['enabled']) {
            return false;
        }

        $x = (int) ($config['x'] ?? 0);
        $y = (int) ($config['y'] ?? 0);
        $size = max(90, (int) ($config['size'] ?? 200));

        if ($x <= 0 || $y <= 0) {
            return false;
        }

        $qrImage = $this->descargarQrImage($token, $size);
        if (!$qrImage) {
            return false;
        }

        if (!empty($config['background'])) {
            $padding = max(0, (int) ($config['padding'] ?? 0));
            imagefilledrectangle(
                $image,
                max(0, $x - $padding),
                max(0, $y - $padding),
                min(imagesx($image), $x + $size + $padding),
                min(imagesy($image), $y + $size + $padding),
                $this->color($image, (string) $config['background'])
            );
        }

        imagecopyresampled(
            $image,
            $qrImage,
            $x,
            $y,
            0,
            0,
            $size,
            $size,
            imagesx($qrImage),
            imagesy($qrImage)
        );
        imagedestroy($qrImage);
        return true;
    }

    private function descargarQrImage(string $token, int $size)
    {
        $encoded = rawurlencode($token);
        $providers = [
            'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . $encoded,
            'https://quickchart.io/qr?size=' . $size . '&text=' . $encoded,
        ];

        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        foreach ($providers as $url) {
            $binary = @file_get_contents($url, false, $context);
            if ($binary === false || $binary === '') {
                continue;
            }

            $img = @imagecreatefromstring($binary);
            if ($img !== false) {
                return $img;
            }
        }

        return null;
    }

    private function numero($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return is_numeric($value) ? (float) $value : 0;
    }

    private function fechaValida(string $value): bool
    {
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    private function generarFolio(): string
    {
        return 'REC-' . date('Ymd-His') . '-' . random_int(100, 999);
    }

    private function periodoCorto(?string $periodo): string
    {
        $periodo = trim((string) $periodo);

        if ($periodo === '') {
            return 'Sin periodo';
        }

        $meses = [
            'ENERO' => 'Ene',
            'FEBRERO' => 'Feb',
            'MARZO' => 'Mar',
            'ABRIL' => 'Abr',
            'MAYO' => 'May',
            'JUNIO' => 'Jun',
            'JULIO' => 'Jul',
            'AGOSTO' => 'Ago',
            'SEPTIEMBRE' => 'Sep',
            'OCTUBRE' => 'Oct',
            'NOVIEMBRE' => 'Nov',
            'DICIEMBRE' => 'Dic',
        ];
        $upper = mb_strtoupper($periodo, 'UTF-8');
        $year = '';

        if (preg_match('/\b(20\d{2}|19\d{2})\b/', $periodo, $matches)) {
            $year = ' ' . $matches[1];
        }

        foreach ($meses as $nombre => $abreviado) {
            $upper = str_replace($nombre, $abreviado, $upper);
        }

        $upper = preg_replace('/\s+(20\d{2}|19\d{2})\b/', '', $upper);
        $upper = str_replace(' ', '', (string) $upper);

        return mb_convert_case($upper . $year, MB_CASE_TITLE, 'UTF-8');
    }

    private function periodoConsumoTexto(string $fechaInicio, string $fechaFin, string $periodoNombre): string
    {
        if ($this->fechaValida($fechaInicio) && $this->fechaValida($fechaFin)) {
            return 'PERIODO DE CONSUMO: ' . $this->fechaLargaRecibo($fechaInicio) . "\n"
                . 'AL ' . $this->fechaLargaRecibo($fechaFin);
        }

        $periodoNombre = trim($periodoNombre);
        return $periodoNombre !== '' ? $periodoNombre : 'SIN PERIODO';
    }

    private function construirDireccionRecibo(array $lectura): array
    {
        $lineas = [];
        $calleNumero = trim(implode(' ', array_filter([
            $lectura['calle'] ?? '',
            $lectura['numero_domicilio'] ?? '',
        ])));
        $colonia = trim((string) ($lectura['colonia'] ?? ''));

        if ($calleNumero !== '') {
            $lineas[] = $calleNumero;
        }

        if ($colonia !== '') {
            $lineas[] = $colonia;
        }

        $lineas[] = 'Mexquitic de Carmona, S.L.P.';
        $lineas[] = 'C.P. 78480';

        return array_slice(array_values(array_filter($lineas)), 0, 4);
    }

    private function fechaLargaRecibo(string $fecha): string
    {
        if (!$this->fechaValida($fecha)) {
            return $fecha;
        }

        $meses = [
            1 => 'ENERO',
            2 => 'FEBRERO',
            3 => 'MARZO',
            4 => 'ABRIL',
            5 => 'MAYO',
            6 => 'JUNIO',
            7 => 'JULIO',
            8 => 'AGOSTO',
            9 => 'SEPTIEMBRE',
            10 => 'OCTUBRE',
            11 => 'NOVIEMBRE',
            12 => 'DICIEMBRE',
        ];
        $time = strtotime($fecha);

        return (int) date('j', $time) . ' DE ' . $meses[(int) date('n', $time)] . ' DE ' . date('Y', $time);
    }

    private function fechaCortaRecibo(string $fecha): string
    {
        if (!$this->fechaValida($fecha)) {
            return $fecha;
        }

        $meses = [
            1 => 'ENE',
            2 => 'FEB',
            3 => 'MAR',
            4 => 'ABR',
            5 => 'MAY',
            6 => 'JUN',
            7 => 'JUL',
            8 => 'AGO',
            9 => 'SEP',
            10 => 'OCT',
            11 => 'NOV',
            12 => 'DIC',
        ];
        $time = strtotime($fecha);

        return date('d', $time) . '/' . $meses[(int) date('n', $time)] . '/' . date('Y', $time);
    }

    private function periodoMovimiento(string $periodo): string
    {
        $periodo = mb_strtoupper(trim($periodo), 'UTF-8');

        return preg_replace('/\b(20|19)(\d{2})\b/', '$2', $periodo) ?: $periodo;
    }

    private function obtenerContextoHistoricoCobro(array $lectura, int $reciboId): array
    {
        $medidorId = (int) ($lectura['medidor_id'] ?? 0);
        if ($medidorId <= 0) {
            return [
                'saldo_anterior' => 0.0,
                'pago_periodo_anterior' => 0.0,
                'periodo_anterior' => '',
            ];
        }

        $sql = "SELECT
                    r.id,
                    r.total,
                    p.nombre AS periodo,
                    COALESCE(pg.total_pagado, 0) AS total_pagado
                FROM recibos r
                LEFT JOIN periodos_bimestrales p ON p.id = r.periodo_id
                LEFT JOIN (
                    SELECT recibo_id, SUM(monto) AS total_pagado
                    FROM pagos
                    GROUP BY recibo_id
                ) pg ON pg.recibo_id = r.id
                WHERE r.medidor_id = :medidor_id";

        $params = ['medidor_id' => $medidorId];

        if ($reciboId > 0) {
            $sql .= " AND r.id <> :recibo_id";
            $params['recibo_id'] = $reciboId;
        }

        $fechaInicio = (string) ($lectura['fecha_inicio'] ?? '');
        if ($this->fechaValida($fechaInicio)) {
            $sql .= " AND p.fecha_fin < :fecha_inicio";
            $params['fecha_inicio'] = $fechaInicio;
        }

        $sql .= " ORDER BY p.fecha_fin DESC, r.id DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $anterior = $stmt->fetch();

        if (!$anterior) {
            return [
                'saldo_anterior' => 0.0,
                'pago_periodo_anterior' => 0.0,
                'periodo_anterior' => '',
            ];
        }

        $totalAnterior = (float) ($anterior['total'] ?? 0);
        $pagadoAnterior = (float) ($anterior['total_pagado'] ?? 0);

        return [
            'saldo_anterior' => max($totalAnterior - $pagadoAnterior, 0),
            'pago_periodo_anterior' => min($pagadoAnterior, $totalAnterior),
            'periodo_anterior' => $this->periodoCorto((string) ($anterior['periodo'] ?? '')),
        ];
    }

    private function formatoNumero($value): string
    {
        $numero = (float) $value;

        if (abs($numero - round($numero)) < 0.00001) {
            return number_format($numero, 0, '.', ',');
        }

        return number_format($numero, 2, '.', ',');
    }

    private function moneda(float $value): string
    {
        return '$' . number_format($value, 2, '.', ',');
    }

    private function folioImpreso(string $folio, int $reciboId): string
    {
        $folio = trim($folio);
        $soloDigitos = preg_replace('/\D+/', '', $folio ?? '');

        if ($soloDigitos !== '') {
            return substr($soloDigitos, -4);
        }

        if ($reciboId > 0) {
            return str_pad((string) $reciboId, 4, '0', STR_PAD_LEFT);
        }

        return '0000';
    }
}
