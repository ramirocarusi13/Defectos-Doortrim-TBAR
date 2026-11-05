// Helpers compartidos de la linea de door trims (marcar, retrabajo, validar).
import { formatCountdown } from "./utils";

export const TIPOS = [
  { id: "PANEL", label: "PANEL" },
  { id: "ARM_REST", label: "ARM REST" },
  { id: "GARNISH", label: "GARNISH" },
];

// Mismas equivalencias que DoorService (backend).
export function normTipo(t) {
  const v = String(t || "").trim().toUpperCase().replace(/[ -]/g, "_");
  if (["DOOR", "PANEL", "DOOR_PANEL"].includes(v)) return "PANEL";
  if (v === "ARMREST") return "ARM_REST";
  return v;
}
export function normLado(l) {
  const v = String(l || "").trim().toUpperCase();
  if (["LH", "L", "IZQ", "IZQUIERDO", "IZQUIERDA", "LEFT"].includes(v)) return "LH";
  if (["RH", "R", "DER", "DERECHO", "DERECHA", "RIGHT"].includes(v)) return "RH";
  return v;
}
export const normModelo = (m) => String(m || "").trim().toUpperCase();
export const tipoLabel = (t) => TIPOS.find((x) => x.id === normTipo(t))?.label || t || "-";

// Familia JITMS del numero de parte escaneado -> tipo de pieza de la planilla.
export const FAMILIA_A_TIPO = { DOOR: "PANEL", PANEL: "PANEL", ARMREST: "ARM_REST", ARM_REST: "ARM_REST", GARNISH: "GARNISH" };

export function modeloPorCodigo(cfg, codigo) {
  const c = String(codigo || "").toUpperCase();
  if (!c) return null;
  return (cfg?.models || []).find((m) => String(m.code || "").toUpperCase() === c) || null;
}

export function ladoDeModelo(m) {
  const mano = String(m?.jitms?.mano || "").toUpperCase();
  if (mano === "LH" || mano === "RH") return mano;
  const nombre = String(m?.name || "");
  return /\bLH\b/i.test(nombre) ? "LH" : /\bRH\b/i.test(nombre) ? "RH" : "";
}

// Piezas de la planilla que corresponden a lo escaneado. Siempre UNA por tipo, asi
// se va directo a la foto: el arm rest tiene dos listas en la planilla (con / sin
// cover) y se juntan en una sola "ARM REST" con los defectos de las dos (sin
// repetir los de igual nombre). Si la familia no se conoce, se ofrecen todas.
export function piezasPara(catalogo, familia) {
  const todas = catalogo?.piezas || [];
  const tipo = FAMILIA_A_TIPO[String(familia || "").toUpperCase()];
  if (!tipo) return todas;
  const filtradas = todas.filter((p) => p.tipo_pieza === tipo);
  if (filtradas.length <= 1) return filtradas.length ? filtradas : todas;
  const vistos = new Set();
  const defectos = [];
  for (const p of filtradas) {
    for (const d of p.defectos || []) {
      const clave = String(d?.nombre || d?.codigo || "").trim().toUpperCase();
      if (vistos.has(clave)) continue;
      vistos.add(clave);
      defectos.push(d);
    }
  }
  return [{ ...filtradas[0], id: tipo, nombre: tipoLabel(tipo), defectos }];
}

// Requisitos del reemplazo con los QR de la app de camaras (POST eol-door/qr):
// mismo tipo, SD, posicion y mano, curado de 4 hs (armrest: fecha de encolado del
// QR; panel: base TB-L) y disponible. El garnish solo compara el SD (su QR es el SD).
//   entra / sale: lo que devolvio eol-door/qr de cada QR (sale puede ser null)
//   pieza:        la que entra en el maestro del buffer (null si nunca se dio de alta)
export function chequearReemplazoQr(entra, { sale, pieza, tipo }) {
  const checks = [];
  const t = normTipo(tipo || sale?.tipo);
  checks.push({
    id: "tipo",
    label: `Es ${tipoLabel(t)}`,
    ok: entra.tipo === t,
    detalle: entra.tipo === t ? null : `Es ${tipoLabel(entra.tipo)}`,
  });
  const campos = entra.tipo === "GARNISH"
    ? [["sd", "Mismo modelo (SD)"]]
    : [["sd", "Mismo modelo (SD)"], ["posicion", "Misma posición"], ["mano", "Misma mano"]];
  for (const [campo, label] of campos) {
    const v = entra[campo];
    const r = sale?.[campo];
    if (!v || !r) checks.push({ id: campo, label, ok: true, detalle: "Sin dato para comparar" });
    else checks.push({ id: campo, label, ok: v === r, detalle: v === r ? v : `Es ${v}, tiene que ser ${r}` });
  }
  if (entra.tipo === "GARNISH") return checks;

  if (pieza) {
    checks.push({
      id: "disponible",
      label: "Disponible",
      ok: pieza.estado === "DISPONIBLE",
      detalle: pieza.estado === "DISPONIBLE" ? null : `Estado: ${pieza.estado}`,
    });
  }
  const faltaBuffer = pieza ? formatCountdown(pieza.disponible_desde) : null;
  if (entra.curado_fuente === "SIN_DATO") {
    checks.push({ id: "curado", label: "Curado (4 hs)", ok: !faltaBuffer, detalle: faltaBuffer ? `Faltan ${faltaBuffer}` : "Sin hora del panel (TB-L)" });
  } else {
    const minutos = entra.curado_faltan_minutos || 0;
    const porMinutos = minutos >= 60 ? `${Math.floor(minutos / 60)}h ${minutos % 60}m` : `${minutos}m`;
    const falta = entra.curado_ok === false ? formatCountdown(entra.curado_hasta) || porMinutos : faltaBuffer;
    checks.push({ id: "curado", label: "Curado completo (4 hs)", ok: !falta, detalle: falta ? `Faltan ${falta}` : null });
  }
  return checks;
}

// Chequeo previo (en la tablet) de los requisitos de la pieza del buffer que
// entra, para ver por que no sirve antes de confirmar. El backend vuelve a
// chequear todo al guardar.
//   entra: pieza del maestro (o null si no existe)
//   tipo:  PANEL / ARM_REST / GARNISH
//   sale:  pieza que sale (o null si no estaba registrada)
//   lado:  lado del door trim (referencia si la que sale no esta registrada)
//   codigo: numero de parte del QR del door trim (idem, para el modelo)
export function chequearReemplazo(entra, { tipo, sale, lado, codigo }) {
  if (!entra) {
    return [{ id: "existe", label: "Registrada en el buffer", ok: false, detalle: "No existe en el sistema" }];
  }
  const checks = [];
  checks.push({
    id: "disponible",
    label: "Disponible",
    ok: entra.estado === "DISPONIBLE",
    detalle: entra.estado === "DISPONIBLE" ? null : `Estado: ${entra.estado}`,
  });
  const falta = formatCountdown(entra.disponible_desde);
  checks.push({ id: "curado", label: "Curado completo (4 hs)", ok: !falta, detalle: falta ? `Faltan ${falta}` : null });
  checks.push({
    id: "tipo",
    label: `Es ${tipoLabel(tipo)}`,
    ok: normTipo(entra.tipo_pieza) === normTipo(tipo),
    detalle: normTipo(entra.tipo_pieza) === normTipo(tipo) ? null : `Es ${tipoLabel(entra.tipo_pieza)}`,
  });
  // Referencia: la pieza que sale. Si no esta registrada, el lado sale del door
  // trim y el modelo del numero de parte de su QR (el maestro puede tenerlo con
  // sufijo, ej 74220-0K140-C0: se compara por prefijo).
  const modeloSale = sale ? normModelo(sale.modelo) : "";
  const porPrefijo = !modeloSale && !!codigo;
  const refs = {
    modelo: modeloSale || normModelo(codigo),
    lado: (sale ? normLado(sale.lado) : "") || normLado(lado),
  };
  for (const [campo, label, norm] of [
    ["modelo", "Mismo modelo", normModelo],
    ["lado", "Mismo lado", normLado],
  ]) {
    const v = norm(entra[campo]);
    const r = refs[campo];
    if (!v || !r) {
      checks.push({ id: campo, label, ok: true, detalle: "Sin dato para comparar" });
    } else {
      const ok = campo === "modelo" && porPrefijo ? v.startsWith(r) || r.startsWith(v) : v === r;
      checks.push({ id: campo, label, ok, detalle: ok ? v : `Es ${v}, tiene que ser ${r}` });
    }
  }
  return checks;
}
