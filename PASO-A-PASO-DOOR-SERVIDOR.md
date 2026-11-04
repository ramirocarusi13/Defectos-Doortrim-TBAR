# DOORTRIM DEFECTOS en el servidor (.150)

Se instala **igual que EOL Defectos de asientos** (ver `PASO-A-PASO-SERVIDOR-CLIENTE.md`): imagen
Docker en un `.tar`, `docker load` y `docker compose up`, en el **mismo servidor 172.17.132.150**,
en la **misma carpeta** y con el **mismo `.env`**. Corre **aparte, en el puerto 9001**: no toca el
backend de asientos.

- Guarda todo en **EOL_TBAR** (la misma base de asientos, sus propias tablas de door).
- Lee los QR de la app de cámaras; la hora del panel sale de **TB-L** en el .153, **solo lectura**.
- **No usa JITMS ni el interlock** (quedan apagados).
- Las tablets ya buscan solas `http://172.17.132.150:9001/api`: no hay que reinstalar el APK.

## Qué llevar al servidor

| Archivo | Qué es |
|---|---|
| `eol-door-api.tar` | Imagen Docker del backend de door |
| `docker-compose.door.yml` | Compose de door (puerto 9001) |

Copiarlos a la carpeta donde ya está el `docker-compose.yml` de asientos.

## 1 — Agregar TB-L al `.env`

Al `.env` que ya usa asientos, sumarle:

```ini
DB_HOST_TBL=172.17.132.153,1433
DB_USER_TBL=dx
DB_PASS_TBL=<clave de TB-L>
```

`DB_HOST`, `DB_USER` y `DB_PASS` (EOL_TBAR) son los mismos de asientos.

## 2 — Cargar la imagen y levantar

```bash
docker load -i eol-door-api.tar
docker compose -f docker-compose.door.yml up -d
docker compose -f docker-compose.door.yml ps
```

## 3 — Tablas de door en EOL_TBAR

Ver si faltan migraciones (tablas/columnas nuevas de door; solo agregan, no borran):

```bash
docker compose -f docker-compose.door.yml exec door-api php artisan migrate:status
docker compose -f docker-compose.door.yml exec door-api php artisan migrate --force
```

## 4 — Abrir el puerto 9001

En el firewall del .150, permitir entrada TCP **9001** (igual que se hizo con el 9051 de asientos).

## 5 — Verificar

```bash
curl http://172.17.132.150:9001/api/factory-config/version
curl -X POST http://172.17.132.150:9001/api/eol-door/qr -H "Content-Type: application/json" -d "{\"qr\":\"GDC\"}"
```

El segundo tiene que contestar `"tipo":"GARNISH"`. Después, en una tablet en TBAR-OFFICE y **sin
cable**: entrar con un usuario y escanear una pieza.

## 6 — Hora de curado del panel (TB-L)

Para armar la consulta del panel hay que ver las tablas de TB-L. Desde el servidor (que llega al .153):

```bash
docker compose -f docker-compose.door.yml exec door-api php tbl-explorar.php "<QR de un panel>" > tbl-tablas.txt
```

Pasarle `tbl-tablas.txt` a Claude, que arma `DOOR_TBL_SQL_PANEL`. Se agrega al `.env` y se reinicia
con `docker compose -f docker-compose.door.yml up -d`.

## Si algo falla

| Síntoma | Causa probable |
|---|---|
| La tablet dice "No se pudo conectar con el servidor" | Puerto 9001 cerrado en el firewall del .150, o el contenedor parado (`... ps`) |
| `factory-config/version` da error 500 | El contenedor no llega a EOL_TBAR: revisar `DB_HOST`/`DB_USER`/`DB_PASS` y `docker compose -f docker-compose.door.yml logs door-api` |
| Escanea pero "no es una pieza de door trim" | El QR no tiene el formato de la app de cámaras (panel `sec;mano;pos;SD`, armrest 21 caracteres, garnish = SD) |
| El panel dice "sin hora (TB-L)" | Falta `DOOR_TBL_SQL_PANEL` (paso 6) |
