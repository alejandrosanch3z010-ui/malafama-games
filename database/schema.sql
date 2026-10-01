-- ============================================================
--  Mala Fama Games — Esquema de la base de datos (MySQL / MariaDB)
--  Tienda de videojuegos de PlayStation 5 · Equipo Mala Fama Crew · ITESI
--  Crea la base desde cero. Después importa seed.sql para los datos de prueba.
-- ============================================================
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS malafama_games;
CREATE DATABASE malafama_games CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE malafama_games;

-- ---------- Catálogo ----------
CREATE TABLE generos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(40) NOT NULL UNIQUE,
    activo TINYINT(1) NOT NULL DEFAULT 1            -- baja lógica
);

CREATE TABLE juegos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    titulo VARCHAR(120) NOT NULL,
    genero_id INT NOT NULL,
    sinopsis TEXT NOT NULL,
    fecha_lanzamiento DATE NOT NULL,
    desarrollador VARCHAR(80) NOT NULL,
    clasificacion VARCHAR(10) NOT NULL,            -- ESRB: E, E10+, T, M, RP
    voces VARCHAR(120) NOT NULL,
    subtitulos VARCHAR(120) NOT NULL,
    requiere_internet TINYINT(1) NOT NULL DEFAULT 0,
    nota_internet VARCHAR(120) NULL,
    trailer_youtube VARCHAR(20) NULL,              -- ID del video (lo que va después de v=)
    portada VARCHAR(150) NULL,
    calificacion DECIMAL(2,1) NOT NULL DEFAULT 0,  -- se recalcula con las reseñas
    total_resenas INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,          -- baja lógica
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_juego_genero FOREIGN KEY (genero_id) REFERENCES generos(id),
    CONSTRAINT chk_calificacion CHECK (calificacion BETWEEN 0 AND 5),
    INDEX idx_juegos_activo (activo),
    INDEX idx_juegos_fecha (fecha_lanzamiento)
);

-- Cada juego puede venderse en físico, en digital o en ambos
CREATE TABLE ediciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    juego_id INT NOT NULL,
    formato ENUM('fisico','digital') NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    precio_oferta DECIMAL(10,2) NULL,
    stock INT NOT NULL DEFAULT 0,
    UNIQUE (juego_id, formato),
    CONSTRAINT fk_edicion_juego FOREIGN KEY (juego_id) REFERENCES juegos(id) ON DELETE CASCADE,
    CONSTRAINT chk_precio CHECK (precio > 0),
    CONSTRAINT chk_oferta CHECK (precio_oferta IS NULL OR (precio_oferta > 0 AND precio_oferta < precio)),
    CONSTRAINT chk_stock CHECK (stock >= 0)
);

CREATE TABLE capturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    juego_id INT NOT NULL,
    ruta VARCHAR(150) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_captura_juego FOREIGN KEY (juego_id) REFERENCES juegos(id) ON DELETE CASCADE
);

CREATE TABLE tarjetas_psn (
    id INT AUTO_INCREMENT PRIMARY KEY,
    monto DECIMAL(10,2) NOT NULL,                  -- saldo que da la tarjeta
    precio DECIMAL(10,2) NOT NULL,                 -- lo que paga el cliente
    stock INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT chk_tarjeta CHECK (monto > 0 AND precio > 0 AND stock >= 0)
);

-- ---------- Usuarios y roles ----------
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,           -- bcrypt (password_hash de PHP)
    rol ENUM('admin','empleado','cliente') NOT NULL DEFAULT 'cliente',
    telefono VARCHAR(20) NULL,
    direccion VARCHAR(255) NULL,
    puntos INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,          -- baja lógica: no se borran usuarios con pedidos
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_puntos CHECK (puntos >= 0),
    INDEX idx_usuarios_rol (rol)
);

CREATE TABLE resenas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    juego_id INT NOT NULL,
    usuario_id INT NOT NULL,
    calificacion TINYINT NOT NULL,
    comentario TEXT NULL,
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (juego_id, usuario_id),                 -- una reseña por cliente y juego
    CONSTRAINT fk_resena_juego FOREIGN KEY (juego_id) REFERENCES juegos(id) ON DELETE CASCADE,
    CONSTRAINT fk_resena_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    CONSTRAINT chk_resena CHECK (calificacion BETWEEN 1 AND 5)
);

CREATE TABLE resenas_tienda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,                -- una opinión por cliente
    calificacion TINYINT NOT NULL,
    comentario TEXT NULL,
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_opinion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    CONSTRAINT chk_opinion CHECK (calificacion BETWEEN 1 AND 5)
);

CREATE TABLE wishlist (
    usuario_id INT NOT NULL,
    juego_id INT NOT NULL,
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, juego_id),
    CONSTRAINT fk_deseo_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_deseo_juego FOREIGN KEY (juego_id) REFERENCES juegos(id) ON DELETE CASCADE
);

-- ---------- Ventas ----------
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folio VARCHAR(20) NULL UNIQUE,                 -- MF-2026-000001
    usuario_id INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    descuento_puntos DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    iva DECIMAL(10,2) NOT NULL DEFAULT 0,          -- IVA 16% incluido en el total
    puntos_usados INT NOT NULL DEFAULT 0,
    puntos_ganados INT NOT NULL DEFAULT 0,
    metodo_pago ENUM('tarjeta','paypal','mercadopago','oxxo') NOT NULL,
    estado ENUM('pagado','preparando','enviado','entregado','cancelado') NOT NULL DEFAULT 'pagado',
    direccion_envio VARCHAR(255) NULL,
    numero_guia VARCHAR(40) NULL,
    motivo_cancelacion VARCHAR(255) NULL,
    cancelado_por INT NULL,
    fecha_cancelacion DATETIME NULL,
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pedido_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    CONSTRAINT fk_pedido_cancelo FOREIGN KEY (cancelado_por) REFERENCES usuarios(id),
    CONSTRAINT chk_totales CHECK (subtotal >= 0 AND total >= 0 AND descuento_puntos >= 0),
    INDEX idx_pedidos_creado (creado),
    INDEX idx_pedidos_estado (estado)
);

CREATE TABLE pedido_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    edicion_id INT NULL,                           -- juego (físico o digital)
    tarjeta_id INT NULL,                           -- o tarjeta PSN
    cantidad INT NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_detalle_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    CONSTRAINT fk_detalle_edicion FOREIGN KEY (edicion_id) REFERENCES ediciones(id),
    CONSTRAINT fk_detalle_tarjeta FOREIGN KEY (tarjeta_id) REFERENCES tarjetas_psn(id),
    CONSTRAINT chk_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_un_producto CHECK ((edicion_id IS NULL) <> (tarjeta_id IS NULL))
);

CREATE TABLE codigos_digitales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_detalle_id INT NOT NULL,
    codigo VARCHAR(40) NOT NULL UNIQUE,
    activo TINYINT(1) NOT NULL DEFAULT 1,          -- se desactiva si el pedido se cancela
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_codigo_detalle FOREIGN KEY (pedido_detalle_id) REFERENCES pedido_detalle(id) ON DELETE CASCADE
);

-- Programa de lealtad: puntos ganados (+) y usados (-)
CREATE TABLE puntos_movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    pedido_id INT NULL,
    puntos INT NOT NULL,
    concepto VARCHAR(120) NOT NULL,
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_puntos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_puntos_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE SET NULL
);

-- Movimientos de inventario: ventas (-), cancelaciones (+) y ajustes manuales
CREATE TABLE movimientos_inventario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    edicion_id INT NULL,
    tarjeta_id INT NULL,
    pedido_id INT NULL,
    usuario_id INT NULL,                           -- quién hizo el movimiento
    tipo ENUM('venta','cancelacion','ajuste') NOT NULL,
    cantidad INT NOT NULL,                         -- negativo = salida, positivo = entrada
    stock_resultante INT NOT NULL,
    nota VARCHAR(160) NULL,
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mov_edicion FOREIGN KEY (edicion_id) REFERENCES ediciones(id),
    CONSTRAINT fk_mov_tarjeta FOREIGN KEY (tarjeta_id) REFERENCES tarjetas_psn(id),
    CONSTRAINT fk_mov_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE SET NULL,
    CONSTRAINT fk_mov_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_mov_creado (creado)
);

-- Historial (auditoría) de operaciones importantes
CREATE TABLE historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,                           -- quién la hizo (NULL = sistema)
    accion VARCHAR(40) NOT NULL,                   -- crear, editar, baja, cambiar_estado, cancelar, ajuste_stock...
    entidad VARCHAR(40) NOT NULL,                  -- juego, genero, usuario, pedido, edicion...
    entidad_id INT NULL,
    antes TEXT NULL,                               -- estado anterior (JSON)
    despues TEXT NULL,                             -- estado nuevo (JSON)
    ip VARCHAR(45) NULL,
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_historial_creado (creado),
    INDEX idx_historial_entidad (entidad, entidad_id)
);
