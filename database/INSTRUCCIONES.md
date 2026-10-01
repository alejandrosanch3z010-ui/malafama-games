# Base de datos

1. En phpMyAdmin (o la consola de MySQL) importa **`schema.sql`**: borra y crea la base `malafama_games` con sus 15 tablas, llaves, restricciones e índices.
2. Después importa **`seed.sql`**: catálogo, usuarios de prueba (admin, empleado y clientes), reseñas y tres meses de ventas.

Desde la consola:

```bash
mysql -u root -p < schema.sql
mysql -u root -p < seed.sql
```

Usuarios de prueba: `admin@malafama.mx / admin1234`, `empleado@malafama.mx / empleado1234` y clientes como `brandon@demo.mx / demo1234`.

Descripción de tablas, relaciones, normalización y diccionario de datos: [`docs/database.md`](../docs/database.md).
