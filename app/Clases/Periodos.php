<?php

class Periodos
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listar(int $page = 1, int $perPage = 30): array
    {
        $page = max(1, $page);
        $perPage = (int) $perPage;
        $allowAll = $perPage <= 0;

        $stmtCount = $this->db->query("SELECT COUNT(*) AS total FROM periodos_bimestrales");
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
                id AS periodo_id,
                anio,
                bimestre,
                mes_inicio,
                mes_fin,
                nombre,
                fecha_inicio,
                fecha_fin,
                estado
             FROM periodos_bimestrales
             ORDER BY fecha_inicio DESC, id DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $periodos = $stmt->fetchAll();

        return [
            'periodos' => $periodos,
            'pagination' => [
                'page' => $page,
                'per_page' => $allowAll ? 0 : $perPage,
                'effective_per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? $offset + count($periodos) : 0,
            ],
        ];
    }

    public function obtener(int $periodoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                id AS periodo_id,
                anio,
                bimestre,
                mes_inicio,
                mes_fin,
                nombre,
                fecha_inicio,
                fecha_fin,
                estado
             FROM periodos_bimestrales
             WHERE id = :periodo_id
             LIMIT 1"
        );
        $stmt->execute(['periodo_id' => $periodoId]);
        $periodo = $stmt->fetch();

        if (!$periodo) {
            throw new RuntimeException('No se encontro el periodo solicitado.');
        }

        return $periodo;
    }

    public function guardar(array $input): array
    {
        $data = $this->normalizar($input);
        $errors = $this->validar($data);

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $this->validarPeriodoUnico($data['anio'], $data['bimestre']);

        $stmt = $this->db->prepare(
            "INSERT INTO periodos_bimestrales
                (anio, bimestre, mes_inicio, mes_fin, nombre, fecha_inicio, fecha_fin, estado)
             VALUES
                (:anio, :bimestre, :mes_inicio, :mes_fin, :nombre, :fecha_inicio, :fecha_fin, 'abierto')"
        );
        $stmt->execute($data);

        return $this->obtener((int) $this->db->lastInsertId());
    }

    public function actualizar(array $input): array
    {
        $periodoId = (int) ($input['periodo_id'] ?? 0);
        $data = $this->normalizar($input);
        $errors = $this->validar($data);

        if ($periodoId <= 0) {
            $errors['periodo_id'] = 'No se recibio el periodo a editar.';
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $this->validarPeriodoUnico($data['anio'], $data['bimestre'], $periodoId);

        $stmt = $this->db->prepare(
            "UPDATE periodos_bimestrales
             SET anio = :anio,
                 bimestre = :bimestre,
                 mes_inicio = :mes_inicio,
                 mes_fin = :mes_fin,
                 nombre = :nombre,
                 fecha_inicio = :fecha_inicio,
                 fecha_fin = :fecha_fin
             WHERE id = :periodo_id"
        );
        $stmt->execute($data + ['periodo_id' => $periodoId]);

        return $this->obtener($periodoId);
    }

    public function darDeBaja(int $periodoId): array
    {
        if ($periodoId <= 0) {
            throw new RuntimeException('No se recibio el periodo a dar de baja.');
        }

        $stmt = $this->db->prepare(
            "UPDATE periodos_bimestrales
             SET estado = 'cancelado'
             WHERE id = :periodo_id"
        );
        $stmt->execute(['periodo_id' => $periodoId]);

        return $this->obtener($periodoId);
    }

    private function normalizar(array $input): array
    {
        $fechaInicio = Request::cleanString($input['fecha_inicio'] ?? null);
        $fechaFin = Request::cleanString($input['fecha_fin'] ?? null);
        $mesInicio = $fechaInicio ? (int) date('n', strtotime($fechaInicio)) : 0;
        $mesFin = $fechaFin ? (int) date('n', strtotime($fechaFin)) : 0;
        $anio = $fechaInicio ? (int) date('Y', strtotime($fechaInicio)) : 0;
        $bimestre = $mesInicio > 0 ? (int) ceil($mesInicio / 2) : 0;

        return [
            'anio' => $anio,
            'bimestre' => $bimestre,
            'mes_inicio' => $mesInicio,
            'mes_fin' => $mesFin,
            'nombre' => $this->mayusculas(Request::cleanString($input['nombre'] ?? null)),
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ];
    }

    private function validar(array $data): array
    {
        $errors = [];

        if (!$data['nombre']) {
            $errors['nombre'] = 'Captura el nombre del periodo.';
        }

        if (!$data['fecha_inicio'] || !$this->fechaValida($data['fecha_inicio'])) {
            $errors['fecha_inicio'] = 'Captura una fecha de inicio valida.';
        }

        if (!$data['fecha_fin'] || !$this->fechaValida($data['fecha_fin'])) {
            $errors['fecha_fin'] = 'Captura una fecha de fin valida.';
        }

        if ($data['fecha_inicio'] && $data['fecha_fin'] && $data['fecha_fin'] < $data['fecha_inicio']) {
            $errors['fecha_fin'] = 'La fecha de fin no puede ser menor a la fecha de inicio.';
        }

        if ($data['mes_inicio'] > 0 && $data['mes_fin'] > 0) {
            $diff = (($data['anio'] * 12) + $data['mes_fin']) - (($data['anio'] * 12) + $data['mes_inicio']) + 1;
            if ($diff > 2) {
                $errors['fecha_fin'] = 'El periodo de cobro debe ser bimestral, maximo dos meses.';
            }
        }

        return $errors;
    }

    private function validarPeriodoUnico(int $anio, int $bimestre, int $periodoIdExcluir = 0): void
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM periodos_bimestrales
             WHERE anio = :anio
                AND bimestre = :bimestre
                AND id <> :periodo_id
             LIMIT 1"
        );
        $stmt->execute([
            'anio' => $anio,
            'bimestre' => $bimestre,
            'periodo_id' => $periodoIdExcluir,
        ]);

        if ($stmt->fetch()) {
            throw new RuntimeException('Ya existe un periodo registrado para ese bimestre.');
        }
    }

    private function fechaValida(string $fecha): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d', $fecha);
        return $dt && $dt->format('Y-m-d') === $fecha;
    }

    private function mayusculas(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strtoupper($value, 'UTF-8');
    }
}
