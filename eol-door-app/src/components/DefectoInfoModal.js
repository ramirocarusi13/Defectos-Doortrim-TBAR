import React from "react";
import { Modal, View, Text, TouchableOpacity, ScrollView, Image } from "react-native";
import { styles, colors } from "../styles";
import { FotoConMarcas, findViewForDefecto } from "./AsientoViewer";
import { resolveImageSource } from "../utils";

// Modal de SOLO LECTURA con el detalle de un defecto: muestra la foto del asiento
// con la marca del defecto, los datos del defecto y QUIEN lo marco (operario de calidad).
export default function DefectoInfoModal({ visible, defecto, views = [], onClose }) {
  if (!defecto) return null;

  const view = findViewForDefecto(views, defecto);
  const reparado = defecto?.estado && defecto.estado !== "PENDIENTE";
  // Foto capturada con la marca dibujada al momento de marcar (si existe).
  const marcadaSrc = resolveImageSource(defecto?.imagen?.imagen || defecto?.imagen_marcada);

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.75)", justifyContent: "flex-end" }}>
        <View
          style={{
            backgroundColor: colors.bg,
            borderTopLeftRadius: 16,
            borderTopRightRadius: 16,
            padding: 16,
            maxHeight: "92%",
          }}
        >
          <View
            style={{
              flexDirection: "row",
              justifyContent: "space-between",
              alignItems: "center",
              marginBottom: 10,
            }}
          >
            <Text style={[styles.headerTitle, { fontSize: 20 }]}>DETALLE DEL DEFECTO</Text>
            <TouchableOpacity onPress={onClose} hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}>
              <Text style={{ color: colors.orange, fontSize: 22, fontWeight: "bold" }}>✕</Text>
            </TouchableOpacity>
          </View>

          <ScrollView>
            {/* Foto de dónde estaba el defecto:
                1) la foto capturada con la marca (imagen_marcada) si existe;
                2) si no, la vista del modelo con la marca calculada por posición. */}
            {marcadaSrc ? (
              <View
                style={{
                  borderWidth: 2,
                  borderColor: colors.green,
                  borderRadius: 8,
                  overflow: "hidden",
                  backgroundColor: "#fff",
                }}
              >
                <Image
                  source={{ uri: marcadaSrc }}
                  resizeMode="contain"
                  style={{ width: "100%", height: 360 }}
                />
              </View>
            ) : view ? (
              <FotoConMarcas view={view} defectos={[defecto]} />
            ) : (
              <Text style={[styles.cardMeta, { textAlign: "center" }]}>
                Sin foto disponible para esta vista.
              </Text>
            )}

            {/* Datos del defecto */}
            <View style={[styles.card, { marginTop: 12 }]}>
              <View style={{ flexDirection: "row", alignItems: "center", flexWrap: "wrap" }}>
                <Text style={styles.cardFalla}>
                  {defecto?.falla?.codigo} - {String(defecto?.falla?.nombre || "").toUpperCase()}
                </Text>
              </View>

              <Text style={styles.cardMeta}>
                TIPO: {defecto?.tipo?.tipo || "-"} {defecto?.lado?.lado || ""}   SEC: {defecto?.secuencia || "-"}
              </Text>
              {defecto?.observaciones ? (
                <Text style={styles.cardMeta}>OBS: {defecto.observaciones}</Text>
              ) : null}

              {/* Trazabilidad: quien lo marco */}
              <Text style={[styles.cardMeta, { color: colors.text, fontWeight: "bold", marginTop: 8 }]}>
                Marcado por: {defecto?.marcado_por_nombre || "-"}
                {defecto?.turno ? `  ·  Turno ${defecto.turno}` : ""}
              </Text>
              {defecto?.created_at ? (
                <Text style={styles.cardMeta}>Fecha: {defecto.created_at}</Text>
              ) : null}

              <View
                style={[
                  styles.estadoBanner,
                  { backgroundColor: reparado ? colors.green : colors.orange, marginTop: 10 },
                ]}
              >
                <Text style={styles.estadoText}>
                  {defecto?.estado}
                  {defecto?.reparado_por_nombre ? ` · ${defecto.reparado_por_nombre}` : ""}
                </Text>
              </View>
            </View>
          </ScrollView>

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
