# Mala Fama Games

Tienda en línea de videojuegos de **PlayStation 5** (físicos, digitales y tarjetas PSN) con carrito, pagos simulados, programa de puntos, preventas, panel de administración, reportes y API REST.

> **Proyecto escolar.** No es una tienda real: los pagos son simulados, no se cobra ni se envía nada y no se guardan datos de tarjetas. Los juegos y marcas pertenecen a sus dueños y se usan con fines educativos.

- **Institución:** Instituto Tecnológico Superior de Irapuato (ITESI) · Ingeniería en Informática
- **Equipo:** Mala Fama Crew
- **Tipo de proyecto:** Punto de Venta Personalizado (Proyecto 6)
- **Sistema publicado:** https://malafamacrew.com _(actualizar con el subdominio final)_
- **Repositorio:** _(agregar enlace de GitHub)_

## Integrantes

| Nombre | Número de control |
|---|---|
| David Alejandro Sanchez Vital | IS23110831 |
| Brandon Cristóbal Olivares | IS23110021 |
| Alexis Antonio Mosqueda Vargas | IS23110759 |
| Juan Pablo González Martínez | IS23110457 |

## Objetivo

Desarrollar un punto de venta en línea especializado en juegos de PS5 que permita a los clientes buscar, comparar y comprar juegos en físico o digital, y al personal administrar el catálogo, el inventario, los pedidos y consultar reportes de ventas, con reglas de negocio, seguridad y una API REST.

**Características diferenciadoras** (Proyecto 6): programa de puntos de lealtad, preventas (apartados) con contador, venta de códigos digitales y tarjetas PSN, y política de devoluciones con cancelación controlada.

## Tecnologías

| Capa | Tecnología |
|---|---|
| Frontend | HTML5, CSS3, JavaScript (sin frameworks), Chart.js 4 para gráficas, GSAP 3.15 (Flip, DrawSVG, ScrambleText) para animaciones |
| API REST | PHP 8 con JSON, enrutador propio (`api/enrutador.php`) |
| Backend | PHP 8 + PDO (consultas parametrizadas) |
| Base de datos | MySQL / MariaDB (XAMPP) |
| Servidor | Apache (XAMPP en local, Hostinger en producción) |
| Control de versiones | Git + GitHub |

## Arquitectura

```text
USUARIO
   ↓
FRONTEND (páginas .php + js/api.js, js/catalogo.js, js/admin.js)
   ↓  fetch() con JSON y token CSRF
API REST (api/enrutador.php → api/recursos/*.php)
   ↓
BACKEND (includes/: reglas de negocio, sesiones, permisos, transacciones)
   ↓  PDO con consultas parametrizadas
BASE DE DATOS (MySQL / MariaDB)
```

El JavaScript del navegador nunca se conecta a la base de datos: todo pasa por la API, que valida, revisa permisos y aplica las reglas de negocio. Las páginas del cliente también funcionan sin JavaScript como respaldo, usando las mismas funciones del backend.

## Estructura

```text
malafama-games/
├── api/                  API REST
│   ├── enrutador.php     Enrutador
│   ├── nucleo.php        Respuestas JSON, errores, autenticación, permisos, validación, paginación
│   └── recursos/         Endpoints por recurso (api-auth.php, api-juegos.php, api-pedidos.php...)
├── includes/             Backend
│   ├── config.php        Lee el archivo .env
│   ├── db.php            Conexión PDO
│   ├── sesion.php        Sesión, CSRF, mensajes
│   ├── negocio.php       Roles/permisos, historial, venta, cancelación, envío, inventario
│   ├── logica-carrito.php Carrito, puntos, IVA, códigos
│   └── funciones.php     Búsqueda de juegos y componentes de interfaz
├── js/                   Frontend (api.js, catalogo.js, admin.js, animaciones.js, main.js, hero.js)
│   └── vendor/           Chart.js y GSAP (copias locales, funcionan sin internet)
├── css/                  Estilos
├── img/                  Logo, favicon y portadas
├── database/             schema.sql, seed.sql e INSTRUCCIONES.md
├── docs/                 api.md, database.md, git.md
├── admin.php             Panel de administración
├── *.php                 Páginas de la tienda
├── .htaccess             Envía /api/... al enrutador y protege archivos
├── .env.example          Variables de entorno de ejemplo
└── .gitignore
```

## Requisitos

- XAMPP con PHP 8.0 o superior y MariaDB/MySQL (Apache con `mod_rewrite`, que XAMPP ya trae activo).
- Navegador actualizado (Chrome, Edge o Firefox).

## Instalación

1. Copia la carpeta `malafama-games` dentro de `C:\xampp\htdocs\`.
2. En el panel de XAMPP enciende **Apache** y **MySQL**.
3. Abre `http://localhost/phpmyadmin` → **Importar** e importa, en este orden:
   1. `database/schema.sql` (crea la base `malafama_games` desde cero)
   2. `database/seed.sql` (datos de prueba)
4. Copia `.env.example` como `.env` y ajusta los datos si tu MySQL tiene contraseña.
5. Abre `http://localhost/malafama-games`.

## Variables de entorno

Se leen del archivo `.env` (no se sube a Git; se incluye `.env.example`):

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=malafama_games
DB_USER=root
DB_PASSWORD=
APP_ENV=development
APP_ZONA_HORARIA=America/Mexico_City
```

## Usuarios de prueba

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | admin@malafama.mx | admin1234 |
| Empleado | empleado@malafama.mx | empleado1234 |
| Cliente | brandon@demo.mx, alexis@demo.mx, juanpablo@demo.mx, mariana@demo.mx, diego@demo.mx, fernanda@demo.mx, luis@demo.mx, valeria@demo.mx | demo1234 |

## Roles y permisos

| Permiso | Administrador | Empleado | Cliente |
|---|:---:|:---:|:---:|
| Comprar, lista de deseos, reseñas, su cuenta | ✅ | ✅ | ✅ |
| Ver panel, dashboard y reportes | ✅ | ✅ | ❌ |
| Ver todos los pedidos y avanzar envíos | ✅ | ✅ | ❌ |
| Ajustar existencias | ✅ | ✅ | ❌ |
| CRUD de juegos, precios y tarjetas PSN | ✅ | ❌ | ❌ |
| CRUD de géneros | ✅ | ❌ | ❌ |
| CRUD de usuarios y roles | ✅ | ❌ | ❌ |
| Cancelar ventas | ✅ | ❌ | ❌ |
| Historial de operaciones | ✅ | ❌ | ❌ |

Los permisos se revisan en el servidor en cada endpoint (`conPermiso()` en `api/nucleo.php`), no solo ocultando menús.

## Reglas de negocio

1. **No se vende sin existencias:** al pagar se bloquean las filas de inventario (`SELECT ... FOR UPDATE`) y se valida que alcance.
2. **Máximo 5 piezas por producto** en el carrito y nunca más de lo que hay en existencia.
3. **La venta es una transacción:** crear pedido + detalle + descontar inventario + registrar movimiento + códigos + puntos + historial; si algo falla, `ROLLBACK`.
4. **Total, IVA y folio automáticos:** los precios incluyen IVA (16 %), que se desglosa; cada venta recibe un folio único `MF-AAAA-000000`.
5. **Programa de puntos:** se gana el 5 % del precio de cada juego (las tarjetas PSN no generan puntos); cada punto vale $1 y con puntos se paga como máximo el 50 % del subtotal.
6. **Las ventas no se eliminan:** solo el administrador puede cancelarlas, con motivo, y únicamente si no han sido enviadas o entregadas. Al cancelar se regresa el inventario, se anulan los códigos y se revierten los puntos.
7. **El envío avanza en orden:** pagado → preparando → enviado (genera número de guía) → entregado. Los pedidos solo digitales se entregan al instante.
8. **Una reseña por cliente y juego**, de 1 a 5 estrellas; las preventas no se pueden reseñar hasta que salgan.
9. **Baja lógica:** juegos, géneros, usuarios y tarjetas no se borran, se desactivan; un género con juegos activos no se puede dar de baja y un administrador no puede darse de baja a sí mismo.
10. **Alerta de existencias bajas:** los juegos físicos con 5 piezas o menos aparecen en el dashboard y en el reporte de inventario.
11. **Precio de oferta** siempre menor al precio normal y mayor a 0.
12. **Tarjeta de prueba 0002:** cualquier tarjeta que termine en 0002 se rechaza, para demostrar un pago fallido.
13. **Versiones:** un juego puede venderse en varias versiones (Estándar, Deluxe, Ultimate...) y cada una en físico y/o digital, con su propio precio, existencia y contenido; no se puede repetir la misma versión en el mismo formato.

## Endpoints principales

Documentación completa en [`docs/api.md`](docs/api.md).

| Método | Endpoint | Permiso |
|---|---|---|
| POST | `/api/auth/login` | Público |
| POST | `/api/auth/registro` | Público |
| GET | `/api/juegos?q=&genero=&orden=&page=&limit=` | Público |
| POST / PUT / PATCH / DELETE | `/api/juegos[/{id}]` | Administrador |
| GET / POST / PUT / DELETE | `/api/generos[/{id}]` | Administrador (GET público) |
| GET / POST / PUT / PATCH / DELETE | `/api/usuarios[/{id}]` | Administrador |
| GET / POST / PATCH / DELETE | `/api/carrito[/{clave}]` | Público (sesión) |
| POST | `/api/pedidos` | Cliente autenticado |
| PATCH | `/api/pedidos/{id}/estado` | Administrador, empleado |
| POST | `/api/pedidos/{id}/cancelar` | Administrador |
| GET | `/api/dashboard` | Administrador, empleado |
| GET | `/api/reportes/ventas?desde=&hasta=` | Administrador, empleado |
| GET | `/api/historial` | Administrador |

## Seguridad

- Contraseñas con **bcrypt** (`password_hash`), nunca en texto plano.
- **Consultas parametrizadas** con PDO (sin concatenar datos del usuario) contra SQL Injection.
- Token **CSRF** en formularios y en el encabezado `X-CSRF-Token` de la API.
- Sesión con cookie `HttpOnly` y `SameSite=Lax`, `session_regenerate_id` al iniciar sesión.
- Salida escapada con `htmlspecialchars` contra XSS.
- Errores internos al registro del servidor; al usuario solo le llegan mensajes entendibles (nunca SQLSTATE, rutas ni contraseñas).
- Credenciales en `.env`, protegido por `.htaccess` y fuera de Git.
- Cuentas desactivadas no pueden iniciar sesión.

## Capturas

_(Agregar capturas de: inicio, catálogo, ficha, carrito, pago, pedido, dashboard, reportes, CRUD de juegos, historial.)_
