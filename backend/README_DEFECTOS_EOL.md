# API Defectos Internos EOL

Esta carpeta es una copia separada de `C:\DATOS\api` para empezar a aislar el backend que usa `defectos_internos_eol-1`.

## Que se agrego

- Modelo `App\Models\FactoryConfig`.
- Controlador `App\Http\Controllers\FactoryConfigController`.
- Migracion `2026_06_18_000001_create_factory_configs_table.php`.
- Rutas:
  - `GET /api/factory-config`
  - `POST /api/factory-config`
  - `PUT /api/factory-config`

La configuracion inicial ya incluye empresas `SAR` y `TBAR`, lineas `EOL-SAR` y `EOL-TBAR`, categorias de defectos, defectos base y switch de scrap por linea.

## Endpoints que sigue usando el front EOL

La copia conserva los endpoints actuales del front:

- `POST /api/auth/validar_usuario`
- `GET /api/fallas`
- `POST /api/etiqueta/findelinea`
- `POST /api/eol`
- `POST /api/fallas_informadas`
- `POST /api/retrabajo`
- `GET /api/retrabajos/sin_etiqueta`
- `GET /api/retrabajos/pendientes/scrap`
- `GET /api/fallas/fmds`
- `POST /api/fallas/fmds`
- `GET /api/scrap/sectores`
- `GET /api/scrap/materiales_piezas`
- `POST /api/scrap`
- `POST /api/scrap/filter`
- `DELETE /api/scrap/{id}`
- `GET /api/modelos/estructura`
- `POST /api/import/bom-m11/preview`
- `POST /api/import/bom-m11/confirm`

## Puesta en marcha

1. Instalar dependencias PHP:

```powershell
cd "C:\DATOS\api defectos"
composer install
```

2. Crear `.env` desde `.env.example` y configurar la base de datos:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

3. Ejecutar migraciones. Si la base ya existe y solo queres agregar la tabla nueva de config:

```powershell
php artisan migrate --path=database/migrations/2026_06_18_000001_create_factory_configs_table.php
```

4. Levantar la API local:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

5. Apuntar el front `C:\DATOS\defectos_internos_eol-1` a esta API:

```env
VITE_API_URI='http://localhost:8000/api/'
VITE_API_PUBLIC_URI='http://localhost:8000/'
```

En produccion, cambiar esas URLs por la IP/puerto donde se publique esta API separada.

## Verificacion rapida

Con la API levantada:

```powershell
Invoke-RestMethod http://localhost:8000/api/factory-config
```

Debe devolver `error: false` y un objeto `data` con empresas `SAR` y `TBAR`.
