import React from "react";
import { Modal, View, Text, TouchableOpacity, ScrollView } from "react-native";
import { styles, colors } from "../styles";

// Modal para elegir el PUESTO de control de calidad de esta tablet.
// Se muestra obligatorio la primera vez (sin puesto guardado) y se puede
// reabrir despues desde el header para cambiarlo.
// puestos = [{ id, code, name, active }]
export default function PuestoSelectorModal({ visible, puestos, puestoActual, onElegir, onClose, obligatorio }) {
  const lista = (puestos || []).filter((p) => p.active !== false);

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={() => !obligatorio && onClose && onClose()}>
      <View style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.85)", justifyContent: "flex-end" }}>
        <View style={{ backgroundColor: colors.bg, borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 16, maxHeight: "85%" }}>
          <View style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginBottom: 4 }}>
            <Text style={[styles.headerTitle, { fontSize: 20, textAlign: "left" }]}>PUESTO DE ESTA TABLET</Text>
            {!obligatorio ? (
              <TouchableOpacity onPress={onClose} hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}>
                <Text style={{ color: colors.orange, fontSize: 22, fontWeight: "bold" }}>✕</Text>
              </TouchableOpacity>
            ) : null}
          </View>
          <Text style={{ color: colors.muted, fontSize: 14, marginBottom: 14 }}>
            {obligatorio
              ? "Elegí el puesto de control de calidad que corresponde a esta tablet. Se guarda en el dispositivo."
              : "Tocá el puesto correcto para esta tablet."}
          </Text>

          <ScrollView>
            {lista.length === 0 ? (
              <Text style={styles.cardMeta}>No hay puestos configurados en /config.</Text>
            ) : (
              lista.map((p) => {
                const sel = puestoActual?.id === p.id;
                return (
                  <TouchableOpacity
                    key={p.id}
                    onPress={() => onElegir && onElegir(p)}
                    style={{
                      paddingVertical: 18,
                      paddingHorizontal: 16,
                      borderRadius: 8,
                      borderWidth: 2,
                      marginBottom: 10,
                      borderColor: sel ? colors.green : "#404040",
                      backgroundColor: sel ? colors.green : colors.card,
                    }}
                  >
                    <Text style={{ color: sel ? "#000" : colors.text, fontWeight: "bold", fontSize: 18 }}>
                      {p.name || p.code}
                    </Text>
                  </TouchableOpacity>
                );
              })
            )}
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}
