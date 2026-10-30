# Cuentas, sesiones y permisos

## Recorrido de una persona

| Paso | Actor | Datos y resultado |
|---|---|---|
| 1. Solicitar acceso | Visitante | Nombres, apellidos, tipo y número de documento, correo, finca y consentimiento. Crea una solicitud, sin sesión. |
| 2. Aprobar acceso | Superadmin | Crea o vincula la cuenta y finca pendiente; emite clave temporal de 24 horas y revoca sesiones anteriores. Instrucciones por cola; consola solo local. |
| 3. Primer ingreso | Productor | Entra con la temporal y escribe dos veces su contraseña personal. No se pide la actual. El servidor bloquea los datos de campo hasta completar el cambio. |
| 4. Consultar el panel | Productor | Ve Mi cuenta y sus fincas, incluidas pendientes y rechazadas. |
| 5. Habilitar la finca | Superadmin y productor | Se aprueba la vinculación; después el titular completa coordenadas y superficie, con suelo opcional. Solo entonces registra lotes y operación. |

La aprobación de una persona, el estado de su cuenta y la aprobación de una finca son decisiones diferentes. Una finca pendiente no impide entrar al panel para consultar su estado.

## Qué puede ver cada rol

| Información o acción | Superadmin | Productor |
|---|---|---|
| Datos de fincas, lotes, actividades y evidencia | Todos los productores | Solo sus propios recursos |
| Solicitudes de acceso y vinculación de fincas | Revisa, aprueba o rechaza | Envía solicitudes y consulta su resultado |
| Registro de operaciones | Supervisa y corrige con motivo | Registra en sus fincas habilitadas y lotes disponibles |
| Perfil de la persona | Consulta para administrar el acceso | Consulta documento y correo; edita nombre y teléfono |
| Suspensión y reactivación | Productores, con motivo registrado | No puede administrar otras cuentas |
| Recuperación administrativa | Genera una nueva clave temporal para una cuenta activa y aprobada | Puede solicitar ayuda o usar recuperación por correo |
| Contraseña personal de otra persona | No puede verla | No puede verla |

Estas reglas se comprueban en la API además de mostrarse en la interfaz.

## Acciones sobre cuentas

La pantalla **Usuarios y acceso** está disponible en `/accounts`. La guía común está en `/guia`.

| Acción | Efecto inmediato | Lo que conserva |
|---|---|---|
| Suspender | Bloquea el login, revoca access y refresh, cancela retos y enlaces de recuperación y anula invitaciones pendientes | Fincas, lotes, actividades, lecturas, archivos e historial |
| Reactivar | Permite una nueva sesión si la solicitud sigue aprobada y la contraseña está vigente | Contraseña y estados de fincas; no recupera sesiones revocadas |
| Recuperar acceso | Cambia la contraseña por una temporal, revoca sesiones y encola instrucciones | Datos y aprobaciones; no levanta una suspensión |

Todas requieren un motivo de al menos 10 caracteres. El historial conserva actor, fecha, acción, motivo y estados anterior y posterior; no guarda claves ni tokens. Las cuentas Superadmin están protegidas contra estos cambios desde esta pantalla. No se implementa borrado de cuentas. Las escrituras aceptan `Idempotency-Key` para reintentos de red sin duplicar la operación.

Suspender a la persona no cambia la identidad técnica de sus sensores ni elimina las mediciones. La operación de los dispositivos se administra por separado.

## Sesión web

- Angular usa `/api/web/auth/*`. El access token permanece en memoria, con vida de 15 minutos por defecto; no se guardan tokens en localStorage ni sessionStorage. Las credenciales persistidas por la versión anterior se retiran al cargar la app.
- El refresh se guarda cifrado en una cookie HttpOnly, host-only, SameSite=Strict. En producción se llama `__Host-aggrio_refresh`, utiliza Secure y Path=/; el servidor rechaza autenticación web por HTTP.
- Las respuestas web no incluyen el refresh token ni el alias legado `token`. Login y segundo factor comprueban contraseña, aprobación, estado de cuenta y rol en el servidor.
- Las solicitudes web verifican origen exacto o referente y una cabecera propia. Refresh, cambio de clave y logout verifican además un token CSRF HMAC ligado a la sesión. No se aceptan orígenes desconocidos ni tokens CSRF de otra sesión.
- Al recargar se obtiene el token CSRF por cookie y se renueva el access. La cookie se rota y el refresh anterior deja de funcionar. Web Locks coordina renovaciones entre pestañas donde el navegador lo soporta.
- Las rutas de datos siguen exigiendo Bearer; una cookie de refresh no permite consultar fincas o ejecutar acciones administrativas. El cambio de contraseña web debe pertenecer a la misma sesión de cookie y access.
- Cerrar sesión invalida el par correspondiente y retira la cookie. Si se cerró sin conexión, la aplicación conserva una marca sin credenciales y completa el cierre cuando vuelve a contactar al servidor, sin restaurar esa sesión.
- Angular es la interfaz del producto. Las rutas legadas del servidor son compatibilidad técnica interna; no forman parte de un compromiso de cliente móvil. Los refresh web solo se renuevan por el transporte de cookie.

La actualización requiere iniciar sesión de nuevo en los navegadores que solo tenían los tokens antiguos en localStorage.

## Configuración para producción

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tu-dominio.example
FRONTEND_URL=https://app.tu-dominio.example
CORS_ALLOWED_ORIGINS=https://app.tu-dominio.example
WEB_AUTH_ORIGINS=https://app.tu-dominio.example
WEB_ACCESS_TOKEN_TTL=15
```

Frontend y API deben servirse en el mismo sitio mediante HTTPS, preferiblemente detrás de un proxy de `/api`, o en subdominios del mismo dominio. SameSite=Strict no está pensado para frontend y API en sitios independientes. Si hay un proxy TLS, configurar `TRUSTED_PROXIES` únicamente con sus IP conocidas para que Laravel reconozca HTTPS. No usar comodines de origen para las cookies.

Aplicar las migraciones, ejecutar `php artisan config:clear`, compilar Angular y mantener activos los trabajadores y scheduler del despliegue. Verificar con dominios y certificados reales antes del piloto: login, segundo factor, recarga, expiración, logout y cambio de contraseña; comprobar los atributos de las cookies y que las respuestas no revelan refresh. La recuperación por correo sigue dependiendo de un SMTP operativo; el modo consola es solo local.

## Pendientes de aceptación

La implementación de cuentas y sesión web está disponible en el código. La documentación formal de pruebas se realizará en una fase posterior. La aceptación con dominio/certificado reales y la entrega por SMTP corresponden al cierre operativo del despliegue.

## Referencias de seguridad

- [OWASP: prevención CSRF y tokens ligados a la sesión](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html).
- [MDN: atributos de cookies y prefijo __Host-](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Set-Cookie).
