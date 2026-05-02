-- ============================================================
-- Script: Actualización de teléfonos — Contactos Batch 2
-- Fecha: 2026-05-02
-- Descripción: Agrega teléfonos de comunidad Centro (Cent1).
--              Los Llani ya fueron actualizados en el batch 1.
--              Se omiten entradas sin número y Cent1-096 (número
--              incompleto "44").
-- Duplicados resueltos por nombre: Cent1-003, Cent1-026, Cent1-102
-- ============================================================

-- Centro (Cent1) — orden numérico
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4445723235' WHERE d.ruta = 'Cent1-001';
-- Cent1-003: RUTA DUPLICADA — solo LUIS RAMIREZ tiene número
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4443897867' WHERE d.ruta = 'Cent1-003' AND u.nombre LIKE '%LUIS RAMIREZ%';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441364925' WHERE d.ruta = 'Cent1-004';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442336556' WHERE d.ruta = 'Cent1-006';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4443305297' WHERE d.ruta = 'Cent1-007';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444283307' WHERE d.ruta = 'Cent1-008';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441219322' WHERE d.ruta = 'Cent1-009';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442202224' WHERE d.ruta = 'Cent1-011';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4443404909' WHERE d.ruta = 'Cent1-012';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441959299' WHERE d.ruta = 'Cent1-013';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442990591' WHERE d.ruta = 'Cent1-014';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442203809' WHERE d.ruta = 'Cent1-016';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441705165' WHERE d.ruta = 'Cent1-017';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442050939' WHERE d.ruta = 'Cent1-018';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4448840033' WHERE d.ruta = 'Cent1-019';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442092593' WHERE d.ruta = 'Cent1-020';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441428819' WHERE d.ruta = 'Cent1-021';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444200452' WHERE d.ruta = 'Cent1-022';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444200452' WHERE d.ruta = 'Cent1-023';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444470726' WHERE d.ruta = 'Cent1-024';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441338140' WHERE d.ruta = 'Cent1-025';
-- Cent1-026: RUTA DUPLICADA — se diferencia por nombre
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441928356' WHERE d.ruta = 'Cent1-026' AND u.nombre LIKE '%RICARDO%';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441428811' WHERE d.ruta = 'Cent1-026' AND u.nombre LIKE '%ALBERTA%';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441228814' WHERE d.ruta = 'Cent1-027';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441695929' WHERE d.ruta = 'Cent1-028';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441718073' WHERE d.ruta = 'Cent1-029';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4445463770' WHERE d.ruta = 'Cent1-030';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444523811' WHERE d.ruta = 'Cent1-031';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4445756303' WHERE d.ruta = 'Cent1-032';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444980358' WHERE d.ruta = 'Cent1-033';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442092593' WHERE d.ruta = 'Cent1-034';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4443348197' WHERE d.ruta = 'Cent1-036';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4443348197' WHERE d.ruta = 'Cent1-037';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442208791' WHERE d.ruta = 'Cent1-038';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4443374988' WHERE d.ruta = 'Cent1-039';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442938407' WHERE d.ruta = 'Cent1-040';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442208791' WHERE d.ruta = 'Cent1-041';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4445838422' WHERE d.ruta = 'Cent1-043';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444988060' WHERE d.ruta = 'Cent1-044';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441254238' WHERE d.ruta = 'Cent1-045';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444231785' WHERE d.ruta = 'Cent1-046';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441254238' WHERE d.ruta = 'Cent1-047';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4446523710' WHERE d.ruta = 'Cent1-048';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4447099056' WHERE d.ruta = 'Cent1-049';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441651264' WHERE d.ruta = 'Cent1-050';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441741828' WHERE d.ruta = 'Cent1-051';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441228814' WHERE d.ruta = 'Cent1-052';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441651264' WHERE d.ruta = 'Cent1-056';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4443317258' WHERE d.ruta = 'Cent1-057';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442362416' WHERE d.ruta = 'Cent1-059';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442140704' WHERE d.ruta = 'Cent1-060';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441962663' WHERE d.ruta = 'Cent1-061';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441354232' WHERE d.ruta = 'Cent1-063';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4445463770' WHERE d.ruta = 'Cent1-064';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4443108518' WHERE d.ruta = 'Cent1-065';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441695929' WHERE d.ruta = 'Cent1-066';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4443121479' WHERE d.ruta = 'Cent1-067';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444520064' WHERE d.ruta = 'Cent1-068';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441228989' WHERE d.ruta = 'Cent1-069';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441366239' WHERE d.ruta = 'Cent1-072';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441922962' WHERE d.ruta = 'Cent1-073';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442259420' WHERE d.ruta = 'Cent1-074';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441191886' WHERE d.ruta = 'Cent1-075';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441847345' WHERE d.ruta = 'Cent1-076';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442141562' WHERE d.ruta = 'Cent1-077';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444340591' WHERE d.ruta = 'Cent1-078';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442015432' WHERE d.ruta = 'Cent1-079';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444259339' WHERE d.ruta = 'Cent1-080';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4448005675' WHERE d.ruta = 'Cent1-081';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444202575' WHERE d.ruta = 'Cent1-082';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444988060' WHERE d.ruta = 'Cent1-083';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442008609' WHERE d.ruta = 'Cent1-084';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4448453325' WHERE d.ruta = 'Cent1-085';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442001986' WHERE d.ruta = 'Cent1-086';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442414871' WHERE d.ruta = 'Cent1-087';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444438973' WHERE d.ruta = 'Cent1-088';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4446681533' WHERE d.ruta = 'Cent1-089';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441414261' WHERE d.ruta = 'Cent1-091';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441835391' WHERE d.ruta = 'Cent1-092';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4446681533' WHERE d.ruta = 'Cent1-093';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444462847' WHERE d.ruta = 'Cent1-094';
-- Cent1-096: número inválido ("44"), se omite
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4445388170' WHERE d.ruta = 'Cent1-097';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4445388170' WHERE d.ruta = 'Cent1-098';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4448435555' WHERE d.ruta = 'Cent1-099';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444280044' WHERE d.ruta = 'Cent1-100';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4448435555' WHERE d.ruta = 'Cent1-101';
-- Cent1-102: RUTA DUPLICADA — solo GONZALO HERNANDEZ tiene número
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4445883103' WHERE d.ruta = 'Cent1-102' AND u.nombre LIKE '%GONZALO%';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4445458498' WHERE d.ruta = 'Cent1-104';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442050939' WHERE d.ruta = 'Cent1-108';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444281673' WHERE d.ruta = 'Cent1-109';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4448581860' WHERE d.ruta = 'Cent1-110';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441329148' WHERE d.ruta = 'Cent1-111';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442050939' WHERE d.ruta = 'Cent1-112';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4442847221' WHERE d.ruta = 'Cent1-113';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4443343320' WHERE d.ruta = 'Cent1-115';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4445376541' WHERE d.ruta = 'Cent1-116';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4447209333' WHERE d.ruta = 'Cent1-119';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4444486744' WHERE d.ruta = 'Cent1-123';
UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET u.telefono = '4441887266' WHERE d.ruta = 'Cent1-266';
