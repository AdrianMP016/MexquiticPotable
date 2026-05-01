<?php

class CobroAgua
{
    private PDO $db;
    private ?array $cache = null;

    private const DEFAULTS = [
        'tarifa_agua_nombre' => 'DOMESTICA',
        'tarifa_agua_limite_base_m3' => '15',
        'tarifa_agua_precio_base_m3' => '10',
        'tarifa_agua_precio_excedente_m3' => '15',
        'tarifa_agua_cooperacion_default' => '0',
        'tarifa_agua_multa_default' => '0',
        'tarifa_agua_recargo_default' => '0',
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function parametros(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $values = self::DEFAULTS;

        try {
            $stmt = $this->db->query('SELECT clave, valor FROM configuracion_cobro_agua');
            foreach ($stmt->fetchAll() as $row) {
                $clave = (string) ($row['clave'] ?? '');
                if ($clave === '') {
                    continue;
                }

                $values[$clave] = (string) ($row['valor'] ?? '');
            }
        } catch (Throwable $exception) {
        }

        $this->cache = $this->normalizarParametros($values);
        return $this->cache;
    }

    public function parametrosFrontend(): array
    {
        $parametros = $this->parametros();

        return [
            'nombre' => $parametros['nombre'],
            'limite_tramo_base_m3' => $parametros['limite_tramo_base_m3'],
            'precio_tramo_base_m3' => $parametros['precio_tramo_base_m3'],
            'precio_excedente_m3' => $parametros['precio_excedente_m3'],
            'cooperacion_default' => $parametros['cooperacion_default'],
            'multa_default' => $parametros['multa_default'],
            'recargo_default' => $parametros['recargo_default'],
            'descripcion' => $this->descripcionTarifa($parametros),
            'descripcion_corta' => $this->descripcionTarifaCorta($parametros),
        ];
    }

    public function snapshot(): array
    {
        return $this->parametros();
    }

    public function desdeSnapshot(?string $snapshotJson): ?array
    {
        $snapshotJson = trim((string) $snapshotJson);
        if ($snapshotJson === '') {
            return null;
        }

        $decoded = json_decode($snapshotJson, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $this->normalizarParametros($decoded);
    }

    public function calcular(float $consumo, ?array $parametros = null): array
    {
        $config = $this->normalizarParametros($parametros ?? $this->parametros());
        $consumo = round(max($consumo, 0), 2);
        $limiteBase = $config['limite_tramo_base_m3'];
        $consumoBase = round(min($consumo, $limiteBase), 2);
        $consumoExcedente = round(max($consumo - $limiteBase, 0), 2);
        $importeBase = round($consumoBase * $config['precio_tramo_base_m3'], 2);
        $importeExcedente = round($consumoExcedente * $config['precio_excedente_m3'], 2);
        $subtotal = round($importeBase + $importeExcedente, 2);

        $detalles = [];
        if ($consumoExcedente > 0) {
            $detalles[] = [
                'descripcion' => sprintf('Consumo de agua (primeros %s m3)', $this->formatoNumero($limiteBase)),
                'cantidad' => $consumoBase,
                'precio_unitario' => $config['precio_tramo_base_m3'],
                'importe' => $importeBase,
            ];
            $detalles[] = [
                'descripcion' => 'Consumo de agua excedente',
                'cantidad' => $consumoExcedente,
                'precio_unitario' => $config['precio_excedente_m3'],
                'importe' => $importeExcedente,
            ];
        } else {
            $detalles[] = [
                'descripcion' => 'Consumo de agua',
                'cantidad' => $consumo,
                'precio_unitario' => $config['precio_tramo_base_m3'],
                'importe' => $subtotal,
            ];
        }

        return [
            'parametros' => $config,
            'subtotal' => $subtotal,
            'detalle' => $detalles,
            'descripcion' => $this->descripcionTarifa($config),
            'descripcion_corta' => $this->descripcionTarifaCorta($config),
        ];
    }

    private function normalizarParametros(array $values): array
    {
        $nombre = trim((string) ($values['nombre'] ?? $values['tarifa_agua_nombre'] ?? self::DEFAULTS['tarifa_agua_nombre']));
        $limiteBase = $this->toFloat($values['limite_tramo_base_m3'] ?? $values['tarifa_agua_limite_base_m3'] ?? self::DEFAULTS['tarifa_agua_limite_base_m3']);
        $precioBase = $this->toFloat($values['precio_tramo_base_m3'] ?? $values['tarifa_agua_precio_base_m3'] ?? self::DEFAULTS['tarifa_agua_precio_base_m3']);
        $precioExcedente = $this->toFloat($values['precio_excedente_m3'] ?? $values['tarifa_agua_precio_excedente_m3'] ?? self::DEFAULTS['tarifa_agua_precio_excedente_m3']);

        return [
            'nombre' => $nombre !== '' ? $nombre : self::DEFAULTS['tarifa_agua_nombre'],
            'limite_tramo_base_m3' => max($limiteBase, 0),
            'precio_tramo_base_m3' => max($precioBase, 0),
            'precio_excedente_m3' => max($precioExcedente, 0),
            'cooperacion_default' => max($this->toFloat($values['cooperacion_default'] ?? $values['tarifa_agua_cooperacion_default'] ?? self::DEFAULTS['tarifa_agua_cooperacion_default']), 0),
            'multa_default' => max($this->toFloat($values['multa_default'] ?? $values['tarifa_agua_multa_default'] ?? self::DEFAULTS['tarifa_agua_multa_default']), 0),
            'recargo_default' => max($this->toFloat($values['recargo_default'] ?? $values['tarifa_agua_recargo_default'] ?? self::DEFAULTS['tarifa_agua_recargo_default']), 0),
        ];
    }

    private function descripcionTarifa(array $parametros): string
    {
        return sprintf(
            '%s: primeros %s m3 a $%s y excedente a $%s por m3',
            $parametros['nombre'],
            $this->formatoNumero($parametros['limite_tramo_base_m3']),
            $this->formatoNumero($parametros['precio_tramo_base_m3']),
            $this->formatoNumero($parametros['precio_excedente_m3'])
        );
    }

    private function descripcionTarifaCorta(array $parametros): string
    {
        return sprintf(
            '%s %sm3 x $%s | Exc. $%s',
            $parametros['nombre'],
            $this->formatoNumero($parametros['limite_tramo_base_m3']),
            $this->formatoNumero($parametros['precio_tramo_base_m3']),
            $this->formatoNumero($parametros['precio_excedente_m3'])
        );
    }

    private function formatoNumero(float $value): string
    {
        $rounded = round($value, 2);
        if (abs($rounded - round($rounded)) < 0.00001) {
            return (string) (int) round($rounded);
        }

        return number_format($rounded, 2, '.', '');
    }

    private function toFloat($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return 0.0;
        }

        $text = str_replace([',', ' '], ['', ''], $text);
        return is_numeric($text) ? (float) $text : 0.0;
    }
}
