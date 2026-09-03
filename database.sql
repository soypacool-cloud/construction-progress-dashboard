-- Construction Progress Dashboard
-- Demo database with fictional data

CREATE DATABASE IF NOT EXISTS construction_dashboard
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE construction_dashboard;

DROP TABLE IF EXISTS avance_detalle;
DROP TABLE IF EXISTS proyectos;
DROP TABLE IF EXISTS catalogo_procesos;

CREATE TABLE catalogo_procesos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    ponderacion DECIMAL(6,2) NOT NULL DEFAULT 0,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE proyectos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    ubicacion VARCHAR(150) DEFAULT NULL,
    fecha_inicio DATE DEFAULT NULL,
    fecha_fin_estimada DATE DEFAULT NULL,
    estado ENUM('PENDIENTE','EN PROCESO','TERMINADO') NOT NULL DEFAULT 'PENDIENTE',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE avance_detalle (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT UNSIGNED NOT NULL,
    proceso_id INT UNSIGNED NOT NULL,
    avance_ejecutado DECIMAL(5,2) NOT NULL DEFAULT 0,
    fecha DATE NOT NULL,
    observaciones VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_avance_proyecto
        FOREIGN KEY (proyecto_id) REFERENCES proyectos(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_avance_proceso
        FOREIGN KEY (proceso_id) REFERENCES catalogo_procesos(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY uk_proyecto_proceso_fecha (proyecto_id, proceso_id, fecha)
) ENGINE=InnoDB;

INSERT INTO catalogo_procesos (nombre, ponderacion, orden) VALUES
('PRELIMINARES', 5.00, 1),
('CIMENTACIÓN', 10.00, 2),
('ESTRUCTURA', 15.00, 3),
('MUROS', 10.00, 4),
('INSTALACIÓN ELÉCTRICA', 10.00, 5),
('INSTALACIÓN HIDRÁULICA', 10.00, 6),
('INSTALACIÓN SANITARIA', 10.00, 7),
('ACABADOS', 15.00, 8),
('CARPINTERÍA', 5.00, 9),
('PINTURA', 5.00, 10),
('LIMPIEZA Y ENTREGA', 5.00, 11);

INSERT INTO proyectos
(codigo, nombre, ubicacion, fecha_inicio, fecha_fin_estimada, estado) VALUES
('OBR-001', 'Residencial Las Flores', 'Morelia, Michoacán', '2026-01-15', '2026-11-30', 'EN PROCESO'),
('OBR-002', 'Fraccionamiento Los Pinos', 'Morelia, Michoacán', '2026-02-01', '2026-12-15', 'EN PROCESO'),
('OBR-003', 'Casa Modelo Norte', 'Uruapan, Michoacán', '2026-03-10', '2026-09-30', 'TERMINADO'),
('OBR-004', 'Residencial La Hacienda', 'Zamora, Michoacán', '2026-04-05', '2027-01-30', 'PENDIENTE');

INSERT INTO avance_detalle
(proyecto_id, proceso_id, avance_ejecutado, fecha, observaciones) VALUES
(1,1,100,'2026-08-01','Proceso terminado'),
(1,2,100,'2026-08-01','Proceso terminado'),
(1,3,80,'2026-08-15','Avance estructural'),
(1,4,65,'2026-08-15','Muros en proceso'),
(1,5,40,'2026-08-20','Instalación iniciada'),
(1,6,30,'2026-08-20','Instalación iniciada'),
(2,1,100,'2026-08-01','Proceso terminado'),
(2,2,70,'2026-08-15','Cimentación en proceso'),
(2,3,25,'2026-08-20','Estructura iniciada'),
(3,1,100,'2026-07-01','Proceso terminado'),
(3,2,100,'2026-07-01','Proceso terminado'),
(3,3,100,'2026-07-15','Proceso terminado'),
(3,4,100,'2026-07-15','Proceso terminado'),
(3,5,100,'2026-07-20','Proceso terminado'),
(3,6,100,'2026-07-20','Proceso terminado'),
(3,7,100,'2026-07-25','Proceso terminado'),
(3,8,100,'2026-08-01','Proceso terminado'),
(3,9,100,'2026-08-05','Proceso terminado'),
(3,10,100,'2026-08-10','Proceso terminado'),
(3,11,100,'2026-08-15','Proyecto terminado');
