import React, { useEffect, useRef, useState } from "react";
import {
  SafeAreaView,
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ScrollView,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
} from "react-native";
import { StatusBar } from "expo-status-bar";
import { styles, colors } from "./styles";
import { getDoorContexto, getPieza, getPiezas, getBufferStock, crearDoor, getFactoryConfig } from "./api";
import { getTipo, genClientUuid } from "./utils";
import { getConfiguredModelImagesForKanban } from "./factoryConfig";
import DefectoCard from "./components/DefectoCard";
import DefectoInfoModal from "./components/DefectoInfoModal";
import PiezaHistorialModal from "./components/PiezaHistorialModal";
import PuestoSelectorModal from "./components/PuestoSelectorModal";
import BufferSelectorModal from "./components/BufferSelectorModal";
import { getPuestoGuardado, guardarPuesto } from "./puesto";
import { getBufferGuardado, guardarBuffer } from "./buffer";

const STEP_ASIENTO = 1;
const STEP_DEFECTUOSA = 2;
const STEP_REEMPLAZO = 3;

const PASOS = [
  { n: STEP_ASIENTO, label: "ASIENTO" },
  { n: STEP_DEFECTUOSA, label: "DEFECTUOSA" },
  { n: STEP_REEMPLAZO, label: "REEMPLAZO" },
];

// Wizard de cambio de pieza: escanea ASIENTO -> PIEZA DEFECTUOSA -> PIEZA DE REEMPLAZO y
// confirma. La defectuosa queda en el buffer (para retrabajar despues), la de
// reemplazo sale del buffer y queda montada. Sin retrabajo en linea.
export default function PantallaDoor({ user, config: configProp, onLogout }) {
  // Config recargable: si /config todavia no tiene buffers dados de alta, el
  // selector obligatorio ofrece "REINTENTAR" para volver a bajarla sin
  // depender de que App.js la haya cacheado bien al loguear.
  const [configOverride, setConfigOverride] = useState(null);
  const config = configOverride || configProp;
  const recargarConfig = async () => {
    const r = await getFactoryConfig();
    if (r?.data) setConfigOverride(r.data);
  };

  const [step, setStep] = useState(STEP_ASIENTO);
  const [qr, setQr] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const [contexto, setContexto] = useState(null); // { asiento/modelo/tipo/lado/secuencia, defectos_abiertos }
  const [views, setViews] = useState([]); // vistas/fotos del modelo (factory-config) para el asiento actual
  const [defectosSel, setDefectosSel] = useState(() => new Set());
  const [infoDefecto, setInfoDefecto] = useState(null);

  const [piezaDefectuosaQr, setPiezaDefectuosaQr] = useState("");
  const [piezaDefectuosaExiste, setPiezaDefectuosaExiste] = useState(true);
  const [sugerencias, setSugerencias] = useState([]); // piezas disponibles en el buffer segun tipo_pieza de la defectuosa
  const [verFichaQr, setVerFichaQr] = useState(null);

  const [piezaReemplazoQr, setPiezaReemplazoQr] = useState("");
  const [observaciones, setObservaciones] = useState("");
  const [rejection, setRejection] = useState(null);
  const [guardando, setGuardando] = useState(false);
  const [exito, setExito] = useState(null); // { saliente, entrante }

  const [bufferStock, setBufferStock] = useState(null);

  const [puesto, setPuesto] = useState(null);
  const [showPuestoSelector, setShowPuestoSelector] = useState(false);
  const [buffer, setBuffer] = useState(null);
  const [showBufferSelector, setShowBufferSelector] = useState(false);

  const qrRef = useRef(null);
  const scanTimer = useRef(null);
  const clientUuidRef = useRef(null);
  // Timers de foco/auto-reset: se guardan para poder limpiarlos si el
  // componente se desmonta antes de que disparen (evita setState en un
  // componente ya desmontado / navegar a otra pantalla con un reset pendiente).
  const focusTimer = useRef(null);
  const autoResetTimer = useRef(null);
  const exitoTimer = useRef(null);

  const focusQr = (delay = 150) => {
    if (focusTimer.current) clearTimeout(focusTimer.current);
    focusTimer.current = setTimeout(() => qrRef.current?.focus(), delay);
  };

  useEffect(() => {
    getPuestoGuardado().then((p) => {
      setPuesto(p);
      if (!p) setShowPuestoSelector(true);
    });
    getBufferGuardado().then((b) => {
      setBuffer(b);
      if (!b) setShowBufferSelector(true);
    });
  }, []);

  useEffect(() => {
    focusQr();
  }, [step]);

  // Cleanup general: al desmontar la pantalla (cambio de modo/logout) se
  // cancelan todos los timers pendientes.
  useEffect(() => {
    return () => {
      if (scanTimer.current) clearTimeout(scanTimer.current);
      if (focusTimer.current) clearTimeout(focusTimer.current);
      if (autoResetTimer.current) clearTimeout(autoResetTimer.current);
      if (exitoTimer.current) clearTimeout(exitoTimer.current);
    };
  }, []);

  // Stock del buffer visible desde que se conoce (paso 1 en adelante). Se
  // refresca al entrar al wizard y despues de cada intento de cambio.
  const refrescarStock = async (bufferId) => {
    const id = bufferId ?? buffer?.id;
    if (id == null) return;
    const res = await getBufferStock(id);
    if (res?.data) setBufferStock(res.data);
  };

  useEffect(() => {
    if (buffer?.id != null) refrescarStock(buffer.id);
  }, [buffer?.id]);

  const elegirPuesto = async (p) => {
    const ok = await guardarPuesto(p);
    if (ok) {
      setPuesto(p);
      setShowPuestoSelector(false);
    }
  };

  const elegirBuffer = async (b) => {
    const ok = await guardarBuffer(b);
    if (ok) {
      setBuffer(b);
      setShowBufferSelector(false);
    }
  };

  const resetWizard = () => {
    setStep(STEP_ASIENTO);
    setQr("");
    setContexto(null);
    setViews([]);
    setDefectosSel(new Set());
    setPiezaDefectuosaQr("");
    setPiezaDefectuosaExiste(true);
    setSugerencias([]);
    setPiezaReemplazoQr("");
    setObservaciones("");
    setRejection(null);
    setError(null);
    clientUuidRef.current = null;
    focusQr();
  };

  // ---------- PASO 1: ASIENTO ----------
  const buscarAsiento = async (scan) => {
    const value = String(scan || "").trim();
    if (!value || loading) return;
    if (scanTimer.current) clearTimeout(scanTimer.current);

    setLoading(true);
    setError(null);
    const res = await getDoorContexto(value);
    setLoading(false);
    setQr("");

    if (res?.error || !res?.data) {
      setError(res?.message || "No se pudo leer el asiento. Reintentá el escaneo.");
      return;
    }

    const data = res.data;
    const abiertos = data?.defectos_abiertos || data?.defectos || [];

    if (abiertos.length === 0) {
      setError("Este asiento no tiene defectos de cambio pendientes.");
      if (autoResetTimer.current) clearTimeout(autoResetTimer.current);
      autoResetTimer.current = setTimeout(resetWizard, 2200);
      return;
    }

    clientUuidRef.current = genClientUuid();
    setContexto(data);
    // Vistas/fotos del modelo (misma fuente que la web: factory-config), para
    // que "VER FOTO / DETALLE" muestre la marca sobre la foto real del asiento.
    setViews(getConfiguredModelImagesForKanban(data, config));
    setDefectosSel(new Set(abiertos.map((d) => d.id)));
    refrescarStock();
    setStep(STEP_DEFECTUOSA);
  };

  const toggleDefecto = (defecto) => {
    setDefectosSel((prev) => {
      const next = new Set(prev);
      if (next.has(defecto.id)) next.delete(defecto.id);
      else next.add(defecto.id);
      return next;
    });
  };

  // ---------- PASO 2: PIEZA DEFECTUOSA ----------
  const buscarPiezaDefectuosa = async (scan) => {
    const value = String(scan || "").trim();
    if (!value || loading) return;
    if (scanTimer.current) clearTimeout(scanTimer.current);

    if (value === piezaReemplazoQr && piezaReemplazoQr) {
      setError("Esa pieza ya está elegida como reemplazo. Escaneá la pieza defectuosa.");
      return;
    }

    setLoading(true);
    setError(null);
    const res = await getPieza(value);
    setLoading(false);
    setQr("");

    setPiezaDefectuosaQr(value);
    setPiezaDefectuosaExiste(!(res?.error || !res?.data));

    // Sugerencias de reemplazo: el backend ya no infiere el tipo de pieza a
    // partir de los defectos, asi que lo tomamos de la pieza defectuosa recien
    // escaneada. Si es alta automatica (no existia en el maestro) no tiene
    // tipo_pieza: mostramos el stock del buffer sin filtrar.
    const tipoPieza = res?.data?.tipo_pieza || undefined;
    const sug = await getPiezas({ buffer_id: buffer?.id, estado: "DISPONIBLE", tipo_pieza: tipoPieza });
    setSugerencias(sug?.data || []);

    setStep(STEP_REEMPLAZO);
  };

  // ---------- PASO 3: PIEZA DE REEMPLAZO ----------
  const buscarPiezaReemplazo = (scan) => {
    const value = String(scan || "").trim();
    if (!value) return;
    if (scanTimer.current) clearTimeout(scanTimer.current);

    if (value === piezaDefectuosaQr) {
      setError("La pieza de reemplazo no puede ser la misma que la defectuosa.");
      return;
    }

    setError(null);
    setRejection(null);
    setPiezaReemplazoQr(value);
    setQr("");
  };

  const confirmarCambio = async () => {
    if (!piezaReemplazoQr || guardando || defectosSel.size === 0) return;
    setGuardando(true);
    setRejection(null);

    const res = await crearDoor({
      qr: contexto?.qr,
      pieza_defectuosa_qr: piezaDefectuosaQr,
      pieza_reemplazo_qr: piezaReemplazoQr,
      defecto_ids: Array.from(defectosSel),
      buffer_id: buffer?.id,
      buffer_nombre: buffer?.name,
      user: user?.id,
      puesto_id: puesto?.id,
      puesto_nombre: puesto?.name,
      observaciones,
      client_uuid: clientUuidRef.current,
    });
    setGuardando(false);

    if (res?.error) {
      // Si el POST anterior llego al server y se perdio la respuesta (timeout,
      // corte de red), reintentar con el mismo client_uuid devolveria el cambio
      // viejo como si fuera nuevo. Se regenera para que el proximo intento sea
      // siempre una operacion nueva.
      clientUuidRef.current = genClientUuid();
      setRejection(res?.message || "No se pudo registrar el cambio con esa pieza. Probá con otra.");
      refrescarStock();
      setPiezaReemplazoQr("");
      focusQr();
      return;
    }

    // Si el cambio ya estaba registrado (mismo client_uuid de un reintento) el
    // backend devuelve el cambio original: si la pieza entregada no es la que
    // se acaba de escanear, avisamos para verificar fisicamente en vez de
    // mostrar un exito que puede confundir al operario.
    const entregada = res?.data?.door?.pieza_entregada_qr;
    if (entregada && entregada !== piezaReemplazoQr) {
      setExito({
        saliente: piezaDefectuosaQr,
        entrante: entregada,
        advertencia: `Ese cambio ya estaba registrado con la pieza ${entregada}, verificá físicamente`,
      });
    } else {
      setExito({ saliente: piezaDefectuosaQr, entrante: piezaReemplazoQr });
    }
    refrescarStock();
    if (autoResetTimer.current) clearTimeout(autoResetTimer.current);
    autoResetTimer.current = setTimeout(resetWizard, 3000);
    if (exitoTimer.current) clearTimeout(exitoTimer.current);
    exitoTimer.current = setTimeout(() => setExito(null), 3000);
  };

  // ---------- scan handlers (debounce 300ms + onSubmitEditing) ----------
  const onScanChange = (text) => {
    setQr(text);
    if (scanTimer.current) clearTimeout(scanTimer.current);
    const v = String(text || "").trim();
    if (v.length < 6) return;
    const fn = step === STEP_ASIENTO ? buscarAsiento : step === STEP_DEFECTUOSA ? buscarPiezaDefectuosa : buscarPiezaReemplazo;
    scanTimer.current = setTimeout(() => fn(v), 300);
  };

  const onSubmit = (e) => {
    const v = e?.nativeEvent?.text ?? qr;
    if (step === STEP_ASIENTO) buscarAsiento(v);
    else if (step === STEP_DEFECTUOSA) buscarPiezaDefectuosa(v);
    else buscarPiezaReemplazo(v);
  };

  const placeholder =
    step === STEP_ASIENTO
      ? "Escaneá el ASIENTO"
      : step === STEP_DEFECTUOSA
      ? "Escaneá la PIEZA DEFECTUOSA"
      : "Escaneá la PIEZA DE REEMPLAZO";

  // ---------- PANTALLA DE ÉXITO ----------
  if (exito) {
    return (
      <SafeAreaView style={styles.screen}>
        <StatusBar style="light" />
        <View style={styles.header}>
          <Text style={styles.headerTitle}>EOL DOOR</Text>
        </View>
        <View style={[styles.body, { alignItems: "center", justifyContent: "center" }]}>
          <View style={{ backgroundColor: exito.advertencia ? colors.orange : colors.green, borderRadius: 12, padding: 24, width: "100%" }}>
            <Text style={{ color: "#000", fontSize: 26, fontWeight: "bold", textAlign: "center" }}>
              {exito.advertencia ? "CAMBIO YA REGISTRADO ⚠" : "CAMBIO REGISTRADO ✓"}
            </Text>
            <Text style={{ color: "#000", fontSize: 16, marginTop: 16 }}>SALIÓ (al buffer):</Text>
            <Text style={{ color: "#000", fontSize: 20, fontWeight: "bold" }}>{exito.saliente}</Text>
            <Text style={{ color: "#000", fontSize: 16, marginTop: 12 }}>ENTRÓ (montada):</Text>
            <Text style={{ color: "#000", fontSize: 20, fontWeight: "bold" }}>{exito.entrante}</Text>
            {exito.advertencia ? (
              <Text style={{ color: "#000", fontSize: 15, fontWeight: "bold", marginTop: 14, textAlign: "center" }}>
                {exito.advertencia}
              </Text>
            ) : null}
          </View>
          <Text style={[styles.hint, { marginTop: 20 }]}>Reiniciando para el próximo asiento…</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.screen}>
      <StatusBar style="light" />
      <View style={styles.header}>
        <Text style={styles.headerTitle}>EOL DOOR</Text>
        <View style={{ flexDirection: "row", justifyContent: "center", gap: 14, marginTop: 6 }}>
          <TouchableOpacity onPress={() => setShowPuestoSelector(true)}>
            <Text style={{ color: "#fff", fontSize: 13, fontWeight: "700" }}>
              {puesto ? `PUESTO: ${puesto.name}` : "PUESTO: sin elegir"} ✎
            </Text>
          </TouchableOpacity>
          <TouchableOpacity onPress={() => setShowBufferSelector(true)}>
            <Text style={{ color: "#fff", fontSize: 13, fontWeight: "700" }}>
              {buffer ? `BUFFER: ${buffer.name}` : "BUFFER: sin elegir"} ✎
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      <KeyboardAvoidingView behavior={Platform.OS === "ios" ? "padding" : undefined} style={styles.body}>
        <View style={styles.topbar}>
          <Text style={styles.userTag}>{user?.name || user?.email || "Operario"}</Text>
          <TouchableOpacity onPress={onLogout}>
            <Text style={[styles.userTag, { color: colors.orange }]}>Cambiar usuario</Text>
          </TouchableOpacity>
        </View>

        {/* Indicador de paso 1/2/3 */}
        <View style={{ flexDirection: "row", justifyContent: "center", gap: 10, marginBottom: 12 }}>
          {PASOS.map((p) => {
            const activo = p.n === step;
            const hecho = p.n < step;
            return (
              <View
                key={p.n}
                style={{
                  paddingHorizontal: 12,
                  paddingVertical: 8,
                  borderRadius: 20,
                  backgroundColor: activo ? colors.green : hecho ? colors.blue : colors.card,
                  borderWidth: 1,
                  borderColor: colors.border,
                }}
              >
                <Text style={{ color: activo ? "#000" : "#fff", fontWeight: "bold", fontSize: 12 }}>
                  {p.n}. {p.label}
                </Text>
              </View>
            );
          })}
        </View>

        {/* Stock del buffer, visible desde que hay buffer elegido */}
        {bufferStock ? (
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8, marginBottom: 10 }}>
            <StockChip label="DISPONIBLES" value={bufferStock.disponibles} color={colors.green} />
            <StockChip label="CURANDO" value={bufferStock.curando} color={colors.cyan} />
            <StockChip label="DEFECTUOSAS" value={bufferStock.defectuosas} color={colors.red} />
            <StockChip label="EN RETRABAJO" value={bufferStock.en_retrabajo} color={colors.blue} />
          </View>
        ) : null}

        <Text style={styles.label}>{placeholder}</Text>
        <TextInput
          ref={qrRef}
          style={styles.input}
          value={qr}
          onChangeText={onScanChange}
          placeholder="Escaneá el QR"
          placeholderTextColor="#9ca3af"
          autoFocus
          blurOnSubmit={false}
          onSubmitEditing={onSubmit}
          returnKeyType="search"
        />

        {loading ? <ActivityIndicator color={colors.green} size="large" style={{ marginTop: 20 }} /> : null}
        {error ? <Text style={styles.emptyText}>{error}</Text> : null}

        <ScrollView style={{ flex: 1, marginTop: 12 }} keyboardShouldPersistTaps="handled">
          {/* Banner del asiento (desde paso 2 en adelante) */}
          {contexto && step >= STEP_DEFECTUOSA ? (
            <View style={{ backgroundColor: colors.yellow, padding: 10, borderRadius: 6, marginBottom: 10 }}>
              <Text style={{ color: "#000", fontWeight: "bold", fontSize: 16 }}>
                {contexto?.modelo} · {getTipo(contexto?.tipo)} {contexto?.lado || ""}
                {contexto?.secuencia ? ` · SEC ${contexto.secuencia}` : ""}
              </Text>
              <Text style={{ color: "#000", fontSize: 13 }}>{contexto?.qr}</Text>
            </View>
          ) : null}

          {/* Paso 2: defectos abiertos a resolver con este cambio */}
          {step === STEP_DEFECTUOSA ? (
            <>
              <Text style={styles.label}>DEFECTOS ABIERTOS · elegí cuáles resuelve este cambio</Text>
              {(contexto?.defectos_abiertos || contexto?.defectos || []).map((d) => (
                <DefectoCard
                  key={d.id}
                  defecto={d}
                  onSeleccionar={toggleDefecto}
                  seleccionado={defectosSel.has(d.id)}
                  onVerInfo={setInfoDefecto}
                  readOnly={false}
                />
              ))}
            </>
          ) : null}

          {/* Paso 3: resumen de defectuosa + reemplazo */}
          {step === STEP_REEMPLAZO ? (
            <>
              <TouchableOpacity
                style={[styles.card, { borderColor: colors.red }]}
                activeOpacity={0.7}
                onPress={() => setVerFichaQr(piezaDefectuosaQr)}
              >
                <Text style={styles.label}>PIEZA DEFECTUOSA (va al buffer)</Text>
                <Text style={styles.cardFalla}>{piezaDefectuosaQr}</Text>
                {!piezaDefectuosaExiste ? (
                  <View style={[styles.estadoBanner, { backgroundColor: colors.orange, marginTop: 8 }]}>
                    <Text style={[styles.estadoText, { fontSize: 15 }]}>
                      Se dará de alta automáticamente como defectuosa
                    </Text>
                  </View>
                ) : (
                  <Text style={[styles.cardMeta, { color: colors.blue }]}>Tocá para ver la ficha ›</Text>
                )}
              </TouchableOpacity>

              {!piezaReemplazoQr ? (
                <Text style={[styles.cardMeta, { marginBottom: 8 }]}>
                  {sugerencias.length > 0
                    ? `${sugerencias.length} pieza(s) disponible(s) para reemplazo en el buffer.`
                    : "No hay piezas disponibles sugeridas: puede que haya que esperar el curado."}
                </Text>
              ) : null}

              {piezaReemplazoQr ? (
                <View style={[styles.card, { borderColor: colors.green }]}>
                  <Text style={styles.label}>PIEZA DE REEMPLAZO (queda montada)</Text>
                  <Text style={styles.cardFalla}>{piezaReemplazoQr}</Text>
                </View>
              ) : null}

              {rejection ? (
                <View style={{ backgroundColor: colors.red, borderRadius: 8, padding: 14, marginTop: 4, marginBottom: 10 }}>
                  <Text style={{ color: "#fff", fontSize: 20, fontWeight: "bold", textAlign: "center" }}>
                    {rejection}
                  </Text>
                  <Text style={{ color: "#fff", fontSize: 14, textAlign: "center", marginTop: 6 }}>
                    Escaneá otra pieza de reemplazo del stock disponible.
                  </Text>
                </View>
              ) : null}

              {piezaReemplazoQr ? (
                <>
                  {defectosSel.size === 0 ? (
                    <Text style={[styles.cardMeta, { color: colors.red, marginBottom: 4 }]}>
                      Seleccioná al menos un defecto
                    </Text>
                  ) : null}
                  <View style={{ flexDirection: "row", gap: 8, marginTop: 12, marginBottom: 40 }}>
                    <TouchableOpacity
                      onPress={confirmarCambio}
                      disabled={guardando || defectosSel.size === 0}
                      style={[
                        styles.bigButton,
                        { flex: 1, backgroundColor: colors.green, opacity: guardando || defectosSel.size === 0 ? 0.6 : 1 },
                      ]}
                    >
                      {guardando ? <ActivityIndicator color="#000" /> : <Text style={styles.bigButtonText}>CONFIRMAR CAMBIO</Text>}
                    </TouchableOpacity>
                  </View>
                </>
              ) : null}
            </>
          ) : null}

          {!loading && !error && step === STEP_ASIENTO ? (
            <Text style={styles.emptyText}>ESCANEE EL ASIENTO PARA EMPEZAR</Text>
          ) : null}
        </ScrollView>

        {step !== STEP_ASIENTO ? (
          <TouchableOpacity
            onPress={resetWizard}
            style={[styles.bigButton, { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.red, marginTop: 8 }]}
          >
            <Text style={[styles.bigButtonText, { color: colors.red }]}>CANCELAR</Text>
          </TouchableOpacity>
        ) : null}
      </KeyboardAvoidingView>

      <DefectoInfoModal visible={!!infoDefecto} defecto={infoDefecto} views={views} onClose={() => setInfoDefecto(null)} />
      <PiezaHistorialModal visible={!!verFichaQr} qr={verFichaQr} onClose={() => setVerFichaQr(null)} />

      <PuestoSelectorModal
        visible={showPuestoSelector}
        puestos={config?.puestos || []}
        puestoActual={puesto}
        onElegir={elegirPuesto}
        onClose={() => setShowPuestoSelector(false)}
        obligatorio={!puesto}
      />
      <BufferSelectorModal
        visible={showBufferSelector}
        buffers={config?.buffers || []}
        bufferActual={buffer}
        onElegir={elegirBuffer}
        onClose={() => setShowBufferSelector(false)}
        obligatorio={!buffer}
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
