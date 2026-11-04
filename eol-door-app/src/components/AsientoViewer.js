import React, { useEffect, useState } from "react";
import { View, Text, Image, ActivityIndicator, TouchableOpacity } from "react-native";
import { styles, colors } from "../styles";
import { resolveImageSource, getTipo, getLado } from "../utils";

const MARKER = 34;

// Normaliza tipo/lado para matchear vista <-> defecto aunque vengan en formato
// crudo (BC/CS, 00) o ya legible (BACK/CUSHION, 100%).
const normTipo = (t) => {
  const v = String(t || "").trim().toUpperCase();
  if (v === "BC") return "BACK";
  if (v === "CS") return "CUSHION";
  return v;
};
const normLado = (l) => {
  const v = String(l || "").trim().toUpperCase();
  return v === "00" ? "100%" : v;
};

// Defectos que corresponden a ESTA vista/foto. Preferimos el match exacto por
// vista_id (la foto donde se marco en fin de linea) para que la marca aparezca
// en la MISMA foto que en la web. Si el defecto es viejo (sin vista_id), caemos
// a tipo/lado; y si la vista es importada (sin tipo/lado), lo mostramos en la 1a.
const defectosDeVista = (view, defectos, isFirst = false) => {
  return defectos.filter((d) => {
    if (d?.vista_id && view?.id) return String(d.vista_id) === String(view.id);
    const vt = normTipo(view?.tipo?.tipo);
    const vl = normLado(view?.lado?.lado);
    if (!vt && !vl) return isFirst; // vista importada sin tipo/lado
    return normTipo(d?.tipo?.tipo) === vt && normLado(d?.lado?.lado) === vl;
  });
};

// Encuentra la vista (foto del modelo) donde se marco un defecto. Primero por
// vista_id (exacto); si no, por tipo/lado; ultimo recurso, la primera vista.
export function findViewForDefecto(views = [], defecto) {
  if (!views.length) return null;
  if (defecto?.vista_id) {
    const byId = views.find((v) => String(v?.id) === String(defecto.vista_id));
    if (byId) return byId;
  }
  const dt = normTipo(defecto?.tipo?.tipo);
  const dl = normLado(defecto?.lado?.lado);
  return (
    views.find((v) => normTipo(v?.tipo?.tipo) === dt && normLado(v?.lado?.lado) === dl) ||
    views[0]
  );
}

// estilo: tamano del recuadro de la foto (por defecto 340 de alto; la pantalla de
// marcar de door trims le pasa { flex: 1 } para que ocupe todo el lugar libre).
export function FotoConMarcas({ view, defectos, onAddMark, estilo }) {
  const src = resolveImageSource(view?.imagen);
  const [container, setContainer] = useState({ w: 0, h: 0 });
  const [natural, setNatural] = useState({ w: 0, h: 0 });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    setNatural({ w: 0, h: 0 });
    if (!src) {
      setLoading(false);
      return;
    }
    Image.getSize(
      src,
      (w, h) => {
        setNatural({ w, h });
        setLoading(false);
      },
      () => setLoading(false)
    );
  }, [src]);

  const fit = (() => {
    const { w: cw, h: ch } = container;
    const { w: iw, h: ih } = natural;
    if (!cw || !ch || !iw || !ih) return null;
    const scale = Math.min(cw / iw, ch / ih);
    const dispW = iw * scale;
    const dispH = ih * scale;
    return { dispW, dispH, offsetX: (cw - dispW) / 2, offsetY: (ch - dispH) / 2 };
  })();

  // Toque para marcar (solo calidad): convierte la posicion del toque a % sobre
  // la imagen visible (ajuste contain), igual que el click de la web.
  const onTap = (e) => {
    if (!fit || !onAddMark) return;
    const { locationX, locationY } = e.nativeEvent;
    const x = ((locationX - fit.offsetX) / fit.dispW) * 100;
    const y = ((locationY - fit.offsetY) / fit.dispH) * 100;
    if (x < 0 || x > 100 || y < 0 || y > 100) return; // toque fuera de la foto
    onAddMark({ x, y });
  };

  return (
    <View
      style={{
        ...(estilo || { height: 340 }),
        backgroundColor: "#fff",
        borderWidth: 2,
        borderColor: onAddMark ? colors.blue : colors.green,
        borderRadius: 8,
        alignItems: "center",
        justifyContent: "center",
        overflow: "hidden",
      }}
      onLayout={(e) =>
        setContainer({ w: e.nativeEvent.layout.width, h: e.nativeEvent.layout.height })
      }
      onStartShouldSetResponder={onAddMark ? () => true : undefined}
      onResponderRelease={onAddMark ? onTap : undefined}
    >
      {loading && <ActivityIndicator size="large" color={colors.green} />}
      {!loading && !!src && (
        <Image source={{ uri: src }} resizeMode="contain" style={{ width: "100%", height: "100%" }} />
      )}
      {!loading && !src && <Text style={{ color: colors.muted }}>Sin imagen de la vista</Text>}

      {fit &&
        defectos.map((d, idx) => {
          const left = fit.offsetX + (Number(d.x) / 100) * fit.dispW;
          const top = fit.offsetY + (Number(d.y) / 100) * fit.dispH;
          const reparado = d?.estado && d.estado !== "PENDIENTE";
          return (
            <View
              key={`${d.id}-${idx}`}
              style={{
                position: "absolute",
                left,
                top,
                width: MARKER,
                height: MARKER,
                borderRadius: MARKER / 2,
                backgroundColor: `#${d.color || "F59E0B"}`,
                transform: [{ translateX: -MARKER / 2 }, { translateY: -MARKER / 2 }],
                borderWidth: 2,
                borderColor: reparado ? colors.green : "#000",
                opacity: reparado ? 0.55 : 1,
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <Text style={{ color: "#fff", fontSize: 11, fontWeight: "bold" }} numberOfLines={1}>
                {d?.falla?.codigo}
              </Text>
            </View>
          );
        })}
    </View>
  );
}

// Visor del asiento: pasa entre las fotos/vistas del modelo y muestra los
// defectos marcados superpuestos en su posicion. defectos = los del reporte.
export default function AsientoViewer({ views = [], defectos = [] }) {
  const [index, setIndex] = useState(0);

  if (!views.length) {
    return (
      <View style={{ marginTop: 12 }}>
        <Text style={[styles.cardMeta, { textAlign: "center" }]}>
          No hay fotos del asiento para esta etiqueta.
        </Text>
      </View>
    );
  }

  // Calculamos las marcas de cada vista y MOSTRAMOS SOLO las que tienen defectos
  // (asi el operario no tiene que pasar las 18 fotos del modelo si solo 1 tiene
  // marca). Si ninguna vista matchea (datos viejos), caemos a todas para no dejar
  // la pantalla en blanco.
  const conMarcas = views
    .map((v, idx) => ({ view: v, marcas: defectosDeVista(v, defectos, idx === 0) }))
    .filter((x) => x.marcas.length > 0);
  const lista = conMarcas.length > 0
    ? conMarcas
    : views.map((v) => ({ view: v, marcas: [] }));

  const total = lista.length;
  const i = Math.min(index, total - 1);
  const view = lista[i].view;
  const marcas = lista[i].marcas;
  const go = (delta) => setIndex(((i + delta) % total + total) % total);
  const nombre = view?.viewName || view?.nombre || `Vista ${i + 1}`;

  return (
    <View style={{ marginTop: 12 }}>
      {/* Carrusel de vistas con flechas: pasar las fotos del modelo (todos los angulos). */}
      <View style={{ flexDirection: "row", alignItems: "center", gap: 8, marginBottom: 8 }}>
        <TouchableOpacity
          onPress={() => go(-1)}
          disabled={total <= 1}
          style={[arrowBtn, total <= 1 && { opacity: 0.4 }]}
        >
          <Text style={arrowTxt}>◀</Text>
        </TouchableOpacity>

        <View style={{ flex: 1, alignItems: "center" }}>
          <Text style={{ color: colors.text, fontWeight: "bold", fontSize: 15 }} numberOfLines={1}>
            {nombre}
          </Text>
          <Text style={{ color: colors.muted, fontSize: 12 }}>
            {i + 1} / {total}
            {marcas.length > 0 ? ` · ${marcas.length} defecto(s) aca` : ""}
          </Text>
        </View>

        <TouchableOpacity
          onPress={() => go(1)}
          disabled={total <= 1}
          style={[arrowBtn, total <= 1 && { opacity: 0.4 }]}
        >
          <Text style={arrowTxt}>▶</Text>
        </TouchableOpacity>
      </View>

      <FotoConMarcas view={view} defectos={marcas} />

      <Text style={[styles.cardMeta, { textAlign: "center", marginTop: 6 }]}>
        {conMarcas.length > 0
          ? `Mostrando solo las ${total} foto(s) con defecto${total > 1 ? " · usá ◀ ▶" : ""}`
          : "Sin defectos en las fotos · usá ◀ ▶ para ver los ángulos"}
      </Text>
    </View>
  );
}

const arrowBtn = {
  paddingHorizontal: 16,
  paddingVertical: 12,
  borderRadius: 8,
  borderWidth: 2,
  borderColor: colors.blue,
  backgroundColor: colors.card,
};
const arrowTxt = { color: colors.text, fontSize: 22, fontWeight: "bold" };
