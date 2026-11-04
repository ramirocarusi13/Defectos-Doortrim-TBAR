import AsyncStorage from "@react-native-async-storage/async-storage";
import * as Network from "expo-network";
import { getBases, getApiBase, setApiBase, setServidorManual } from "./config";

const KEY_SERVIDOR = "doortrim_servidor";

// IP del servidor escrita a mano en la tablet: se guarda y se usa primero.
export async function cargarServidorGuardado() {
  try {
    const v = await AsyncStorage.getItem(KEY_SERVIDOR);
    if (v) setServidorManual(v);
    return v || "";
  } catch (e) {
    return "";
  }
}
export async function guardarServidor(ip) {
  const url = setServidorManual(ip);
  try {
    if (url) await AsyncStorage.setItem(KEY_SERVIDOR, String(ip).trim());
    else await AsyncStorage.removeItem(KEY_SERVIDOR);
  } catch (e) {
    /* sin almacenamiento: vale hasta cerrar la app */
  }
  confirmado = false;
  return elegirServidor();
}

const SIN_CONEXION = { error: true, message: "No se pudo conectar con el servidor" };

// fetch con tiempo maximo: una IP que no esta en la red no tiene que colgar la app.
async function fetchConTiempo(url, opts, ms) {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), ms);
  try {
    return await fetch(url, { ...opts, signal: ctrl.signal });
  } finally {
    clearTimeout(t);
  }
}

let confirmado = false;

// Responde el backend de door en esa URL? (consulta liviana, con tiempo maximo)
async function responde(base, ms) {
  try {
    const res = await fetchConTiempo(`${base}/factory-config/version`, { method: "GET", headers: { Accept: "application/json" } }, ms);
    return res.ok;
  } catch (e) {
    return false;
  }
}

// Busca el primer servidor conocido que responde, empezando por el que se venia
// usando. Si ninguno responde, busca una PC con el backend (puerto 9001) en la
// misma red de la tablet y la recuerda: asi alcanza con abrir
// INICIAR-DOORTRIM-LOCAL.bat en la PC, sin escribir la IP en la tablet.
async function elegirServidor() {
  const actual = getApiBase();
  const bases = getBases();
  const orden = [actual, ...bases.filter((b) => b !== actual)];
  for (const base of orden) {
    if (await responde(base, 2500)) {
      setApiBase(base);
      confirmado = true;
      return true;
    }
  }
  return buscarEnLaRed();
}

let buscando = null;

// Prueba el puerto 9001 en las 254 IPs de la red de la tablet, de a 48 a la vez,
// y se queda con la primera que contesta (unos segundos).
function buscarEnLaRed() {
  if (buscando) return buscando;
  buscando = (async () => {
    let ip = "";
    try {
      ip = await Network.getIpAddressAsync();
    } catch (e) {
      return false;
    }
    if (!/^\d+\.\d+\.\d+\.\d+$/.test(ip || "") || ip === "0.0.0.0") return false;
    const red = ip.split(".").slice(0, 3).join(".");
    const hosts = [];
    for (let i = 1; i <= 254; i++) if (`${red}.${i}` !== ip) hosts.push(`${red}.${i}`);
    for (let i = 0; i < hosts.length; i += 48) {
      const lote = hosts.slice(i, i + 48);
      const encontrado = await new Promise((resolve) => {
        let pendientes = lote.length;
        lote.forEach((h) => {
          responde(`http://${h}:9001/api`, 1500).then((ok) => {
            if (ok) resolve(h);
            else if (--pendientes === 0) resolve(null);
          });
        });
      });
      if (encontrado) {
        setServidorManual(encontrado);
        try {
          await AsyncStorage.setItem(KEY_SERVIDOR, encontrado);
        } catch (e) {
          /* vale hasta cerrar la app */
        }
        confirmado = true;
        return true;
      }
    }
    return false;
  })();
  return buscando.finally(() => {
    buscando = null;
  });
}

// Manda la consulta al servidor elegido. Si se cae, la proxima vuelve a elegir
// (produccion, USB o la PC por red). Una lectura se reintenta enseguida; un
// POST/PUT no, porque pudo haber llegado y no se tiene que guardar dos veces.
async function pedir(path, opts) {
  if (!confirmado && !(await elegirServidor())) return SIN_CONEXION;
  try {
    const res = await fetchConTiempo(`${getApiBase()}/${path}`, opts, 20000);
    return await res.json();
  } catch (error) {
    confirmado = false;
    const esLectura = (opts.method || "GET") === "GET";
    if (esLectura && (await elegirServidor())) {
      try {
        const res = await fetchConTiempo(`${getApiBase()}/${path}`, opts, 20000);
        return await res.json();
      } catch (e) {
        /* tampoco */
      }
    }
    return SIN_CONEXION;
  }
}

function post(path, body) {
  return pedir(path, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });
}

function put(path, body) {
  return pedir(path, {
    method: "PUT",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });
}

function get(path) {
  return pedir(path, { method: "GET", headers: { Accept: "application/json" } });
}

// Arma un querystring a partir de un objeto, descartando claves vacias/undefined.
function qs(params) {
  const parts = Object.entries(params || {})
    .filter(([, v]) => v !== undefined && v !== null && v !== "")
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`);
  return parts.length ? `?${parts.join("&")}` : "";
}

// ---------------------------------------------------------------------------
// Comunes (heredados de eol-leves-app, mismo backend de config/auth/defectos)
// ---------------------------------------------------------------------------

// Configuracion de fabrica (modelos, imagenes, defectos configurables desde /config).
// MISMA fuente que la web -> asi tablet y web muestran lo mismo.
export function getFactoryConfig() {
  return get("factory-config");
}

// Version liviana de la config (timestamp). Se consulta seguido para detectar cambios
// del group leader en /config sin bajar los 488K del config completo cada vez.
export function getFactoryConfigVersion() {
  return get("factory-config/version");
}

// Valida el codigo de autorizacion del operario (mismo endpoint que la web)
export function validarUsuario(codigo) {
  return post("auth/validar_usuario", { codigo });
}

// Defectos de una funda (por QR). Devuelve la forma legacy mapeada desde eol_defectos.
export function getDefectosByQr(qr) {
  return post("eol-defectos/reporte", { qr });
}

// Registra el retrabajo del defecto (estado + quien reparo + fecha/hora)
export function repararDefecto(id, payload) {
  return put(`eol-defecto/${id}/reparar`, payload);
}

// CALIDAD valida o rechaza el retrabajo. payload = { user, accion: 'validar'|'rechazar', observaciones }
export function validarDefecto(id, payload) {
  return put(`eol-defecto/${id}/validar`, payload);
}

// Historial de eventos (trazabilidad) de un defecto
export function getEventosDefecto(id) {
  return get(`eol-defecto/${id}/eventos`);
}

// Guarda los defectos marcados en fin de linea (calidad). Mismo endpoint que la web.
// payload = { circles: [...], qr, user, linea, flujo, puesto_id, puesto_nombre }
export function guardarDefectos(payload) {
  return post("eol-defecto", payload);
}

// Trae los datos del asiento + las vistas/fotos del modelo (modelod.fallas).
// Mismo endpoint que la web de fin de linea.
export function getEtiquetaData(qr) {
  return post("etiqueta/findelinea", { qr });
}

// ---------------------------------------------------------------------------
// Cambio de piezas (DOOR / ARM_REST) contra el mini buffer
// ---------------------------------------------------------------------------
//
// El backend (EolDoorController) devuelve en /eol-door/contexto los
// `defectos` ya en el mismo formato legacy anidado que /eol-defectos/reporte
// ({ falla:{codigo,nombre}, tipo:{tipo}, lado:{lado}, imagen:{imagen},
// vista_id... }), asi que no hace falta adaptarlos: se consumen crudos, igual
// que DefectoCard/DefectoInfoModal/AsientoViewer ya esperan.

// Resuelve el QR escaneado (asiento o pieza): trae los defectos abiertos
// (flujo DOOR, PENDIENTE) del asiento, sugerencias de reemplazo disponibles
// y el stock del buffer correspondiente. Expone modelo/tipo/lado/secuencia
// "planos" (tomados del primer defecto) para pintar el banner del asiento
// sin lógica extra en la pantalla.
export async function getDoorContexto(qr) {
  const res = await get(`eol-door/contexto${qs({ qr })}`);
  if (res?.error || !res?.data) return res;

  const d = res.data;
  const defectosAbiertos = d.defectos || [];
  const primero = defectosAbiertos[0];

  return {
    ...res,
    data: {
      ...d,
      qr: d.qrAsiento || d.qr,
      defectos_abiertos: defectosAbiertos,
      sugerencias: d.sugerencias || [],
      modelo: primero?.modelo,
      tipo: primero?.tipo?.tipo,
      lado: primero?.lado?.lado,
      secuencia: primero?.secuencia,
    },
  };
}

// Crea el cambio: pieza defectuosa -> va al buffer; pieza de reemplazo -> sale
// del buffer y queda montada. payload incluye client_uuid para evitar duplicar
// el cambio si se reintenta el envio (resubmit / reintento de red).
export function crearDoor(payload) {
  return post("eol-door", payload);
}

// Tablet de door trims, todo en un paso: guarda las marcas del defecto y hace el
// cambio de pieza juntos. Si el reemplazo no cumple (tipo/modelo/lado/curado) no
// queda nada guardado y se puede probar con otra pieza.
// payload = { qr, tipo_pieza, circles, pieza_defectuosa_qr, pieza_reemplazo_qr,
//             modelo, lado, secuencia, linea, buffer_id, buffer_nombre, user,
//             puesto_id, puesto_nombre, client_uuid }
// Catalogo de defectos de door trim por pieza (PANEL, ARM REST con/sin cover,
// GARNISH): { grupos: [{id,nombre,color}], piezas: [{id,tipo_pieza,nombre,defectos:[...]}] }.
export function getDefectosDoorTrim() {
  return get("eol-door/defectos");
}

// Calidad manda el defecto a RETRABAJO en vez de cambiar la pieza: queda
// PENDIENTE y lo cierra el retrabajador. No toca el buffer.
// payload = { qr, tipo_pieza, circles, modelo, lado, secuencia, linea, user, puesto_nombre }
export function marcarRetrabajo(payload) {
  return post("eol-door/marcar-retrabajo", payload);
}

// Defectos esperando al retrabajador (opcional: solo los de un door trim).
export function getRetrabajosDoor(qr) {
  return get(`eol-door/retrabajos${qs({ qr })}`);
}

// El retrabajador cierra el defecto con su usuario. payload = { user, observaciones }
export function retrabajarDefectoDoor(id, payload) {
  return put(`eol-door/defecto/${id}/retrabajar`, payload);
}

// Lo que produccion ya resolvio (retrabajado o pieza cambiada) y espera Calidad.
export function getValidacionesDoor(qr) {
  return get(`eol-door/validaciones${qs({ qr })}`);
}

// Calidad valida o rechaza. payload = { user, accion: 'validar'|'rechazar', observaciones }
export function validarDefectoDoor(id, payload) {
  return put(`eol-door/defecto/${id}/validar`, payload);
}

export function marcarYCambiar(payload) {
  return post("eol-door/marcar-y-cambiar", payload);
}

// Stock agregado del buffer (disponibles / curando / defectuosas / en retrabajo
// / pendientes de validar). El backend devuelve enRetrabajo/pendValidacion en
// camelCase (unico endpoint asi); lo normalizamos a snake_case como el resto
// de la app para no mezclar convenciones en las pantallas.
export async function getBufferStock(bufferId) {
  const res = await get(`eol-buffer/stock${qs({ buffer_id: bufferId })}`);
  if (res?.error || !res?.data) return res;
  const d = res.data;
  return {
    ...res,
    data: {
      ...d,
      en_retrabajo: d.enRetrabajo,
      pend_validacion: d.pendValidacion,
    },
  };
}

// Listado de piezas del maestro, con filtros opcionales. El backend pagina
// (Eloquent::paginate) el listado; desenvolvemos para que `data` sea siempre
// el array de piezas y agregamos `meta` con la info de paginado por si hace
// falta mas adelante. per_page/orden son opcionales (ej: listar el buffer
// completo ordenado por antiguedad en vez de la primera pagina).
export async function getPiezas({ buffer_id, estado, tipo_pieza, q, per_page, orden } = {}) {
  const res = await get(`eol-piezas${qs({ buffer_id, estado, tipo_pieza, q, per_page, orden })}`);
  if (res?.error || !res?.data) return res;
  const paginador = res.data;
  const items = Array.isArray(paginador) ? paginador : paginador?.data || [];
  return {
    ...res,
    data: items,
    meta: Array.isArray(paginador)
      ? null
      : { total: paginador?.total, currentPage: paginador?.current_page, perPage: paginador?.per_page },
  };
}

// Ficha de una pieza puntual por su QR permanente. El backend devuelve
// { pieza, defectoAbierto, ultimoDoor, eventos } anidado; exponemos la
// pieza "aplanada" con el defecto abierto adentro para que el consumo sea
// directo (p.defecto en vez de p.defectoAbierto).
export async function getPieza(qr) {
  const res = await get(`eol-piezas/${encodeURIComponent(qr)}`);
  if (res?.error || !res?.data) return res;
  const { pieza, defectoAbierto, ultimoDoor, eventos } = res.data;
  return {
    ...res,
    data: pieza ? { ...pieza, defecto: defectoAbierto || null, ultimo_door: ultimoDoor || null, eventos } : null,
  };
}

// Historial de eventos (trazabilidad) de una pieza.
export function getPiezaEventos(qr) {
  return get(`eol-piezas/${encodeURIComponent(qr)}/eventos`);
}

// Alta de piezas nuevas en el maestro (carga inicial / reposicion del buffer).
// payload = { piezas: [{ qr, tipo_pieza, ... }], buffer_id, buffer_nombre, user }
export function altaPiezas(payload) {
  return post("eol-piezas", payload);
}

// El retrabajador toma una pieza DEFECTUOSA del buffer para retrabajarla.
export function tomarPieza(qr, payload) {
  return put(`eol-door/pieza/${encodeURIComponent(qr)}/tomar`, payload);
}

// Reingresa la pieza ya retrabajada. resultado: 'OK' (arranca el curado de 4hs)
// o 'SCRAP' (se da de baja).
export function reingresarPieza(qr, payload) {
  return put(`eol-door/pieza/${encodeURIComponent(qr)}/reingresar`, payload);
}

// CALIDAD valida o rechaza el reingreso de una pieza retrabajada.
export function validarPieza(qr, payload) {
  return put(`eol-door/pieza/${encodeURIComponent(qr)}/validar`, payload);
}

// Da de baja (SCRAP) una pieza directamente desde el buffer (ej: una pieza
// DEFECTUOSA que no vale la pena retrabajar), sin pasar por el circuito de
// reingreso. payload = { user, motivo }.
export function scrapPieza(qr, payload) {
  return put(`eol-door/pieza/${encodeURIComponent(qr)}/scrap`, payload);
}

// Que pieza es un QR de door trim (formato de la app de camaras, sin JITMS):
// { tipo, sd, mano, posicion, secuencia, codigo, familia, candidatos[], curado_* }.
export function interpretarQrDoor(qr) {
  return post("eol-door/qr", { qr });
}

// QR que dejo el puesto de control final en TB-PLC (tblDoorControlFinal):
// [{ tipo: PANEL|ARM_REST|GARNISH, qr, rh, lh }]. Solo lectura.
export function getControlFinal() {
  return get("eol-door/control-final");
}
