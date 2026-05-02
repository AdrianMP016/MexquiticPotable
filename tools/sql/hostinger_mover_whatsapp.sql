UPDATE usuarios_servicio SET whatsapp = telefono, telefono = NULL WHERE telefono IS NOT NULL AND telefono != '' AND (whatsapp IS NULL OR whatsapp = '');
UPDATE usuarios_servicio SET telefono = whatsapp, whatsapp = telefono WHERE telefono IS NOT NULL AND telefono != '' AND whatsapp IS NOT NULL AND whatsapp != '';
