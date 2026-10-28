# Diseño técnico y construcción

## Alcance de este primer corte

La primera entrega implementa un monolito modular Laravel con autenticación web, sitios, membresías, solicitudes, aprobaciones por sitio, adjuntos privados, auditoría básica y snapshots de cobro. El backend y el HTML se despliegan juntos. Esto mantiene el flujo end-to-end verificable sin introducir microservicios.

La interfaz usa Blade, CSS y jQuery 4.0.0 en una copia estática local versionada en `public/js`; el MVP no requiere Node, npm, CDN ni un proceso de compilación frontend.

Quedan para iteraciones siguientes: recuperación de acceso iniciada por usuarios; formularios configurables visualmente; facturación electrónica/impuestos y cobro automático; notificaciones por correo sobre solicitudes; aprobación en varios pasos; QR para visitantes y check-in/out; exportación; integraciones de control físico.

## Contextos y autorización

1. **Plataforma:** `platform_admin_accounts` reserva una sola fila administradora usando la clave fija `singleton_id=1`. `PlatformAdminSeeder` aprovisiona esa cuenta a partir de configuración de entorno; `/platform` permite gestionar clientes y consultar su operación, y el comando `platform-admin:reset-password` permite recuperación desde el servidor. Toda lectura detallada entre clientes debe registrar actor, cliente consultado y fecha. El administrador de plataforma no se agrega como miembro de ningún cliente.
2. **Cliente:** `organizations` representa clientes. `memberships` relaciona usuarios y clientes y almacena un solo `role`: `client_admin`, `approver` o `requester`. `client_admin_accounts` tiene una fila por organización para garantizar un administrador de cliente como máximo. La creación de cliente aprovisiona esa cuenta dentro de la misma transacción. `memberships.deleted_at` conserva la referencia histórica cuando se da de baja un usuario.
3. **Sitio y aprobación:** cada sitio tiene `site_approvers`. El administrador del cliente gestiona usuarios y sitios desde `/client`. Los usuarios ordinarios tienen perfil único de aprobador o solicitante. Un aprobador también puede solicitar; las políticas y el servicio de decisión rechazan siempre su propia solicitud.
4. **Contexto de tenant:** el middleware lo deriva de la membresía activa asociada al usuario autenticado. En el MVP se permite exactamente una membresía activa por usuario. No se confía en `tenant_id`, encabezados o parámetros enviados por el navegador.
5. **Defensa adicional:** las tablas de sitio, solicitud y membresía incluyen tenant y usan llaves foráneas compuestas para evitar que datos de dos clientes se unan accidentalmente. Las políticas Laravel y las consultas bajo la relación del tenant protegen los endpoints; los ULID hacen menos enumerable la URL, pero no sustituyen autorización.

Si el producto luego permite que una identidad pertenezca a varios clientes, agregar un selector de organización en sesión que valide la membresía cada vez y propagar el contexto validado a colas, caché, almacenamiento y auditoría.

## Datos y flujo

- `organizations`: razón social, identificación tributaria, correo de facturación, contacto, zona horaria, idioma y configuración de marca.
- `users` / `memberships` / `client_admin_accounts`: identidad global, perfil único por cliente y cuenta única de administrador del cliente; email único global.
- `sites`: nombre, código, ubicación, zona horaria y esquema de campos futuros.
- `access_requests`: cliente, sitio, solicitante, motivo, intervalo solicitado, estado y datos adicionales; los estados iniciales son pending, approved, rejected y cancelled.
- `access_people`: personas incluidas; número de documento se cifra mediante cast de Laravel y columna `TEXT`.
- `access_request_attachments`: metadatos y ruta privada; descarga exige autorización del registro padre.
- `audit_events`: cambios relevantes sin copiar información personal a metadata.
- `billing_periods`: snapshot de puestos facturables, tarifa en unidades menores, total, moneda y estado.

La propiedad `organizations.brand_config.color` permite personalizar el color de acento del panel del cliente. La vista acepta solo colores CSS de texto simple (palabras alfabéticas o hexadecimales) antes de insertarlos en una variable CSS; así evita que el JSON se convierta en CSS arbitrario. El ejemplo `{"color":"red"}` aplica rojo a los acentos y botones del área `/client`; la plataforma y la pantalla de acceso usan el tema general. El locale disponible es `es`; la zona horaria se escoge desde `DateTimeZone::listIdentifiers()` y se valida de nuevo en el servidor.

La edición de sitio mantiene tenant y código único. Desactivar un sitio impide crear nuevas solicitudes desde las consultas de sitios activos. La eliminación física se bloquea cuando hay un aprobador asignado o cualquier solicitud histórica; las llaves foráneas usan `RESTRICT` para impedir que una eliminación accidental borre esas relaciones. La baja de usuario exige que no haya solicitudes pendientes creadas por la persona ni solicitudes pendientes en sitios que tenga asignados para aprobar. Luego se desactiva y se marca con `deleted_at`, se quita su asignación de aprobaciones y se retienen solicitudes y auditoría.

Una decisión bloquea la fila de solicitud dentro de una transacción, comprueba estado pendiente, pertenencia a cliente, rol, asignación de sitio y diferencia entre solicitante y aprobador; luego actualiza el estado y agrega una auditoría. Evita que dos aprobaciones concurrentes se sobrescriban.

## Seguridad y privacidad que debe acompañar el producto

- Proteger todo endpoint con sesión Sanctum/CSRF para la SPA propia; habilitar tokens personales con abilities solo si una integración externa lo necesita. Usar HTTPS, expiración corta de sesión, regenerar la sesión al iniciar y throttling. La cuenta de plataforma exige MFA TOTP con códigos de recuperación de un solo uso.
- Operar con cuentas de base de datos de mínimo privilegio; separar credenciales de migración y ejecución si la plataforma de hosting lo permite.
- Adjuntos solo en almacenamiento no público, MIME verificado por contenido, lista de tipos permitidos, límite de 10 MB/archivo, nombre generado, autorización al descargar y análisis antimalware antes de producción.
- No registrar contraseñas, tokens, números de documento, archivos, formularios completos ni comentarios sensibles. Definir TTL por clase de dato, eliminación verificable, exportación y atención de solicitudes del titular.
- Cifrar backups y secretos fuera del repositorio; rotar claves con procedimiento; establecer RPO/RTO, alarmas y restauraciones periódicas.
- Para operación en Colombia, revisar las obligaciones de responsable/encargado, avisos/autorizaciones, finalidad, retención, consultas/reclamos, transferencias y acuerdos con clientes bajo Ley 1581 y sus normas. La plataforma puede actuar como encargado frente a datos que el cliente administra; la asignación exacta depende del servicio y contrato y requiere revisión legal.
- Los códigos de recuperación MFA se muestran una sola vez, se guardan hasheados y se invalidan al usarse o al restablecer MFA. La recuperación administrativa requiere acceso autorizado al servidor y no crea una segunda cuenta administradora.

## TDD y pruebas

La suite cubre reglas unitarias de aprobación, aislamiento entre clientes, creación de organización y administrador inicial, invitaciones de un solo uso, validación de contraseña, alta y baja de usuarios, edición y eliminación protegida de sitios, asignación de aprobadores y denegación de permisos fuera del tenant. Ejecutar `php artisan test` para correr pruebas unitarias y de integración sobre SQLite. CI debe comprobar además MariaDB para validar migraciones e índices compuestos.

## Construcción por iteraciones en IntelliJ

### Iteración 0: entorno

El entorno actual verifica PHP 8.5.12 y Composer 2.10.3. El proyecto usa Laravel 13, compatible con PHP 8.3 o posterior. PHP 8.5.12 fue publicado como actualización de seguridad el 21 de octubre de 2026 para corregir vulnerabilidades críticas; mantener el runtime en una versión parcheada. Instalar MariaDB y extensiones PHP (`pdo_mysql`, `pdo_sqlite`, `fileinfo`, entre otras), abrir la carpeta como proyecto, activar PHP y Blade, importar Composer, conectar la base, copiar `.env.example`, configurar `INITIAL_ADMIN_*`, generar `APP_KEY`, ejecutar `migrate --seed` y abrir la página en el servidor local. Referencias: [requisitos de Laravel](https://laravel.com/framework/docs/deployment), [aviso de PHP 8.5.12](https://www.php.net/releases/8_5_12.php).

### Iteración 1: flujo vertical

Finalizar factories y prueba del endpoint de solicitud, publicar sitios y miembros de prueba con un seeder local que no llegue a producción; completar aprobación, rechazo, políticas, auditoría y descarga privada. Es el alcance del código inicial.

### Iteración 2: gestión de cliente

El administrador de plataforma crea el administrador único del cliente junto con la organización y envía un enlace de activación de un solo uso usando el broker de contraseñas de Laravel. El administrador del cliente crea y edita membresías de perfil único, asigna sitios a los aprobadores y registra los sitios de su organización. Los usuarios también reciben invitaciones; plataforma y cliente pueden enviar un nuevo enlace de acceso.

### Iteración 3: consola y recuperación

La consola aislada del administrador único, MFA y códigos de recuperación de un solo uso ya están implementados. Quedan por añadir avisos de inicio de sesión y completar el procedimiento operativo de recuperación offline. Cada vista de datos entre clientes requiere motivo y auditoría.

### Iteración 4: cobro y operación

Definir asiento facturable y prorrateo con pilotos. Cerrar snapshot mensual transaccional, producir reporte y factura integrada con proveedor local después de revisar impuestos. Añadir correo, colas, backups, monitoreo, límites por cliente, políticas de retención e incidentes.

## Estrategia de despliegue

Comenzar con una base compartida y columna `organization_id`, compartición de tablas estrictamente limitada y almacenamiento privado particionado por tenant. Separar procesos de app, worker, scheduler, MariaDB y storage. Aislar un cliente en esquema/base independiente solo si contrato o evaluación de riesgos lo pide; escribir desde el inicio una migración/restore segura por tenant.

Mantener REST bajo `/api/v1`, versionar los contratos, no exponer excepciones SQL. Usar recursos API explícitos antes de añadir más datos sensibles a respuestas. Para eventos y notificaciones, propagar contexto tenant verificado y autorizar nuevamente en el consumidor.
