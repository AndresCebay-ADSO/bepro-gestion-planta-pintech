# Estándares de Desarrollo y Guía de Estilo

Este documento establece las reglas técnicas y de organización para asegurar la calidad, escalabilidad y profesionalismo del código. Todo colaborador (incluyendo el autor principal) debe seguir estas normas.

## 1. Idioma y Nomenclatura (Naming Conventions)

### 🌍 Regla de Oro: El código se escribe en INGLÉS.
Toda la lógica de programación, variables y base de datos debe estar en inglés para mantener la consistencia con el framework (Laravel) y los estándares globales.

* **Variables:** `camelCase` (ej: `$isUserActive`, `$totalPrice`).
* **Funciones/Métodos:** `camelCase` (ej: `calculateTotal()`, `storeRecord()`).
* **Clases/Modelos:** `PascalCase` y en singular (ej: `User`, `OrderService`).
* **Base de Datos:** Tablas en plural y `snake_case` (ej: `users`, `order_items`). Columnas en `snake_case` (ej: `created_at`, `email_verified_at`).

| Entidad | Bien (Inglés) | Mal (Español) |
| :---    | :---          | :---          |
| Variable | `$isPaid`     | `$estaPagado` |
| Método | `updateProfile()` | `actualizarPerfil()` |
| Tabla BD | `products`    | `productos`   |

---

## 2. Estándar de Código (PHP/Laravel)

Seguiremos el estándar **PSR-12**, que es el utilizado por Laravel de forma nativa.
* Uso estricto de tipos (`declare(strict_types=1);`) en lo posible.
* Validaciones siempre en **Form Requests**, no en el Controlador.
* Lógica compleja en **Services**, los controladores deben ser delgados (*Skinny Controllers*).

---

## 3. Control de Versiones (Git)

### 🌿 GitFlow Simplificado
* `main`: Código en producción, siempre estable.
* `develop`: Rama de integración para desarrollo.
* `feature/PT-XXX-descripcion`: Ramas para nuevas funcionalidades o tareas de las Issues.

### 💬 Mensajes de Commit (Conventional Commits)
Usaremos el estándar de commits convencionales en inglés:
* `feat:` Una nueva funcionalidad.
* `fix:` Corrección de un error.
* `docs:` Cambios en la documentación.
* `style:` Cambios que no afectan la lógica (espacios, formato).
* `refactor:` Cambio de código que no añade funciones ni corrige bugs.

*Ejemplo:* `feat: add email verification to user registration`

---

## 4. Documentación de Issues
Las Issues en GitHub se redactarán en **Español** para facilitar la comunicación del negocio, pero siempre referenciando el código de actividad (ej: `[PT-PP-01]`).

---

## 5. Definición de Terminado (Definition of Done - DoD)
Una tarea se considera finalizada solo si:
1. El código sigue los estándares de este documento.
2. No existen variables o comentarios "muertos" (borradores).
3. La funcionalidad ha sido probada localmente.
4. Se ha actualizado la documentación técnica si es necesario.