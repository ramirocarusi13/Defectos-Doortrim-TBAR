import React, { useEffect, useRef, useState } from "react";
import { SafeAreaView, View, Text, TextInput, TouchableOpacity, ActivityIndicator, ScrollView } from "react-native";
import { StatusBar } from "expo-status-bar";
import { styles, colors } from "./styles";
import { marcarPanelNg, reetiquetarPanel, reimprimirPanel, getPanelesNg } from "./api";

// Puesto de produccion PANEL NG:
//   1) se escanea el panel que volvio con un defecto -> queda NG (con el puesto)
//   2) se escanea el panel nuevo (mismo SD, posicion y mano) -> se le reimprime la
//      etiqueta con SU id y la SECUENCIA del panel NG, en la impresora del puesto.
const PASO_NG = "NG";
const PASO_NUEVO = "NUEVO";
const PASO_LISTO = "LISTO";

function partes(qr) {
  const p = String(qr || "").split("|");
  return { id: p[0], secuencia: p[1], sd: p[2], posicion: p[3], mano: p[4] };
}

export default function PantallaPanelNg({ puesto, impresora, onCambiarPuesto }) {
  const [paso, setPaso] = useState(PASO_NG);
  const [qr, setQr] = useState("");
  const [ng, setNg] = useState(null);
  const [resultado, setResultado] = useState(null);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);
  const [ultimos, setUltimos] = useState([]);
  const inputRef = useRef(null);
  const timer = useRef(null);

  const foco = () => setTimeout(() => inputRef.current?.focus(), 150);
  const cargarUltimos = async () => {
    const r = await getPanelesNg(puesto);
    if (!r?.error && Array.isArray(r?.data)) setUltimos(r.data.slice(0, 6));
  };
  useEffect(() => {
    cargarUltimos();
    foco();
  }, []);

  const empezar = () => {
    setPaso(PASO_NG);
    setNg(null);
    setResultado(null);
    setError(null);
    foco();
  };

  const escanear = async (valor) => {
    const v = String(valor || "").trim();
    setQr("");
    if (!v || busy) return;
    setError(null);
    setBusy(true);
    try {
      if (paso === PASO_NG || paso === PASO_LISTO) {
        // Panel con defecto (o el siguiente, si ya se imprimio el anterior)
        const r = await marcarPanelNg(v, puesto);
        if (r?.error || !r?.data) {
          setError(r?.message || "No se pudo marcar el panel NG");
          return;
        }
        setNg(r.data);
        setResultado(null);
        setPaso(PASO_NUEVO);
      } else {
        const r = await reetiquetarPanel(ng.id, v, puesto);
        if (r?.error || !r?.data) {
          setError(r?.message || "No se pudo imprimir la etiqueta");
          return;
        }
        setResultado(r.data);
        setPaso(PASO_LISTO);
        cargarUltimos();
      }
    } finally {
      setBusy(false);
      foco();
    }
  };

  const onChange = (t) => {
    setQr(t);
    if (timer.current) clearTimeout(timer.current);
    if (String(t || "").trim().length >= 5) timer.current = setTimeout(() => escanear(t), 350);
  };

  const reimprimir = async () => {
    if (!resultado?.ng?.id || busy) return;
    setBusy(true);
    const r = await reimprimirPanel(resultado.ng.id);
    setBusy(false);
    setError(r?.error ? r?.message || "No se pudo reimprimir" : null);
    foco();
  };

  const p = ng ? partes(ng.qr_ng) : null;

  return (
    <SafeAreaView style={styles.screen}>
      <StatusBar style="light" />
      <TouchableOpacity activeOpacity={1} focusable={false} delayLongPress={3000} onLongPress={onCambiarPuesto} style={[styles.header, { paddingVertical: 10 }]}>
        <Text style={[styles.headerTitle, { fontSize: 22 }]}>PANEL NG · {puesto}</Text>
        <Text style={{ color: "#fed7aa", textAlign: "center", fontSize: 13 }}>Impresora {impresora || "-"}</Text>
      </TouchableOpacity>

      <View style={[styles.body, { paddingTop: 10 }]}>
        {/* Lector del TC21: escribe aca, sin teclado en pantalla */}
        <TextInput
          ref={inputRef}
          value={qr}
          onChangeText={onChange}
          onSubmitEditing={(e) => escanear(e.nativeEvent.text)}
          autoFocus
          showSoftInputOnFocus={false}
          blurOnSubmit={false}
          placeholder="Escaneá el QR"
          placeholderTextColor="#6b7280"
          style={[styles.input, { fontSize: 16, paddingVertical: 8 }]}
        />

        <ScrollView style={{ flex: 1 }} keyboardShouldPersistTaps="handled">
          {/* Paso 1 */}
          <View style={[tarjeta, { borderColor: paso === PASO_NG || paso === PASO_LISTO ? colors.orange : colors.border }]}>
            <Text style={titulo}>1 · ESCANEÁ EL PANEL CON DEFECTO</Text>
            {ng ? (
              <View style={{ backgroundColor: colors.red, borderRadius: 8, padding: 10, marginTop: 8 }}>
                <Text style={{ color: "#fff", fontWeight: "bold", fontSize: 22 }}>PANEL NG</Text>
                <Text style={{ color: "#fff", fontSize: 18 }}>
                  {p.id} · SEC {p.secuencia}
                </Text>
                <Text style={{ color: "#fff", fontSize: 18, fontWeight: "bold" }}>
                  {p.sd} · {p.posicion} · {p.mano}
                </Text>
                {ng.ya_estaba ? <Text style={{ color: "#fee2e2", fontSize: 13, marginTop: 4 }}>Ya estaba marcado NG</Text> : null}
              </View>
            ) : null}
          </View>

          {/* Paso 2 */}
          <View style={[tarjeta, { borderColor: paso === PASO_NUEVO ? colors.orange : colors.border, opacity: ng ? 1 : 0.5 }]}>
            <Text style={titulo}>2 · ESCANEÁ EL PANEL NUEVO</Text>
            {ng && paso === PASO_NUEVO ? (
              <Text style={{ color: colors.muted, fontSize: 16, marginTop: 6 }}>
                Tiene que ser {p.sd} · {p.posicion} · {p.mano}. Se le imprime la etiqueta con la secuencia {p.secuencia}.
              </Text>
            ) : null}
          </View>

          {busy ? <ActivityIndicator color={colors.green} size="large" style={{ marginVertical: 12 }} /> : null}

          {error ? (
            <View style={{ backgroundColor: "#450a0a", borderColor: colors.red, borderWidth: 2, borderRadius: 8, padding: 12, marginTop: 10 }}>
              <Text style={{ color: "#fecaca", fontSize: 18, fontWeight: "bold" }}>{error}</Text>
            </View>
          ) : null}

          {resultado ? (
            <View style={{ backgroundColor: colors.green, borderRadius: 10, padding: 14, marginTop: 10 }}>
              <Text style={{ color: "#000", fontSize: 24, fontWeight: "bold", textAlign: "center" }}>ETIQUETA IMPRESA ✓</Text>
              <Text style={{ color: "#000", fontSize: 18, textAlign: "center", marginTop: 6 }}>{resultado.qr_impreso}</Text>
              <Text style={{ color: "#000", fontSize: 14, textAlign: "center", marginTop: 4 }}>
                Escaneá el próximo panel con defecto
              </Text>
            </View>
          ) : null}

          <View style={{ flexDirection: "row", gap: 10, marginTop: 12 }}>
            {paso === PASO_NUEVO ? (
              <TouchableOpacity focusable={false} onPress={empezar} style={[styles.bigButton, { flex: 1, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.red }]}>
                <Text style={[styles.bigButtonText, { color: colors.red, fontSize: 18 }]}>CANCELAR</Text>
              </TouchableOpacity>
            ) : null}
            {paso === PASO_LISTO ? (
              <TouchableOpacity focusable={false} onPress={reimprimir} style={[styles.bigButton, { flex: 1, backgroundColor: colors.blue }]}>
                <Text style={[styles.bigButtonText, { color: "#fff", fontSize: 18 }]}>REIMPRIMIR</Text>
              </TouchableOpacity>
            ) : null}
          </View>

          {ultimos.length > 0 ? (
            <View style={{ marginTop: 16 }}>
              <Text style={[styles.label, { fontSize: 14 }]}>ÚLTIMOS DEL PUESTO</Text>
              {ultimos.map((u) => (
                <View key={u.id} style={{ flexDirection: "row", justifyContent: "space-between", paddingVertical: 6, borderBottomColor: "#262626", borderBottomWidth: 1 }}>
                  <Text style={{ color: colors.text, fontSize: 14, flex: 1 }} numberOfLines={1}>
                    {u.qr_ng}
                  </Text>
                  <Text style={{ color: u.estado === "NG" ? colors.red : colors.green, fontSize: 14, fontWeight: "bold", marginLeft: 8 }}>
                    {u.estado === "NG" ? "NG" : `→ ${String(u.qr_impreso || "").split("|")[0]}`}
                  </Text>
                </View>
              ))}
            </View>
          ) : null}
        </ScrollView>
      </View>
    </SafeAreaView>
  );
}

const tarjeta = { borderWidth: 2, borderRadius: 10, padding: 12, marginTop: 10, backgroundColor: colors.card };
const titulo = { color: colors.text, fontSize: 20, fontWeight: "bold" };
