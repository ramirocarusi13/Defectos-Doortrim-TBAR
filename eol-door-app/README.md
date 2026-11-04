# DOORTRIM DEFECTOS (Expo / React Native)

App para tablet con scanner de la **línea de door trims**. Cada door trim lleva 3 piezas
(**PANEL, ARM REST y GARNISH**), cada una con su QR. Sin interlock JITMS. Ícono y splash: logo TBAR
rojo sobre fondo negro (inverso de EOL Defectos).

## Cómo funciona el puesto (sin pestañas: la pantalla depende del usuario)
1. **Calidad** (`src/PantallaDoorTrim.js`) escanea el door trim, marca el defecto en la foto y toca
   **CARGAR DEFECTO** (`POST eol-door/marcar-retrabajo`). El defecto queda PENDIENTE y listo.
2. **Producción** (`src/PantallaRetrabajoDoor.js`, entra directo al loguearse) escanea el door trim,
   ve el defecto en la foto y decide:
   - **RETRABAJAR** (se arregla en el momento, ej: una mancha) → `PUT eol-door/defecto/{id}/retrabajar`.
   - **CAMBIAR** (el retrabajo tarda) → la pieza que sale es la escaneada; solo se escanea la del
     buffer que entra, con los requisitos (tipo, número de parte, lado, disponible, curado 4 hs) →
     `POST eol-door` con `defecto_ids`. Queda registrado "A se cambió por B"; A va al buffer.
   Al entrar, producción ve solo cuántos retrabajos hay pendientes. El buffer
   (`PantallaBuffer.js`) se abre con "VER BUFFER" desde esa pantalla.
3. **Calidad** reinspecciona escaneando la pieza (la misma si se retrabajó, la nueva si se cambió)
   y, igual que en asientos, salta la ventana con sus defectos ("Esta pieza reemplaza a A…" en el
   caso del cambio): **VALIDAR** o **RECHAZAR** (`PUT eol-door/defecto/{id}/validar`;
   `GET eol-door/validaciones?qr=` busca por la pieza con el defecto o por la que la reemplazó).
   Rechazado vuelve a producción.

Calidad = departamento CALIDAD (y el usuario de práctica 9005, CAPACITACION). Todo el resto entra
como producción. Usuario de práctica de producción: 9006 (`php backend/seed-usuario-retrabajo-practica.php`).
Prueba del circuito: `php backend/doortrim-local-test.php`.

## La pieza sale del QR (app de cámaras, sin JITMS)
Los QR los genera la app de cámaras del .153 (sus datos están en la base `TB-L` de ese server).
Door **no consulta JITMS**: `POST eol-door/qr` (`backend/app/Services/DoorQrService.php`) lee el QR
con el mismo formato que esa app:

| Pieza | Formato | Ejemplo |
|---|---|---|
| PANEL | campos separados por `;`: secuencia; mano; posición; SD | `0001;RH;FR;D3L` |
| ARMREST | por posición, 21: fecha y hora de encolado (14) + SD (3) + posición (2) + mano (2) | `20260728142712A4LRRRH` |
| GARNISH | todo el código es el SD | `GDC` |

Con SD + tipo + posición + mano busca el número de parte en /config (`jitms.sd`/`sdTasa`,
`jitms.familia`, `jitms.posicionMano`). Panel y garnish pasan directo a marcar; ARMREST pregunta solo
"con o sin cover". Si el modelo no tiene foto, el defecto se carga sin posición.

**Cambio de pieza** (requisitos comparando los dos QR): mismo tipo, SD, posición y mano, y curado de
4 hs (`settings.door.minutosSecado`). El armrest trae la hora de encolado en el QR. El panel no:
su hora está en TB-L (`DOOR_TBL_SQL_PANEL`, se arma con `php backend/tbl-explorar.php` cuando la PC
llegue al .153; mientras tanto el panel queda "sin hora"). La pieza que entra no necesita estar dada
de alta en el buffer: se registra sola con los datos de su QR.
**Garnish:** su QR es solo el SD (todos los de un modelo son iguales), así que no se sigue pieza por
pieza: al cambiarlo solo se controla que sea el mismo SD y queda "garnish GDC cambiado".
Prueba: `php backend/doortrim-qr-local-test.php`.

## Usarla con la base local de una PC (tablet por USB)
`INICIAR-DOORTRIM-LOCAL.bat` (raíz del repo): hace `adb reverse tcp:9001 tcp:9001` y levanta
el backend en el 9001. La app tiene que estar compilada con `API_BASE = http://127.0.0.1:9001/api`.
Sirve aunque la PC y la tablet estén en redes distintas.

## Bases de producción: solo lectura
Door trims **no usa JITMS**: el QR se lee con su formato y, para la hora del panel, con la base
`TB-L` de la app de cámaras (conexión `tbl`, que el backend deja **siempre** en solo lectura).
Todo lo que registra door se guarda en `EOL_TBAR`. La app no llama a ningún endpoint del interlock de
asientos (`POST eol-defecto`, `eol-defecto/{id}/validar`) y `DoorService` no usa `InterlockService`.
Igual, el backend de door va con:
```
EOL_INTERLOCK_ENABLED=false
JITMS_SOLO_LECTURA=true
```
Con `JITMS_SOLO_LECTURA=true` la conexión `sqlsrvsar` rechaza antes de mandarla cualquier sentencia que no
sea `SELECT` (INSERT, UPDATE, DELETE, EXEC, SELECT INTO…), aunque el usuario de la base tenga permisos de
escritura (ver `AppServiceProvider::trabarJitmsSoloLectura`).

## Tablets con las dos apps (asientos + door trims)
`kiosco-zebra/INSTALAR-DOS-APPS.bat [serie]`: pone EHS en modo usuario (sin kiosco) con solo
EOL Defectos y DOORTRIM DEFECTOS; el botón Inicio muestra las dos para pasar de una a la otra.
Ajustes siguen restringidos y la depuración USB queda activa. Para volver al kiosco de una sola
app: `INSTALAR-KIOSCO.bat`. Al volver de la otra app Android puede recrear la pantalla: DOORTRIM
recuerda el usuario (`src/sesion.js`, hasta "Cambiar usuario" o 12 hs).

## Lista de defectos
Sale de la planilla de Calidad "Lista defectos Door Trim 737D" (una hoja por pieza: Paneles,
Armrest CON cover, Armrest SIN cover, Garnish; columnas ID | Rango | Defecto | Grupo).
Para cargar una revisión nueva, en el servidor:
```
php backend/importar-defectos-doortrim.php "ruta\Lista defectos Door Trim 737D - Rev. xx.xlsx"
```
Genera `backend/resources/data/defectos-doortrim.json`, que la tablet baja de
`GET /api/eol-door/defectos`: no hace falta recompilar el APK. Códigos sobre la foto:
P# panel, AC# arm rest con cover, AS# arm rest sin cover, G# garnish. El rango (A/B/C)
queda como característica del defecto. La lista de defectos de asientos (/config) no se usa acá.

## Prueba local
- Backend: `php backend/doortrim-local-test.php` (requisitos y flujo en un paso).
- Piezas demo para probar en la tablet: `php backend/seed-doortrim-demo.php`
  (`--borrar` para limpiarlas). Door trim: tipear `DEMO-DOORTRIM-01` y elegir el modelo
  "PANEL ASSY FR LH M1" a mano.
- Door trim real de JITMS local (panel P1A FR LH, resuelve por plantilla):
  `261030100421A00P1A000001212201001101001110`. Todos los QR de prueba juntos para escanear
  desde la pantalla: `qr-prueba-doortrim.html`.
- Sin buffers en /config la pestaña BUFFER trabaja con todas las piezas ("GENERAL").
  No hay selector de puesto: la línea tiene uno solo y se muestran todos los defectos.

## Stack
- **Expo** (managed workflow) + React Native + JavaScript.
- Sin librerías extra: scanning por `TextInput` (sirve para scanners hardware tipo "wedge" que
  tipean + Enter), con debounce de 300ms para los que no mandan Enter.

## Configuración (importante)
1. Editá `src/config.js` y poné la **IP del servidor** (NO `localhost`):
   ```js
   export const API_BASE = "http://172.17.132.150:9001/api";
   ```
   El deploy de DOOR corre en el **puerto 9001**, con base `EOL_DOOR` propia (separada de
   `EOL_TBAR`).
2. La tablet y el servidor tienen que estar en la **misma red**.
3. En el backend, asegurate de que `php artisan serve` escuche en la red:
   `php artisan serve --host=0.0.0.0 --port=9001`

## Correr
```bash
cd eol-door-app
npm install
npx expo start
```
Para compilar el APK: ver `../build-apk-door.bat` (o `../build-apk.ps1 -App door`) en la raíz
del repo.

## Trazabilidad
Toda pieza (DOOR / ARM_REST) tiene un QR permanente. Ciclo de vida:
`DISPONIBLE → (cambio) → tomada por producción → DEFECTUOSA en buffer → EN_RETRABAJO
→ reingresada (OK cura 4hs / SCRAP) → PEND_VALIDACION → VALIDADO → DISPONIBLE`.
Cada paso queda registrado como evento de la pieza (`GET /eol-piezas/{qr}/eventos`).

## Endpoints usados (backend dedicado de DOOR)
- `POST auth/validar_usuario` — login por código.
- `GET factory-config` / `GET factory-config/version` — modelos, fotos, defectos, puestos.
- `POST eol-defectos/reporte`, `POST etiqueta/findelinea`, `POST eol-defecto`,
  `PUT eol-defecto/{id}/reparar`, `PUT eol-defecto/{id}/validar` — marcado de defectos (CALIDAD).
- `GET eol-door/contexto?qr=...` — resuelve asiento/pieza, defectos abiertos y stock del buffer.
- `POST eol-door` — crea el cambio (pieza defectuosa ↔ pieza de reemplazo).
- `GET eol-buffer/stock?buffer_id=...` — stock agregado del buffer.
- `GET eol-piezas`, `GET eol-piezas/{qr}`, `GET eol-piezas/{qr}/eventos`, `POST eol-piezas` —
  maestro de piezas y su trazabilidad.
- `PUT eol-door/pieza/{qr}/tomar`, `PUT eol-door/pieza/{qr}/reingresar`,
  `PUT eol-door/pieza/{qr}/validar` — ciclo del retrabajador y validación de Calidad.
