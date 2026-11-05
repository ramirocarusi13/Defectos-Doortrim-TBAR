import React from "react";
import { Modal, View, Text, TouchableOpacity, ScrollView } from "react-native";
import { styles, colors } from "../styles";

// Modal para elegir el MINI BUFFER de esta tablet (mismo patron que
// PuestoSelectorModal). Se muestra obligatorio la primera vez y se puede
// reabrir despues desde el header para cambiarlo.
// buffers = [{ id, name, active }]. onReintentar (opcional): recarga la
// config desde el server si todavia no hay buffers dados de alta, para que
// la tablet no quede sin salida en el primer arranque.
export default function BufferSelectorModal({ visible, buffers, bufferActual, onElegir, onClose, obligatorio, onReintentar }) {
  const lista = (buffers || []).filter((b) => b.active !== false);

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={() => onClose && onClose()}>
      <View style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.85)", justifyContent: "flex-end" }}>
        <View style={{ backgroundColor: colors.bg, borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 16, maxHeight: "85%" }}>
          <View style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginBottom: 4 }}>
            <Text style={[styles.headerTitle, { fontSize: 20, textAlign: "left" }]}>BUFFER DE ESTA TABLET</Text>
            {onClose ? (
              <TouchableOpacity onPress={onClose} hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}>
                <Text style={{ color: colors.orange, fontSize: 22, fontWeight: "bold" }}>✕</Text>
              </TouchableOpacity>
            ) : null}
          </View>
          <Text style={{ color: colors.muted, fontSize: 14, marginBottom: 14 }}>
            {obligatorio
              ? "Elegí el mini buffer que corresponde a esta tablet. Se guarda en el dispositivo."
              : "Tocá el buffer correcto para esta tablet."}
          </Text>

          <ScrollView>
            {lista.length === 0 ? (
              <View>
                <Text style={styles.cardMeta}>No hay buffers configurados en /config.</Text>
                {obligatorio && onReintentar ? (
                  <TouchableOpacity
                    onPress={onReintentar}
                    style={[styles.bigButton, { backgroundColor: colors.blue, marginTop: 14 }]}
                  >
                    <Text style={styles.bigButtonText}>REINTENTAR</Text>
                  </TouchableOpacity>
                ) : null}
              </View>
            ) : (
              lista.map((b) => {
                const sel = bufferActual?.id === b.id;
                return (
                  <TouchableOpacity
                    key={b.id}
                    onPress={() => onElegir && onElegir(b)}
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
                      {b.name || b.code}
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
