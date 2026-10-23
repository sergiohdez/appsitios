# AppSitios

## Qué es la aplicación

AppSitios es una aplicación web SaaS para gestionar solicitudes de ingreso a sitios físicos con acceso restringido. Cada cliente trabaja en un espacio separado y paga una tarifa mensual según la cantidad de usuarios internos activos.

El proyecto está en etapa MVP. Contiene la base para autenticación, separación de datos por cliente y el flujo de solicitudes; la consola completa de administración, la autenticación multifactor y la facturación automática aún están en desarrollo. No está listo para operar con datos sensibles en producción.

## Cómo funciona

1. Un gestor administra los usuarios y los sitios de su cliente.
2. Un solicitante indica el sitio, el motivo y el horario de ingreso, registra a las personas que lo visitarán y adjunta los documentos requeridos.
3. Un aprobador revisa las solicitudes que tiene asignadas y las aprueba o rechaza. Los usuarios pueden tener más de una función, pero no pueden aprobar sus propias solicitudes.
4. Las decisiones y cambios relevantes quedan disponibles para consulta. Los datos de un cliente no se muestran a los demás.

El alta inicial crea una cuenta administradora de plataforma única. Por ahora, esa cuenta puede iniciar sesión y consultar un resumen general; las herramientas para gestionar clientes y usuarios desde una consola aún están pendientes.

## Cómo instalarla en un ambiente desde cero

### Requisitos

- PHP 8.3 o posterior, con `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `xml`, `ctype`, `curl`, `dom` y `tokenizer` habilitadas.
- Composer 2.
- MariaDB o MySQL.
- Git.

jQuery 4.0.0 se sirve desde el propio proyecto. No necesitas instalar Node.js, npm ni descargar JavaScript desde un CDN para ejecutar la aplicación. [Anuncio oficial de jQuery 4.0.0](https://blog.jquery.com/2026/01/17/jquery-4-0-0/).

### 1. Descargar el proyecto e instalar dependencias

```sh
git clone https://github.com/sergiohdez/appsitios.git
cd appsitios
composer install
```

### 2. Crear la base de datos y configurar el entorno

Crea una base de datos y un usuario exclusivos para la aplicación. Ejemplo en MariaDB/MySQL; reemplaza la contraseña por una segura:

```sql
CREATE DATABASE appsitios CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'appsitios'@'127.0.0.1' IDENTIFIED BY 'CAMBIA_ESTA_CLAVE';
GRANT ALL PRIVILEGES ON appsitios.* TO 'appsitios'@'127.0.0.1';
```

Copia `.env.example` como `.env` (en PowerShell puedes usar `Copy-Item .env.example .env`; en macOS/Linux, `cp .env.example .env`) y configura al menos:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=appsitios
DB_USERNAME=appsitios
DB_PASSWORD="CAMBIA_ESTA_CLAVE"

INITIAL_ADMIN_NAME="Administrador de plataforma"
INITIAL_ADMIN_EMAIL=admin@tu-dominio.com
INITIAL_ADMIN_PASSWORD="USA_UNA_FRASE_SECRETA_DE_16_CARACTERES_O_MAS"
```

Usa una dirección de correo que controles y una contraseña única de al menos 16 caracteres. No compartas ni subas el archivo `.env` al repositorio.

### 3. Crear las tablas y el administrador inicial

```sh
php artisan key:generate
php artisan migrate --seed
```

Las migraciones de Laravel generan las tablas, índices y relaciones de la base de datos. El seeder `PlatformAdminSeeder`, mediante Eloquent, crea el usuario inicial y lo vincula como administrador único de la plataforma. El proceso es idempotente: volver a ejecutarlo no cambia la contraseña de una cuenta ya creada.

### 4. Iniciar sesión

```sh
php artisan serve
```

Abre [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login) e ingresa con el correo y la contraseña configurados en `INITIAL_ADMIN_EMAIL` y `INITIAL_ADMIN_PASSWORD`.

Si el administrador pierde el acceso, un operador con acceso a la consola del servidor puede ejecutar `php artisan platform-admin:reset-password`. El comando confirma el correo registrado, solicita una nueva contraseña y revoca las sesiones y tokens existentes. No crea una segunda cuenta administradora.

### Pruebas

Para ejecutar las pruebas automatizadas del proyecto:

```sh
php artisan test
```

Antes de exponer una instalación en Internet, configura HTTPS, `APP_DEBUG=false`, copias de seguridad, almacenamiento privado para adjuntos y un mecanismo de autenticación multifactor. La configuración de desarrollo anterior no debe reutilizarse como configuración de producción.
