import { getApiPublic } from "./config";

// Resuelve la URL de una imagen igual que la web (FactoryConfigService.resolveImageSource):
// data:/http(s) tal cual; si es solo nombre de archivo, va a /uploads.
export function resolveImageSource(imageNameOrUrl) {
  if (!imageNameOrUrl) return "";
  if (/^(data:|blob:|https?:\/\/)/i.test(imageNameOrUrl)) return imageNameOrUrl;
  return `${getApiPublic()}uploads/${imageNameOrUrl}`;
}

// Helpers de la web (FinDeLineaPage) para matchear vista <-> tipo/lado del asiento.
// Si el valor no es BC/CS (linea/dato distinto) devolvemos el valor crudo en
// vez de "" para que el banner del asiento no quede vacio.
export function getTipo(tipo) {
  if (tipo === "BC") return "BACK";
  if (tipo === "CS") return "CUSHION";
  return tipo || "";
}

export function getLado(lado) {
  return lado === "00" ? "100%" : lado;
}

// Calidad marca defectos y valida reingresos; el resto (retrabajador) opera
// el buffer. Mismo criterio que la web / eol-leves-app.
export function esCalidad(u) {
  return String(u?.departamento || "").trim().toUpperCase() === "CALIDAD";
}

export function getLineaLabel(linea) {
  const value = String(linea || "").trim();
  if (!value) return "";
  if (value.toUpperCase() === "F") return "FLEX";
  return value;
}

// Identificador de cliente para el cambio de pieza: evita duplicar la operacion
// si se reintenta el envio (resubmit por doble tap o reintento de red). No hace
// falta una libreria de UUID: alcanza con timestamp + dos bloques random.
export function genClientUuid() {
  const rnd = () => Math.random().toString(36).slice(2, 10);
  return `${Date.now()}-${rnd()}-${rnd()}`;
}

// Formatea un timestamp futuro (ISO o epoch ms) como cuenta regresiva "Xh Ym"
// o "Ym Zs" cuando falta menos de una hora. Si ya paso, devuelve "".
export function formatCountdown(target) {
  if (!target) return "";
  const t = typeof target === "number" ? target : Date.parse(target);
  if (!t || Number.isNaN(t)) return "";
  const diffMs = t - Date.now();
  if (diffMs <= 0) return "";
  const totalSec = Math.floor(diffMs / 1000);
  const h = Math.floor(totalSec / 3600);
  const m = Math.floor((totalSec % 3600) / 60);
  const s = totalSec % 60;
  if (h > 0) return `${h}h ${m}m`;
  if (m > 0) return `${m}m ${s}s`;
  return `${s}s`;
}

// Formatea una fecha (ISO o "YYYY-MM-DD HH:mm:ss") como "DD/MM HH:mm" legible,
// en vez del ISO crudo que devuelve el backend en los eventos/historial.
export function formatFecha(value) {
  if (!value) return "-";
  const t = Date.parse(String(value).replace(" ", "T"));
  if (Number.isNaN(t)) return String(value);
  const d = new Date(t);
  const pad = (n) => String(n).padStart(2, "0");
  return `${pad(d.getDate())}/${pad(d.getMonth() + 1)} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

// Usuario de practica / capacitacion: ve todas las pestañas para probar el circuito.
export function esCapacitacion(u) {
  return String(u?.departamento || "").trim().toUpperCase() === "CAPACITACION";
}
