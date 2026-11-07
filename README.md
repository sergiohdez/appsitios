# AccesoSitios

## Qué es la aplicación

AccesoSitios es una aplicación web SaaS para gestionar solicitudes de ingreso a sitios físicos con acceso restringido. Cada cliente trabaja en un espacio separado y paga una tarifa mensual según la cantidad de usuarios internos activos.

El proyecto incluye espacios de cliente aislados, solicitudes, aprobaciones, adjuntos privados, una consola de plataforma y un área de administración para cada cliente. La facturación y el cobro automático aún requieren integrar un proveedor y definir las reglas comerciales. Antes de operar con datos sensibles en producción, configura y revisa el entorno de despliegue.

## Cómo funciona

1. **Alta del cliente.** El administrador de plataforma crea el cliente junto con su único administrador y le envía un enlace de un solo uso para que defina su contraseña. Ese administrador gestiona los usuarios y los sitios de su propia organización.
2. **Perfiles de usuario.** Cada usuario del cliente tiene un único perfil: **solicitante** o **aprobador**. El solicitante crea solicitudes y consulta las propias; el aprobador también puede crearlas, pero solo decide las asignadas a sus sitios y nunca puede aprobar una solicitud propia.
3. **Aislamiento por cliente.** Clientes, usuarios, sitios y solicitudes quedan aislados por organización. El administrador de plataforma puede consultar la actividad de cualquier cliente, con registro de la consulta.
4. **Ciclo de vida de una solicitud.** Una solicitud nace **Pendiente** y puede quedar **Aprobada**, **Rechazada** o **Cancelada**. Solo quien la creó o un aprobador con ese sitio asignado puede cancelarla, siempre que esté **Pendiente** o **Aprobada con inicio futuro**. Una vez iniciada, rechazada o ya cancelada no admite cancelación. Cada decisión y cada cancelación se registra con actor, fecha, comentario opcional y auditoría; si un aprobador cancela, el solicitante recibe un aviso por correo.
5. **Detalle y descarga.** En la bandeja **Actividad reciente** (`/`) cada fila ofrece **Ver detalles**. Muestra sitio, estado, solicitante, periodo, motivo, comentario de decisión, personas (nombre, documento y empresa), campos del formulario y **adjuntos con Descargar**. Solo quien creó la solicitud o un aprobador con ese sitio asignado puede abrir el detalle; la descarga exige la misma autorización y queda en auditoría.
6. **Sitios y cuentas del cliente.** El administrador puede editar o desactivar sitios; solo puede eliminar un sitio sin aprobadores asignados ni solicitudes asociadas. Un sitio inactivo deja de recibir nuevas solicitudes. Puede eliminar usuarios cuando no tengan solicitudes pendientes propias ni pendientes asignadas para aprobación; la baja es lógica y conserva solicitudes, auditoría e identidad histórica.
7. **Formulario compartido del cliente.** Desde **Formulario**, al mismo nivel que Usuarios y Sitios, el administrador configura una vez los campos adicionales para todos los sitios, sin código ni JSON: Texto (máximo de caracteres), Texto largo (máximo de líneas), Fecha (`AAAA-MM-DD`, `DD/MM/AAAA`, `DD-MM-AAAA`), Lista (una opción por línea) y Archivo (PDF, JPG, JPEG, PNG, XLS, XLSX, TXT o CSV). Todos los tipos excepto Archivo admiten valor por defecto; en Fecha se valida formato y existencia en el navegador y en el servidor. El administrador puede marcar cada campo como obligatorio. El formulario conserva sus datos básicos y exige al menos una persona. Cada cambio publica una versión nueva y las solicitudes anteriores conservan su formulario y respuestas; los formularios antiguos por sitio se combinaron en una versión inicial compartida al migrar.
8. **Apariencia del cliente.** La zona horaria de PHP está disponible en el formulario y el locale es `es`. La configuración `brand_config` (JSON) admite la propiedad `color` para cambiar el color de acento del panel del cliente (nombre CSS como `red` o hexadecimal como `#087f83`; ejemplo: `{"color":"red"}`). Solo afecta al área del cliente; acceso y consola de plataforma mantienen el tema general, y los valores inválidos se rechazan.
9. **Seguridad de acceso.** El alta inicial crea una única cuenta administradora de plataforma, que en su primer inicio debe configurar TOTP y guardar códigos de recuperación de un solo uso. Un operador con acceso al servidor puede restablecer la contraseña y forzar una nueva inscripción MFA con `php artisan platform-admin:reset-password --reset-mfa`, sin crear una segunda cuenta. Los usuarios pueden recuperar su contraseña desde **¿Olvidaste tu contraseña?**: el sistema responde igual exista o no la cuenta, limita el envío y expide un enlace de un solo uso de 60 minutos; al cambiarla se revocan sesiones, tokens de API y cookies persistentes.

## Importación masiva

Solo el administrador del cliente (`client_admin`) puede importar en `/client/users` (usuarios) y `/client/sites` (sitios). Se admite un archivo por carga con extensión **CSV, XLS o XLSX** (máximo 20 MB).

- **Usuarios:** columnas Nombre, correo electrónico y Perfil. Con aprobadores, agrega la opcional Sitios asignados (códigos o nombres separados por `;`). Perfil admite `Aprobador`/`Solicitante` (también `approver`/`requester`).
- **Sitios:** columnas Nombre, código, dirección y zona horaria (IANA, por ejemplo `America/Bogota`). Código y dirección pueden quedar vacíos.

La primera fila puede ser encabezado (reconoce alias en español e inglés, por ejemplo `Nombre`, `correo`, `perfil`, `código`) o bien omitirse; sin encabezados se usa el orden indicado arriba. Si la primera fila parece encabezado y falta una columna obligatoria (usuarios: Nombre, Correo electrónico, Perfil; sitios: Nombre, Zona horaria), no se crea la importación y se informa el error.

Tras subir el archivo se muestra cuántos registros hay. Al pulsar **Iniciar importación** se procesan en **lotes de 50** con el mensaje **Procesando registro # de ###**. Si un registro falla una validación se muestra el error, se guarda en el archivo de fallidos y continúa con el siguiente; un registro guardado cuenta para las validaciones de unicidad siguientes. Al finalizar (o incluso si la importación quedó incompleta) el enlace **Descargar registros fallidos** ofrece un CSV con los fallos para corregirlos. Las importaciones expiran a las 24 horas. En la lista en pantalla se paginan los resultados de 100 en 100 (botones Anterior/Siguiente).

## Cómo instalarla en un ambiente desde cero

### Requisitos

- PHP 8.3 o posterior, con `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `xml`, `ctype`, `curl`, `dom`, `tokenizer`, `zip`, `gd`, `zlib`, `iconv` y `filter` habilitadas. Las extensiones `zip` y `gd` son obligatorias para la importación masiva con PhpSpreadsheet (leer XLSX).
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

Configura también un servicio SMTP real para las invitaciones y las notificaciones por correo. Cuando se crea una solicitud, se avisa a los aprobadores activos asignados a ese sitio, excepto al propio solicitante; al aprobarla o rechazarla, se informa al solicitante. Los correos incluyen el cliente, el sitio, el periodo y el nombre de quien creó o decidió la solicitud; el correo de decisión puede incluir el comentario del aprobador. No incluyen los datos de las personas visitantes ni adjuntos; para consultar los demás datos se debe ingresar a la aplicación. En desarrollo, `MAIL_MAILER=log` escribe los mensajes en el registro de Laravel. Para producción, cambia `MAIL_MAILER=smtp` y completa `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` y `MAIL_FROM_ADDRESS` con los datos de tu proveedor. Los enlaces de activación vencen en 60 minutos; desde la administración se puede enviar un nuevo enlace.

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
