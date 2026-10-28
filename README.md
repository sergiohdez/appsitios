# AccesoSitios

## Qué es la aplicación

AccesoSitios es una aplicación web SaaS para gestionar solicitudes de ingreso a sitios físicos con acceso restringido. Cada cliente trabaja en un espacio separado y paga una tarifa mensual según la cantidad de usuarios internos activos.

El proyecto incluye espacios de cliente aislados, solicitudes, aprobaciones, adjuntos privados, una consola de plataforma y un área de administración para cada cliente. La facturación y el cobro automático aún requieren integrar un proveedor y definir las reglas comerciales. Antes de operar con datos sensibles en producción, configura y revisa el entorno de despliegue.

## Cómo funciona

1. Al crear un cliente, el administrador de plataforma crea también su único administrador y le envía un enlace de un solo uso para definir la contraseña. Este administrador gestiona los usuarios y los sitios de su organización.
2. Cada usuario del cliente tiene un solo perfil: solicitante o aprobador. Un solicitante crea solicitudes y consulta las propias. Un aprobador puede crear solicitudes y decide las solicitudes asignadas a sus sitios; nunca puede aprobar una solicitud propia.
3. Los datos de clientes, usuarios, sitios y solicitudes quedan aislados por organización. El administrador de plataforma puede consultar la actividad de cualquier cliente.

El formulario del cliente ofrece la lista de zonas horarias de PHP y actualmente permite el locale `es` (español). La configuración `brand_config` acepta un objeto JSON; por ahora usa la propiedad `color` para cambiar el color de acento del área del cliente. Se admiten nombres CSS como `red` y colores hexadecimales como `#087f83`. Ejemplo: `{"color":"red"}`. El color se aplica en el panel del cliente; el acceso y la consola de plataforma mantienen el tema general. Los valores inválidos de `color` se rechazan y las propiedades aún no soportadas no cambian la interfaz.

El administrador del cliente puede editar o desactivar sitios. Solo puede eliminar un sitio si no tiene usuarios aprobadores asignados ni solicitudes asociadas; los sitios inactivos dejan de recibir nuevas solicitudes. Puede eliminar usuarios cuando no tengan solicitudes pendientes propias ni pendientes asignadas para aprobación. La baja de usuario se guarda como eliminación lógica y conserva las solicitudes, auditoría e identidad histórica.

El alta inicial crea una cuenta administradora de plataforma única. En el primer inicio de sesión debe configurar una aplicación compatible con TOTP y guardar los códigos de recuperación, que se muestran una sola vez. Un operador autorizado del servidor puede restablecer la contraseña y forzar una nueva inscripción de MFA con `php artisan platform-admin:reset-password --reset-mfa`.

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

Configura también un servicio SMTP real para las invitaciones de administradores y usuarios. En desarrollo, `MAIL_MAILER=log` escribe los mensajes en el registro de Laravel. Para producción, cambia `MAIL_MAILER=smtp` y completa `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` y `MAIL_FROM_ADDRESS` con los datos de tu proveedor. Los enlaces de activación vencen en 60 minutos; desde la administración se puede enviar un nuevo enlace.

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

Si el administrador pierde el acceso, un operador autorizado con acceso a la consola del servidor puede ejecutar `php artisan platform-admin:reset-password --reset-mfa`. El comando confirma el correo registrado, solicita una nueva contraseña, revoca sesiones y tokens, elimina los códigos de recuperación anteriores y requiere configurar un autenticador nuevo. No crea una segunda cuenta administradora.

### Pruebas

Para ejecutar las pruebas automatizadas del proyecto:

```sh
php artisan test
```

Para configurar IntelliJ IDEA o PhpStorm, incluyendo Run/Debug para `php artisan serve`, consulta [Configuración de PhpStorm](docs/configuracion-phpstorm.md).

Antes de exponer una instalación en Internet, configura HTTPS, `APP_DEBUG=false`, copias de seguridad, almacenamiento privado para adjuntos, correo saliente y monitoreo. La configuración de desarrollo anterior no debe reutilizarse como configuración de producción.
