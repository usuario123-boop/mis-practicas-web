-- =============================================================================
-- Sistema de Gestión de Documentos de una Oficina
-- Escuela de Tecnologías Digitales Aplicadas (ETDA) · Campus IV Tapachula
-- Universidad Autónoma de Chiapas
--
-- Script de creación de la base de datos, tablas, índices y datos de prueba.
-- Ejecutar en phpMyAdmin o con:  mysql -u root -p < database.sql
-- =============================================================================

CREATE DATABASE IF NOT EXISTS gestion_documentos
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE gestion_documentos;

-- -----------------------------------------------------------------------------
-- Tabla: documentos
-- Registro de cada oficio/documento recibido en la oficina.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS documentos (
    id              INT AUTO_INCREMENT PRIMARY KEY          COMMENT 'Identificador único',
    no_oficio       VARCHAR(50)   NOT NULL                  COMMENT 'Número o folio del oficio',
    fecha           DATE          NOT NULL                  COMMENT 'Fecha de recepción',
    asunto          VARCHAR(255)  NOT NULL                  COMMENT 'Descripción breve',
    remitente       VARCHAR(150)  NOT NULL                  COMMENT 'Persona, dependencia o institución que remite',
    destinatario    VARCHAR(150)  NOT NULL                  COMMENT 'Persona, área o dependencia destinataria',
    estatus         ENUM('Recibido','En proceso','Atendido') NOT NULL DEFAULT 'Recibido',
    archivo_pdf     VARCHAR(255)  NOT NULL                  COMMENT 'Ruta relativa del archivo digitalizado',
    archivo_nombre  VARCHAR(255)  DEFAULT NULL              COMMENT 'Nombre del archivo dentro de la carpeta pdf/',
    archivo_tam     INT           DEFAULT 0                 COMMENT 'Tamaño en bytes',
    github_url      VARCHAR(500)  DEFAULT NULL              COMMENT 'URL del respaldo versionado en GitHub',
    creado_en       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices que aceleran el buscador y los filtros del listado
    INDEX idx_no_oficio    (no_oficio),
    INDEX idx_fecha        (fecha),
    INDEX idx_estatus      (estatus),
    INDEX idx_remitente    (remitente),
    INDEX idx_destinatario (destinatario),
    FULLTEXT KEY ft_busqueda (no_oficio, asunto, remitente, destinatario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabla: usuarios
-- Personal de la oficina que opera la plataforma.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(120) NOT NULL,
    correo      VARCHAR(150) NOT NULL UNIQUE,
    area        VARCHAR(120) NOT NULL DEFAULT 'ETDA - Campus IV',
    rol         ENUM('Administrador','Capturista','Consulta') NOT NULL DEFAULT 'Capturista',
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabla: bitacora
-- Historial de cambios de estatus de cada documento (trazabilidad).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bitacora (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    documento_id   INT          NOT NULL,
    accion         VARCHAR(80)  NOT NULL,
    detalle        VARCHAR(255) DEFAULT NULL,
    registrado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_documento (documento_id),
    CONSTRAINT fk_bitacora_documento
        FOREIGN KEY (documento_id) REFERENCES documentos(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Datos de prueba
-- -----------------------------------------------------------------------------
INSERT INTO usuarios (nombre, correo, area, rol) VALUES
    ('Administrador del Sistema', 'admin@unach.mx',      'ETDA - Campus IV',           'Administrador'),
    ('Recepción de Oficios',      'recepcion@unach.mx',  'Coordinación Administrativa','Capturista'),
    ('Dirección ETDA',            'direccion@unach.mx',  'Dirección ETDA',             'Consulta')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

INSERT INTO documentos (no_oficio, fecha, asunto, remitente, destinatario, estatus, archivo_pdf, archivo_nombre) VALUES
    ('001/2026', '2026-10-05', 'Solicitud de materiales',  'Dirección Administrativa',  'Coordinación Académica',      'Recibido',   'pdf/ejemplo.pdf', 'ejemplo.pdf'),
    ('002/2026', '2026-10-04', 'Invitación a evento',      'Secretaría General',        'Dirección ETDA',              'En proceso', 'pdf/ejemplo.pdf', 'ejemplo.pdf'),
    ('003/2026', '2026-10-03', 'Reporte de actividades',   'Departamento de Sistemas',  'Dirección ETDA',              'Atendido',   'pdf/ejemplo.pdf', 'ejemplo.pdf'),
    ('004/2026', '2026-10-02', 'Solicitud de apoyo',       'Institución Externa',       'Coordinación de Investigación','Recibido',  'pdf/ejemplo.pdf', 'ejemplo.pdf'),
    ('005/2026', '2026-10-01', 'Oficio de colaboración',   'C. Municipal de Tapachula', 'Rectoría',                    'En proceso', 'pdf/ejemplo.pdf', 'ejemplo.pdf');
