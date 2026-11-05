import React, { useCallback, useEffect, useState } from "react";
import { SafeAreaView, View, Text, TouchableOpacity, ScrollView, Alert, ActivityIndicator } from "react-native";
import { StatusBar } from "expo-status-bar";
import { styles, colors } from "./styles";
import { getBufferStock, getPiezas, tomarPieza, reingresarPieza, validarPieza, scrapPieza, getFactoryConfig } from "./api";
import { esCalidad, formatCountdown } from "./utils";
import PiezaHistorialModal from "./components/PiezaHistorialModal";
import BufferSelectorModal from "./components/BufferSelectorModal";
import { getBufferGuardado, guardarBuffer } from "./buffer";

const REFRESH_MS = 15000;
const TICK_MS = 30000; // refresco del countdown de curado (no golpea la red)

// El backend NO tiene un estado literal "CURANDO": una pieza DISPONIBLE con
// disponible_desde en el futuro esta curando (adhesivado en secado). Por eso
// pedimos DISPONIBLE y separamos aca en vez de filtrar por estado=CURANDO.
const estaCurando = (p) => p?.estado === "DISPONIBLE" && p?.disponible_desde && new Date(p.disponible_desde) > new Date();

const esDeUsuario = (pieza, user) => {
  const uid = user?.id;
  if (uid == null || pieza?.tomado_por == null) return true; // sin dato, mejor mostrar de mas que ocultar
  return String(pieza.tomado_por) === String(uid);
};

// Pantalla del retrabajador: stock del mini buffer, piezas defectuosas para
// tomar, piezas propias en retrabajo para reingresar (OK/SCRAP) y, si es
// usuario de Calidad, la cola de validación de reingresos.
const BUFFER_GENERAL = { id: null, name: "GENERAL" };

export default function PantallaBuffer({ user, config: configProp, onLogout }) {
  // Config recargable: si /config todavia no tiene buffers dados de alta, el
  // selector obligatorio ofrece "REINTENTAR" para volver a bajarla.
  const [configOverride, setConfigOverride] = useState(null);
  const config = configOverride || configProp;
  const recargarConfig = async () => {
    const r = await getFactoryConfig();
    if (r?.data) setConfigOverride(r.data);
  };

  const [buffer, setBuffer] = useState(null);
  const [showBufferSelector, setShowBufferSelector] = useState(false);

  const [stock, setStock] = useState(null);
  const [defectuosas, setDefectuosas] = useState([]);
  const [curando, setCurando] = useState([]);
  const [misRetrabajo, setMisRetrabajo] = useState([]);
  const [pendValidacion, setPendValidacion] = useState([]);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [busyQr, setBusyQr] = useState(null); // qr con una accion en curso (deshabilita el boton)
  const [verFichaQr, setVerFichaQr] = useState(null);
  const [tick, setTick] = useState(0); // fuerza re-render del countdown

  const calidad = esCalidad(user);

  // Sin buffers dados de alta en /config la pantalla trabaja con TODAS las
  // piezas (buffer "GENERAL") en vez de trabar la tablet con un selector vacio.
  const buffersConfig = (config?.buffers || []).filter((b) => b.active !== false);
  const bufferEfectivo = buffer || (buffersConfig.length === 0 ? BUFFER_GENERAL : null);
  const bufferId = bufferEfectivo?.id ?? undefined;

  useEffect(() => {
    getBufferGuardado().then((b) => setBuffer(b));
  }, []);

  useEffect(() => {
    if (!buffer && buffersConfig.length > 0) setShowBufferSelector(true);
  }, [buffersConfig.length]);

  const cargar = useCallback(async () => {
    if (!bufferEfectivo) return;
    setError(null);
    const [st, defs, disp, mios, pend] = await Promise.all([
      getBufferStock(bufferId),
      getPiezas({ buffer_id: bufferId, estado: "DEFECTUOSA", per_page: 500, orden: "antiguedad" }),
      getPiezas({ buffer_id: bufferId, estado: "DISPONIBLE" }),
      getPiezas({ buffer_id: bufferId, estado: "EN_RETRABAJO" }),
      calidad ? getPiezas({ buffer_id: bufferId, estado: "PEND_VALIDACION" }) : Promise.resolve({ data: [] }),
    ]);

    if (st?.error && !st?.data) setError(st?.message || "No se pudo traer el stock del buffer");
    if (st?.data) setStock(st.data);

    const ordenadas = (defs?.data || []).slice().sort((a, b) => {
      const fa = new Date(a?.ultimo_evento_at || a?.updated_at || 0).getTime();
      const fb = new Date(b?.ultimo_evento_at || b?.updated_at || 0).getTime();
      return fa - fb; // mas antigua primero
    });
    setDefectuosas(ordenadas);
    setCurando((disp?.data || []).filter(estaCurando));
    setMisRetrabajo((mios?.data || []).filter((p) => esDeUsuario(p, user)));
    setPendValidacion(pend?.data || []);
  }, [bufferEfectivo?.name, bufferId, calidad, user]);

  useEffect(() => {
    if (!bufferEfectivo) return;
    setLoading(true);
    cargar().finally(() => setLoading(false));
    const t = setInterval(cargar, REFRESH_MS);
    return () => clearInterval(t);
  }, [bufferEfectivo?.name, cargar]);

  useEffect(() => {
    const t = setInterval(() => setTick((x) => x + 1), TICK_MS);
    return () => clearInterval(t);
  }, []);

  const elegirBuffer = async (b) => {
    const ok = await guardarBuffer(b);
    if (ok) {
      setBuffer(b);
      setShowBufferSelector(false);
    }
  };

  const tomar = (pieza) => {
    Alert.alert("Tomar pieza", `¿Vas a retrabajar la pieza ${pieza.pieza_qr}?`, [
      { text: "Cancelar", style: "cancel" },
      {
        text: "Confirmar",
        onPress: async () => {
          setBusyQr(pieza.pieza_qr);
          const res = await tomarPieza(pieza.pieza_qr, { user: user?.id });
          setBusyQr(null);
          if (res?.error) {
            Alert.alert("Error", res?.message || "No se pudo tomar la pieza");
            return;
          }
          cargar();
        },
      },
    ]);
  };

  // Scrap directo de una pieza DEFECTUOSA (sin pasar por tomar/retrabajar):
  // para piezas que no vale la pena retrabajar.
  const scrapDirecto = (pieza) => {
    Alert.alert(
      "Dar de baja (SCRAP)",
      `¿Confirmás que la pieza ${pieza.pieza_qr} se da de baja (SCRAP)? No se puede deshacer.`,
      [
        { text: "Cancelar", style: "cancel" },
        {
          text: "Confirmar",
          style: "destructive",
          onPress: async () => {
            setBusyQr(pieza.pieza_qr);
            const res = await scrapPieza(pieza.pieza_qr, { user: user?.id, motivo: "SCRAP directo desde buffer" });
            setBusyQr(null);
            if (res?.error) {
              Alert.alert("Error", res?.message || "No se pudo dar de baja la pieza");
              return;
            }
            cargar();
          },
        },
      ]
    );
  };

  const reingresar = (pieza, resultado) => {
    const esScrap = resultado === "SCRAP";
    Alert.alert(
      esScrap ? "Dar de baja (SCRAP)" : "Reingresar OK",
      esScrap
        ? `¿Confirmás que la pieza ${pieza.pieza_qr} se da de baja (SCRAP)? No se puede deshacer.`
        : `¿La pieza ${pieza.pieza_qr} quedó retrabajada OK? Sigue el circuito de validación / curado.`,
      [
        { text: "Cancelar", style: "cancel" },
        {
          text: "Confirmar",
          style: esScrap ? "destructive" : "default",
          onPress: async () => {
            setBusyQr(pieza.pieza_qr);
            const res = await reingresarPieza(pieza.pieza_qr, { user: user?.id, resultado });
            setBusyQr(null);
            if (res?.error) {
              Alert.alert("Error", res?.message || "No se pudo registrar el reingreso");
              return;
            }
            cargar();
          },
        },
      ]
    );
  };

  const validar = (pieza, accion) => {
    Alert.alert(
      accion === "validar" ? "Validar reingreso" : "Rechazar reingreso",
      `¿Confirmás ${accion === "validar" ? "validar" : "rechazar"} la pieza ${pieza.pieza_qr}?`,
      [
        { text: "Cancelar", style: "cancel" },
        {
          text: "Confirmar",
          onPress: async () => {
            setBusyQr(pieza.pieza_qr);
            const res = await validarPieza(pieza.pieza_qr, { user: user?.id, accion });
            setBusyQr(null);
            if (res?.error) {
              Alert.alert("Error", res?.message || "No se pudo registrar la validación");
              return;
            }
            cargar();
          },
        },
      ]
    );
  };

  return (
    <SafeAreaView style={styles.screen}>
      <StatusBar style="light" />
      <View style={styles.header}>
        <Text style={styles.headerTitle}>DOORTRIM · BUFFER</Text>
        {buffersConfig.length > 0 ? (
          <TouchableOpacity onPress={() => setShowBufferSelector(true)} style={{ alignSelf: "center", marginTop: 6 }}>
            <Text style={{ color: "#fff", fontSize: 14, fontWeight: "700" }}>
              {buffer ? `BUFFER: ${buffer.name}` : "BUFFER: sin elegir"} ✎
            </Text>
          </TouchableOpacity>
        ) : null}
      </View>

      <View style={styles.body}>
        <View style={styles.topbar}>
          <Text style={styles.userTag}>{user?.name || user?.email || "Retrabajador"}</Text>
          <TouchableOpacity onPress={onLogout} focusable={false}>
            <Text style={[styles.userTag, { color: colors.orange }]}>Cambiar usuario</Text>
          </TouchableOpacity>
        </View>

        {loading && !stock ? <ActivityIndicator color={colors.green} size="large" style={{ marginTop: 24 }} /> : null}
        {error ? <Text style={styles.emptyText}>{error}</Text> : null}

        {bufferEfectivo ? (
          <ScrollView keyboardShouldPersistTaps="handled" style={{ marginTop: 4 }}>
            {/* Stock del buffer */}
            <Text style={styles.label}>STOCK · {bufferEfectivo.name}</Text>
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8, marginBottom: 14 }}>
              <StockChip label="DISPONIBLES" value={stock?.disponibles} color={colors.green} />
              <StockChip label="CURANDO" value={stock?.curando ?? curando.length} color={colors.cyan} />
              <StockChip label="DEFECTUOSAS" value={stock?.defectuosas ?? defectuosas.length} color={colors.red} />
              <StockChip label="EN RETRABAJO" value={stock?.en_retrabajo ?? misRetrabajo.length} color={colors.blue} />
            </View>

            {/* Curando, con countdown del secado del adhesivo */}
            {curando.length > 0 ? (
              <View style={{ marginBottom: 14 }}>
                <Text style={[styles.cardMeta, { marginBottom: 6 }]}>Curando (secado del adhesivo):</Text>
                <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
                  {curando.map((p) => {
                    const restante = formatCountdown(p?.disponible_desde);
                    return (
                      <TouchableOpacity
                        key={`${p.pieza_qr}-${tick}`}
                        onPress={() => setVerFichaQr(p.pieza_qr)}
                        style={{ backgroundColor: colors.card, borderWidth: 1, borderColor: colors.cyan, borderRadius: 6, paddingHorizontal: 10, paddingVertical: 6 }}
                      >
                        <Text style={{ color: colors.text, fontWeight: "bold", fontSize: 12 }}>{p.pieza_qr}</Text>
                        <Text style={{ color: colors.cyan, fontSize: 12 }}>{restante || "lista"}</Text>
                      </TouchableOpacity>
                    );
                  })}
                </View>
              </View>
            ) : null}

            {/* Defectuosas: para tomar y retrabajar, mas antigua primero */}
            <Text style={styles.label}>DEFECTUOSAS PARA RETRABAJAR · {defectuosas.length}</Text>
            {defectuosas.length === 0 ? (
              <Text style={styles.cardMeta}>No hay piezas defectuosas esperando en el buffer.</Text>
            ) : (
              defectuosas.map((p) => (
                <View key={p.pieza_qr} style={[styles.card, { marginBottom: 10 }]}>
                  <TouchableOpacity onPress={() => setVerFichaQr(p.pieza_qr)} activeOpacity={0.7}>
                    <Text style={styles.cardModel}>{p.tipo_pieza || "PIEZA"}</Text>
                    <Text style={styles.cardMeta}>QR: {p.pieza_qr}</Text>
                    {p?.modelo ? <Text style={styles.cardMeta}>Modelo: {p.modelo}</Text> : null}
                  </TouchableOpacity>
                  <View style={{ flexDirection: "row", gap: 8, marginTop: 10 }}>
                    <TouchableOpacity
                      onPress={() => tomar(p)}
                      disabled={busyQr === p.pieza_qr}
                      style={[styles.bigButton, { flex: 1, backgroundColor: colors.green, opacity: busyQr === p.pieza_qr ? 0.6 : 1 }]}
                    >
                      {busyQr === p.pieza_qr ? <ActivityIndicator color="#000" /> : <Text style={styles.bigButtonText}>TOMAR</Text>}
                    </TouchableOpacity>
                    <TouchableOpacity
                      onPress={() => scrapDirecto(p)}
                      disabled={busyQr === p.pieza_qr}
                      style={{ paddingHorizontal: 18, justifyContent: "center", backgroundColor: colors.card, borderRadius: 6, borderWidth: 1, borderColor: colors.red, opacity: busyQr === p.pieza_qr ? 0.6 : 1 }}
                    >
                      <Text style={{ color: colors.red, fontWeight: "bold", textAlign: "center", fontSize: 13 }}>SCRAP</Text>
                    </TouchableOpacity>
                  </View>
                </View>
              ))
            )}

            {/* Mis piezas en retrabajo: reingresar OK o SCRAP */}
            <Text style={[styles.label, { marginTop: 16 }]}>MIS PIEZAS EN RETRABAJO · {misRetrabajo.length}</Text>
            {misRetrabajo.length === 0 ? (
              <Text style={styles.cardMeta}>No tenés piezas tomadas para retrabajar.</Text>
            ) : (
              misRetrabajo.map((p) => (
                <View key={p.pieza_qr} style={[styles.card, { marginBottom: 10, borderColor: colors.blue }]}>
                  <TouchableOpacity onPress={() => setVerFichaQr(p.pieza_qr)} activeOpacity={0.7}>
                    <Text style={styles.cardModel}>{p.tipo_pieza || "PIEZA"}</Text>
                    <Text style={styles.cardMeta}>QR: {p.pieza_qr}</Text>
                  </TouchableOpacity>
                  <View style={{ flexDirection: "row", gap: 8, marginTop: 10 }}>
                    <TouchableOpacity
                      onPress={() => reingresar(p, "OK")}
                      disabled={busyQr === p.pieza_qr}
                      style={{ flex: 1, backgroundColor: colors.green, borderRadius: 6, paddingVertical: 14, opacity: busyQr === p.pieza_qr ? 0.6 : 1 }}
                    >
                      <Text style={{ color: "#000", fontWeight: "bold", textAlign: "center", fontSize: 16 }}>REINGRESAR OK</Text>
                    </TouchableOpacity>
                    <TouchableOpacity
                      onPress={() => reingresar(p, "SCRAP")}
                      disabled={busyQr === p.pieza_qr}
                      style={{ flex: 1, backgroundColor: colors.red, borderRadius: 6, paddingVertical: 14, opacity: busyQr === p.pieza_qr ? 0.6 : 1 }}
                    >
                      <Text style={{ color: "#fff", fontWeight: "bold", textAlign: "center", fontSize: 16 }}>SCRAP</Text>
                    </TouchableOpacity>
                  </View>
                </View>
              ))
            )}

            {/* Solo Calidad: cola de validacion de reingresos */}
            {calidad ? (
              <>
                <Text style={[styles.label, { marginTop: 16 }]}>PENDIENTES DE VALIDAR · {pendValidacion.length}</Text>
                {pendValidacion.length === 0 ? (
                  <Text style={styles.cardMeta}>No hay reingresos esperando validación.</Text>
                ) : (
                  pendValidacion.map((p) => (
                    <View key={p.pieza_qr} style={[styles.card, { marginBottom: 10, borderColor: colors.orange }]}>
                      <TouchableOpacity onPress={() => setVerFichaQr(p.pieza_qr)} activeOpacity={0.7}>
                        <Text style={styles.cardModel}>{p.tipo_pieza || "PIEZA"}</Text>
                        <Text style={styles.cardMeta}>QR: {p.pieza_qr}</Text>
                        <Text style={styles.cardMeta}>Retrabajó: {p?.tomado_por_nombre || "-"}</Text>
                      </TouchableOpacity>
                      <View style={{ flexDirection: "row", gap: 8, marginTop: 10 }}>
                        <TouchableOpacity
                          onPress={() => validar(p, "validar")}
                          disabled={busyQr === p.pieza_qr}
                          style={{ flex: 1, backgroundColor: colors.green, borderRadius: 6, paddingVertical: 14, opacity: busyQr === p.pieza_qr ? 0.6 : 1 }}
                        >
                          <Text style={{ color: "#000", fontWeight: "bold", textAlign: "center", fontSize: 16 }}>VALIDAR</Text>
                        </TouchableOpacity>
                        <TouchableOpacity
                          onPress={() => validar(p, "rechazar")}
                          disabled={busyQr === p.pieza_qr}
                          style={{ flex: 1, backgroundColor: colors.red, borderRadius: 6, paddingVertical: 14, opacity: busyQr === p.pieza_qr ? 0.6 : 1 }}
                        >
                          <Text style={{ color: "#fff", fontWeight: "bold", textAlign: "center", fontSize: 16 }}>RECHAZAR</Text>
                        </TouchableOpacity>
                      </View>
                    </View>
                  ))
                )}
              </>
            ) : null}

            <View style={{ height: 30 }} />
          </ScrollView>
        ) : (
          !loading && <Text style={styles.emptyText}>ELEGÍ EL BUFFER DE ESTA TABLET</Text>
        )}
      </View>

      <PiezaHistorialModal visible={!!verFichaQr} qr={verFichaQr} onClose={() => setVerFichaQr(null)} />

      <BufferSelectorModal
        visible={showBufferSelector}
        buffers={config?.buffers || []}
        bufferActual={buffer}
        onElegir={elegirBuffer}
        onClose={() => setShowBufferSelector(false)}
        obligatorio={false}
        onReintentar={recargarConfig}
      />
    </SafeAreaView>
  );
}

function StockChip({ label, value, color }) {
  return (
    <View style={{ backgroundColor: colors.card, borderWidth: 1, borderColor: color, borderRadius: 6, paddingHorizontal: 10, paddingVertical: 6 }}>
      <Text style={{ color, fontWeight: "bold", fontSize: 12 }}>
        {label}: {value ?? 0}
      </Text>
    </View>
  );
}
