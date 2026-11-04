import React from "react";
import { View, Text, TouchableOpacity } from "react-native";
import { styles, colors } from "../styles";

const ESTADO_PENDIENTE = "PENDIENTE";

// Tarjeta de defecto. En esta app (línea DOOR) NO se retrabaja en línea,
// asi que por defecto es de SOLO INFO: no muestra boton de accion, solo el
// estado. Si se pasa onSeleccionar, funciona como checkbox (usado en el
// wizard de cambio para elegir que defectos resuelve el cambio). Tocar la
// tarjeta abre el modal de detalle (foto + datos del defecto).
export default function DefectoCard({ defecto, onVerInfo, onSeleccionar, seleccionado, readOnly = true }) {
  const pendiente = defecto?.estado === ESTADO_PENDIENTE;

  return (
    <TouchableOpacity
      activeOpacity={0.7}
      onPress={() => (onSeleccionar ? onSeleccionar(defecto) : onVerInfo?.(defecto))}
      style={[
        styles.card,
        onSeleccionar ? { borderColor: seleccionado ? colors.green : colors.border } : null,
      ]}
    >
      <View style={{ flexDirection: "row", alignItems: "flex-start", justifyContent: "space-between" }}>
        <Text style={styles.cardModel}>
          {defecto?.modelo || defecto?.etiqueta?.modelod?.nombre || "-"}
        </Text>
        {onSeleccionar ? (
          <View
            style={{
              width: 26,
              height: 26,
              borderRadius: 6,
              borderWidth: 2,
              borderColor: seleccionado ? colors.green : colors.border,
              backgroundColor: seleccionado ? colors.green : "transparent",
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            {seleccionado ? <Text style={{ color: "#000", fontWeight: "bold" }}>✓</Text> : null}
          </View>
        ) : null}
      </View>

      <View style={{ flexDirection: "row", alignItems: "center", flexWrap: "wrap" }}>
        <Text style={styles.cardFalla}>
          {defecto?.falla?.codigo} - {String(defecto?.falla?.nombre || "").toUpperCase()}
        </Text>
      </View>

      <Text style={styles.cardMeta}>
        TIPO: {defecto?.tipo?.tipo || "-"}  {defecto?.lado?.lado || ""}   SEC: {defecto?.secuencia || "-"}
      </Text>
      {defecto?.observaciones ? (
        <Text style={styles.cardMeta}>OBS: {defecto.observaciones}</Text>
      ) : null}
      <Text style={styles.cardMeta}>Marcado por: {defecto?.marcado_por_nombre || "-"}</Text>

      {!readOnly ? null : (
        <View
          style={[
            styles.estadoBanner,
            { backgroundColor: defecto?.estado === "RECHAZADO" ? colors.red : pendiente ? colors.orange : colors.green },
          ]}
        >
          <Text style={styles.estadoText}>
            {defecto?.estado}
            {defecto?.reparado_por_nombre ? ` · ${defecto.reparado_por_nombre}` : ""}
          </Text>
        </View>
      )}

      {onVerInfo && onSeleccionar ? (
        <TouchableOpacity onPress={() => onVerInfo(defecto)} style={{ marginTop: 8 }} hitSlop={{ top: 6, bottom: 6, left: 6, right: 6 }}>
          <Text style={{ color: colors.blue, fontWeight: "bold", fontSize: 13 }}>VER FOTO / DETALLE ›</Text>
        </TouchableOpacity>
      ) : null}
    </TouchableOpacity>
  );
}
