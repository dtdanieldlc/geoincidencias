-- ============================================================
--  GeoIncidencias – Sistema Web de Gestión de Incidencias
--  Script MySQL COMPLETO v2: roles, incentivos, apoyos, aprobación
--
--  INSTRUCCIONES:
--  1. Conéctate a tu BD remota (phpMyAdmin / MySQL Workbench)
--  2. Selecciona tu base de datos antes de ejecutar
--  3. Ejecuta todo el script de una sola vez
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- BLOQUE 1: UBICACIÓN NORMALIZADA (País → Provincia → Ciudad)
-- ============================================================
DROP TABLE IF EXISTS ciudades;
DROP TABLE IF EXISTS provincias;
DROP TABLE IF EXISTS paises;

CREATE TABLE paises (
    id_pais     INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL UNIQUE,
    codigo_iso  VARCHAR(5)   NULL
);

CREATE TABLE provincias (
    id_provincia INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_pais      INT          NOT NULL,
    nombre       VARCHAR(100) NOT NULL,
    CONSTRAINT fk_prov_pais FOREIGN KEY (id_pais) REFERENCES paises(id_pais),
    UNIQUE KEY uq_provincia (id_pais, nombre)
);

CREATE TABLE ciudades (
    id_ciudad    INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_provincia INT           NOT NULL,
    nombre       VARCHAR(100)  NOT NULL,
    latitud_ref  DECIMAL(10,6) NULL,
    longitud_ref DECIMAL(10,6) NULL,
    CONSTRAINT fk_ciu_provincia FOREIGN KEY (id_provincia) REFERENCES provincias(id_provincia),
    UNIQUE KEY uq_ciudad (id_provincia, nombre)
);

-- ============================================================
-- BLOQUE 2: ZONAS INTERNAS
-- ============================================================
DROP TABLE IF EXISTS zonas;
CREATE TABLE zonas (
    id_zona      INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_ciudad    INT           NOT NULL,
    nombre       VARCHAR(100)  NOT NULL,
    descripcion  VARCHAR(255)  NULL,
    latitud_ref  DECIMAL(10,6) NULL,
    longitud_ref DECIMAL(10,6) NULL,
    activo       TINYINT(1)    NOT NULL DEFAULT 1,
    CONSTRAINT fk_zona_ciudad FOREIGN KEY (id_ciudad) REFERENCES ciudades(id_ciudad)
);

-- ============================================================
-- BLOQUE 3: CLASIFICACIÓN JERÁRQUICA (Tipo → Subtipo)
-- ============================================================
DROP TABLE IF EXISTS subtipos_incidencia;
DROP TABLE IF EXISTS tipos_incidencia;

CREATE TABLE tipos_incidencia (
    id_tipo      INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(100) NOT NULL UNIQUE,
    descripcion  VARCHAR(255) NULL,
    icono        VARCHAR(50)  NULL DEFAULT 'bi-tag',
    color        VARCHAR(20)  NULL DEFAULT '#6366f1',
    activo       TINYINT(1)   NOT NULL DEFAULT 1
);

CREATE TABLE subtipos_incidencia (
    id_subtipo   INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_tipo      INT          NOT NULL,
    nombre       VARCHAR(100) NOT NULL,
    descripcion  VARCHAR(255) NULL,
    activo       TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT fk_subtipo_tipo FOREIGN KEY (id_tipo) REFERENCES tipos_incidencia(id_tipo),
    UNIQUE KEY uq_subtipo (id_tipo, nombre)
);

-- ============================================================
-- BLOQUE 4: ESTADOS
-- ============================================================
DROP TABLE IF EXISTS estados;
CREATE TABLE estados (
    id_estado    INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(50)  NOT NULL UNIQUE,
    descripcion  VARCHAR(255) NULL,
    color        VARCHAR(20)  NULL DEFAULT '#64748b',
    orden        INT          NOT NULL DEFAULT 0,
    activo       TINYINT(1)   NOT NULL DEFAULT 1
);

-- ============================================================
-- BLOQUE 5: USUARIOS (con rol admin/usuario para login JWT)
-- ============================================================
DROP TABLE IF EXISTS usuarios;
CREATE TABLE usuarios (
    id_usuario   INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(100) NOT NULL,
    apellido     VARCHAR(100) NULL,
    correo       VARCHAR(150) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,             -- hash bcrypt
    rol          ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
    telefono     VARCHAR(20)  NULL,
    saldo_incentivos DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- acumulado de incentivos aprobados
    activo       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- BLOQUE 6: TABLA DE INCENTIVOS POR PRIORIDAD (configurable)
-- ============================================================
DROP TABLE IF EXISTS incentivos_prioridad;
CREATE TABLE incentivos_prioridad (
    id_incentivo  INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    prioridad     ENUM('Baja','Media','Alta') NOT NULL UNIQUE,
    monto         DECIMAL(10,2) NOT NULL
);

-- ============================================================
-- BLOQUE 7: INCIDENCIAS
--   Incluye estado_aprobacion: una incidencia nueva entra como
--   'pendiente_revision' y el admin decide si la aprueba o rechaza.
-- ============================================================
DROP TABLE IF EXISTS incidencias;
CREATE TABLE incidencias (
    id_incidencia        INT             NOT NULL AUTO_INCREMENT PRIMARY KEY,

    titulo               VARCHAR(200)    NOT NULL,
    descripcion          TEXT            NULL,
    prioridad            ENUM('Baja','Media','Alta') NOT NULL DEFAULT 'Media',

    id_tipo              INT             NOT NULL,
    id_subtipo           INT             NULL,
    id_estado_actual     INT             NOT NULL,

    -- Aprobación / veracidad (pide el admin validar antes de ser pública)
    estado_aprobacion    ENUM('pendiente_revision','aprobada','rechazada') NOT NULL DEFAULT 'pendiente_revision',
    id_admin_revisor     INT             NULL,
    fecha_revision       TIMESTAMP       NULL,
    motivo_rechazo       VARCHAR(255)    NULL,

    id_zona              INT             NOT NULL,
    latitud              DECIMAL(10,6)   NULL,
    longitud             DECIMAL(10,6)   NULL,
    direccion_texto      VARCHAR(255)    NULL,

    fecha_ocurrencia     DATE            NOT NULL,
    hora_ocurrencia      TIME            NULL,
    fecha_resolucion     DATETIME        NULL,
    tiempo_resolucion_horas DECIMAL(10,2) NULL,

    reportante_nombre    VARCHAR(100)    NULL,
    reportante_contacto  VARCHAR(150)    NULL,

    id_usuario_creador   INT             NULL,
    fecha_registro       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_inc_tipo      FOREIGN KEY (id_tipo)          REFERENCES tipos_incidencia(id_tipo),
    CONSTRAINT fk_inc_subtipo   FOREIGN KEY (id_subtipo)       REFERENCES subtipos_incidencia(id_subtipo),
    CONSTRAINT fk_inc_estado    FOREIGN KEY (id_estado_actual) REFERENCES estados(id_estado),
    CONSTRAINT fk_inc_zona      FOREIGN KEY (id_zona)          REFERENCES zonas(id_zona),
    CONSTRAINT fk_inc_usuario   FOREIGN KEY (id_usuario_creador) REFERENCES usuarios(id_usuario),
    CONSTRAINT fk_inc_revisor   FOREIGN KEY (id_admin_revisor) REFERENCES usuarios(id_usuario)
);

CREATE INDEX idx_inc_estado     ON incidencias(id_estado_actual);
CREATE INDEX idx_inc_tipo       ON incidencias(id_tipo);
CREATE INDEX idx_inc_zona       ON incidencias(id_zona);
CREATE INDEX idx_inc_fecha      ON incidencias(fecha_ocurrencia);
CREATE INDEX idx_inc_prioridad  ON incidencias(prioridad);
CREATE INDEX idx_inc_aprobacion ON incidencias(estado_aprobacion);

-- ============================================================
-- BLOQUE 8: ASIGNACIONES (responsable / apoyo)
-- ============================================================
DROP TABLE IF EXISTS incidencia_asignaciones;
CREATE TABLE incidencia_asignaciones (
    id_asignacion  INT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_incidencia  INT       NOT NULL,
    id_usuario     INT       NOT NULL,
    rol_asignacion ENUM('responsable','apoyo') NOT NULL DEFAULT 'responsable',
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_asig_incidencia FOREIGN KEY (id_incidencia) REFERENCES incidencias(id_incidencia) ON DELETE CASCADE,
    CONSTRAINT fk_asig_usuario    FOREIGN KEY (id_usuario)    REFERENCES usuarios(id_usuario),
    UNIQUE KEY uq_asignacion (id_incidencia, id_usuario)
);

-- ============================================================
-- BLOQUE 9: APOYOS VOLUNTARIOS CON INCENTIVO ECONÓMICO
--   Un usuario marca "voy a apoyar" sobre una incidencia.
--   Queda pendiente_aprobacion hasta que el admin lo valide
--   (confirma que sí fue al lugar) y entonces se paga el monto.
-- ============================================================
DROP TABLE IF EXISTS incidencia_apoyos;
CREATE TABLE incidencia_apoyos (
    id_apoyo         INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_incidencia    INT           NOT NULL,
    id_usuario       INT           NOT NULL,
    monto_incentivo  DECIMAL(10,2) NOT NULL,         -- copiado de incentivos_prioridad al momento de marcar apoyo
    estado_pago      ENUM('pendiente_aprobacion','aprobado','rechazado','pagado') NOT NULL DEFAULT 'pendiente_aprobacion',
    comentario_usuario VARCHAR(255) NULL,             -- el usuario puede describir cómo apoyó
    id_admin_revisor  INT          NULL,
    comentario_admin   VARCHAR(255) NULL,
    fecha_apoyo       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    fecha_revision    TIMESTAMP    NULL,
    fecha_pago        TIMESTAMP    NULL,
    CONSTRAINT fk_apoyo_incidencia FOREIGN KEY (id_incidencia)   REFERENCES incidencias(id_incidencia) ON DELETE CASCADE,
    CONSTRAINT fk_apoyo_usuario    FOREIGN KEY (id_usuario)      REFERENCES usuarios(id_usuario),
    CONSTRAINT fk_apoyo_revisor    FOREIGN KEY (id_admin_revisor) REFERENCES usuarios(id_usuario),
    UNIQUE KEY uq_apoyo (id_incidencia, id_usuario)
);

CREATE INDEX idx_apoyo_estado ON incidencia_apoyos(estado_pago);

-- ============================================================
-- BLOQUE 10: HISTORIAL DE CAMBIOS DE ESTADO
-- ============================================================
DROP TABLE IF EXISTS incidencia_estados_historial;
CREATE TABLE incidencia_estados_historial (
    id_historial    INT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_incidencia   INT       NOT NULL,
    id_estado_anterior INT    NULL,
    id_estado_nuevo INT       NOT NULL,
    id_usuario      INT       NULL,
    comentario      VARCHAR(255) NULL,
    fecha_cambio    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_incidencia FOREIGN KEY (id_incidencia)      REFERENCES incidencias(id_incidencia) ON DELETE CASCADE,
    CONSTRAINT fk_hist_est_ant    FOREIGN KEY (id_estado_anterior) REFERENCES estados(id_estado),
    CONSTRAINT fk_hist_est_nuevo  FOREIGN KEY (id_estado_nuevo)    REFERENCES estados(id_estado),
    CONSTRAINT fk_hist_usuario    FOREIGN KEY (id_usuario)         REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- BLOQUE 11: COMENTARIOS / SEGUIMIENTO
-- ============================================================
DROP TABLE IF EXISTS incidencia_comentarios;
CREATE TABLE incidencia_comentarios (
    id_comentario  INT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_incidencia  INT       NOT NULL,
    id_usuario     INT       NULL,
    comentario     TEXT      NOT NULL,
    fecha          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_com_incidencia FOREIGN KEY (id_incidencia) REFERENCES incidencias(id_incidencia) ON DELETE CASCADE,
    CONSTRAINT fk_com_usuario    FOREIGN KEY (id_usuario)    REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- BLOQUE 12: NOTIFICACIONES
-- ============================================================
DROP TABLE IF EXISTS notificaciones;
CREATE TABLE notificaciones (
    id_notificacion INT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_usuario       INT       NOT NULL,
    id_incidencia    INT       NULL,
    titulo           VARCHAR(150) NOT NULL,
    mensaje          VARCHAR(255) NULL,
    leida            TINYINT(1) NOT NULL DEFAULT 0,
    fecha            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_usuario    FOREIGN KEY (id_usuario)    REFERENCES usuarios(id_usuario),
    CONSTRAINT fk_notif_incidencia FOREIGN KEY (id_incidencia) REFERENCES incidencias(id_incidencia) ON DELETE CASCADE
);

CREATE INDEX idx_notif_usuario_leida ON notificaciones(id_usuario, leida);

-- ============================================================
-- BLOQUE 13: HISTORIAL GENERAL DE ACTIVIDAD (auditoría completa)
--   Registra TODA acción relevante del sistema: quién, qué, cuándo.
--   Esto es lo que pediste como "historial que guarde todo".
-- ============================================================
DROP TABLE IF EXISTS historial_actividad;
CREATE TABLE historial_actividad (
    id_actividad   INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_usuario     INT          NULL,
    id_incidencia  INT          NULL,
    accion         VARCHAR(100) NOT NULL,   -- ej: 'creo_incidencia','aprobo_incidencia','marco_apoyo','aprobo_apoyo'
    detalle        VARCHAR(255) NULL,
    fecha_hora     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    ip_origen      VARCHAR(45)  NULL,
    CONSTRAINT fk_act_usuario    FOREIGN KEY (id_usuario)    REFERENCES usuarios(id_usuario),
    CONSTRAINT fk_act_incidencia FOREIGN KEY (id_incidencia) REFERENCES incidencias(id_incidencia) ON DELETE SET NULL
);

CREATE INDEX idx_act_fecha   ON historial_actividad(fecha_hora);
CREATE INDEX idx_act_usuario ON historial_actividad(id_usuario);
CREATE INDEX idx_act_accion  ON historial_actividad(accion);

SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- DATOS INICIALES — UBICACIÓN
-- ============================================================
INSERT INTO paises (nombre, codigo_iso) VALUES ('Ecuador', 'EC');
INSERT INTO provincias (id_pais, nombre) VALUES (1,'Guayas'), (1,'Pichincha'), (1,'Santa Elena');
INSERT INTO ciudades (id_provincia, nombre, latitud_ref, longitud_ref) VALUES
(1,'Guayaquil',-2.170998,-79.922359), (2,'Quito',-0.180653,-78.467838), (3,'La Libertad',-2.232450,-80.905610);

-- ============================================================
-- DATOS INICIALES — ZONAS
-- ============================================================
INSERT INTO zonas (id_ciudad, nombre, descripcion, latitud_ref, longitud_ref) VALUES
(1,'Planta Baja','Área de recepción y acceso principal',-2.900100,-79.005900),
(1,'Piso 1','Oficinas administrativas',-2.900200,-79.005800),
(1,'Piso 2','Área técnica y sistemas',-2.900300,-79.005700),
(1,'Piso 3','Gerencia y salas de reuniones',-2.900400,-79.005600),
(1,'Bodega','Almacén y logística',-2.900500,-79.005500),
(1,'Parqueadero','Zona de parqueo vehicular',-2.900600,-79.005400),
(1,'Sala de Servidores','Centro de datos principal',-2.900700,-79.005300),
(1,'Cafetería','Área de descanso y comedor',-2.900800,-79.005200);

-- ============================================================
-- DATOS INICIALES — TIPOS Y SUBTIPOS
-- ============================================================
INSERT INTO tipos_incidencia (nombre, descripcion, icono, color) VALUES
('Infraestructura','Daños en instalaciones físicas','bi-building','#f97316'),
('Equipos TI','Fallas en hardware o software','bi-pc-display','#6366f1'),
('Red y Conectividad','Problemas de red, internet o telefonía','bi-wifi-off','#3b82f6'),
('Seguridad','Incidentes de seguridad física o digital','bi-shield-exclamation','#ef4444'),
('Suministros','Falta o daño de materiales','bi-box-seam','#eab308'),
('Servicios Básicos','Agua, luz, clima, aseo','bi-lightning-charge','#10b981'),
('Accidentes','Accidentes laborales o de tránsito','bi-bandaid','#f43f5e');

INSERT INTO subtipos_incidencia (id_tipo, nombre) VALUES
(1,'Alumbrado'),(1,'Goteras / Filtraciones'),(1,'Puertas y accesos'),(1,'Mobiliario dañado'),
(2,'Computador no enciende'),(2,'Error de software'),(2,'Impresora'),(2,'Pérdida de datos'),
(3,'Internet lento'),(3,'Sin conexión'),(3,'Telefonía IP'),
(4,'Robo'),(4,'Acceso no autorizado'),(4,'Cámara dañada'),(4,'Alarma activada'),
(5,'Falta de insumos de oficina'),(5,'Falta de equipo de protección'),
(6,'Corte de energía'),(6,'Falla de climatización'),(6,'Falta de agua'),
(7,'Accidente laboral'),(7,'Accidente vehicular');

-- ============================================================
-- DATOS INICIALES — ESTADOS
-- ============================================================
INSERT INTO estados (nombre, descripcion, color, orden) VALUES
('Pendiente','Incidencia reportada, aún no atendida','#ef4444',1),
('En proceso','Incidencia siendo atendida por el responsable','#f59e0b',2),
('Resuelto','Incidencia solucionada','#22c55e',3),
('Cerrado','Incidencia verificada y cerrada oficialmente','#64748b',4);

-- ============================================================
-- DATOS INICIALES — INCENTIVOS POR PRIORIDAD
-- ============================================================
INSERT INTO incentivos_prioridad (prioridad, monto) VALUES
('Baja', 5.00),
('Media', 10.00),
('Alta', 20.00);

-- ============================================================
-- DATOS INICIALES — USUARIOS
--   Contraseña real para ambos en texto plano: "123456"
--   Hash bcrypt correspondiente (10 rounds) — válido para probar login.
-- ============================================================
INSERT INTO usuarios (nombre, apellido, correo, password, rol, telefono, saldo_incentivos) VALUES
('Admin',  'Sistema',  'admin@geoincidencias.com',  '$2a$10$Hz0R4QVLBRsMTAOJnyBx/OEMiN/iKE/A.SttM5zUT1BxP5NwZBpT2', 'admin',   '0990000000', 0.00),
('Carlos', 'Mendoza',  'cmendoza@empresa.com',      '$2a$10$Hz0R4QVLBRsMTAOJnyBx/OEMiN/iKE/A.SttM5zUT1BxP5NwZBpT2', 'usuario', '0991234567', 20.00),
('María',  'González', 'mgonzalez@empresa.com',     '$2a$10$Hz0R4QVLBRsMTAOJnyBx/OEMiN/iKE/A.SttM5zUT1BxP5NwZBpT2', 'usuario', '0992345678', 0.00),
('Pedro',  'Ramírez',  'pramirez@empresa.com',      '$2a$10$Hz0R4QVLBRsMTAOJnyBx/OEMiN/iKE/A.SttM5zUT1BxP5NwZBpT2', 'usuario', '0993456789', 5.00),
('Lucía',  'Torres',   'ltorres@empresa.com',       '$2a$10$Hz0R4QVLBRsMTAOJnyBx/OEMiN/iKE/A.SttM5zUT1BxP5NwZBpT2', 'usuario', '0994567890', 0.00);

-- ============================================================
-- DATOS DE EJEMPLO — INCIDENCIAS (algunas aprobadas, una pendiente)
-- ============================================================
INSERT INTO incidencias
  (titulo, descripcion, prioridad, id_tipo, id_subtipo, id_estado_actual, estado_aprobacion,
   id_admin_revisor, fecha_revision, id_zona, latitud, longitud, fecha_ocurrencia, hora_ocurrencia,
   reportante_nombre, reportante_contacto, id_usuario_creador)
VALUES
('Falla en servidor principal',
 'El servidor de base de datos no responde desde las 08:00.',
 'Alta', 2, 5, 2, 'aprobada', 1, '2026-06-15 09:00:00', 7, -2.900700, -79.005300,
 '2026-06-15', '08:15', 'Ana Suárez', '0997001122', 2),

('Corte de energía en piso 2',
 'Se fue la luz en el ala norte del piso 2.',
 'Alta', 6, 18, 2, 'aprobada', 1, '2026-06-15 10:00:00', 3, -2.900200, -79.005800,
 '2026-06-15', '09:30', 'Luis Paredes', '0997002233', 3),

('Filtración de agua en techo de bodega',
 'Se detectó humedad y goteo en el techo de la bodega sector B.',
 'Alta', 1, 2, 1, 'aprobada', 1, '2026-06-14 15:00:00', 5, -2.900500, -79.005500,
 '2026-06-14', '14:00', 'Roberto Mora', '0997003344', 4),

('Impresora de recepción fuera de servicio',
 'La impresora HP LaserJet de recepción muestra error de cabezal.',
 'Media', 2, 7, 3, 'aprobada', 1, '2026-06-13 17:00:00', 1, -2.900100, -79.005900,
 '2026-06-13', '16:45', 'Recepción', NULL, 5),

('Posible fuga de gas en cocina',
 'Olor extraño reportado en la cafetería, no confirmado aún.',
 'Alta', 6, 18, 1, 'pendiente_revision', NULL, NULL, 8, -2.900800, -79.005200,
 '2026-06-17', '07:50', 'Empleado anónimo', NULL, 3);

-- ============================================================
-- DATOS DE EJEMPLO — ASIGNACIONES
-- ============================================================
INSERT INTO incidencia_asignaciones (id_incidencia, id_usuario, rol_asignacion) VALUES
(1, 2, 'responsable'),
(2, 3, 'responsable'),
(3, 4, 'responsable'),
(4, 5, 'responsable');

-- ============================================================
-- DATOS DE EJEMPLO — APOYOS CON INCENTIVO
-- ============================================================
INSERT INTO incidencia_apoyos
  (id_incidencia, id_usuario, monto_incentivo, estado_pago, comentario_usuario, id_admin_revisor, comentario_admin, fecha_revision, fecha_pago)
VALUES
(1, 2, 20.00, 'pagado',              'Fui al data center a ayudar con el reinicio del servidor.', 1, 'Confirmado, asistencia verificada.', '2026-06-15 11:00:00', '2026-06-15 12:00:00'),
(4, 5, 5.00,  'pendiente_aprobacion','Voy a revisar la impresora esta tarde.', NULL, NULL, NULL, NULL);

-- ============================================================
-- DATOS DE EJEMPLO — HISTORIAL DE ESTADOS
-- ============================================================
INSERT INTO incidencia_estados_historial (id_incidencia, id_estado_anterior, id_estado_nuevo, id_usuario, comentario) VALUES
(1, NULL, 1, 1, 'Incidencia registrada'),
(1, 1, 2, 2, 'Técnico asignado, revisando el servidor'),
(4, NULL, 1, 1, 'Incidencia registrada'),
(4, 1, 2, 5, 'Revisando cabezal de impresión'),
(4, 2, 3, 5, 'Cabezal reemplazado, impresora funcionando');

-- ============================================================
-- DATOS DE EJEMPLO — COMENTARIOS
-- ============================================================
INSERT INTO incidencia_comentarios (id_incidencia, id_usuario, comentario) VALUES
(1, 2, 'Revisando logs del servidor, parece ser un problema de memoria.'),
(1, 1, 'Confirmado por administración, prioridad máxima.');

-- ============================================================
-- DATOS DE EJEMPLO — NOTIFICACIONES
-- ============================================================
INSERT INTO notificaciones (id_usuario, id_incidencia, titulo, mensaje, leida) VALUES
(2, 1, 'Incentivo pagado', 'Tu apoyo en la incidencia #1 fue aprobado y pagado: $20.00', 1),
(5, 4, 'Apoyo registrado', 'Tu solicitud de apoyo está pendiente de aprobación del administrador.', 0),
(1, 5, 'Nueva incidencia por revisar', 'Hay una incidencia nueva pendiente de aprobación: "Posible fuga de gas".', 0);

-- ============================================================
-- DATOS DE EJEMPLO — HISTORIAL DE ACTIVIDAD (auditoría)
-- ============================================================
INSERT INTO historial_actividad (id_usuario, id_incidencia, accion, detalle) VALUES
(2, 1, 'creo_incidencia', 'Usuario Carlos Mendoza registró la incidencia "Falla en servidor principal"'),
(1, 1, 'aprobo_incidencia', 'Admin aprobó la incidencia #1'),
(2, 1, 'marco_apoyo', 'Carlos Mendoza marcó apoyo voluntario en incidencia #1'),
(1, 1, 'aprobo_apoyo', 'Admin aprobó y pagó el incentivo de $20.00 a Carlos Mendoza'),
(3, 5, 'creo_incidencia', 'Usuario María González registró la incidencia "Posible fuga de gas en cocina"'),
(5, 4, 'marco_apoyo', 'Pedro Ramírez marcó apoyo voluntario en incidencia #4, pendiente de aprobación');


-- ============================================================
-- VISTAS PARA CONSULTAS, AGRUPACIONES Y MÉTRICAS
-- ============================================================

CREATE OR REPLACE VIEW vw_incidencias_completo AS
SELECT
    i.id_incidencia, i.titulo, i.descripcion, i.prioridad,
    ti.nombre AS tipo, st.nombre AS subtipo,
    e.nombre AS estado, e.color AS color_estado,
    i.estado_aprobacion,
    z.nombre AS zona, c.nombre AS ciudad, p.nombre AS provincia, pa.nombre AS pais,
    i.latitud, i.longitud,
    i.fecha_ocurrencia, i.hora_ocurrencia, i.fecha_resolucion, i.tiempo_resolucion_horas,
    i.fecha_registro, i.fecha_actualizacion,
    i.reportante_nombre, i.reportante_contacto,
    CONCAT(uc.nombre,' ',IFNULL(uc.apellido,'')) AS creado_por,
    i.id_tipo, i.id_subtipo, i.id_estado_actual, i.id_zona, i.id_usuario_creador
FROM incidencias i
INNER JOIN tipos_incidencia ti  ON i.id_tipo = ti.id_tipo
LEFT  JOIN subtipos_incidencia st ON i.id_subtipo = st.id_subtipo
INNER JOIN estados e            ON i.id_estado_actual = e.id_estado
INNER JOIN zonas z              ON i.id_zona = z.id_zona
INNER JOIN ciudades c           ON z.id_ciudad = c.id_ciudad
INNER JOIN provincias p         ON c.id_provincia = p.id_provincia
INNER JOIN paises pa            ON p.id_pais = pa.id_pais
LEFT  JOIN usuarios uc          ON i.id_usuario_creador = uc.id_usuario;

CREATE OR REPLACE VIEW vw_resumen_dashboard AS
SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN e.nombre='Pendiente'  THEN 1 ELSE 0 END) AS pendientes,
    SUM(CASE WHEN e.nombre='En proceso' THEN 1 ELSE 0 END) AS en_proceso,
    SUM(CASE WHEN e.nombre='Resuelto'   THEN 1 ELSE 0 END) AS resueltas,
    SUM(CASE WHEN e.nombre='Cerrado'    THEN 1 ELSE 0 END) AS cerradas,
    SUM(CASE WHEN i.prioridad='Alta'    THEN 1 ELSE 0 END) AS alta_prioridad,
    SUM(CASE WHEN i.estado_aprobacion='pendiente_revision' THEN 1 ELSE 0 END) AS pendientes_aprobacion
FROM incidencias i
INNER JOIN estados e ON i.id_estado_actual = e.id_estado;

CREATE OR REPLACE VIEW vw_incidencias_por_tipo AS
SELECT ti.nombre AS tipo, COUNT(i.id_incidencia) AS total
FROM tipos_incidencia ti
LEFT JOIN incidencias i ON ti.id_tipo = i.id_tipo AND i.estado_aprobacion='aprobada'
GROUP BY ti.nombre;

CREATE OR REPLACE VIEW vw_incidencias_por_estado AS
SELECT e.nombre AS estado, e.color, COUNT(i.id_incidencia) AS total
FROM estados e
LEFT JOIN incidencias i ON e.id_estado = i.id_estado_actual AND i.estado_aprobacion='aprobada'
GROUP BY e.nombre, e.color;

CREATE OR REPLACE VIEW vw_incidencias_por_zona AS
SELECT z.nombre AS zona, COUNT(i.id_incidencia) AS total
FROM zonas z
LEFT JOIN incidencias i ON z.id_zona = i.id_zona AND i.estado_aprobacion='aprobada'
GROUP BY z.nombre;

CREATE OR REPLACE VIEW vw_tiempo_promedio_resolucion AS
SELECT
    AVG(TIMESTAMPDIFF(HOUR, i.fecha_registro, i.fecha_resolucion)) AS horas_promedio,
    COUNT(*) AS total_resueltas
FROM incidencias i
WHERE i.fecha_resolucion IS NOT NULL;

-- Vista de apoyos con datos completos (para el panel admin de incentivos)
CREATE OR REPLACE VIEW vw_apoyos_completo AS
SELECT
    a.id_apoyo, a.id_incidencia, i.titulo AS incidencia_titulo, i.prioridad,
    CONCAT(u.nombre,' ',IFNULL(u.apellido,'')) AS usuario, u.id_usuario,
    a.monto_incentivo, a.estado_pago, a.comentario_usuario, a.comentario_admin,
    a.fecha_apoyo, a.fecha_revision, a.fecha_pago
FROM incidencia_apoyos a
INNER JOIN incidencias i ON a.id_incidencia = i.id_incidencia
INNER JOIN usuarios u    ON a.id_usuario    = u.id_usuario;

-- Resumen de incentivos por usuario (para mostrar "mis ganancias")
CREATE OR REPLACE VIEW vw_incentivos_por_usuario AS
SELECT
    u.id_usuario, CONCAT(u.nombre,' ',IFNULL(u.apellido,'')) AS usuario,
    SUM(CASE WHEN a.estado_pago='pagado' THEN a.monto_incentivo ELSE 0 END) AS total_pagado,
    SUM(CASE WHEN a.estado_pago='pendiente_aprobacion' THEN a.monto_incentivo ELSE 0 END) AS total_pendiente,
    COUNT(CASE WHEN a.estado_pago='pagado' THEN 1 END) AS apoyos_completados
FROM usuarios u
LEFT JOIN incidencia_apoyos a ON u.id_usuario = a.id_usuario
GROUP BY u.id_usuario, u.nombre, u.apellido;

-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================
