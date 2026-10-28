# Validación inicial de mercado

**Fecha de consulta: 22 de octubre de 2026.** Es una búsqueda documental de ofertas públicas, no un estudio de tamaño de mercado ni una validación con entrevistas. La existencia de competidores valida la categoría y sus casos de uso; no demuestra demanda, disposición a pagar ni viabilidad de un precio por usuario.

## Productos cercanos

| Producto | Enfoque que declara públicamente | Implicación para AccesoSitios |
|---|---|---|
| [Delot / AE Colombia](https://aecolombia.com/control-de-acceso/) | Control de personal, contratistas y visitantes en operación, permisos/documentos, trazabilidad en tiempo real y app para validación en sitio. Declara operar en Colombia. | Competidor local directo en control de terceros; competir por facilidad de configuración, onboarding y licenciamiento simple. |
| [NAVA](https://navapro.com.co/soluciones/) | Registro de visitantes por enlace/QR, carga de requisitos, preaprobación y registro de entrada/salida. | La solicitud autónoma y el QR ya son patrones conocidos en Colombia. |
| [Superaccess](https://superaccess.com.co/) | Preautorización de empleados, contratistas y visitantes; integración con equipos de control físico y trazabilidad, con orientación a copropiedades. | La integración con hardware y el control físico son una categoría adyacente; conviene iniciar como capa de flujo que convive con ellos. |
| [Safeday Access Management](https://www.safeday.io/solutions/access-management) | Requisitos y documentos por flujo, aprobaciones, visibilidad de quién está autorizado e integraciones con Nedap AEOS, AFAS y Facilitor. | Formularios y aprobación ajustados al sitio ya son expectativas; la integración futura puede ser una ventaja. |
| [PermitHub](https://permithub.co.uk/pricing) | Permisos digitales, aprobaciones, auditoría y, en plan superior, visitantes, cumplimiento de contratistas e inducción. Publica £99 y £249 por mes con facturación anual y sin cargo por permiso. | Hay clientes que pagan por suscripción fija y esperan uso amplio. Un cobro por usuario interno es comprensible, pero debe definirse con precisión. |
| [Rapid Global](https://rapidglobal.com/products/online-work-permit-software/) | Permisos de trabajo conectados a cumplimiento, inducción de contratistas y habilitación de acceso. | En sectores industriales el permiso suele integrarse con seguridad laboral; una bandeja genérica puede quedarse corta en este segmento. |
| [CMOps](https://cmops.org/) | Plataforma para construcción con permisos de acceso, aprobaciones configurables, pases QR, formularios para contratistas sin cuenta y marca blanca multiempresa. | QR, formularios externos y marca blanca tampoco son diferenciadores exclusivos. |

## Conclusión

La idea tiene un problema reconocible y competidores en Colombia y fuera del país. El concepto es comercialmente plausible como SaaS si reduce trabajo manual y conserva evidencia, pero la descripción actual se solapa con software de control de visitantes, gestión de contratistas y permisos de trabajo. La oportunidad no puede afirmarse sin pruebas con compradores.

La mejor primera posición sería **portal configurable de solicitud y aprobación previa de acceso para empresas con varios sitios**, complementario a la portería y al control físico existentes. El producto podría enfocarse en empresas medianas que reciben contratistas/proveedores y hoy coordinan permisos por correo, hojas de cálculo o mensajería. No prometer integración con puertas, validación de identidad o cumplimiento laboral hasta tener esos módulos.

Posibles ventajas que se deben validar:

- Configurar requisitos y responsables por sitio sin proyecto de consultoría.
- Crear una bandeja móvil sencilla para solicitantes y aprobadores, con historial claro.
- Cobro en moneda local y factura consolidada por cliente, con reglas de puestos transparentes.
- Iniciar sin hardware: exportar/compartir una autorización verificable y luego integrar sistemas de acceso mediante API/webhooks.
- Ofrecer privacidad y retención controlada para documentos identificatorios, con responsabilidades contractuales claras.

## Modelo de cobro recomendado para probar

No fijar tarifas todavía. Probar un paquete mensual por cliente más puestos internos activos, manteniendo ilimitados los sitios o colocando un límite por plan, y sin cobrar por visitante o solicitud. Definir en contrato:

1. **Puesto facturable:** una cuenta interna única con membresía habilitada durante el periodo; varios permisos (solicitante y aprobador) siguen siendo un solo puesto.
2. **Usuarios externos:** personas incluidas en solicitudes no cuentan como puestos; no necesitan cuenta durante el primer MVP.
3. **Prorrateo y bajas:** elegir si una cuenta activa un solo día se cobra por mes completo, por promedio diario o por corte de fin de mes. El corte mensual es más sencillo; el promedio es más proporcional.
4. **Factura:** guardar un snapshot inmutable del número de puestos, tarifa, moneda, impuestos aplicables, periodo y ajuste manual, y emitir una sola factura por cliente.
5. **Excedentes:** avisar al administrador del cliente antes de crear puestos que excedan el plan; evitar suspender solicitudes de seguridad sin periodo de gracia.

El código inicial guarda periodos de cobro y snapshots por cliente, pero no calcula impuestos, no genera factura fiscal ni integra un proveedor de pagos/facturación.

## Validación comercial antes de ampliar el alcance

Realizar 10–15 entrevistas con responsables de seguridad física, facilities, HSE y administración de empresas con múltiples sitios. Pedirles reconstruir su último permiso de ingreso, identificar quién aprueba, cuánto tarda y qué evidencia se audita. Después probar un prototipo con sus propios formularios y comparar el precio por puesto contra su presupuesto actual. Buscar 3 clientes piloto que acepten probar el flujo y compartir métricas como tiempo de aprobación, solicitudes incompletas y uso semanal.

Preguntas que pueden invalidar la hipótesis:

- ¿Quién compra y paga: seguridad, facilities, HSE, TI o administración?
- ¿El proceso se realiza por empleado interno o por empresas contratistas con frecuencia alta?
- ¿Los aprobadores necesitan cuentas o es más fácil aprobar desde correo/enlace temporal?
- ¿El comprador necesita check-in/out, validación documental, inducción o integración física desde el primer día?
- ¿Aceptan cobro por usuario interno, o su unidad de presupuesto es sitio, portería, contratista o volumen de permisos?
- ¿Qué datos de identidad son realmente obligatorios y cuánto tiempo se deben conservar?
