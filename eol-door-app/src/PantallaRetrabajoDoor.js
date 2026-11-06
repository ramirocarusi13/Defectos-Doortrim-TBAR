import React, { useCallback, useEffect, useRef, useState } from "react";
import {
  SafeAreaView,
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ScrollView,
  Alert,
  ActivityIndicator,
} from "react-native";
import { StatusBar } from "expo-status-bar";
import { styles, colors } from "./styles";
import {
  getFactoryConfig,
  getRetrabajosDoor,
  retrabajarDefectoDoor,
  getPieza,
  crearDoor,
  interpretarQrDoor,
} from "./api";
import { vistasDoorTrim } from "./factoryConfig";
import { normTipo, tipoLabel, chequearReemplazo, chequearReemplazoQr } from "./doortrim";
import { genClientUuid } from "./utils";
import { getBufferGuardado } from "./buffer";
import DefectoInfoModal from "./components/DefectoInfoModal";

const REFRESH_MS = 15000;

// Pantalla de PRODUCCION (retrabajador) de la linea de door trims. Calidad marco
// el defecto; aca se decide y se hace:
//   - RETRABAJADO OK: se arreglo ahi mismo (ej. una mancha que se limpia).
//   - CAMBIAR PIEZA: se escanea la que sale y la del buffer que entra (con los
//     requisitos: tipo, modelo, lado, disponible, curado).
// Despues Calidad valida el door trim en su pestaña VALIDAR.
export default function PantallaRetrabajoDoor({ user, config: configProp, onLogout, onAbrirBuffer }) {
  const [config, setConfig] = useState(configProp || null);
  const [defectos, setDefectos] = useState([]);
  const [filtroQr, setFiltroQr] = useState(null);
  const [qr, setQr] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(null);
  const [verDefecto, setVerDefecto] = useState(null);
  const [buffer, setBuffer] = useState(null);

  // Cambio de pieza en curso: { grupo, saleQr, salePieza, entraQr, checks, rechazo }
  const [cambio, setCambio] = useState(null);
  const [exito, setExito] = useState(null);

  const qrRef = useRef(null);
  const scanTimer = useRef(null);
  const exitoTimer = useRef(null);
  const clientUuidRef = useRef(null);

  useEffect(() => {
    if (configProp) setConfig(configProp);
  }, [configProp]);

  useEffect(() => {
    if (!config) getFactoryConfig().then((c) => c?.data && setConfig(c.data));
    getBufferGuardado().then((b) => setBuffer(b));
    setTimeout(() => qrRef.current?.focus(), 200);
    return () => {
      if (scanTimer.current) clearTimeout(scanTimer.current);
      if (exitoTimer.current) clearTimeout(exitoTimer.current);
    };
  }, []);

  const cargar = useCallback(async () => {
    // Siempre se traen todos: al entrar solo se muestra cuantos hay; el detalle
    // aparece cuando se escanea el door trim.
    const res = await getRetrabajosDoor();
    if (res?.error && !res?.data) {
      setError(res?.message || "No se pudo traer la lista de retrabajos.");
      return;
    }
    setError(null);
    setDefectos(Array.isArray(res?.data) ? res.data : []);
  }, []);

  useEffect(() => {
    setLoading(true);
    cargar().finally(() => setLoading(false));
    const t = setInterval(cargar, REFRESH_MS);
    return () => clearInterval(t);
  }, [cargar]);

  // Agrupado por door trim + pieza: produccion resuelve la pieza entera.
  const grupos = (() => {
    const map = new Map();
    for (const d of defectos) {
      const tipo = normTipo(d?.tipo?.tipo) || "PIEZA";
      const key = `${d.qr}|${tipo}`;
      if (!map.has(key)) {
        map.set(key, { key, qr: d.qr, tipo, modelo: d.modelo, codigo: d.codigo, lado: d?.lado?.lado, secuencia: d.secuencia, defectos: [] });
      }
      map.get(key).defectos.push(d);
    }
    return Array.from(map.values());
  })();

  const gruposVisibles = filtroQr ? grupos.filter((gr) => gr.qr === filtroQr) : [];

  const focusQr = () => setTimeout(() => qrRef.current?.focus(), 120);

  const mostrarExito = (data) => {
    setExito(data);
    setFiltroQr(null);
    if (exitoTimer.current) clearTimeout(exitoTimer.current);
    exitoTimer.current = setTimeout(() => {
      setExito(null);
      focusQr();
    }, 2500);
  };

  // ---------- escaneo ----------
  const procesarScan = async (raw) => {
    const v = String(raw || "").trim();
    if (!v) return;
    if (scanTimer.current) clearTimeout(scanTimer.current);
    setQr("");
    if (!cambio) {
      setFiltroQr(v);
      focusQr();
      return;
    }
    return escanearEntra(v);
  };

  const onScanChange = (text) => {
    setQr(text);
    if (scanTimer.current) clearTimeout(scanTimer.current);
    const v = String(text || "").trim();
    if (v.length >= 3) scanTimer.current = setTimeout(() => procesarScan(v), 300);
  };

  // ---------- RETRABAJADO OK ----------
  const retrabajarGrupo = (g) => {
    Alert.alert(
      "Retrabajar",
      `¿Quedó retrabajado el ${tipoLabel(g.tipo)}? (${g.defectos.map((d) => d?.falla?.codigo).join(", ")}). Se guarda con tu usuario y pasa a Calidad.`,
      [
        { text: "Cancelar", style: "cancel" },
        {
          text: "Sí, quedó OK",
          onPress: async () => {
            setBusy(g.key);
            let fallo = null;
            for (const d of g.defectos) {
              const res = await retrabajarDefectoDoor(d.id, { user: user?.id });
              if (res?.error) fallo = res?.message || "No se pudo guardar el retrabajo.";
            }
            setBusy(null);
            await cargar();
            if (fallo) Alert.alert("Error", fallo);
            else mostrarExito({ titulo: "RETRABAJADO ✓", detalle: `${tipoLabel(g.tipo)} · pasa a Calidad para validar` });
          },
        },
      ]
    );
  };

  // ---------- CAMBIAR ----------
  // La pieza que sale es la que se escaneo (la del defecto): solo se escanea la
  // pieza del buffer que entra. Queda registrado "esta pieza se cambio por esa".
  const empezarCambio = async (g) => {
    clientUuidRef.current = genClientUuid();
    const [res, qrRes] = await Promise.all([getPieza(g.qr), interpretarQrDoor(g.qr)]);
    const p = res?.error ? null : res?.data || null;
    const saleInfo = qrRes?.error ? null : qrRes?.data || null;
    // El garnish no se sigue pieza por pieza (su QR es el SD).
    const rechazo =
      saleInfo?.tipo !== "GARNISH" && p && !["EN_ASIENTO", "DISPONIBLE"].includes(p.estado)
        ? `Esta pieza figura como ${p.estado}: ya se cambió antes.`
        : null;
    setCambio({ grupo: g, saleQr: g.qr, salePieza: p, saleInfo, entraQr: "", checks: [], rechazo });
    focusQr();
  };

  const escanearEntra = async (v) => {
    // El garnish nuevo tiene el mismo QR que el que sale (su QR es el SD).
    if (v === cambio.saleQr && cambio.saleInfo?.tipo !== "GARNISH") {
      setCambio((c) => ({ ...c, rechazo: "La pieza que entra no puede ser la misma que sale." }));
      return focusQr();
    }
    const [res, qrRes] = await Promise.all([getPieza(v), interpretarQrDoor(v)]);
    const p = res?.error ? null : res?.data || null;
    const entraInfo = qrRes?.error ? null : qrRes?.data || null;
    // QR de la app de camaras: los requisitos salen de los QR (no hace falta que la
    // pieza este dada de alta en el buffer). Si no, el chequeo viejo por el maestro.
    const checks = entraInfo
      ? chequearReemplazoQr(entraInfo, { sale: cambio.saleInfo, pieza: entraInfo.tipo === "GARNISH" ? null : p, tipo: cambio.grupo.tipo })
      : chequearReemplazo(p, { tipo: cambio.grupo.tipo, sale: cambio.salePieza, lado: cambio.grupo.lado, codigo: cambio.grupo.codigo });
    setCambio((c) => ({ ...c, entraQr: v, checks, rechazo: null }));
    focusQr();
  };

  const cumple = !!cambio && cambio.checks.length > 0 && cambio.checks.every((c) => c.ok);

  const confirmarCambio = async () => {
    if (!cumple || busy) return;
    const g = cambio.grupo;
    setBusy("cambio");
    const res = await crearDoor({
      qr: g.qr,
      tipo_pieza: g.tipo,
      defecto_ids: g.defectos.map((d) => d.id),
      pieza_defectuosa_qr: cambio.saleQr,
      pieza_reemplazo_qr: cambio.entraQr,
      modelo: g.modelo,
      codigo: g.codigo,
      lado: g.lado,
      secuencia: g.secuencia != null ? String(g.secuencia) : undefined,
      buffer_id: buffer?.id != null ? String(buffer.id) : undefined,
      buffer_nombre: buffer?.name,
      user: user?.id,
      puesto_nombre: "DOORTRIM",
      client_uuid: clientUuidRef.current,
    });
    setBusy(null);
    if (res?.error || !res?.data) {
      clientUuidRef.current = genClientUuid();
      setCambio((c) => ({ ...c, entraQr: "", checks: [], rechazo: res?.message || "No se pudo registrar el cambio. Probá con otra pieza." }));
      return focusQr();
    }
    setCambio(null);
    await cargar();
    mostrarExito({
      titulo: "PIEZA CAMBIADA ✓",
      detalle: `Salió ${cambio.saleQr} (al buffer) · entró ${res.data?.door?.pieza_entregada_qr || cambio.entraQr} · pasa a Calidad`,
    });
  };

  const vistasDe = (d) => (config ? vistasDoorTrim({ modelo: d.modelo, codigo: d.codigo, lado: d?.lado?.lado }, config) : []);

  // ---------- UI ----------
  if (exito) {
    return (
      <SafeAreaView style={styles.screen}>
        <StatusBar style="light" />
        <View style={styles.header}>
          <Text style={styles.headerTitle}>DOORTRIM · RETRABAJO</Text>
        </View>
        <View style={[styles.body, { alignItems: "center", justifyContent: "center" }]}>
          <View style={{ backgroundColor: colors.green, borderRadius: 12, padding: 24, width: "100%" }}>
            <Text style={{ color: "#000", fontSize: 26, fontWeight: "bold", textAlign: "center" }}>{exito.titulo}</Text>
            <Text style={{ color: "#000", fontSize: 17, textAlign: "center", marginTop: 10 }}>{exito.detalle}</Text>
          </View>
        </View>
      </SafeAreaView>
    );
  }

  const g = cambio?.grupo;
  return (
    <SafeAreaView style={styles.screen}>
      <StatusBar style="light" />
      <View style={styles.header}>
        <Text style={styles.headerTitle}>DOORTRIM · RETRABAJO</Text>
        {onAbrirBuffer ? (
          <TouchableOpacity onPress={onAbrirBuffer} style={{ alignSelf: "center", marginTop: 6 }} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
            <Text style={{ color: "#fff", fontSize: 14, fontWeight: "700" }}>VER BUFFER ›</Text>
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

        <Text style={styles.label}>
          {!cambio
            ? "Escaneá el door trim para ver sus defectos"
            : !cambio.saleQr
            ? `Escaneá el ${tipoLabel(g.tipo)} que SALE`
            : `Escaneá el ${tipoLabel(g.tipo)} del BUFFER que ENTRA`}
        </Text>
        <TextInput
          ref={qrRef}
          style={styles.input}
          value={qr}
          onChangeText={onScanChange}
          placeholder="Escaneá el QR"
          placeholderTextColor="#9ca3af"
          blurOnSubmit={false}
          onSubmitEditing={(e) => procesarScan(e.nativeEvent.text)}
          returnKeyType="search"
        />

        {/* ----- CAMBIO DE PIEZA EN CURSO ----- */}
        {cambio ? (
          <ScrollView style={{ flex: 1, marginTop: 10 }} keyboardShouldPersistTaps="handled">
            <View style={{ backgroundColor: colors.yellow, padding: 10, borderRadius: 6, marginBottom: 10 }}>
              <Text style={{ color: "#000", fontWeight: "bold", fontSize: 16 }}>
                CAMBIO DE {tipoLabel(g.tipo)} · {g.modelo} {g.lado || ""}
              </Text>
              <Text style={{ color: "#000", fontSize: 13 }}>
                {g.qr} · {g.defectos.map((d) => d?.falla?.codigo).join(", ")}
              </Text>
            </View>

            {cambio.saleQr ? (
              <View style={[styles.card, { borderColor: colors.red }]}>
                <Text style={styles.label}>SALE {tipoLabel(g.tipo)} (va al buffer)</Text>
                <Text style={styles.cardFalla}>{cambio.saleQr}</Text>
                {cambio.salePieza ? (
                  <Text style={styles.cardMeta}>
                    {cambio.salePieza.modelo || "-"} · {cambio.salePieza.lado || "-"}
                  </Text>
                ) : (
                  <Text style={styles.cardMeta}>
                    {g.modelo || "-"} · {g.lado || "-"}
                  </Text>
                )}
              </View>
            ) : null}

            {cambio.entraQr ? (
              <View style={[styles.card, { borderColor: cumple ? colors.green : colors.red }]}>
                <Text style={styles.label}>ENTRA {tipoLabel(g.tipo)} (del buffer)</Text>
                <Text style={styles.cardFalla}>{cambio.entraQr}</Text>
                {cambio.checks.map((c) => (
                  <Text key={c.id} style={{ color: c.ok ? colors.green : colors.red, fontSize: 16, fontWeight: "bold", marginTop: 4 }}>
                    {c.ok ? "✓" : "✗"} {c.label}
                    {c.detalle ? <Text style={{ color: colors.muted, fontWeight: "normal" }}>{`  ·  ${c.detalle}`}</Text> : null}
                  </Text>
                ))}
                {!cumple ? (
                  <Text style={{ color: colors.red, fontWeight: "bold", marginTop: 8 }}>NO CUMPLE · escaneá otra pieza del buffer</Text>
                ) : null}
              </View>
            ) : cambio.saleQr ? (
              <Text style={[styles.cardMeta, { marginBottom: 8 }]}>
                Tiene que ser {tipoLabel(g.tipo)}, del mismo modelo y lado, disponible y con el curado completo.
              </Text>
            ) : null}

            {cambio.rechazo ? (
              <View style={{ backgroundColor: colors.red, borderRadius: 8, padding: 14, marginBottom: 10 }}>
                <Text style={{ color: "#fff", fontSize: 18, fontWeight: "bold", textAlign: "center" }}>{cambio.rechazo}</Text>
              </View>
            ) : null}

            {cambio.entraQr && cumple ? (
              <TouchableOpacity
                onPress={confirmarCambio}
                disabled={!!busy}
                style={[styles.bigButton, { backgroundColor: colors.green, marginTop: 8, opacity: busy ? 0.6 : 1 }]}
              >
                {busy ? <ActivityIndicator color="#000" /> : <Text style={styles.bigButtonText}>CONFIRMAR CAMBIO</Text>}
              </TouchableOpacity>
            ) : null}

            <TouchableOpacity
              onPress={() => {
                setCambio(null);
                focusQr();
              }}
              style={[styles.bigButton, { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.red, marginTop: 12, marginBottom: 24 }]}
            >
              <Text style={[styles.bigButtonText, { color: colors.red }]}>CANCELAR CAMBIO</Text>
            </TouchableOpacity>
          </ScrollView>
        ) : (
          <>
            {filtroQr ? (
              <View style={{ flexDirection: "row", alignItems: "center", marginTop: 8, gap: 8 }}>
                <Text style={[styles.cardMeta, { flex: 1 }]} numberOfLines={1}>
                  Door trim: {filtroQr}
                </Text>
                <TouchableOpacity onPress={() => setFiltroQr(null)} style={{ padding: 6 }}>
                  <Text style={{ color: colors.orange, fontWeight: "bold" }}>‹ VOLVER</Text>
                </TouchableOpacity>
              </View>
            ) : null}

            {loading && defectos.length === 0 ? <ActivityIndicator color={colors.green} size="large" style={{ marginTop: 20 }} /> : null}
            {error ? <Text style={[styles.emptyText, { color: colors.orange }]}>{error}</Text> : null}

            {/* Al entrar: solo cuantos retrabajos hay. El detalle, al escanear. */}
            {!filtroQr ? (
              <View style={{ alignItems: "center", marginTop: 40 }}>
                <Text style={{ color: grupos.length ? colors.orange : colors.green, fontSize: 72, fontWeight: "bold" }}>
                  {grupos.length}
                </Text>
                <Text style={{ color: colors.text, fontSize: 24, fontWeight: "bold", textAlign: "center" }}>
                  {grupos.length === 1 ? "RETRABAJO PENDIENTE" : "RETRABAJOS PENDIENTES"}
                </Text>
              </View>
            ) : null}

            <ScrollView style={{ flex: 1 }} keyboardShouldPersistTaps="handled">
              {gruposVisibles.map((gr) => (
                <View key={gr.key} style={[styles.card, { marginBottom: 10 }]}>
                  <Text style={styles.cardFalla}>
                    {tipoLabel(gr.tipo)} · {gr.modelo || "-"} {gr.lado || ""}
                    {gr.secuencia ? ` · SEC ${gr.secuencia}` : ""}
                  </Text>
                  <Text style={styles.cardMeta} numberOfLines={1}>
                    {gr.qr}
                  </Text>
                  {gr.defectos.map((d) => (
                    <View key={d.id} style={{ flexDirection: "row", alignItems: "center", marginTop: 6 }}>
                      <Text style={{ color: colors.text, fontWeight: "bold", fontSize: 16, flex: 1 }}>
                        {d?.falla?.codigo} - {d?.falla?.nombre}
                        {d.reaperturas ? <Text style={{ color: colors.red }}>{"  · rechazado por Calidad"}</Text> : null}
                      </Text>
                      <TouchableOpacity onPress={() => setVerDefecto(d)} style={{ paddingHorizontal: 10, paddingVertical: 6 }}>
                        <Text style={{ color: colors.cyan, fontWeight: "bold" }}>VER FOTO</Text>
                      </TouchableOpacity>
                    </View>
                  ))}
                  <Text style={[styles.cardMeta, { marginTop: 4 }]}>
                    Marcó: {gr.defectos[0]?.marcado_por_nombre || "-"} · {gr.defectos[0]?.created_at || ""}
                  </Text>
                  <View style={{ flexDirection: "row", gap: 8, marginTop: 10 }}>
                    <TouchableOpacity
                      onPress={() => retrabajarGrupo(gr)}
                      disabled={busy === gr.key}
                      style={{ flex: 1, backgroundColor: colors.green, borderRadius: 6, paddingVertical: 16, opacity: busy === gr.key ? 0.6 : 1 }}
                    >
                      {busy === gr.key ? (
                        <ActivityIndicator color="#000" />
                      ) : (
                        <Text style={{ color: "#000", fontWeight: "bold", textAlign: "center", fontSize: 16 }}>RETRABAJAR</Text>
                      )}
                    </TouchableOpacity>
                    <TouchableOpacity
                      onPress={() => empezarCambio(gr)}
                      style={{ flex: 1, backgroundColor: colors.blue, borderRadius: 6, paddingVertical: 16 }}
                    >
                      <Text style={{ color: "#fff", fontWeight: "bold", textAlign: "center", fontSize: 16 }}>CAMBIAR</Text>
                    </TouchableOpacity>
                  </View>
                </View>
              ))}
              {filtroQr && !loading && !error && gruposVisibles.length === 0 ? (
                <Text style={styles.emptyText}>Este door trim no tiene retrabajos pendientes.</Text>
              ) : null}
            </ScrollView>
          </>
        )}
      </View>

      {/* Sin foto capturada: se fuerza la vista del modelo con la marca dibujada
          (el backend manda en "imagen" la foto lisa de la vista). */}
      <DefectoInfoModal
        visible={!!verDefecto}
        defecto={verDefecto ? { ...verDefecto, imagen: null, imagen_marcada: null } : null}
        views={verDefecto ? vistasDe(verDefecto) : []}
        onClose={() => setVerDefecto(null)}
      />
    </SafeAreaView>
  );
}
