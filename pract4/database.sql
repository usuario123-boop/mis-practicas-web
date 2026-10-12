-- Base de datos del sistema de gestión de documentos.
-- Importar desde phpMyAdmin o con:  mysql -u root -p < database.sql

CREATE DATABASE IF NOT EXISTS gestion_documentos
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE gestion_documentos;

CREATE TABLE IF NOT EXISTS documentos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    no_oficio       VARCHAR(50)   NOT NULL,
    fecha           DATE          NOT NULL,
    asunto          VARCHAR(255)  NOT NULL,
    remitente       VARCHAR(150)  NOT NULL,
    destinatario    VARCHAR(150)  NOT NULL,
    estatus         ENUM('Recibido','En proceso','Atendido') NOT NULL DEFAULT 'Recibido',
    archivo_pdf     VARCHAR(255)  NOT NULL,
    archivo_nombre  VARCHAR(255)  DEFAULT NULL,
    archivo_tam     INT           DEFAULT 0,
    github_url      VARCHAR(500)  DEFAULT NULL,
    creado_en       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices para el buscador y los filtros del listado
    INDEX idx_no_oficio    (no_oficio),
    INDEX idx_fecha        (fecha),
    INDEX idx_estatus      (estatus),
    INDEX idx_remitente    (remitente),
    INDEX idx_destinatario (destinatario),
    FULLTEXT KEY ft_busqueda (no_oficio, asunto, remitente, destinatario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS sesiones (
    id          VARCHAR(128) PRIMARY KEY,
    datos       MEDIUMBLOB   NOT NULL,
    actualizada INT UNSIGNED NOT NULL,
    INDEX idx_actualizada (actualizada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
