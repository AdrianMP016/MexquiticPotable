<?php

class ConfigBomba
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtener(string $clave, string $default = ''): string
    {
        $stmt = $this->db->prepare("SELECT valor FROM bomba_config WHERE clave = :clave LIMIT 1");
        $stmt->execute(['clave' => $clave]);
        $row = $stmt->fetch();

        return $row ? (string) $row['valor'] : $default;
    }

    public function obtenerNumero(string $clave, float $default = 0): float
    {
        $valor = $this->obtener($clave, (string) $default);
        return is_numeric($valor) ? (float) $valor : $default;
    }

    public function establecer(string $clave, string $valor): void
    {
        $stmt = $this->db->prepare(
            "UPDATE bomba_config SET valor = :valor WHERE clave = :clave"
        );
        $stmt->execute(['valor' => $valor, 'clave' => $clave]);
    }

    public function listarEditables(): array
    {
        $stmt = $this->db->query(
            "SELECT clave, valor, tipo, descripcion
             FROM bomba_config
             WHERE clave IN ('proteccion_delay_segundos')
             ORDER BY clave ASC"
        );

        return $stmt->fetchAll();
    }
}
