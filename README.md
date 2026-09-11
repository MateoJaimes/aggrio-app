# Aggrio

Plataforma digital de trazabilidad e inteligencia agrícola (sector cacaotero). Centraliza el registro de fincas, lotes, actividades, evidencia multimedia georreferenciada y lecturas de sensores IoT.

Este repositorio contiene el **backend** (Laravel, en la raíz del repo) y el **frontend en migración** (Angular, en `frontend/`). El panel administrativo actual está construido en **Filament** y sigue siendo la herramienta real de gestión mientras se completa la migración a Angular, módulo por módulo.

## Stack

| Capa | Tecnología |
| --- | --- |
| Backend / API REST | Laravel 13 + Sanctum (auth por token Bearer) |
| Admin panel (actual) | Filament 5 |
| Admin panel (en migración) | Angular 21 |
| Almacenamiento de archivos | MinIO (compatible con S3) |
| Base de datos | MySQL |

## Requisitos previos

- PHP 8.4+ y Composer
- Node.js 20+ y npm
- MySQL (o el motor configurado en `.env`)
- Angular CLI: `npm install -g @angular/cli`

## Puesta en marcha

Necesitas **dos servidores corriendo en paralelo**: uno para el backend y otro para Angular.

### 1. Backend (Laravel + Filament)

```bash
composer install
cp .env.example .env   # si no existe, crear con las credenciales de BD y MinIO
php artisan key:generate
php artisan migrate
php artisan serve       # http://localhost:8000
```

El panel de Filament queda disponible en `http://localhost:8000/admin`.

### 2. Frontend (Angular)

```bash
cd frontend
npm install
ng serve                # http://localhost:4200
```

Angular usa `proxy.conf.json` para reenviar todo lo que empiece en `/api` hacia `http://localhost:8000`, así en desarrollo nunca hay problemas de CORS ni hay que tocar `config/cors.php` en el backend.

Con ambos corriendo: abre `http://localhost:4200`, el login pega contra la API real de Laravel.

## Cómo se conectan Angular y Laravel

**Angular nunca toca la base de datos ni Filament directamente.** Solo consume la API REST bajo `/api/*` — la misma que ya usa la app de Flutter.

**La autenticación es por token, no por cookie de sesión.** No usamos el flujo "SPA" de Sanctum (`/sanctum/csrf-cookie`, `withCredentials`). El flujo real es:

1. Angular hace `POST /api/login` con email/contraseña.
2. Laravel responde con un token Sanctum.
3. Angular guarda el token y lo manda en cada request como `Authorization: Bearer <token>` (vía `core/auth/auth.interceptor.ts`).

Si alguien del equipo sigue un tutorial de "Angular + Sanctum SPA auth", no le va a funcionar con este backend — es a propósito, para no duplicar lógica con el cliente Flutter, que ya usa este mismo esquema.

**Subida de multimedia** es el único flujo de tres pasos:

1. Angular pide una URL prefirmada: `POST /api/minio/presigned-url`.
2. Angular sube el archivo **directo a MinIO** con esa URL (Laravel nunca recibe los bytes).
3. Angular registra la metadata en Laravel: `POST /api/multimedia/subir`.

## Endpoints de la API (referencia rápida)

| Recurso | Rutas |
| --- | --- |
| Auth | `POST /api/login` · `GET /api/me` · `POST /api/logout` · `POST /api/logout-all` |
| Fincas (estates) | `GET /api/mis-fincas` · `POST /api/fincas/solicitar` · `PUT /api/fincas/{id}/completar` |
| Lotes (lots) | `GET /api/fincas/{finca_id}/lotes` · `POST /api/fincas/{finca_id}/lotes` · `PATCH /api/lotes/{lote_id}/estado` |
| Actividades (activities) | `GET /api/lotes/{lote_id}/actividades` · `POST /api/lotes/{lote_id}/actividades` |
| Multimedia | `POST /api/minio/presigned-url` · `POST /api/multimedia/subir` · `POST /api/fincas/{id}/multimedia` |
| IoT | `POST /api/iot/lecturas` (pública, sin token) · `GET /api/lotes/{lote_id}/lecturas` |

Cualquier dato que necesites desde Angular y no esté en esta lista **no existe todavía en la API** — hay que crearlo en el backend antes de consumirlo, no inventarlo del lado del cliente.

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

## Orden de migración (Filament → Angular)

No se migra todo de una vez. Filament sigue siendo el panel real hasta que cada módulo esté probado en Angular:

1. **Estates + Lots** — ya tienen API completa, es el core del dominio.
2. **Activities**
3. **Multimedia** — más compleja por el flujo de presigned URL.
4. **IoT** — al final.

## Comandos útiles

```bash
ng serve                    # levantar Angular en desarrollo
ng build                    # build de producción, sale en frontend/dist/
ng generate component features/estates/pages/estate-list   # scaffolding respetando la convención de naming
ng test                     # tests unitarios (Vitest)
php artisan serve            # levantar el backend
php artisan migrate:fresh --seed   # resetear BD local con datos de prueba
```

## Variables de entorno

**Angular** (`frontend/src/environments/`): `apiUrl` apunta a `http://localhost:8000/api` en desarrollo. No hardcodear URLs en los servicios.

**Laravel** (`.env`): credenciales de BD y de MinIO. `config/cors.php` tiene `allowed_origins => ['*']` para desarrollo local — **hay que restringirlo al dominio real antes de pasar a producción**.

## Dudas frecuentes

- **¿Por qué no veo mis cambios en Filament reflejados en Angular?** Angular no lee de Eloquent ni de los `Resources` de Filament, solo de `routes/api.php`. Si algo existe en Filament pero no está expuesto como endpoint de API, Angular no puede verlo.
- **¿Dónde reporto que falta un endpoint?** Ábrelo como issue en el repo backend antes de improvisar la lógica en el frontend.
