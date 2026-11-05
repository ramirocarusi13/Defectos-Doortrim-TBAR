import React, { useEffect, useState } from "react";
import { Modal, View, Text, TouchableOpacity, ScrollView, ActivityIndicator } from "react-native";
import { styles, colors } from "../styles";
import { getPieza, getPiezaEventos } from "../api";
import { formatCountdown, formatFecha } from "../utils";

// Bottom-sheet con la ficha de una pieza (DOOR/ARM_REST) + su historial de
// eventos (trazabilidad completa del ciclo cambio -> buffer -> retrabajo ->
// reingreso -> validacion -> disponible). Mismo patron visual que
// DefectoInfoModal: modal deslizante desde abajo, solo lectura.
export default function PiezaHistorialModal({ visible, qr, onClose }) {
  const [pieza, setPieza] = useState(null);
  const [eventos, setEventos] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!visible || !qr) return;
    let activo = true;
    setLoading(true);
    setError(null);
    setPieza(null);
    setEventos([]);

    Promise.all([getPieza(qr), getPiezaEventos(qr)]).then(([p, ev]) => {
      if (!activo) return;
      setLoading(false);
      if (p?.error) {
        setError(p?.message || "No se pudo traer la ficha de la pieza");
        return;
      }
      setPieza(p?.data || null);
      setEventos(Array.isArray(ev?.data) ? ev.data : []);
    });

    return () => {
      activo = false;
    };
  }, [visible, qr]);

  const colorEstado = (estado) => {
    const e = String(estado || "").toUpperCase();
    if (e === "DISPONIBLE") return colors.green;
    if (e === "DEFECTUOSA") return colors.red;
    if (e === "EN_RETRABAJO") return colors.blue;
    if (e === "CURANDO") return colors.cyan;
    if (e === "PEND_VALIDACION") return colors.orange;
    if (e === "SCRAP") return colors.red;
    return colors.orange;
  };

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.8)", justifyContent: "flex-end" }}>
        <View style={{ backgroundColor: colors.bg, borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 16, maxHeight: "92%" }}>
          <View style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginBottom: 10 }}>
            <Text style={[styles.headerTitle, { fontSize: 20 }]}>FICHA DE LA PIEZA</Text>
            <TouchableOpacity onPress={onClose} hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}>
              <Text style={{ color: colors.orange, fontSize: 22, fontWeight: "bold" }}>✕</Text>
            </TouchableOpacity>
          </View>

          {loading ? <ActivityIndicator color={colors.green} size="large" style={{ marginTop: 24 }} /> : null}
          {!loading && error ? <Text style={styles.emptyText}>{error}</Text> : null}

          {!loading && !error ? (
            <ScrollView keyboardShouldPersistTaps="handled">
              <View style={[styles.card, { marginBottom: 10 }]}>
                <Text style={styles.cardModel}>{pieza?.tipo_pieza || "PIEZA"}</Text>
                <Text style={styles.cardMeta}>QR: {pieza?.pieza_qr || qr}</Text>
                {pieza?.buffer_nombre ? (
                  <Text style={styles.cardMeta}>Buffer: {pieza.buffer_nombre}</Text>
                ) : null}
                {pieza?.modelo ? <Text style={styles.cardMeta}>Modelo: {pieza.modelo}</Text> : null}
                {pieza?.defecto?.falla_nombre ? (
                  <Text style={styles.cardMeta}>
                    Defecto actual: {pieza.defecto.falla_codigo} - {pieza.defecto.falla_nombre}
                  </Text>
                ) : null}
                <View style={[styles.estadoBanner, { backgroundColor: colorEstado(pieza?.estado) }]}>
                  <Text style={styles.estadoText}>{pieza?.estado || "SIN DATOS"}</Text>
                </View>
                {pieza?.disponible_desde && new Date(pieza.disponible_desde) > new Date() ? (
                  <Text style={[styles.cardMeta, { color: colors.cyan, marginTop: 6 }]}>
                    Curando · disponible en {formatCountdown(pieza.disponible_desde)}
                  </Text>
                ) : null}
              </View>

              <Text style={styles.label}>HISTORIAL · {eventos.length} evento(s)</Text>
              {eventos.length === 0 ? (
                <Text style={styles.cardMeta}>Sin eventos registrados todavía.</Text>
              ) : (
                eventos.map((ev, idx) => (
                  <View key={ev.id ?? idx} style={[styles.card, { marginBottom: 8, paddingVertical: 10 }]}>
                    <Text style={styles.cardFalla}>{ev?.evento || "EVENTO"}</Text>
                    <Text style={styles.cardMeta}>
                      {ev?.estado_desde || "-"} → {ev?.estado_hasta || "-"}
                    </Text>
                    <Text style={styles.cardMeta}>Usuario: {ev?.user_nombre || "-"}</Text>
                    {ev?.observaciones ? (
                      <Text style={styles.cardMeta}>OBS: {ev.observaciones}</Text>
                    ) : null}
                    {ev?.created_at ? <Text style={styles.cardMeta}>Fecha: {formatFecha(ev.created_at)}</Text> : null}
                  </View>
                ))
              )}
            </ScrollView>
          ) : null}

          <TouchableOpacity
            style={[styles.bigButton, { backgroundColor: colors.card, marginTop: 12, borderWidth: 1, borderColor: colors.border }]}
            onPress={onClose}
          >
            <Text style={[styles.bigButtonText, { color: colors.text }]}>CERRAR</Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}
