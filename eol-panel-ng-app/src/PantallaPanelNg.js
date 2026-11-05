import React, { useEffect, useRef, useState } from "react";
import { SafeAreaView, View, Text, TextInput, TouchableOpacity, ActivityIndicator, Modal } from "react-native";
import { StatusBar } from "expo-status-bar";
import { styles, colors } from "./styles";
import { marcarPanelNg, reetiquetarPanel } from "./api";

// Puesto de produccion PANEL NG:
//   1) se escanea el panel que volvio con un defecto -> queda NG (con el puesto)
//   2) se escanea el panel nuevo (mismo SD, posicion y mano) -> se le reimprime la
//      etiqueta con SU id y la SECUENCIA del panel NG.
// La pantalla queda limpia: solo el campo de escaneo y que escanear. El resultado
// se muestra en un recuadro grande (verde OK / rojo PANEL INCORRECTO) que se va solo.
const PASO_NG = "NG";
const PASO_NUEVO = "NUEVO";
const SEG_OK = 2500;
const SEG_ERROR = 4500;

// "15213|1|D3H|RR|LH" (o con espacios) -> partes
function partes(qr) {
  const t = String(qr || "").trim().toUpperCase();
  const p = (t.includes("|") ? t.split("|") : t.split(/\s+/)).map((x) => x.trim()).filter(Boolean);
  return { id: p[0] || "", secuencia: p[1] || "", sd: p[2] || "", posicion: p[3] || "", mano: p[4] || "" };
}
const resumen = (p) => [p.sd, p.posicion, p.mano].filter(Boolean).join("  ");

export default function PantallaPanelNg({ puesto, impresora, onCambiarPuesto }) {
  const [paso, setPaso] = useState(PASO_NG);
  const [qr, setQr] = useState("");
  const [ng, setNg] = useState(null);
  const [busy, setBusy] = useState(false);
  const [aviso, setAviso] = useState(null); // { ok: bool, titulo, lineas: [] }
  const inputRef = useRef(null);
  const timer = useRef(null);
  const avisoTimer = useRef(null);

  const foco = () => setTimeout(() => inputRef.current?.focus(), 150);
  useEffect(() => {
    foco();
    return () => avisoTimer.current && clearTimeout(avisoTimer.current);
  }, []);

  const mostrar = (a) => {
    if (avisoTimer.current) clearTimeout(avisoTimer.current);
    setAviso(a);
    avisoTimer.current = setTimeout(() => {
      setAviso(null);
      foco();
    }, a.ok ? SEG_OK : SEG_ERROR);
  };

  const escanear = async (valor) => {
    const v = String(valor || "").trim();
    setQr("");
    if (!v || busy || aviso) return;
    setBusy(true);
    try {
      if (paso === PASO_NG) {
        const r = await marcarPanelNg(v, puesto);
        if (r?.error || !r?.data) {
          mostrar({ ok: false, titulo: "PANEL INCORRECTO", lineas: [] });
          return;
        }
        setNg(r.data);
        setPaso(PASO_NUEVO);
      } else {
        const r = await reetiquetarPanel(ng.id, v, puesto);
        if (r?.error || !r?.data) {
          mostrar({ ok: false, titulo: "PANEL INCORRECTO", lineas: [] });
          return;
        }
        // Listo: vuelve al principio para el proximo panel con defecto.
        setNg(null);
        setPaso(PASO_NG);
        mostrar({ ok: true, titulo: "OK", lineas: [] });
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

  const cancelar = () => {
    setNg(null);
    setPaso(PASO_NG);
    foco();
  };

  const e = ng ? partes(ng.qr_ng) : null;

  return (
    <SafeAreaView style={styles.screen}>
      <StatusBar style="light" />
      <View style={[styles.header, { paddingVertical: 8 }]}>
        <Text style={[styles.headerTitle, { fontSize: 22 }]}>PANEL NG · {puesto}</Text>
        <View style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginTop: 4 }}>
          <Text style={{ color: "#fed7aa", fontSize: 13 }}>Impresora {impresora || "-"}</Text>
          <TouchableOpacity focusable={false} onPress={onCambiarPuesto} hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }} style={{ borderColor: "#fed7aa", borderWidth: 1, borderRadius: 6, paddingHorizontal: 10, paddingVertical: 4 }}>
            <Text style={{ color: "#fff", fontSize: 13, fontWeight: "bold" }}>CAMBIAR PUESTO</Text>
          </TouchableOpacity>
        </View>
      </View>

      <View style={[styles.body, { paddingTop: 14 }]}>
        {/* Que escanear ahora */}
        <Text style={textoPaso}>{paso === PASO_NG ? "ESCANEÁ EL PANEL CON DEFECTO" : "ESCANEÁ EL PANEL NUEVO"}</Text>
        {e ? <Text style={textoNg}>NG: {e.id}  ·  {resumen(e)}  ·  SEC {e.secuencia}</Text> : null}

        {/* Lector del TC21: escribe aca, sin teclado en pantalla */}
        <TextInput
          ref={inputRef}
          value={qr}
          onChangeText={onChange}
          onSubmitEditing={(ev) => escanear(ev.nativeEvent.text)}
          autoFocus
          showSoftInputOnFocus={false}
          blurOnSubmit={false}
          placeholder="Escaneá el QR"
          placeholderTextColor="#6b7280"
          style={[styles.input, { fontSize: 18, paddingVertical: 12, marginTop: 12 }]}
        />
        {busy ? <ActivityIndicator color={colors.green} size="large" style={{ marginTop: 16 }} /> : null}

        {paso === PASO_NUEVO && !busy ? (
          <TouchableOpacity focusable={false} onPress={cancelar} style={{ alignSelf: "center", marginTop: 24, padding: 10 }}>
            <Text style={{ color: colors.muted, fontSize: 16, fontWeight: "bold" }}>CANCELAR</Text>
          </TouchableOpacity>
        ) : null}
      </View>

      {/* Resultado: recuadro grande que se va solo */}
      <Modal visible={!!aviso} transparent animationType="fade" onRequestClose={() => {}}>
        <View style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.6)", alignItems: "center", justifyContent: "center", padding: 18 }}>
          <View
            style={{
              width: "100%",
              aspectRatio: 1,
              backgroundColor: aviso?.ok ? "#15803d" : "#b91c1c",
              alignItems: "center",
              justifyContent: "center",
              padding: 20,
            }}
          >
            <Text style={{ color: "#fff", fontWeight: "bold", fontSize: aviso?.ok ? 110 : 56, textAlign: "center" }}>{aviso?.titulo}</Text>
            {(aviso?.lineas || []).map((l, i) => (
              <Text key={i} style={{ color: "#fff", fontWeight: "bold", fontSize: 20, textAlign: "center", marginTop: 14 }}>
                {l}
              </Text>
            ))}
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const textoPaso = { color: colors.text, fontSize: 24, fontWeight: "bold", textAlign: "center" };
const textoNg = { color: "#fca5a5", fontSize: 18, fontWeight: "bold", textAlign: "center", marginTop: 8 };
