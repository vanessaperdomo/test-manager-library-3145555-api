# test-manager-library-3145555-api

> **Backend REST API en PHP (Laravel 11) — Manager Library**
> Ficha ADSO 3145555 · SDD (Spec-Driven Development)

Backend oficial de *Manager Library* implementado en **PHP con Laravel 11**, respetando estrictamente el contrato HTTP en `spec-laravel-react/api-contract.md` y el modelo de datos físico en PostgreSQL bajo el esquema `library`.

---

## 1. Características Técnicas

- **Framework:** Laravel 11 sobre PHP 8.2+.
- **Motor de Base de Datos:** PostgreSQL 16 (Esquema `library`).
- **Autenticación:** Tokens Bearer firmados con HMAC-SHA256 (JWT), vigencia de 8 horas.
- **Control de Acceso:** Middleware de roles (`admin`, `librarian`).
- **Formato Canónico de Errores:** Todos los errores HTTP devuelven el esquema `{ "code", "message", "details" }`.
- **Inmutabilidad y Concurrencia:** Bloqueo transaccional de ejemplares (`lockForUpdate`), datos históricos congelados en `loan_item` (ADR-004), y baja lógica de libros (`deleted_at`).

---

## 2. Endpoints Implementados (Base `/api/v1`)

### Autenticación y Salud (Públicos)
- `GET /api/v1/health` — Comprobación de estado del servicio (`{"status":"ok"}`).
- `POST /api/v1/auth/login` — Autenticación de usuario (`username`, `password`) retornando token Bearer y rol.

### Categorías y Libros (Autenticados)
- `GET /api/v1/categories` — Listado de las 5 categorías oficiales sembradas en inglés.
- `GET /api/v1/books` — Búsqueda paginada con filtro de texto (`q`) y categoría (`category_id`).
- `GET /api/v1/books/{id}` — Detalle del libro.
- `POST /api/v1/books` *(Admin)* — Registro de libros con ISBN de 13 dígitos y total de ejemplares.
- `PUT /api/v1/books/{id}` *(Admin)* — Actualización del catálogo sin permitir disponibles negativos.
- `DELETE /api/v1/books/{id}` *(Admin)* — Baja lógica (rechazada si tiene préstamos abiertos).
- `PUT /api/v1/books/{id}/cover` *(Admin)* — Subida de portada JPG/PNG hasta 2 MB.
- `GET /api/v1/books/{id}/cover` — Descarga binaria de portada.
- `DELETE /api/v1/books/{id}/cover` *(Admin)* — Eliminación de portada.

### Socios y Préstamos (Autenticados)
- `GET /api/v1/members` — Búsqueda paginada de socios con conteo de préstamos activos.
- `GET /api/v1/members/{id}` — Detalle del socio.
- `POST /api/v1/members` — Alta de socios con documento único.
- `POST /api/v1/loans` — Registro transaccional de préstamos (1 a 3 libros, límite de 3 sin devolver, bloqueo por morosidad).
- `GET /api/v1/loans` — Listado de préstamos por rango de fechas, estado (`open`, `overdue`, `closed`) y socio.
- `GET /api/v1/loans/{id}` — Detalle del préstamo con líneas y estado calculado en tiempo real.
- `POST /api/v1/loans/{loan_id}/items/{item_id}/return` — Devolución de línea y reposición de ejemplares.
- `GET /api/v1/loans/overdue` — Listado de líneas en mora ordenadas por días de retraso.

### Reportes y Usuarios (Admin)
- `POST /api/v1/users` *(Admin)* — Alta de bibliotecarios (`role: librarian`).
- `GET /api/v1/reports/most-borrowed` *(Admin)* — Reporte de libros más prestados por rango de fechas agrupado por título y categoría congelados.

---

## 3. Puesta en Marcha con Docker

```bash
# 1. Configurar variables de entorno
cp .env.example .env

# 2. Ejecutar migraciones y seeders
docker compose run --rm api php artisan migrate --force
docker compose run --rm api php artisan db:seed --force

# 3. Iniciar servidor
docker compose up -d
```
