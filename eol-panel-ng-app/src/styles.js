import { StyleSheet } from "react-native";

// Paleta alineada con la pantalla de retrabajo de la web:
// fondo oscuro, header naranja, tarjetas con borde gris, botones grandes.
export const colors = {
  bg: "#0a0a0a",
  header: "#9a3412", // orange-800
  card: "#1c1917",
  border: "#9ca3af",
  text: "#ffffff",
  muted: "#d1d5db",
  green: "#22c55e",
  red: "#ef4444",
  yellow: "#facc15",
  cyan: "#22d3ee",
  blue: "#2563eb",
  orange: "#fb923c",
};

export const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },

  header: {
    backgroundColor: colors.header,
    paddingVertical: 14,
    paddingHorizontal: 12,
  },
  headerTitle: {
    color: colors.text,
    fontSize: 26,
    fontWeight: "bold",
    textAlign: "center",
  },

  body: { flex: 1, padding: 12 },

  label: { color: colors.muted, fontSize: 16, marginBottom: 6, fontWeight: "600" },

  input: {
    backgroundColor: "#262626",
    color: colors.text,
    fontSize: 22,
    paddingVertical: 14,
    paddingHorizontal: 14,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: "#404040",
  },

  bigButton: {
    paddingVertical: 18,
    borderRadius: 6,
    alignItems: "center",
    justifyContent: "center",
  },
  bigButtonText: { color: "#000", fontSize: 22, fontWeight: "bold" },

  hint: { color: colors.muted, fontSize: 14, textAlign: "center", marginTop: 16 },
  emptyText: {
    color: colors.orange,
    fontSize: 26,
    fontWeight: "bold",
    textAlign: "center",
    marginTop: 40,
    paddingHorizontal: 20,
  },

  // tarjeta de defecto
  card: {
    backgroundColor: colors.card,
    borderWidth: 2,
    borderColor: colors.border,
    borderRadius: 8,
    padding: 12,
    marginBottom: 12,
  },
  cardModel: { color: colors.text, fontSize: 22, fontWeight: "bold" },
  cardFalla: { color: colors.text, fontSize: 19, fontWeight: "bold", marginTop: 2 },
  cardMeta: { color: colors.muted, fontSize: 15, marginTop: 4 },

  badge: {
    alignSelf: "flex-start",
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
    marginTop: 6,
  },
  badgeText: { color: "#000", fontWeight: "bold", fontSize: 13 },

  estadoBanner: { paddingVertical: 10, borderRadius: 6, marginTop: 10, alignItems: "center" },
  estadoText: { color: "#000", fontSize: 22, fontWeight: "bold" },

  topbar: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 10,
  },
  userTag: { color: colors.text, fontSize: 14, fontWeight: "600" },
});
