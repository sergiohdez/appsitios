# Acceso Claro — SaaS de solicitudes de acceso

Primer corte de una aplicación web multiempresa para solicitar, aprobar y consultar permisos de ingreso a sitios físicos. La interfaz usa Blade, JavaScript y CSS; el backend expone una API REST versionada en Laravel.

## Estado del código

Este repositorio contiene una base ejecutable para autenticación por sesión, contexto de cliente, consulta/creación de sitios, bandejas y flujo de solicitudes, aprobación/rechazo, adjuntos privados, bitácora, estructura inicial de cobro por puesto y pruebas unitarias/de integración. Aún requiere completar administración de usuarios por invitación, formularios configurables de cada sitio, gestión del administrador de plataforma y su MFA/recuperación antes de tratarse como producto listo para producción.

El entorno local se verificó con PHP 8.5.12 y Composer 2.10.3. Laravel 13 requiere PHP 8.3 o posterior; se actualizó el proyecto a Laravel 13 y Composer resolverá un nuevo `composer.lock` compatible con el runtime actual. PHP 8.5.12 es una versión de seguridad publicada el 21 de octubre de 2026 que corrige vulnerabilidades críticas; conserva esta versión parcheada y verifica extensiones antes de ejecutar la aplicación ([requisitos de Laravel](https://laravel.com/framework/docs/deployment), [aviso de PHP 8.5.12](https://www.php.net/releases/8_5_12.php)).

## Stack propuesto

- PHP 8.3+ y Laravel 13 (MVC, middleware, validación, políticas, sesiones, almacenamiento y pruebas integradas).
- MariaDB Community Server como primera opción de base de datos; MySQL Community Edition también funciona con el driver `mysql` de Laravel.
- Sanctum: cookies de sesión/CSRF para la SPA de primera parte; tokens con abilities solo para integraciones externas futuras.
- Blade, JavaScript nativo y CSS para la primera interfaz, sin proceso de build de Node requerido.
- PHPUnit para unit tests y pruebas de integración HTTP; GitHub Actions para ejecutarlas contra MariaDB.

## Preparación local

1. PHP 8.5.12 y Composer 2 ya están instalados en el entorno actual. PHP debe tener las extensiones Laravel `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `session`, `tokenizer` y `xml`; habilita `pdo_mysql` para MariaDB y `pdo_sqlite` para pruebas locales. Instala MariaDB.
2. Crea una base de datos y un usuario de aplicación con privilegios sobre esa base. Evita usar `root` desde la aplicación.
3. En la raíz del proyecto ejecuta:

   ```powershell
   composer install
   Copy-Item .env.example .env
   php artisan key:generate
   php artisan migrate
   php artisan serve
   ```

4. Para iniciar con datos de demostración, define `DEMO_ADMIN_EMAIL` y `DEMO_ADMIN_PASSWORD` en `.env`, ejecuta `php artisan db:seed` y abre `http://127.0.0.1:8000/login`. Para probar aprobación sin autoaprobación, configura además `DEMO_APPROVER_EMAIL` y `DEMO_APPROVER_PASSWORD`; será una cuenta diferente asignada a la sede demo. El seeder solo funciona con `APP_ENV=local`; no habilites registros públicos.

En producción configura `APP_DEBUG=false`, cookies `Secure`, HTTPS, una clave de cifrado guardada en un gestor de secretos, almacenamiento de adjuntos privado, correo transaccional, copias cifradas y colas. `CACHE_STORE=database` y `QUEUE_CONNECTION=database` tienen sus tablas incluidas para desarrollo; para producción multiinstancia migra a Redis y almacenamiento de objetos privado con políticas equivalentes.

## Abrir en IntelliJ IDEA

1. Abre `D:\Apps\Permisos` como proyecto existente. En IntelliJ IDEA Ultimate instala los plugins PHP y Blade desde **Settings → Plugins**; el soporte PHP no viene habilitado en la instalación base. Laravel Idea es opcional.
2. En **Settings → PHP**, selecciona el intérprete PHP 8.5.12 disponible; configura Composer y el nivel de lenguaje correspondiente. Comprueba que el intérprete ve las extensiones indicadas arriba.
3. Configura MariaDB como Data Source desde **Database**, crea `.env` copiando `.env.example` y apunta `DB_*` a la base local.
4. Ejecuta `composer install` desde la terminal integrada; abre `artisan` como configuración de PHP Script si quieres perfiles separados para `migrate`, `serve` y `test`.
5. Activa la integración Git del IDE y usa el remoto público `https://github.com/sergiohdez/appsitios`. El workflow de pruebas ya está en `.github/workflows/tests.yml`.

## Ejecutar pruebas

```powershell
php artisan test
```

`tests/Unit` cubre reglas aisladas de decisión. `tests/Feature` verifica peticiones HTTP con base de datos, incluida la separación entre clientes y la prohibición de autoaprobación. Los tests de feature usan SQLite en memoria para ser rápidos; el workflow de GitHub además ejecuta las migraciones y pruebas contra MariaDB.

## Rutas implementadas

- `GET /login`, `POST /login`, `POST /logout` — sesión web protegida por CSRF y límite de intentos.
- `GET /api/v1/me` — identidad autenticada.
- `GET|POST /api/v1/sites` — listar sitios disponibles o crear (gestores).
- `GET|POST /api/v1/access-requests` — bandeja visible según membresía y creación de solicitud.
- `GET /api/v1/access-requests/{ulid}` — detalle si es solicitante, gestor o aprobador asignado.
- `POST /api/v1/access-requests/{ulid}/approve|reject` — decisión transaccional con bloqueo de fila.
- `GET /api/v1/access-requests/{ulid}/attachments/{ulid}` — descarga autorizada desde almacenamiento privado.

## Documentación

- [Validación de mercado](docs/validacion-mercado.md)
- [Diseño técnico y plan de construcción](docs/diseno-tecnico.md)
