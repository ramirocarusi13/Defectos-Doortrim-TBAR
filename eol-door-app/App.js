import React, { useEffect, useRef, useState } from "react";
import {
  SafeAreaView,
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  StatusBar as RNStatusBar,
  Linking,
  Alert,
  Modal,
} from "react-native";

// expo-status-bar deja la barra de Android translucida: sin este margen las
// pestanas de arriba quedan tapadas por la hora y los iconos.
const TOP_ANDROID = Platform.OS === "android" ? RNStatusBar.currentHeight || 0 : 0;
import SplashTbar from "./src/components/SplashTbar";
import { StatusBar } from "expo-status-bar";
import { styles, colors } from "./src/styles";
import { validarUsuario, getFactoryConfig, cargarServidorGuardado, guardarServidor } from "./src/api";
import { getApiBase } from "./src/config";
import { esCalidad, esCapacitacion } from "./src/utils";
import PantallaDoorTrim from "./src/PantallaDoorTrim";
import PantallaBuffer from "./src/PantallaBuffer";
import PantallaRetrabajoDoor from "./src/PantallaRetrabajoDoor";
import ErrorPantalla from "./src/components/ErrorPantalla";
import { getSesionGuardada, guardarSesion, borrarSesion } from "./src/sesion";

// Usuario del puesto de Calidad cuando la tablet no tiene login (el nombre real de
// quien carga viene de tblDoorMain.usuario).
const USUARIO_PUESTO = { id: null, name: "CALIDAD DOORTRIM", departamento: "CALIDAD" };

export default function App() {
  const [user, setUser] = useState(null);
  const [codigo, setCodigo] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [config, setConfig] = useState(null);
  const [splash, setSplash] = useState(true);
  // Servidor escrito a mano (mantener 3 s el titulo del login).
  const [showServidor, setShowServidor] = useState(false);
  const [servidorTxt, setServidorTxt] = useState("");
  const [servidorMsg, setServidorMsg] = useState(null);
  const [probandoServidor, setProbandoServidor] = useState(false);

  // La app NO pregunta nada al abrir: el modo sale solo del usuario que loguea
  // (CALIDAD marca defectos; el resto arranca en el cambio de pieza) y las
  // pestañas del tope permiten pasar a BUFFER cuando haga falta.
  const [modo, setModo] = useState("DOOR"); // 'CALIDAD' | 'DOOR' | 'BUFFER'

  const codigoRef = useRef(null);
  const scanTimer = useRef(null);

  // Sin login: la tablet arranca en la pantalla de Calidad del puesto. Quien carga
  // el defecto sale de tblDoorMain.usuario. Produccion (retrabajo) entra con su
  // codigo desde "Producción ›".
  const [conLogin, setConLogin] = useState(false);

  // Splash de arranque: logo TBAR + loading durante 2 segundos.
  useEffect(() => {
    const t = setTimeout(() => setSplash(false), 2000);
    borrarSesion();
    cargarServidorGuardado().then((v) => {
      setServidorTxt(v || "");
      getFactoryConfig().then((c) => {
        if (c?.data) setConfig(c.data);
      });
    });
    return () => clearTimeout(t);
  }, []);

  useEffect(() => {
    if (user) {
      setTimeout(() => codigoRef.current?.focus(), 100);
    }
  }, [user]);

  if (splash) {
    return <SplashTbar />;
  }

  const login = async (codeArg) => {
    const code = String(codeArg ?? codigo).trim();
    if (!code || loading) return;
    setLoading(true);
    setError(null);
    const res = await validarUsuario(code);
    setLoading(false);

    if (res?.error || !res?.data) {
      setError(res?.message || "Código de autorización inválido");
      return;
    }
    setUser(res.data);
    guardarSesion(res.data);
    // Calidad arranca marcando defectos; produccion arranca en RETRABAJO.
    setModo("RETRABAJO");
    setCodigo("");
    // Cargamos la factory-config (modelos/fotos/defectos/puestos/buffers) para
    // mostrar lo mismo que la web. Se cachea en estado; si falla, se reintenta
    // dentro de cada pantalla.
    getFactoryConfig().then((c) => {
      if (c?.data) setConfig(c.data);
    });
  };

  // Pantalla de Wi-Fi de Android, para cambiar de red sin salir del kiosco (igual
  // que EOL Defectos). Requiere en la config EHS apps_enabled con Ajustes y
  // system_settings_restricted=0 (kiosco-zebra/enterprisehomescreen-doortrim.xml).
  const abrirWifi = async () => {
    try {
      await Linking.sendIntent("android.settings.WIFI_SETTINGS");
    } catch (e) {
      Alert.alert("Wi-Fi", "No se pudo abrir la configuración de Wi-Fi en este equipo.");
    }
  };

  const probarServidor = async () => {
    setProbandoServidor(true);
    setServidorMsg(null);
    const ok = await guardarServidor(servidorTxt);
    setProbandoServidor(false);
    setServidorMsg(ok ? `Conectado a ${getApiBase()}` : "No responde ningún servidor. Revisá la IP y que la PC esté en la misma red con el .bat abierto.");
    if (ok) setTimeout(() => setShowServidor(false), 1200);
  };

  const logout = () => {
    borrarSesion();
    setUser(null);
    setCodigo("");
    setError(null);
    setConLogin(false); // vuelve a la pantalla de Calidad sin login
  };

  const onCodigoChange = (text) => {
    setCodigo(text);
    if (scanTimer.current) clearTimeout(scanTimer.current);
    const v = String(text || "").trim();
    if (v.length >= 4) {
      scanTimer.current = setTimeout(() => login(v), 350);
    }
  };

  // ---------- LOGIN ----------
  // ---------- CALIDAD SIN LOGIN (pantalla por defecto del puesto) ----------
  if (!user && !conLogin) {
    return (
      <SafeAreaView style={[styles.screen, { flex: 1, paddingTop: TOP_ANDROID }]}>
        <View style={{ flex: 1 }}>
          <ErrorPantalla>
            <PantallaDoorTrim
              user={USUARIO_PUESTO}
              config={config}
              onLogout={() => {
                setError(null);
                setConLogin(true);
              }}
              textoLogout="Producción ›"
            />
          </ErrorPantalla>
        </View>
      </SafeAreaView>
    );
  }

  if (!user) {
    return (
      <SafeAreaView style={[styles.screen, { paddingTop: TOP_ANDROID }]}>
        <StatusBar style="light" />
        <TouchableOpacity
          activeOpacity={1}
          focusable={false}
          delayLongPress={3000}
          onLongPress={() => {
            setServidorMsg(null);
            setShowServidor(true);
          }}
          style={styles.header}
        >
          <Text style={styles.headerTitle}>DOORTRIM DEFECTOS</Text>
        </TouchableOpacity>

        {/* IP del servidor (una PC en la misma red con INICIAR-DOORTRIM-LOCAL.bat). */}
        <Modal visible={showServidor} transparent animationType="fade" onRequestClose={() => setShowServidor(false)}>
          <View style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.7)", justifyContent: "center", padding: 20 }}>
            <View style={{ backgroundColor: "#1f2937", borderRadius: 12, padding: 20 }}>
              <Text style={{ color: "#fff", fontSize: 20, fontWeight: "bold", marginBottom: 6 }}>Servidor</Text>
              <Text style={{ color: "#d1d5db", fontSize: 15, marginBottom: 12 }}>
                IP de la PC con el backend (la muestra INICIAR-DOORTRIM-LOCAL.bat). Vacío = automático.
              </Text>
              <TextInput
                style={styles.input}
                value={servidorTxt}
                onChangeText={setServidorTxt}
                placeholder="ej. 172.17.130.50"
                placeholderTextColor="#9ca3af"
                autoCapitalize="none"
                keyboardType="url"
                onSubmitEditing={probarServidor}
              />
              <Text style={{ color: "#9ca3af", fontSize: 13, marginTop: 8 }}>Ahora: {getApiBase()}</Text>
              {servidorMsg ? <Text style={{ color: "#fbbf24", fontSize: 15, marginTop: 8 }}>{servidorMsg}</Text> : null}
              <View style={{ flexDirection: "row", marginTop: 16 }}>
                <TouchableOpacity
                  onPress={() => setShowServidor(false)}
                  style={[styles.bigButton, { flex: 1, backgroundColor: "#374151", marginRight: 8 }]}
                >
                  <Text style={[styles.bigButtonText, { color: "#fff" }]}>CERRAR</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  onPress={probarServidor}
                  disabled={probandoServidor}
                  style={[styles.bigButton, { flex: 1, backgroundColor: colors.green }]}
                >
                  {probandoServidor ? <ActivityIndicator color="#000" /> : <Text style={styles.bigButtonText}>GUARDAR</Text>}
                </TouchableOpacity>
              </View>
            </View>
          </View>
        </Modal>

        <KeyboardAvoidingView behavior={Platform.OS === "ios" ? "padding" : undefined} style={styles.body}>
          <Text style={styles.label}>Ingrese el código de autorización</Text>
          <TextInput
            ref={codigoRef}
            style={styles.input}
            value={codigo}
            onChangeText={onCodigoChange}
            placeholder="Código de autorización"
            placeholderTextColor="#9ca3af"
            autoFocus
            secureTextEntry
            onSubmitEditing={(e) => login(e.nativeEvent.text)}
            returnKeyType="go"
          />
          <TouchableOpacity
            style={[styles.bigButton, { backgroundColor: colors.green, marginTop: 16 }]}
            onPress={() => login()}
            disabled={loading}
          >
            {loading ? <ActivityIndicator color="#000" /> : <Text style={styles.bigButtonText}>ENTRAR</Text>}
          </TouchableOpacity>
          {error ? <Text style={[styles.hint, { color: colors.red }]}>{error}</Text> : null}
          <TouchableOpacity onPress={() => setConLogin(false)} focusable={false} style={{ alignSelf: "center", padding: 14 }}>
            <Text style={{ color: colors.orange, fontWeight: "bold", fontSize: 16 }}>‹ VOLVER A CALIDAD</Text>
          </TouchableOpacity>
        </KeyboardAvoidingView>

        {/* Cambiar de Wi-Fi: MANTENER APRETADO 3s el engranaje (el tap corto no hace
            nada, para que nadie lo dispare sin querer). */}
        <TouchableOpacity
          onLongPress={abrirWifi}
          delayLongPress={3000}
          focusable={false}
          style={{ position: "absolute", bottom: 10, right: 14, padding: 10, opacity: 0.45 }}
        >
          <Text style={{ color: "#9ca3af", fontSize: 22 }}>⚙</Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  // ---------- PANTALLA SEGUN EL USUARIO (sin pestañas) ----------
  //   CALIDAD (y el usuario de practica 9005): escanea el door trim; si ya tiene
  //     defectos los ve y valida lo que volvio de produccion; si no, marca.
  //   PRODUCCION: va directo al retrabajo (retrabajar o cambiar la pieza). El
  //     buffer se abre con un boton desde esa pantalla.
  let pantalla;
  if (esCalidad(user) || esCapacitacion(user)) {
    pantalla = <PantallaDoorTrim user={user} config={config} onLogout={logout} />;
  } else if (modo === "BUFFER") {
    pantalla = (
      <View style={{ flex: 1 }}>
        <TouchableOpacity onPress={() => setModo("RETRABAJO")} style={{ backgroundColor: "#111", paddingVertical: 12, paddingHorizontal: 16 }}>
          <Text style={{ color: colors.orange, fontWeight: "bold", fontSize: 16 }}>‹ VOLVER AL RETRABAJO</Text>
        </TouchableOpacity>
        <View style={{ flex: 1 }}>
          <PantallaBuffer user={user} config={config} onLogout={logout} />
        </View>
      </View>
    );
  } else {
    pantalla = <PantallaRetrabajoDoor user={user} config={config} onLogout={logout} onAbrirBuffer={() => setModo("BUFFER")} />;
  }

  return (
    <SafeAreaView style={[styles.screen, { flex: 1, paddingTop: TOP_ANDROID }]}>
      <View style={{ flex: 1 }}>
        <ErrorPantalla>{pantalla}</ErrorPantalla>
      </View>
    </SafeAreaView>
  );
}
