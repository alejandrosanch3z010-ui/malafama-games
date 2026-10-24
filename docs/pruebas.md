# Bitácora de pruebas — Mala Fama Games

Registro de las pruebas del sistema. Se ejecutan en local (XAMPP) con la base cargada desde `database/schema.sql` y `database/seed.sql`, y se repiten después de cada corrección.

**Cómo se llena:** en cada caso se anota lo que realmente pasó en *Resultado obtenido* y se cambia el *Estado* a ✅ Pasa o ❌ Falla. Si falla, se describe el error en la sección **Errores encontrados** y se corrige con un commit propio.

**Usuarios de prueba:** los de la tabla del [README](../README.md#usuarios-de-prueba) (administrador, empleado y clientes).

## 1. Pruebas funcionales

| # | Caso | Pasos | Resultado esperado | Resultado obtenido | Estado |
|---|---|---|---|---|---|
| 1 | Ver catálogo | Abrir `catalogo.php` | Se muestran los juegos activos con portada, precio y existencias | | Pendiente |
| 2 | Buscar un juego | Escribir "god" en el buscador | Solo aparecen los juegos que coinciden | | Pendiente |
| 3 | Filtrar y ordenar | Elegir un género y ordenar por precio | La lista respeta el filtro y el orden | | Pendiente |
| 4 | Paginación | Cambiar de página en el catálogo | Se muestran los siguientes juegos sin repetir | | Pendiente |
| 5 | Ficha del juego | Abrir un juego | Se ven sinopsis, tráiler, idiomas, versiones y existencias | | Pendiente |
| 6 | Agregar al carrito | Agregar una edición al carrito | El carrito muestra el producto, la cantidad y el total | | Pendiente |
| 7 | Comprar | Pagar con un cliente de prueba | Se genera el pedido con folio y aparece en "Mis pedidos" | | Pendiente |
| 8 | Lista de deseos | Agregar y quitar un juego de la lista | La lista se actualiza | | Pendiente |
| 9 | Reseña | Calificar un juego con un cliente | La reseña aparece y la calificación del juego se recalcula | | Pendiente |
| 10 | Dashboard | Entrar al panel como administrador | Se ven los indicadores y las 2 gráficas con datos de la base | | Pendiente |
| 11 | CRUD de juegos | Crear, editar y dar de baja un juego | Cada cambio se refleja en el catálogo | | Pendiente |
| 12 | Reporte con fechas | Generar el reporte de ventas de un rango | Solo incluye ventas del rango y se descarga en CSV | | Pendiente |
| 13 | Historial | Revisar el historial después de editar un juego | Aparece el cambio con usuario, acción y fecha | | Pendiente |

## 2. Validaciones

| # | Caso | Pasos | Resultado esperado | Resultado obtenido | Estado |
|---|---|---|---|---|---|
| 14 | Registro incompleto | Registrarse con campos vacíos | Muestra qué campos faltan y no crea la cuenta | | Pendiente |
| 15 | Correo repetido | Registrarse con un correo que ya existe | Muestra un mensaje y no crea la cuenta | | Pendiente |
| 16 | Login incorrecto | Entrar con contraseña equivocada | Mensaje de error sin decir cuál dato falló | | Pendiente |
| 17 | Precio inválido | Guardar un juego con precio 0 o negativo | No se guarda y se indica el error | | Pendiente |
| 18 | Oferta mayor al precio | Poner precio de oferta mayor al normal | No se guarda y se indica el error | | Pendiente |

## 3. Reglas de negocio

| # | Regla | Pasos | Resultado esperado | Resultado obtenido | Estado |
|---|---|---|---|---|---|
| 19 | Máximo 5 piezas | Agregar 6 piezas del mismo producto | El carrito no permite más de 5 | | Pendiente |
| 20 | Sin existencias no se vende | Comprar un juego físico con existencia 0 | La compra se rechaza | | Pendiente |
| 21 | IVA y folio | Revisar un pedido nuevo | El IVA del 16 % aparece desglosado y el folio tiene el formato MF-AAAA-000000 | | Pendiente |
| 22 | Puntos | Comprar un juego y luego pagar con puntos | Se gana el 5 % y con puntos se paga máximo el 50 % del subtotal | | Pendiente |
| 23 | Cancelación | Cancelar un pedido pagado como administrador | Regresa el inventario, se anulan los códigos y se revierten los puntos | | Pendiente |
| 24 | Envío en orden | Avanzar un pedido de pagado a entregado | Pasa por preparando y enviado, y al enviarse genera número de guía | | Pendiente |
| 25 | Tarjeta de prueba 0002 | Pagar con una tarjeta que termine en 0002 | El pago se rechaza | | Pendiente |
| 26 | Existencias bajas | Dejar un juego físico con 5 piezas o menos | Aparece en la alerta del dashboard | | Pendiente |

## 4. Transacción de la venta

| # | Caso | Pasos | Resultado esperado | Resultado obtenido | Estado |
|---|---|---|---|---|---|
| 27 | Venta completa | Comprar y revisar en phpMyAdmin | Se crean el pedido, su detalle, el movimiento de inventario, los puntos y el registro del historial | | Pendiente |
| 28 | Venta que falla | Comprar con la tarjeta 0002 | No queda ningún registro a medias: ni pedido ni descuento de inventario | | Pendiente |

## 5. Diseño responsivo y seguridad

| # | Caso | Pasos | Resultado esperado | Resultado obtenido | Estado |
|---|---|---|---|---|---|
| 29 | Celular | Abrir la tienda con DevTools a 375 px | Todo se lee y se usa sin desplazarse a los lados | | Pendiente |
| 30 | Escritorio | Abrir la tienda a 1366 px | El diseño se ve completo | | Pendiente |
| 31 | Archivo .env | Abrir `http://localhost/malafama-games/.env` | El servidor niega el acceso | | Pendiente |
| 32 | Contraseñas | Revisar la tabla `usuarios` en phpMyAdmin | Las contraseñas están cifradas (hash), no en texto | | Pendiente |

## 6. Pruebas de la API y permisos

Se prueban con el navegador y con Postman (ver la guía del [README](../README.md#cómo-probar-la-api)). En las peticiones que modifican datos se envían la cookie de sesión y el encabezado `X-CSRF-Token`.

| # | Endpoint | Usuario | Resultado esperado | Resultado obtenido | Estado |
|---|---|---|---|---|---|
| 33 | `GET /api/juegos` | Sin sesión | Responde la lista de juegos en JSON | | Pendiente |
| 34 | `GET /api/juegos?q=god&page=1&limit=5` | Sin sesión | Responde solo los que coinciden, máximo 5 | | Pendiente |
| 35 | `POST /api/juegos` | Sin sesión | La petición se rechaza por falta de sesión | | Pendiente |
| 36 | `POST /api/juegos` | Cliente | La petición se rechaza por falta de permiso | | Pendiente |
| 37 | `POST /api/juegos` | Administrador | El juego se crea y queda en el historial | | Pendiente |
| 38 | `POST /api/juegos` sin `X-CSRF-Token` | Administrador | La petición se rechaza | | Pendiente |
| 39 | `PATCH /api/pedidos/{id}/estado` | Empleado | El pedido avanza al siguiente estado | | Pendiente |
| 40 | `POST /api/pedidos/{id}/cancelar` | Empleado | La petición se rechaza: solo el administrador cancela | | Pendiente |
| 41 | `POST /api/pedidos/{id}/cancelar` | Administrador | El pedido se cancela con su motivo | | Pendiente |
| 42 | `GET /api/reportes/ventas?desde=&hasta=` | Empleado | Responde las ventas del rango | | Pendiente |
| 43 | `GET /api/historial` | Empleado | La petición se rechaza: el historial es solo del administrador | | Pendiente |
| 44 | `GET /api/juegos/999999` | Sin sesión | Responde que el juego no existe, sin mostrar errores internos | | Pendiente |

## Errores encontrados

| Fecha | Caso | Descripción del error | Corrección (commit) |
|---|---|---|---|
| | | | |
