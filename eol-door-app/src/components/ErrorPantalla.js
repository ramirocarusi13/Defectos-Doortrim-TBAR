import React from "react";
import { View, Text, TouchableOpacity, ScrollView } from "react-native";
import { colors } from "../styles";

// Si una pantalla falla, en vez de que la app se reinicie sola (y se pierda el
// usuario logueado sin explicacion) se muestra el error y un boton para volver.
export default class ErrorPantalla extends React.Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidCatch(error, info) {
    console.error("ErrorPantalla:", error?.message, info?.componentStack);
  }

  render() {
    if (!this.state.error) return this.props.children;
    return (
      <View style={{ flex: 1, backgroundColor: colors.bg, padding: 20, justifyContent: "center" }}>
        <Text style={{ color: colors.red, fontSize: 22, fontWeight: "bold", textAlign: "center" }}>
          Hubo un error en la pantalla
        </Text>
        <ScrollView style={{ maxHeight: 260, marginVertical: 16 }}>
          <Text style={{ color: colors.muted, fontSize: 14 }}>{String(this.state.error?.message || this.state.error)}</Text>
        </ScrollView>
        <TouchableOpacity
          onPress={() => this.setState({ error: null })}
          style={{ backgroundColor: colors.green, borderRadius: 8, paddingVertical: 16 }}
        >
          <Text style={{ color: "#000", fontWeight: "bold", fontSize: 18, textAlign: "center" }}>VOLVER</Text>
        </TouchableOpacity>
      </View>
    );
  }
}
