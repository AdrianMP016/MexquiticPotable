-- Ejecutar solo si la columna no existe:
ALTER TABLE recibos
  ADD COLUMN recibo_entregado TINYINT(1) NOT NULL DEFAULT 0 AFTER estado;

-- Ejecutar solo si la columna no existe:
ALTER TABLE recibos
  ADD COLUMN fecha_entrega DATETIME NULL DEFAULT NULL AFTER recibo_entregado;

ALTER TABLE pagos
  MODIFY COLUMN metodo ENUM('efectivo', 'transferencia', 'spei', 'tarjeta', 'otro')
  NOT NULL DEFAULT 'efectivo';
