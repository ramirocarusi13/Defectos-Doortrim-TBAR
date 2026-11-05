// URLs del backend, en orden de prueba. La app usa la primera que responde y se
// queda con esa; si deja de responder, prueba las otras (src/api.js).
//   1) 172.17.132.150:9001 -> servidor de PRODUCCION de door (lee los QR de TB-L
//      en el .153). Red TBAR-OFFICE.
//   2) 127.0.0.1:9001 -> tablet por USB a una PC ("adb reverse",
//      INICIAR-DOORTRIM-LOCAL.bat)
//   3) la PC de prueba por la red (tablet en el mismo Wi-Fi que la PC):
//      192.168.9.74 en SAR-OFICINA, 172.16.0.149 en RND. Si cambia la IP de la
//      PC (CMD -> "ipconfig"), cambiarla aca.
// El backend tiene que escuchar en la red: php -S 0.0.0.0:9001 (ver el .bat).
export const API_BASES = [
  "http://172.17.132.150:9001/api",
  "http://127.0.0.1:9001/api",
  "http://192.168.9.74:9001/api",
  "http://172.16.0.149:9001/api",
];

// Servidor de produccion: SIEMPRE se prueba primero. Lo demas (PC recordada, USB,
// PCs de prueba) es solo de respaldo cuando el servidor no responde.
const SERVIDOR = API_BASES[0];

// PC encontrada en la red o escrita a mano (mantener 3 s el titulo del login):
// va despues del servidor.
let manual = null;

export const getServidorManual = () => manual;
export const setServidorManual = (ip) => {
  const v = String(ip || "").trim();
  if (!v) {
    manual = null;
  } else if (/^https?:\/\//i.test(v)) {
    manual = v.replace(/\/+$/, "").replace(/(\/api)?$/, "/api");
  } else {
    manual = `http://${v.includes(":") ? v : `${v}:9001`}/api`;
  }
  return manual;
};
export const getBases = () =>
  manual && manual !== SERVIDOR
    ? [SERVIDOR, manual, ...API_BASES.filter((b) => b !== SERVIDOR && b !== manual)]
    : API_BASES;

let actual = API_BASES[0];

export const getApiBase = () => actual;
export const setApiBase = (base) => {
  actual = base;
};

// Base pública (sin /api) para servir imágenes: ${getApiPublic()}uploads/<archivo>.
export const getApiPublic = () => actual.replace(/\/api\/?$/, "/");
