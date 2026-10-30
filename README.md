# Aggrio

Seguridad del superadmin: [Configuración, aplicaciones autenticadoras y navegadores recordados](docs/configuracion-y-dispositivos.md), disponible en `/configuracion`.

Gestión de producción integrada en `/produccion`: [parametrización, cultivos, ciclos, costos, cosechas, ventas y trazabilidad](docs/produccion-y-rentabilidad.md). Incluye reportes financieros y la paleta principal `#ec1c21`.

Documentación vigente: [cuentas, sesiones y permisos](docs/cuentas-sesiones-y-permisos.md), [implementación de semanas 3 y 4](docs/implementation-weeks-3-4.md) y [diseño compartido](docs/design-system.md). La documentación formal de pruebas se actualizará en la fase posterior.

Plataforma digital de trazabilidad e inteligencia agrícola (sector cacaotero). Centraliza el registro de fincas, lotes, actividades, evidencia multimedia georreferenciada y lecturas de sensores IoT.

Este repositorio contiene el **backend API** (Laravel, en la raíz del repo) y el **frontend** (Angular, en `frontend/`). Angular ofrece administración y el espacio propio del productor en `/campo`; Laravel no expone un panel administrativo web.

## Stack

| Capa | Tecnología |
| --- | --- |
| Backend / API REST | Laravel 13 + Sanctum (auth por token Bearer) |
| Interfaz de Superadmin y productor | Angular 21 |
| Almacenamiento de archivos | Disco local privado o MinIO/S3 configurado |
| Base de datos | PostgreSQL en el entorno local; conexión configurable |

## Requisitos previos

- PHP 8.4+ y Composer
- Node.js 20+ y npm
- PostgreSQL (o el motor configurado en `.env`)
- Angular CLI: `npm install -g @angular/cli`

## Puesta en marcha

Necesitas **dos servidores corriendo en paralelo**: uno para el backend y otro para Angular.

### 1. Backend (Laravel API)

```bash
composer install
cp .env.example .env   # si no existe, crear con las credenciales de BD y MinIO
php artisan key:generate
php artisan migrate
php artisan serve       # API: http://localhost:8000
```

Al abrir `http://localhost:8000`, Laravel muestra la landing con acceso al login de Angular. Angular debe estar levantado también.

### 2. Frontend (Angular)

```bash
cd frontend
npm install
ng serve                # http://localhost:4200
```

Angular usa la regla `/api/**` de `proxy.conf.json` para reenviar las rutas de la API hacia `http://127.0.0.1:8000`. Si frontend y API se sirven desde orígenes distintos, configura los orígenes permitidos en el entorno del backend.

Con ambos corriendo: abre `http://localhost:4200`, el login pega contra la API real de Laravel.

## Cómo se conectan Angular y Laravel

**Angular nunca toca la base de datos directamente.** Consume la API REST bajo `/api/*`. El producto actual es web, con panel Superadmin y espacio del productor; no contempla una aplicación móvil independiente.

La sesión web sigue este contrato:

1. Angular inicia sesión por `/api/web/auth/login`, completa el segundo factor cuando corresponde y conserva el access token solo en memoria.
2. El refresh web se guarda en una cookie HttpOnly y se renueva con comprobaciones de origen y CSRF. No se devuelve en JSON ni se guarda en el almacenamiento de JavaScript.
3. Las rutas de datos exigen `Authorization: Bearer`; la cookie de refresh no autentica contra ellas.
4. Al recargar, Angular restaura la sesión mediante la cookie. El cierre actual o total revoca las sesiones y retira la cookie. Los endpoints legados del servidor no forman parte de un compromiso de producto móvil.

Consulta [cuentas, sesiones y permisos](docs/cuentas-sesiones-y-permisos.md) para los flujos de suspensión, reactivación, recuperación y la configuración HTTPS de producción. En la app, `/guia` explica los permisos por rol y `/accounts` permite al Superadmin administrar productores.

**Subida de multimedia con URL firmada** mantiene el flujo de tres pasos para los clientes que lo usan:

1. Angular pide una URL prefirmada: `POST /api/minio/presigned-url`.
2. El cliente sube el archivo con esa URL a MinIO/S3, o a la ruta privada firmada si utiliza almacenamiento local.
3. El cliente finaliza en Laravel con `POST /api/multimedia/subir`; el servidor verifica el objeto real y el ticket antes de registrar.

El espacio Angular del productor también dispone de carga multipart privada por `/api/campo/multimedia/archivo`, con las mismas reglas de verificación y límites.

## Endpoints de la API (referencia rápida)

| Recurso | Rutas |
| --- | --- |
| Auth web | `POST /api/web/auth/login` · `GET /api/me` · `POST /api/web/auth/logout` · `POST /api/web/auth/logout-all` |
| Renovación y contraseña | `GET /api/web/auth/csrf` · `POST /api/web/auth/refresh` · `PUT /api/web/auth/password` |
| Cuenta y guía | `GET/PUT /api/campo/perfil` · pantallas `/campo`, `/guia` y `/accounts` según rol |
| Fincas (estates) | `GET /api/campo/fincas` · `POST /api/fincas/solicitar` · `PUT /api/fincas/{id}/completar` |
| Lotes (lots) | `GET/POST /api/campo/fincas/{finca}/lotes` · `PUT /api/campo/fincas/{finca}/lotes/{id}` · `DELETE /api/campo/lotes/{id}` archiva |
| Actividades (activities) | `GET/POST /api/campo/lotes/{lote}/actividades` |
| Multimedia | `POST /api/minio/presigned-url` · `POST /api/multimedia/subir` · `POST /api/fincas/{id}/multimedia` |
| IoT | `POST /api/iot/lecturas` (identidad técnica `X-Device-Key`; obligatoria en producción) · `GET/POST /api/campo/lotes/{lote}/lecturas` (historia propia y registro manual) |

Esta tabla resume el contrato web actual. `/api/campo/*` incluye todos los estados de finca, perfil, edición/archivo de lotes, monitoreo paginado, evidencia privada y dashboard propio. El alta pública usa `POST /api/access-requests`. La referencia de implementación y las tareas de cierre están en [Semanas 3 y 4](docs/implementation-weeks-3-4.md).

## Estructura del frontend

#Extendido en el frontend/README.md

```
frontend/src/app/
├── core/        # auth, interceptors, guards, modelos y enums transversales
├── shared/      # shell (layout), componentes reutilizables, pipes — sin lógica de negocio
└── features/    # un módulo por recurso de la API
    ├── auth/
    ├── estates/       (fincas)
    ├── lots/           (lotes)
    ├── activities/    (actividades)
    ├── multimedia/
    └── iot/
```

Convenciones:

- Componentes **standalone**, sin `NgModule`. Sin el infijo `.component.`: `estate-list.ts`, no `estate-list.component.ts`.
- Cada feature trae su propio `*.routes.ts` (lazy-loaded) y no importa directamente de otra feature — si dos módulos necesitan compartir algo, ese algo va en `shared/` o `core/`.
- `multimedia` tiene dos servicios porque la subida es un proceso de dos llamadas (`presigned-url.service.ts` + `multimedia.service.ts`), no uno solo.

## Panel Angular

Angular contiene el panel administrativo y Mi campo, con navegación y permisos propios de cada rol. Las nuevas funcionalidades se implementan en el espacio correspondiente y se respaldan con autorización en la API.

## Comandos útiles

```bash
ng serve                    # levantar Angular en desarrollo
ng build                    # build de producción, sale en frontend/dist/
ng generate component features/estates/pages/estate-list   # scaffolding respetando la convención de naming
ng test                     # tests unitarios (Vitest)
php artisan serve            # levantar el backend
php artisan migrate               # aplicar migraciones pendientes
```

## Variables de entorno

**Angular** (`frontend/src/environments/`): `apiUrl` usa `/api`; el proxy de desarrollo reenvía al backend. En producción el servidor debe reenviar ese prefijo a Laravel. No hardcodear URLs en los servicios.

**Laravel** (`.env`): credenciales de BD, almacenamiento, `FRONTEND_URL` y credenciales iniciales `SUPERADMIN_EMAIL` / `SUPERADMIN_PASSWORD`. `CORS_ALLOWED_ORIGINS` acepta orígenes explícitos separados por comas; por defecto utiliza `FRONTEND_URL`. Las contraseñas de BD se obtienen del entorno.

Sin MinIO configurado, la evidencia nueva se guarda en `storage/app/private` y se descarga mediante API autenticada. `EVIDENCE_DISK=s3` habilita MinIO/S3, cuyo bucket debe ser privado. No ejecutar `storage:link` para exponer evidencia privada. Las notas de audio se transcriben mediante trabajos de cola; se requiere `php artisan queue:work --timeout=180 --tries=3` y el proveedor configurado. El scheduler ejecuta la limpieza de cargas abandonadas.

## Dudas frecuentes

- **¿Dónde reporto que falta un endpoint?** Ábrelo como issue en el repo backend antes de improvisar la lógica en el frontend.

## Recorrido y responsabilidades

Consulta [Recorrido sencillo](docs/recorrido-sencillo.md) para el flujo de aprobación, preparación de finca, lotes, cultivos, trabajo y cuentas, y la separación entre el productor y el superadmin.
