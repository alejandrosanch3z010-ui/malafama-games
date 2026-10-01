# Guía de Git — Mala Fama Crew

El maestro pide que **cada integrante tenga participación identificable** y que el proyecto **no se suba completo el último día**. Estas son las reglas del equipo.

## 1. Preparar Git (cada integrante, una sola vez)

```bash
git config --global user.name "Tu Nombre Completo"
git config --global user.email "el-correo-de-tu-cuenta-de-github@ejemplo.com"
```

El correo debe ser el mismo de tu cuenta de GitHub para que tus commits aparezcan con tu nombre.

## 2. Crear el repositorio (lo hace una persona)

```bash
cd C:\xampp\htdocs\malafama-games
git init
git branch -M main
git add .gitignore README.md .env.example
git commit -m "chore: estructura inicial del proyecto"
git remote add origin https://github.com/USUARIO/malafama-games.git
git push -u origin main
```

Después, en GitHub: **Settings → Collaborators** y agrega a los otros tres integrantes.

> El archivo `.env` **no se sube** (ya está en `.gitignore`). Cada quien copia `.env.example` como `.env` en su computadora.

## 3. Los demás integrantes

```bash
cd C:\xampp\htdocs
git clone https://github.com/USUARIO/malafama-games.git
```

## 4. Flujo de trabajo por módulo

Cada módulo se trabaja en su propia rama y se une a `main` con un Pull Request:

```bash
git checkout main
git pull
git checkout -b feat/reportes          # rama para el módulo
# ... trabajar ...
git add api/recursos/api-panel.php js/admin.js
git commit -m "feat: agrega reporte de ventas por periodo"
git push -u origin feat/reportes
```

En GitHub se abre el **Pull Request**, otro integrante lo revisa y lo aprueba (**Merge**).

## 5. Formato de los mensajes

```text
feat: agrega autenticación con roles
feat: agrega CRUD de juegos en el panel
fix: corrige validación de existencias al pagar
docs: documenta endpoints de pedidos
refactor: separa las reglas de negocio en includes/negocio.php
style: ajusta la tabla del panel en celular
test: agrega pruebas de la API con Postman
```

## 6. Reparto de módulos (sugerido)

| Integrante | Módulos y archivos principales |
|---|---|
| David | Arquitectura, API (`api/enrutador.php`, `api/nucleo.php`), autenticación y roles, despliegue |
| Brandon | Catálogo y juegos: `api/recursos/api-juegos.php`, `generos.php`, `js/catalogo.js`, CRUD de juegos y géneros |
| Alexis | Ventas: carrito, pago, pedidos, cancelación, puntos (`includes/negocio.php`, `api/recursos/api-carrito.php`, `pedidos.php`) |
| Juan Pablo | Panel: dashboard, reportes, inventario, historial y usuarios (`api/recursos/api-panel.php`, `usuarios.php`, `js/admin.js`), documentación |

Cada integrante hace los commits de **sus** módulos desde **su** cuenta. Commits pequeños y frecuentes (uno por avance real) valen más que uno gigante.

## 7. Importante

- No subas contraseñas, el `.env` ni respaldos de la base con datos personales.
- Haz `git pull` antes de empezar a trabajar para evitar conflictos.
- El historial debe reflejar el trabajo real de cada quien: no se deben alterar fechas ni autores de los commits.
