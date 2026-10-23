# Diseño técnico y construcción

## Alcance de este primer corte

La primera entrega implementa un monolito modular Laravel con autenticación web, sitios, membresías, solicitudes, aprobaciones por sitio, adjuntos privados, auditoría básica y snapshots de cobro. El backend y el HTML se despliegan juntos. Esto mantiene el flujo end-to-end verificable sin introducir microservicios.

La interfaz usa Blade, CSS y jQuery 4.0.0 en una copia estática local versionada en `public/js`; el MVP no requiere Node, npm, CDN ni un proceso de compilación frontend.

Quedan para iteraciones siguientes: creación/invitación de usuarios y asignación de permisos por el gestor; formularios configurables visualmente; consola del único administrador de plataforma; segundo factor y flujo completo de recuperación; facturación electrónica/impuestos; notificaciones por correo; aprobación en varios pasos; QR y check-in/out; exportación; integraciones de control físico.

## Contextos y autorización

1. **Plataforma:** `platform_admin_accounts` reserva una sola fila administradora usando la clave fija `singleton_id=1`. `PlatformAdminSeeder` aprovisiona esa cuenta a partir de configuración de entorno; `/platform` muestra un resumen general y el comando `platform-admin:reset-password` permite recuperación desde el servidor. La consola completa queda pendiente. Toda lectura detallada entre clientes debe registrar actor, motivo, cliente consultado y fecha. El administrador no se agrega como gestor de ningún cliente.
2. **Cliente:** `organizations` representa clientes. `memberships` relaciona usuarios y clientes y guarda capacidades independientes `can_manage`, `can_approve`, `can_request`; así una persona puede tener más de una capacidad sin duplicar su cuenta.
3. **Sitio y aprobación:** cada sitio tiene `site_approvers`. Una solicitud se lista en la bandeja de aprobación solo para aprobadores asignados a ese sitio. Un aprobador también puede solicitar; las políticas y el servicio de decisión rechazan siempre su propia solicitud.
4. **Contexto de tenant:** el middleware lo deriva de la membresía activa asociada al usuario autenticado. En el MVP se permite exactamente una membresía activa por usuario. No se confía en `tenant_id`, encabezados o parámetros enviados por el navegador.
5. **Defensa adicional:** las tablas de sitio, solicitud y membresía incluyen tenant y usan llaves foráneas compuestas para evitar que datos de dos clientes se unan accidentalmente. Las políticas Laravel y las consultas bajo la relación del tenant protegen los endpoints; los ULID hacen menos enumerable la URL, pero no sustituyen autorización.

Si el producto luego permite que una identidad pertenezca a varios clientes, agregar un selector de organización en sesión que valide la membresía cada vez y propagar el contexto validado a colas, caché, almacenamiento y auditoría.

## Datos y flujo

- `organizations`: razón social, identificación tributaria, correo de facturación, contacto, zona horaria, idioma y configuración de marca.
- `users` / `memberships`: identidad global y capacidades para cada cliente; email único global.
- `sites`: nombre, código, ubicación, zona horaria y esquema de campos futuros.
- `access_requests`: cliente, sitio, solicitante, motivo, intervalo solicitado, estado y datos adicionales; los estados iniciales son pending, approved, rejected y cancelled.
- `access_people`: personas incluidas; número de documento se cifra mediante cast de Laravel y columna `TEXT`.
- `access_request_attachments`: metadatos y ruta privada; descarga exige autorización del registro padre.
- `audit_events`: cambios relevantes sin copiar información personal a metadata.
- `billing_periods`: snapshot de puestos facturables, tarifa en unidades menores, total, moneda y estado.

Una decisión bloquea la fila de solicitud dentro de una transacción, comprueba estado pendiente, pertenencia a cliente, rol, asignación de sitio y diferencia entre solicitante y aprobador; luego actualiza el estado y agrega una auditoría. Evita que dos aprobaciones concurrentes se sobrescriban.

## Seguridad y privacidad que debe acompañar el producto

- Proteger todo endpoint con sesión Sanctum/CSRF para la SPA propia; habilitar tokens personales con abilities solo si una integración externa lo necesita. Usar HTTPS, expiración corta de sesión, regenerar la sesión al iniciar y throttling. MFA no está implementado; debe exigirse antes de producción para la cuenta de plataforma.
- Operar con cuentas de base de datos de mínimo privilegio; separar credenciales de migración y ejecución si la plataforma de hosting lo permite.
- Adjuntos solo en almacenamiento no público, MIME verificado por contenido, lista de tipos permitidos, límite de 10 MB/archivo, nombre generado, autorización al descargar y análisis antimalware antes de producción.
- No registrar contraseñas, tokens, números de documento, archivos, formularios completos ni comentarios sensibles. Definir TTL por clase de dato, eliminación verificable, exportación y atención de solicitudes del titular.
- Cifrar backups y secretos fuera del repositorio; rotar claves con procedimiento; establecer RPO/RTO, alarmas y restauraciones periódicas.
- Para operación en Colombia, revisar las obligaciones de responsable/encargado, avisos/autorizaciones, finalidad, retención, consultas/reclamos, transferencias y acuerdos con clientes bajo Ley 1581 y sus normas. La plataforma puede actuar como encargado frente a datos que el cliente administra; la asignación exacta depende del servicio y contrato y requiere revisión legal.
- La tabla de códigos de recuperación solo define el soporte de datos: falta implementar generación, hash, vencimiento, invalidación, notificación y auditoría de un flujo con códigos offline. La recuperación actual requiere acceso de operador al servidor y no crea una segunda cuenta administradora.

## TDD y pruebas

Escribir primero los casos de aceptación y luego el código:

1. **Unit:** reglas de permisos independientes de Laravel: el solicitante no se aprueba; una membresía inactiva no actúa; rol, tenant o asignación incorrecta deniegan; decisión fuera de estado no procede.
2. **Feature/integration:** requests HTTP con base de datos y autenticación: cliente A no enumera, consulta ni descarga archivos del cliente B; requester ve solo solicitudes propias; approver ve bandeja de sitios asignados; gestor administra dentro de su tenant; subida valida tipo/tamaño; descarga valida autorización; transición concurrente produce una sola decisión.
3. Probar contra SQLite en cada cambio para feedback rápido y contra MariaDB en CI por compatibilidad de llaves compuestas, índices y migraciones. Añadir pruebas de recuperación y retención antes de exponer administración.
4. Correr `php artisan test` y revisión estática (Pint + PHPStan/Larastan) en CI antes de integrar. El código de prueba inicial incluye casos de autoaprobación y aislamiento; aún no se ejecutaron en esta máquina.

## Construcción por iteraciones en IntelliJ

### Iteración 0: entorno

El entorno actual verifica PHP 8.5.12 y Composer 2.10.3. El proyecto usa Laravel 13, compatible con PHP 8.3 o posterior. PHP 8.5.12 fue publicado como actualización de seguridad el 21 de octubre de 2026 para corregir vulnerabilidades críticas; mantener el runtime en una versión parcheada. Instalar MariaDB y extensiones PHP (`pdo_mysql`, `pdo_sqlite`, `fileinfo`, entre otras), abrir la carpeta como proyecto, activar PHP y Blade, importar Composer, conectar la base, copiar `.env.example`, configurar `INITIAL_ADMIN_*`, generar `APP_KEY`, ejecutar `migrate --seed` y abrir la página en el servidor local. Referencias: [requisitos de Laravel](https://laravel.com/framework/docs/deployment), [aviso de PHP 8.5.12](https://www.php.net/releases/8_5_12.php).

### Iteración 1: flujo vertical

Finalizar factories y prueba del endpoint de solicitud, publicar sitios y miembros de prueba con un seeder local que no llegue a producción; completar aprobación, rechazo, políticas, auditoría y descarga privada. Es el alcance del código inicial.

### Iteración 2: gestión de cliente

Crear flujo de invitación por correo con token aleatorio de un solo uso guardando solo hash, caducidad y aceptación; gestor crea/inhabilita membresías, combina capacidades y asigna aprobadores por sitio. Añadir formularios con versión y validación por sitio.

### Iteración 3: consola y recuperación

Consola aislada del administrador único; MFA; avisos de inicio de sesión; códigos de recuperación hasheados con vencimiento/uso único y procedimiento offline. Cada vista de datos entre clientes requiere motivo y auditoría.

### Iteración 4: cobro y operación

Definir asiento facturable y prorrateo con pilotos. Cerrar snapshot mensual transaccional, producir reporte y factura integrada con proveedor local después de revisar impuestos. Añadir correo, colas, backups, monitoreo, límites por cliente, políticas de retención e incidentes.

## Estrategia de despliegue

Comenzar con una base compartida y columna `organization_id`, compartición de tablas estrictamente limitada y almacenamiento privado particionado por tenant. Separar procesos de app, worker, scheduler, MariaDB y storage. Aislar un cliente en esquema/base independiente solo si contrato o evaluación de riesgos lo pide; escribir desde el inicio una migración/restore segura por tenant.

Mantener REST bajo `/api/v1`, versionar los contratos, no exponer excepciones SQL. Usar recursos API explícitos antes de añadir más datos sensibles a respuestas. Para eventos y notificaciones, propagar contexto tenant verificado y autorizar nuevamente en el consumidor.
