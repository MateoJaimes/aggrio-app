# Agro Tech + Agro Field

Plataforma AgroTech para captura de multimedia agricola (fotos, videos, audio, notas) vinculada a fincas, lotes y actividades.

## Arquitectura

- **Backend:** Laravel 13 + Sanctum (API REST) en `100.95.77.110:8080`
- **Panel admin:** Filament 5
- **App movil:** Flutter con Provider
- **Almacenamiento:** MinIO en `100.95.77.110`

## Requisitos

- PHP >= 8.4
- Composer
- Node.js + npm
- Flutter SDK >= 3.11
- SQLite

## Levantar el Backend

1. Clonar el repositorio
2. Copiar y configurar el archivo de entorno:
   ```
   cp .env.example .env
   ```
3. Instalar dependencias:
   ```
   composer install
   npm install
   ```
4. Generar clave de aplicacion y correr migraciones:
   ```
   php artisan key:generate
   php artisan migrate
   ```
5. Configurar las variables de MinIO en `.env` (ver seccion Variables de Entorno)
6. Iniciar el servidor:
   ```
   php artisan serve
   ```

## Levantar MinIO

```
docker compose -f docker-compose.minio.yml up -d
```

Acceso al panel: `http://localhost:9001`

Crear un bucket y configurar las credenciales en `.env`.

## Levantar la App Flutter

1. Ir al directorio del proyecto
2. Instalar dependencias:
   ```
   flutter pub get
   ```
3. Ejecutar:
   ```
   flutter run
   ```

La app se conecta al servidor compartido en `http://100.95.77.110:8080/api`. Para cambiar la URL, editar `lib/config/app_config.dart`.

## Variables de Entorno

| Variable | Descripcion |
|---|---|
| `FILESYSTEM_DISK` | `s3` para usar MinIO |
| `AWS_ENDPOINT` | URL de MinIO (ej: `http://100.95.77.110:9000`) |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` para MinIO |
| `AWS_ACCESS_KEY_ID` | Credencial de MinIO |
| `AWS_SECRET_ACCESS_KEY` | Credencial de MinIO |
| `AWS_BUCKET` | Nombre del bucket |

## Endpoints API

### Publicos
- `POST /login` - Autenticacion (devuelve token Bearer)
- `POST /iot/lecturas` - Datos de sensores IoT

### Protegidos (auth:sanctum)
- `GET /me` - Datos del usuario
- `POST /logout` - Revocar token
- `POST /logout-all` - Revocar todos los tokens
- `GET /mis-fincas` - Fincas aprobadas del usuario
- `POST /fincas/solicitar` - Solicitar nueva finca
- `PUT /fincas/{id}/completar` - Completar datos de finca
- `POST /fincas/{id}/multimedia` - Subir multimedia (archivo directo)
- `GET /fincas/{id}/lotes` - Lotes de una finca
- `POST /fincas/{id}/lotes` - Crear lote
- `GET /lotes/{id}/actividades` - Historial de actividades
- `POST /lotes/{id}/actividades` - Registrar actividad
- `GET /lotes/{id}/lecturas` - Lecturas IoT de un lote
- `POST /minio/presigned-url` - Obtener URL temporal para subida
- `POST /multimedia/subir` - Registrar multimedia post-subida

## Acceso al Panel Admin

El panel Filament esta disponible en `/admin`. El superadmin es el usuario con `id = 1`.

## Estructura del Proyecto

```
├── app/
│   ├── Console/          # Comandos artisan
│   ├── Filament/         # Panel admin (Resources, Widgets)
│   ├── Http/Controllers/ # Controladores API y Web
│   ├── Models/           # Modelos Eloquent
│   └── Services/         # Servicios
├── config/               # Configuracion Laravel
├── database/migrations/  # Migraciones
├── lib/                  # App Flutter
│   ├── config/           # Configuracion de la app
│   ├── models/           # Modelos locales
│   ├── providers/        # State management (Provider)
│   ├── screens/          # Pantallas
│   └── services/         # Servicios (API, DB, Sync, Media, etc.)
├── routes/               # Rutas API y Web
└── docker-compose.minio.yml
```
