# Diseño técnico y construcción

## Alcance de este primer corte

La primera entrega implementa un monolito modular Laravel con autenticación web, sitios, membresías, solicitudes, aprobaciones por sitio, adjuntos privados, auditoría básica y snapshots de cobro. El backend y el HTML se despliegan juntos. Esto mantiene el flujo end-to-end verificable sin introducir microservicios.

La interfaz usa Blade, CSS y jQuery 4.0.0 en una copia estática local versionada en `public/js`; el MVP no requiere Node, npm, CDN ni un proceso de compilación frontend.

Quedan para iteraciones siguientes: facturación electrónica/impuestos y cobro automático; aprobación en varios pasos; QR para visitantes y check-in/out; exportación; integraciones de control físico.

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
- `sites`: nombre, código, ubicación y zona horaria. Los sitios no mantienen formularios propios.
- `organization_form_versions`: versiones inmutables del esquema compartido de solicitudes de cada cliente, con los tipos permitidos texto, texto largo, fecha, selección y archivo. El administrador del cliente las edita desde **Formulario**, al mismo nivel que Usuarios y Sitios. Cada campo puede llevar etiqueta, opciones y obligatoriedad; no hay un límite de cantidad definido por la aplicación.
- `site_form_versions`: versiones históricas de los antiguos formularios por sitio. Se conservan para que las solicitudes creadas antes de la migración sigan mostrando el esquema con que fueron enviadas.
- `access_requests`: cliente, sitio, solicitante, motivo, intervalo solicitado, estado, valores adicionales y la versión exacta del formulario usada. Las nuevas solicitudes apuntan a `organization_form_version_id`; las anteriores mantienen su referencia histórica a `form_version_id`. Los estados iniciales son pending, approved, rejected y cancelled.
- `access_people`: personas incluidas; número de documento se cifra mediante cast de Laravel y columna `TEXT`.
- `access_request_attachments`: metadatos y ruta privada; descarga exige autorización del registro padre. Los archivos de campos configurables guardan además la clave del campo que los produjo.
- `audit_events`: cambios relevantes sin copiar información personal a metadata.
- `billing_periods`: snapshot de puestos facturables, tarifa en unidades menores, total, moneda y estado.

### Importación masiva (usuarios y sitios)

- `client_bulk_imports`: ULID, `organization_id`, `created_by_user_id`, `entity` (`users`|`sites`), `status` (`pending`|`in_progress`|`completed`), `errors_path`, contadores (`total_records`, `processed_records`, `successful_records`, `failed_records`) y `expires_at` (24 h). Solo `errors_path` se persiste; los registros se guardan como filas para poder reanudar y descargar fallos parciales.
- `client_bulk_import_records`: `client_bulk_import_id`, `source_row` (1-based en el archivo), `data` (JSON normalizado), `status` (`pending`|`succeeded`|`failed`) y `errors` (JSON). Unicidad `(client_bulk_import_id, source_row)`.
- Rutas: `POST /client/imports/{entity}/preview`, `POST /client/imports/{importId}/next` (un registro por llamada), `GET /client/imports/{importId}/errors` (CSV de fallidos, disponible aunque la importación esté incompleta). Todas bajo `client-admin`.
- `ClientBulkImportFileParser`: lee CSV con `PhpSpreadsheet\Reader\Csv` (delimitador `,;`\\t) y XLS/XLSX con `IOFactory`. No usa `toArray()`; recorre `getRowIterator()` en **chunks de 1000 filas** y normaliza por chunk para no materializar toda la hoja. Detecta encabezados por alias; si la primera fila contiene algún alias pero falta una columna obligatoria (usuarios: Nombre, Correo electrónico, Perfil; sitios: Nombre, Zona horaria) lanza `ValidationException` sin crear la importación. Sin ningún alias usa mapeo posicional (usuarios: Nombre, Correo, Perfil [+ Sitios]; sitios: Nombre, Código, Dirección, Zona).
- `ClientBulkImportService::processNext()`: procesa un registro por transacción (unicidad contra la BD, por lo que un alta anterior invalida un duplicado posterior en el mismo archivo). Si `failed_records > 0` regenera `registros-fallidos.csv` (BOM UTF-8) tras cada fallo, para que el enlace exista incluso sin llegar a `completed`. `findForDownload()` genera el CSV bajo demanda si falta.
- Frontend (`public/js/client-bulk-import.js`): inicializa `processed_records: 0` tras el preview, muestra "Procesando registro # de ###", agrupa en lotes de 50 (oculta la etiqueta de lote si solo hay uno) y pagina los resultados visibles de 100 en 100.

La propiedad `organizations.brand_config.color` permite personalizar el color de acento del panel del cliente. La vista acepta solo colores CSS de texto simple (palabras alfabéticas o hexadecimales) antes de insertarlos en una variable CSS; así evita que el JSON se convierta en CSS arbitrario. El ejemplo `{"color":"red"}` aplica rojo a los acentos y botones del área `/client`; la plataforma y la pantalla de acceso usan el tema general. El locale disponible es `es`; la zona horaria se escoge desde `DateTimeZone::listIdentifiers()` y se valida de nuevo en el servidor.

El formulario configurable pertenece al cliente y se comparte entre todos sus sitios. El administrador agrega campos desde **Formulario** en el panel del cliente. Texto tiene un máximo de caracteres; Texto largo, un máximo de líneas; Fecha, un formato escogido de `Y-m-d`, `d/m/Y` o `d-m-Y`; las listas usan una opción por línea; y Archivo permite escoger PDF, JPG, JPEG, PNG, XLS, XLSX, TXT o CSV (máximo 10 MB). Todos excepto Archivo admiten valor por defecto. Las fechas por defecto y las respuestas de fecha se validan en JavaScript y en el servidor, incluyendo que el día exista en el calendario. El formulario base siempre conserva sitio, motivo, fechas, adjuntos comunes y al menos una persona con nombre. Al publicar un cambio se crea una nueva versión, sin modificar las anteriores. Cada solicitud conserva la referencia a la versión del cliente que recibió el solicitante, por lo que las etiquetas y reglas históricas se pueden consultar aunque el formulario cambie después. Las entradas se validan contra esa versión en el servidor y se rechazan claves desconocidas; los archivos personalizados quedan vinculados a su campo y almacenados en el disco privado. La migración combina los campos vigentes de los antiguos formularios por sitio para crear la versión compartida inicial; conserva intactos los snapshots anteriores.

La edición de sitio mantiene tenant y código único. Desactivar un sitio impide crear nuevas solicitudes desde las consultas de sitios activos. La eliminación física se bloquea cuando hay un aprobador asignado o cualquier solicitud histórica; las llaves foráneas usan `RESTRICT` para impedir que una eliminación accidental borre esas relaciones. La baja de usuario exige que no haya solicitudes pendientes creadas por la persona ni solicitudes pendientes en sitios que tenga asignados para aprobar. Luego se desactiva y se marca con `deleted_at`, se quita su asignación de aprobaciones y se retienen solicitudes y auditoría.

Una decisión bloquea la fila de solicitud dentro de una transacción, comprueba estado pendiente, pertenencia a cliente, rol, asignación de sitio y diferencia entre solicitante y aprobador; luego actualiza el estado y agrega una auditoría. Evita que dos aprobaciones concurrentes se sobrescriban.

La cancelación sigue el mismo patrón (`POST /api/v1/access-requests/{requestId}/cancel`, `AccessRequestPolicy::cancel` + `CancelAccessRequest` + `CancellationRules`): solo quien creó la solicitud o un aprobador activo con ese sitio asignado puede cancelar, y únicamente en `pending` o en `approved` con `requested_from` futura. En `approved` ya iniciada, `rejected`, `cancelled` o de otro cliente no se puede cancelar. Actualiza `status` a `cancelled`, registra actor/fecha/comentario y auditoría `access_request.cancelled`; el listado expone `can_cancel` y el panel `/` muestra el botón **Cancelar** con confirmación. Si cancela un aprobador, avisa al solicitante con `AccessRequestDecision` (`cancelada`).

El detalle de `/` usa `GET /api/v1/access-requests/{requestId}` (`AccessRequestPolicy::view`): creador o aprobador con sitio, con personas completas, solicitante/decisor, campos del snapshot vigente, `can_decide`/`can_cancel` y `download_url` por adjunto sin exponer `storage_path`. El diálogo **Ver detalles** (`dashboard.blade.php` + `public/js/app.js`) lo renderiza en secciones y descarga adjuntos con `fetch` autenticado + blob. La descarga (`GET /api/v1/access-requests/{requestId}/attachments/{attachmentId}`) verifica prefijo `tenants/{org}/requests/{id}/`, existencia y `nosniff`, y registra `access_request.attachment_downloaded`. La consola de plataforma mantiene su vista propia en `platform.organizations.requests.show`.

## Seguridad y privacidad que debe acompañar el producto

- Proteger todo endpoint con sesión Sanctum/CSRF para la SPA propia; habilitar tokens personales con abilities solo si una integración externa lo necesita. Usar HTTPS, expiración corta de sesión, regenerar la sesión al iniciar y throttling. La cuenta de plataforma exige MFA TOTP con códigos de recuperación de un solo uso.
- Operar con cuentas de base de datos de mínimo privilegio; separar credenciales de migración y ejecución si la plataforma de hosting lo permite.
- Adjuntos solo en almacenamiento no público, MIME verificado por contenido, lista de tipos permitidos, límite de 10 MB/archivo, nombre generado, autorización al descargar y análisis antimalware antes de producción.
- No registrar contraseñas, tokens, números de documento, archivos, formularios completos ni comentarios sensibles. Definir TTL por clase de dato, eliminación verificable, exportación y atención de solicitudes del titular.
- Cifrar backups y secretos fuera del repositorio; rotar claves con procedimiento; establecer RPO/RTO, alarmas y restauraciones periódicas.
- Para operación en Colombia, revisar las obligaciones de responsable/encargado, avisos/autorizaciones, finalidad, retención, consultas/reclamos, transferencias y acuerdos con clientes bajo Ley 1581 y sus normas. La plataforma puede actuar como encargado frente a datos que el cliente administra; la asignación exacta depende del servicio y contrato y requiere revisión legal.
- Los códigos de recuperación MFA se muestran una sola vez, se guardan hasheados y se invalidan al usarse o al restablecer MFA. La recuperación administrativa requiere acceso autorizado al servidor y no crea una segunda cuenta administradora.
- La recuperación de contraseña usa el broker de Laravel, con token de un solo uso y vencimiento de 60 minutos, limitación por IP y por correo, y una respuesta genérica para evitar enumerar cuentas. Tras el restablecimiento se rota `remember_token`, se eliminan sesiones de la tabla configurada y se revocan tokens personales de Sanctum.

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
