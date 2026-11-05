import React, { useEffect, useState } from "react";
import { SafeAreaView, View, Text, TouchableOpacity, Platform, StatusBar as RNStatusBar, ActivityIndicator } from "react-native";
import { StatusBar } from "expo-status-bar";
import AsyncStorage from "@react-native-async-storage/async-storage";
import SplashTbar from "./src/components/SplashTbar";
import ErrorPantalla from "./src/components/ErrorPantalla";
import { styles, colors } from "./src/styles";
import { getPuestosNg } from "./src/api";
import PantallaPanelNg from "./src/PantallaPanelNg";

// PANEL NG: app del puesto de produccion que reetiqueta paneles (Produccion 4 / 3).
// Usa la misma API que DOORTRIM (servidor .150) pero no toca nada de door.
// El puesto se elige una vez por equipo y queda guardado; para cambiarlo, mantener
// apretado 3 s el titulo.
const TOP_ANDROID = Platform.OS === "android" ? RNStatusBar.currentHeight || 0 : 0;
const KEY_PUESTO = "panelng_puesto";
const PUESTOS_FIJOS = [
  { puesto: "PRODUCCION 4", impresora: "" },
  { puesto: "PRODUCCION 3", impresora: "" },
];

export default function App() {
  const [splash, setSplash] = useState(true);
  const [puesto, setPuesto] = useState(null);
  const [puestos, setPuestos] = useState(PUESTOS_FIJOS);
  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    const t = setTimeout(() => setSplash(false), 1500);
    (async () => {
      try {
        const g = await AsyncStorage.getItem(KEY_PUESTO);
        if (g) setPuesto(g);
      } catch (e) {
        /* sin almacenamiento: se elige de nuevo */
      }
      const r = await getPuestosNg();
      if (!r?.error && Array.isArray(r?.data) && r.data.length) setPuestos(r.data);
      setCargando(false);
    })();
    return () => clearTimeout(t);
  }, []);

  const elegir = async (p) => {
    setPuesto(p);
    try {
      await AsyncStorage.setItem(KEY_PUESTO, p);
    } catch (e) {
      /* vale hasta cerrar la app */
    }
  };
  const cambiarPuesto = async () => {
    setPuesto(null);
    try {
      await AsyncStorage.removeItem(KEY_PUESTO);
    } catch (e) {
      /* nada */
    }
  };

  if (splash) return <SplashTbar />;

  if (!puesto) {
    return (
      <SafeAreaView style={[styles.screen, { paddingTop: TOP_ANDROID }]}>
        <StatusBar style="light" />
        <View style={styles.header}>
          <Text style={styles.headerTitle}>PANEL NG</Text>
        </View>
        <View style={[styles.body, { justifyContent: "center" }]}>
          <Text style={[styles.label, { textAlign: "center", fontSize: 20, marginBottom: 20 }]}>¿Qué puesto es este equipo?</Text>
          {cargando ? <ActivityIndicator color={colors.green} size="large" style={{ marginBottom: 16 }} /> : null}
          {puestos.map((p) => (
            <TouchableOpacity key={p.puesto} onPress={() => elegir(p.puesto)} style={[styles.bigButton, { backgroundColor: colors.blue, marginBottom: 14, paddingVertical: 24 }]}>
              <Text style={[styles.bigButtonText, { color: "#fff", fontSize: 26 }]}>{p.puesto}</Text>
              {p.impresora ? <Text style={{ color: "#dbeafe", fontSize: 14, marginTop: 4 }}>Impresora {p.impresora}</Text> : null}
            </TouchableOpacity>
          ))}
        </View>
      </SafeAreaView>
    );
  }

  const impresora = puestos.find((p) => p.puesto === puesto)?.impresora || "";
  return (
    <SafeAreaView style={[styles.screen, { flex: 1, paddingTop: TOP_ANDROID }]}>
      <View style={{ flex: 1 }}>
        <ErrorPantalla>
          <PantallaPanelNg puesto={puesto} impresora={impresora} onCambiarPuesto={cambiarPuesto} />
        </ErrorPantalla>
      </View>
    </SafeAreaView>
  );
}
