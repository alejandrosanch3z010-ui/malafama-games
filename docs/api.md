# API REST — Mala Fama Games

Base: `http://localhost/malafama-games/api/`

## Convenciones

- Todas las respuestas son **JSON** (`Content-Type: application/json; charset=utf-8`).
- Respuesta correcta:
  ```json
  { "ok": true, "mensaje": "Texto opcional para el usuario", "datos": { } }
  ```
- Respuesta con error:
  ```json
  { "ok": false, "error": "Mensaje entendible", "campos": { "email": "Escribe un correo válido." } }
  ```
- Listas paginadas (`?page=2&limit=20`):
  ```json
  { "ok": true, "pagina": 2, "limite": 20, "total": 78, "totalPaginas": 4, "datos": [ ] }
  ```
- **Autenticación:** por sesión (cookie `PHPSESSID`) creada con `POST /auth/login`.
- **CSRF:** toda petición que no sea `GET` debe enviar el encabezado `X-CSRF-Token` con el token de `GET /auth/csrf` (en las páginas viene en `<meta name="csrf-token">`).
- **Encabezado opcional `X-Flash: 1`:** guarda el mensaje de la respuesta para mostrarlo en la siguiente página.

## Códigos HTTP

| Código | Uso en la API |
|---|---|
| 200 | Consulta u operación correcta |
| 201 | Recurso creado (juego, género, usuario, pedido, reseña, deseo) |
| 400 | Solicitud incorrecta (identificador no numérico, PATCH sin campos) |
| 401 | No hay sesión iniciada o credenciales incorrectas |
| 403 | Sin permiso para la operación, cuenta desactivada o token CSRF inválido |
| 404 | El recurso o la ruta no existe (o no pertenece al usuario) |
| 405 | Método HTTP no permitido para esa ruta |
| 409 | Conflicto con una regla de negocio (sin existencias, duplicado, pedido ya enviado, etc.) |
| 422 | Error de validación de datos (incluye `campos`) |
| 500 | Error interno (se registra en el servidor; el usuario recibe un mensaje genérico) |
| 503 | La base de datos no responde |

## Roles

`publico` (sin sesión) · `cliente` · `empleado` · `admin`. Los permisos se revisan en el servidor en cada endpoint.

---

## Autenticación y perfil

| Método | Endpoint | Body / parámetros | Respuesta | Códigos | Permiso |
|---|---|---|---|---|---|
| GET | `/auth/csrf` | — | `{ csrf }` | 200 | Público |
| POST | `/auth/login` | `{ email, password, volver? }` | Usuario + `redirigir` | 200, 401, 403, 422 | Público |
| POST | `/auth/registro` | `{ nombre, email, password, password2, volver? }` | `{ id }` + `redirigir` | 201, 409, 422 | Público |
| POST | `/auth/logout` | — | — | 200 | Público |
| GET | `/auth/yo` | — | Usuario con sesión | 200, 401 | Autenticado |
| PUT | `/perfil` | `{ nombre, email, telefono?, direccion? }` | — | 200, 401, 409, 422 | Autenticado |
| PATCH | `/perfil/password` | `{ actual, nueva, nueva2 }` | — | 200, 401, 422 | Autenticado |

Ejemplo:
```http
POST /api/auth/login
X-CSRF-Token: 3f9a...
Content-Type: application/json

{ "email": "admin@malafama.mx", "password": "admin1234" }
```
```json
{ "ok": true, "mensaje": "¡Qué onda, Administrador! Ya iniciaste sesión.", "redirigir": "admin.php",
  "datos": { "id": 1, "nombre": "Administrador Mala Fama", "email": "admin@malafama.mx", "rol": "admin", "rol_nombre": "Administrador", "puntos": 0 } }
```

## Juegos (CRUD)

| Método | Endpoint | Body / parámetros | Respuesta | Códigos | Permiso |
|---|---|---|---|---|---|
| GET | `/juegos` | `q, genero, formato (fisico\|digital), precio_max, cal_min, estado (novedades\|preventa\|ofertas), orden (novedades\|precio_asc\|precio_desc\|calificacion\|titulo), page, limit` · admin: `todos=1, activo` | Lista paginada | 200 | Público |
| GET | `/juegos/{id}` | — | Juego con `ediciones` y `capturas` | 200, 400, 404 | Público |
| POST | `/juegos` | Juego + `ediciones: [{ version, formato, precio, precio_oferta?, stock, incluye? }]` | Juego creado | 201, 401, 403, 409, 422 | Administrador |
| PUT | `/juegos/{id}` | Igual que POST | Juego actualizado | 200, 403, 404, 409, 422 | Administrador |
| PATCH | `/juegos/{id}` | `{ activo: true\|false }` | Juego | 200, 400, 403, 404 | Administrador |
| DELETE | `/juegos/{id}` | — | Baja lógica | 200, 403, 404, 409 | Administrador |
| PATCH | `/ediciones/{id}` | `{ stock, nota? }` y/o `{ precio, precio_oferta }` | — | 200, 403, 404, 422 | Admin/empleado (precios solo admin) |

Campos del juego: `titulo, genero_id, sinopsis (mín. 20), fecha_lanzamiento (AAAA-MM-DD), desarrollador, clasificacion (E, E10+, T, M, RP), voces, subtitulos, requiere_internet, nota_internet?, trailer_youtube?, portada?`.

## Reseñas

| Método | Endpoint | Body / parámetros | Respuesta | Códigos | Permiso |
|---|---|---|---|---|---|
| GET | `/juegos/{id}/resenas` | `page, limit` | Lista paginada | 200 | Público |
| POST | `/juegos/{id}/resenas` | `{ calificacion (1-5), comentario? }` | Crea o actualiza la del cliente | 201, 200, 401, 404, 409 (preventa), 422 | Autenticado |

## Géneros (CRUD)

| Método | Endpoint | Body / parámetros | Respuesta | Códigos | Permiso |
|---|---|---|---|---|---|
| GET | `/generos` | `todos=1` (admin), `orden=juegos` | Lista con `juegos_activos` | 200 | Público |
| GET | `/generos/{id}` | — | Género | 200, 404 | Público |
| POST | `/generos` | `{ nombre }` | Género | 201, 403, 409, 422 | Administrador |
| PUT | `/generos/{id}` | `{ nombre, activo? }` | Género | 200, 403, 404, 409, 422 | Administrador |
| DELETE | `/generos/{id}` | — | Baja lógica | 200, 403, 404, 409 (tiene juegos activos) | Administrador |

## Usuarios (CRUD)

| Método | Endpoint | Body / parámetros | Respuesta | Códigos | Permiso |
|---|---|---|---|---|---|
| GET | `/usuarios` | `q, rol, activo, orden (nombre\|nombre_desc\|recientes\|gastado), page, limit` | Lista paginada con pedidos y gasto | 200, 401, 403 | Administrador |
| GET | `/usuarios/{id}` | — | Usuario | 200, 404 | Administrador |
| POST | `/usuarios` | `{ nombre, email, password, rol }` | Usuario | 201, 409, 422 | Administrador |
| PUT | `/usuarios/{id}` | `{ nombre, email, rol, telefono?, direccion? }` | Usuario | 200, 404, 409, 422 | Administrador |
| PATCH | `/usuarios/{id}` | `{ rol? , activo?, password? }` | Usuario | 200, 400, 409, 422 | Administrador |
| DELETE | `/usuarios/{id}` | — | Baja lógica | 200, 404, 409 (a sí mismo o ya dado de baja) | Administrador |

## Tarjetas PSN

| Método | Endpoint | Body / parámetros | Respuesta | Códigos | Permiso |
|---|---|---|---|---|---|
| GET | `/tarjetas` | `todos=1` (admin) | Lista | 200 | Público |
| PUT | `/tarjetas/{id}` | `{ precio, stock, activo }` | Tarjeta | 200, 403, 404, 422 | Administrador |

## Carrito

El carrito vive en la sesión; los precios y existencias siempre se leen de la base de datos.

| Método | Endpoint | Body / parámetros | Respuesta | Códigos | Permiso |
|---|---|---|---|---|---|
| GET | `/carrito` | `usar_puntos=1` | `{ items, piezas, totales }` | 200 | Público |
| POST | `/carrito` | `{ edicion \| tarjeta, cantidad?, comprar_ya? }` | Carrito | 200, 404, 409 (agotado), 422 | Público |
| PATCH | `/carrito/{clave}` | `{ cantidad }` (clave: `e12` o `t3`) | Carrito | 200, 404, 409, 422 | Público |
| DELETE | `/carrito/{clave}` | — | Carrito | 200, 404 | Público |
| DELETE | `/carrito` | — | Carrito vacío | 200 | Público |

## Lista de deseos

| Método | Endpoint | Body | Respuesta | Códigos | Permiso |
|---|---|---|---|---|---|
| GET | `/deseos` | — | Juegos guardados | 200, 401 | Autenticado |
| POST | `/deseos` | `{ juego_id }` | `{ total }` | 201, 401, 404, 409, 422 | Autenticado |
| DELETE | `/deseos/{juego_id}` | — | `{ total }` | 200, 401, 404 | Autenticado |

## Pedidos (ventas)

| Método | Endpoint | Body / parámetros | Respuesta | Códigos | Permiso |
|---|---|---|---|---|---|
| POST | `/pedidos` | `{ metodo, acepto, usar_puntos?, recibe, telefono, direccion, cp, ciudad, estado, guardar_direccion?, tarjeta_numero, tarjeta_nombre, tarjeta_venc, tarjeta_cvv }` | `{ id, folio, total }` + `redirigir` | 201, 401, 409, 422, 500 | Autenticado |
| GET | `/pedidos` | `q, estado, metodo, desde, hasta, orden (recientes\|antiguos\|total_desc\|total_asc), page, limit` | Lista paginada (el cliente solo ve los suyos) | 200, 401, 422 | Autenticado |
| GET | `/pedidos/{id}` | — | Pedido con artículos (y códigos para el dueño o admin) | 200, 404 | Dueño, admin, empleado |
| PATCH | `/pedidos/{id}/estado` | — | `{ estado }` nuevo | 200, 403, 404, 409 | Admin, empleado |
| POST | `/pedidos/{id}/cancelar` | `{ motivo }` | — | 200, 403, 404, 409, 422 | Administrador |
| DELETE | `/pedidos/{id}` | — | Siempre rechazado (regla: las ventas no se eliminan) | 409 | — |

Los datos de dirección solo son obligatorios si el carrito tiene productos físicos; los de tarjeta, solo si `metodo = tarjeta`. La tarjeta **nunca se guarda**.

## Dashboard, reportes, inventario e historial

| Método | Endpoint | Parámetros | Respuesta | Códigos | Permiso |
|---|---|---|---|---|---|
| GET | `/dashboard` | — | Tarjetas (ventas, pedidos, ticket, por enviar, clientes, existencias bajas), ventas de 30 días, ventas por género, métodos de pago, últimos pedidos, alertas | 200, 401, 403 | Admin, empleado |
| GET | `/reportes/ventas` | `desde, hasta (AAAA-MM-DD), agrupar (dia\|mes)` | Filas por periodo + totales | 200, 403, 422 | Admin, empleado |
| GET | `/reportes/juegos-mas-vendidos` | `desde, hasta, limite` | Ranking con piezas e ingresos | 200, 403, 422 | Admin, empleado |
| GET | `/reportes/inventario` | `solo_bajo=1` | Existencias, alertas y ventas de 30 días | 200, 403 | Admin, empleado |
| GET | `/inventario/movimientos` | `edicion_id, tipo (venta\|cancelacion\|ajuste), page, limit` | Lista paginada | 200, 403 | Admin, empleado |
| GET | `/historial` | `q, entidad, accion, desde, hasta, page, limit` | Lista paginada con estado anterior y nuevo | 200, 403 | Administrador |

Reglas del rango de fechas: formato `AAAA-MM-DD`, la fecha inicial no puede ser mayor que la final y el rango máximo es de 366 días (422 si no se cumple).
