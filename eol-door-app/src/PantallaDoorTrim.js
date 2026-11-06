import React, { useEffect, useRef, useState } from "react";
import {
  SafeAreaView,
  View,
  Text,
  TextInput,
  TouchableOpacity,
  ScrollView,
  Alert,
  ActivityIndicator,
  Modal,
  Keyboard,
  useWindowDimensions,
} from "react-native";
import { StatusBar } from "expo-status-bar";import { styles, colors } from "./styles";
import {
  interpretarQrDoor,
  getControlFinal,
  getFactoryConfig,
  getFactoryConfigVersion,
  marcarRetrabajo,
  getDefectosDoorTrim,
  getRetrabajosDoor,
  getValidacionesDoor,
  validarDefectoDoor,
} from "./api";
import { vistasDoorTrim } from "./factoryConfig";
import { FAMILIA_A_TIPO, modeloPorCodigo, ladoDeModelo, piezasPara, tipoLabel } from "./doortrim";
import { FotoConMarcas } from "./components/AsientoViewer";
import DefectoInfoModal from "./components/DefectoInfoModal";

// Pantalla de CALIDAD de la linea de door trims: solo MARCA el defecto.
//   1. escanear el door trim (el QR dice si es panel, arm rest o garnish)
//   2. (solo arm rest) elegir con o sin cover
//   3. marcar el defecto sobre la foto, con la lista de defectos de esa pieza
//   4. GUARDAR: el defecto queda PENDIENTE para produccion, que lo retrabaja o
//      cambia la pieza del buffer.
// Igual que en asientos: si el door trim escaneado ya tiene defectos, se muestran
// primero. Los que produccion ya resolvio (retrabajo o cambio de pieza) se
// VALIDAN o RECHAZAN desde ahi.
const STEP_SET = "SET";
const STEP_PIEZA = "PIEZA";
const STEP_MARCAR = "MARCAR";

const PALETTE = ["F59E0B", "EF4444", "22C55E", "2563EB", "A855F7", "EC4899", "14B8A6", "F97316"];

export default function PantallaDoorTrim({ user, config: configProp, onLogout, textoLogout }) {
  const [config, setConfig] = useState(configProp || null);
  const [step, setStep] = useState(STEP_SET);
  const [qr, setQr] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  // Door trim escaneado + fotos del modelo
  const [setInfo, setSetInfo] = useState(null);
  const [views, setViews] = useState([]);
  const [viewIdx, setViewIdx] = useState(0);
  // La app gira: en horizontal la pantalla de marcar pone la foto a la izquierda.
  const ventana = useWindowDimensions();
  const horizontal = ventana.width > ventana.height;

  // Catalogo de defectos por pieza y pieza elegida
  const [catalogo, setCatalogo] = useState(null);
  const [errorCatalogo, setErrorCatalogo] = useState(null);
  const [pieza, setPieza] = useState(null);

  // Marcado
  const [circles, setCircles] = useState([]);
  const [fallaSel, setFallaSel] = useState(null);
  const [showFallas, setShowFallas] = useState(false);
  const [catSel, setCatSel] = useState(null);
  const [guardando, setGuardando] = useState(false);
  const [exito, setExito] = useState(null);

  // Defectos que ya tiene el door trim escaneado (pendientes y para validar).
  const [existentes, setExistentes] = useState([]);
  const [showExistentes, setShowExistentes] = useState(false);
  const [verDefecto, setVerDefecto] = useState(null);
  const [busyId, setBusyId] = useState(null);

  // Plan B cuando el QR del door trim no se resuelve: elegir el modelo a mano.
  const [qrSinModelo, setQrSinModelo] = useState(null);
  const [showModelos, setShowModelos] = useState(false);
  const [busquedaModelo, setBusquedaModelo] = useState("");

  const qrRef = useRef(null);
  const scanTimer = useRef(null);
  const focusTimer = useRef(null);
  const resetTimer = useRef(null);

  const focusQr = (delay = 150) => {
    if (focusTimer.current) clearTimeout(focusTimer.current);
    focusTimer.current = setTimeout(() => qrRef.current?.focus(), delay);
  };

  const cargarCatalogo = async () => {
    const r = await getDefectosDoorTrim();
    if (r?.data?.piezas?.length) {
      setCatalogo(r.data);
      setErrorCatalogo(null);
      return r.data;
    }
    setErrorCatalogo(r?.message || "No se pudo cargar la lista de defectos de door trim.");
    return null;
  };

  useEffect(() => {
    if (!config) getFactoryConfig().then((c) => c?.data && setConfig(c.data));
    cargarCatalogo();
    return () => {
      [scanTimer, focusTimer, resetTimer].forEach((t) => t.current && clearTimeout(t.current));
    };
  }, []);

  useEffect(() => {
    if (configProp) setConfig(configProp);
  }, [configProp]);

  // Refresco en vivo de la config (fotos/modelos) sin reinstalar el APK.
  const configVersionRef = useRef(null);
  useEffect(() => {
    let activo = true;
    const chequear = async () => {
      try {
        const v = (await getFactoryConfigVersion())?.data?.version;
        if (v == null) return;
        if (configVersionRef.current == null) {
          configVersionRef.current = v;
          return;
        }
        if (v !== configVersionRef.current) {
          const c = await getFactoryConfig();
          if (activo && c?.data) {
            setConfig(c.data);
            configVersionRef.current = v;
          }
        }
      } catch (e) {
        /* red intermitente: reintenta al proximo tick */
      }
    };
    const t = setInterval(chequear, 10000);
    return () => {
      activo = false;
      clearInterval(t);
    };
  }, []);

  useEffect(() => {
    if (step === STEP_SET) focusQr();
  }, [step]);

  const modelosFiltrados = (() => {
    const q = busquedaModelo.trim().toUpperCase();
    const activos = (config?.models || []).filter((m) => m.active !== false);
    const lista = q
      ? activos.filter((m) => `${m.code || ""} ${m.name || ""} ${m.jitms?.sd || ""}`.toUpperCase().includes(q))
      : activos;
    return lista.slice(0, 60);
  })();

  // Defectos de la pieza elegida, agrupados como en la planilla.
  const defectosPieza = pieza?.defectos || [];
  const categorias = (catalogo?.grupos || []).filter((g) => defectosPieza.some((d) => d.grupo === g.id));
  useEffect(() => {
    if (categorias.length > 0 && !categorias.some((c) => c.id === catSel?.id)) setCatSel(categorias[0]);
  }, [pieza?.id, catalogo]);
  const defectosFiltrados = catSel ? defectosPieza.filter((d) => d.grupo === catSel.id) : defectosPieza;
  const piezasCandidatas = piezasPara(catalogo, setInfo?.familia);
  const colorGrupo = (grupoId) => (catalogo?.grupos || []).find((g) => g.id === grupoId)?.color;

  const cargarExistentes = async (qrSet) => {
    if (!qrSet) return [];
    const [pend, val] = await Promise.all([getRetrabajosDoor(qrSet), getValidacionesDoor(qrSet)]);
    const lista = [...(Array.isArray(val?.data) ? val.data : []), ...(Array.isArray(pend?.data) ? pend.data : [])];
    setExistentes(lista);
    return lista;
  };

  const resolverDefecto = (d, accion) => {
    const ejecutar = async () => {
      setBusyId(d.id);
      const res = await validarDefectoDoor(d.id, { user: user?.id, accion });
      setBusyId(null);
      if (res?.error) Alert.alert("Error", res?.message || "No se pudo registrar.");
      cargarExistentes(setInfo?.qr);
    };
    if (accion === "validar") return ejecutar();
    Alert.alert("Rechazar", "¿Rechazar " + (d?.falla?.codigo || "") + " - " + (d?.falla?.nombre || "") + "? Vuelve a producción.", [
      { text: "Cancelar", style: "cancel" },
      { text: "Sí, rechazar", style: "destructive", onPress: ejecutar },
    ]);
  };

  const reset = () => {
    setStep(STEP_SET);
    setExistentes([]);
    setShowExistentes(false);
    setQr("");
    setError(null);
    setSetInfo(null);
    setViews([]);
    setViewIdx(0);
    setCircles([]);
    setFallaSel(null);
    setPieza(null);
    setQrSinModelo(null);
    focusQr();
  };

  const elegirPieza = (p) => {
    if (pieza && pieza.id !== p.id) {
      setCircles([]);
      setFallaSel(null);
    }
    setPieza(p);
    setStep(STEP_MARCAR);
  };

  // ---------- 1. DOOR TRIM ----------
  const buscarSet = async (value) => {
    setLoading(true);
    setError(null);
    let cfg = config;
    if (!cfg) {
      const c = await getFactoryConfig();
      cfg = c?.data || null;
      if (cfg) setConfig(cfg);
    }
    // El QR lo genera la app de camaras: dice que pieza es (panel / armrest /
    // garnish), su SD, mano y posicion. No se consulta JITMS.
    const res = await interpretarQrDoor(value);
    setLoading(false);
    const q = res?.error ? null : res?.data || null;
    if (!q) {
      setQrSinModelo(value);
      setError(res?.message || "Ese QR no es de una pieza de door trim.");
      // Puede ser una pieza del buffer que reemplazo a otra: se muestra igual.
      setSetInfo({ qr: value });
      const lista = await cargarExistentes(value);
      if (lista.length > 0) setShowExistentes(true);
      return;
    }
    // Numero de parte exacto si /config lo tiene. El garnish no trae mano: se
    // muestra su SD (GDC), sin lado. Un SD sin cargar en /config queda con el SD.
    const codigo = q.codigo || null;
    await abrirSet(
      {
        qr: q.qr || value,
        // Se muestra y se guarda lo que dice la etiqueta (D1H FR LH); codigo es
        // el modelo que da la foto (puede ser un SD parecido: D1G FR LH).
        modelo: q.mostrar || codigo || q.sd,
        codigo,
        sd: q.sd,
        lado: q.mano || null,
        posicion: q.posicion,
        secuencia: q.secuencia,
        familia: q.familia,
      },
      cfg
    );
    const lista = await cargarExistentes(value);
    if (lista.length > 0) setShowExistentes(true);
  };

  const elegirModelo = async (m) => {
    const qrSet = qrSinModelo;
    await abrirSet({ modelo: m.code || m.name, codigo: m.code, lado: ladoDeModelo(m) || null, familia: m.jitms?.familia, qr: qrSet });
    const lista = await cargarExistentes(qrSet);
    if (lista.length > 0) setShowExistentes(true);
  };

  const abrirSet = async (d, cfg = config) => {
    setQrSinModelo(null);
    setShowModelos(false);
    setError(null);
    // Las vistas vienen en el orden de /config (importar-fotos-doortrim.php deja
    // Front A como 1/6) y siempre se arranca en la primera.
    Keyboard.dismiss();
    const vistas = vistasDoorTrim(d, cfg);
    setSetInfo(d);
    setViews(vistas);
    setViewIdx(0);
    const cat = catalogo || (await cargarCatalogo());
    const candidatas = piezasPara(cat, d.familia);
    // Panel o garnish: ya se sabe la pieza, se pasa directo a marcar el defecto.
    if (candidatas.length === 1) elegirPieza(candidatas[0]);
    else setStep(STEP_PIEZA);
  };

  const procesarScan = (raw) => {
    const value = String(raw || "").trim();
    if (!value || loading) return;
    if (scanTimer.current) clearTimeout(scanTimer.current);
    setQr("");
    if (step === STEP_SET) buscarSet(value);
  };

  const onScanChange = (text) => {
    setQr(text);
    if (scanTimer.current) clearTimeout(scanTimer.current);
    const v = String(text || "").trim();
    // 3: el QR del garnish es solo el SD (ej. GDC).
    if (v.length >= 3) scanTimer.current = setTimeout(() => procesarScan(v), 300);
  };

  // ---------- CONTROL FINAL (sin escanear) ----------
  // El puesto de control final deja el QR de la pieza en TB-PLC
  // (tblDoorControlFinal, una fila por tipo). Mientras la tablet espera, se mira
  // cada 2 s y, cuando aparece un QR que todavia no se abrio, se abre solo para
  // cargar el defecto. Escanear a mano sigue andando igual.
  // El puesto va PISANDO el QR de esa fila con cada pieza que escanea: tambien con
  // una pieza abierta se sigue mirando. Si llega otro QR y no hay marcas sin
  // cargar, se pasa directo a la pieza nueva (cambia la foto). Si hay marcas, no se
  // pierden: aparece un aviso y se pasa a la nueva al cargar o cancelar.
  const buscarSetRef = useRef(null);
  buscarSetRef.current = buscarSet;
  const resetRef = useRef(null);
  resetRef.current = reset;
  const estadoRef = useRef({ step, marcas: 0 });
  estadoRef.current = { step, marcas: circles.length };
  // tblDoorMain (id, QR, fecha, usuario): se toma SIEMPRE la ultima fila. Cuando
  // entra una fila nueva (otro id) se abre esa pieza. El usuario de la fila es quien
  // carga el defecto (la tablet no tiene login).
  const ultimoIdAbierto = useRef(null);
  const [usuarioPieza, setUsuarioPieza] = useState(null);
  const [controlActivo, setControlActivo] = useState(false);
  const [piezaNueva, setPiezaNueva] = useState(null);
  useEffect(() => {
    if (step === STEP_SET) setPiezaNueva(null);
  }, [step]);
  useEffect(() => {
    if (loading || showExistentes || guardando) return undefined;
    let vivo = true;
    const revisar = async () => {
      const r = await getControlFinal();
      if (!vivo) return;
      setControlActivo(!r?.error);
      const fila = !r?.error ? r?.data : null;
      if (!fila?.qr || ultimoIdAbierto.current === fila.id) return;
      const { step: paso, marcas } = estadoRef.current;
      if (paso !== STEP_SET && marcas > 0) {
        setPiezaNueva(fila.qr); // se abre cuando termine con esta
        return;
      }
      ultimoIdAbierto.current = fila.id;
      if (paso !== STEP_SET) resetRef.current?.();
      setUsuarioPieza(fila.usuario || null);
      buscarSetRef.current?.(fila.qr);
    };
    revisar();
    const t = setInterval(revisar, 2000);
    return () => {
      vivo = false;
      clearInterval(t);
    };
  }, [loading, showExistentes, guardando]);

  // ---------- 3. MARCAR ----------
  const view = views[Math.min(viewIdx, Math.max(0, views.length - 1))] || null;
  const goView = (delta) => {
    const total = views.length;
    if (total) setViewIdx(((viewIdx + delta) % total + total) % total);
  };

  // Sin foto del modelo (x/y null) el defecto queda cargado igual, sin posicion.
  const agregarMarca = (falla, { x, y }, vista) => {
    const idx = circles.length;
    setCircles((prev) => [
      ...prev,
      {
        id: `${Date.now()}-${idx}`,
        x,
        y,
        color: colorGrupo(falla.grupo) || PALETTE[idx % PALETTE.length],
        // Rango de la planilla (A/B/C) = caracteristica del defecto.
        falla: { id: falla.id, codigo: falla.codigo, nombre: falla.nombre, caracteristica: falla.rango },
        caracteristica: falla.rango,
        cantidad: 1,
        esCritico: falla.rango === "A",
        esLeve: falla.rango === "C",
        imageId: vista?.id,
        viewName: vista?.nombre || "Sin foto",
        typeName: vista?.tipo?.tipo,
        lado: vista?.lado?.lado,
        obs: "",
        falla_codigo: falla.codigo,
      },
    ]);
  };

  const marcarEnFoto = ({ x, y }) => {
    if (!fallaSel) {
      Alert.alert("Elegí el defecto", "Primero seleccioná el tipo de defecto y después tocá la foto.");
      return;
    }
    agregarMarca(fallaSel, { x, y }, view);
  };
  const borrarMarca = (id) => setCircles((prev) => prev.filter((c) => c.id !== id));
  const marcasVista = circles
    .filter((c) => c.imageId === view?.id)
    .map((c) => ({ ...c, falla: { codigo: c.falla_codigo } }));

  // ---------- 4. GUARDAR ----------
  const guardar = async () => {
    if (circles.length === 0 || guardando) return;
    setGuardando(true);
    const res = await marcarRetrabajo({
      qr: setInfo?.qr,
      tipo_pieza: pieza?.tipo_pieza,
      circles,
      modelo: setInfo?.modelo,
      lado: setInfo?.lado,
      codigo: setInfo?.codigo,
      secuencia: setInfo?.secuencia != null ? String(setInfo.secuencia) : undefined,
      linea: setInfo?.linea || (typeof user?.linea === "string" ? user.linea : user?.linea?.codigo),
      user: user?.id,
      usuario_nombre: usuarioPieza || undefined,
      puesto_nombre: "DOORTRIM",
    });
    setGuardando(false);
    if (res?.error || !res?.data) {
      Alert.alert("Error", res?.message || "No se pudo guardar el defecto. Reintentá.");
      return;
    }
    setExito({ pieza: pieza?.nombre, defectos: circles.length });
    if (resetTimer.current) clearTimeout(resetTimer.current);
    resetTimer.current = setTimeout(() => {
      setExito(null);
      reset();
    }, 2500);
  };

  // ---------- UI ----------
  if (exito) {
    return (
      <SafeAreaView style={styles.screen}>
        <StatusBar style="light" />
        <View style={styles.header}>
          <Text style={styles.headerTitle}>DOOR TRIM · MARCAR</Text>
        </View>
        <View style={[styles.body, { alignItems: "center", justifyContent: "center" }]}>
          <View style={{ backgroundColor: colors.green, borderRadius: 12, padding: 24, width: "100%" }}>
            <Text style={{ color: "#000", fontSize: 26, fontWeight: "bold", textAlign: "center" }}>DEFECTO CARGADO ✓</Text>
            <Text style={{ color: "#000", fontSize: 18, textAlign: "center", marginTop: 6 }}>
              {exito.pieza} · {exito.defectos} defecto(s)
            </Text>
            <Text style={{ color: "#000", fontSize: 16, textAlign: "center", marginTop: 14 }}>
              Queda para producción: lo retrabaja o cambia la pieza.
            </Text>
          </View>
          <Text style={[styles.hint, { marginTop: 20 }]}>Listo para el próximo door trim…</Text>
        </View>
      </SafeAreaView>
    );
  }

  // Paso de marcar: todo compacto para que la foto ocupe la mayor parte de la pantalla.
  const compacto = step === STEP_MARCAR;
  const pedirCancelar = () =>
    Alert.alert("Cancelar", "¿Descartar este defecto y empezar de nuevo? No se guarda nada.", [
      { text: "No", style: "cancel" },
      { text: "Sí, cancelar", style: "destructive", onPress: reset },
    ]);

  // ---- Piezas de la pantalla de marcar (se acomodan en vertical u horizontal) ----
  const infoPieza = (
    <View style={{ flexDirection: "row", alignItems: "center", backgroundColor: colors.yellow, borderRadius: 6, paddingVertical: 5, paddingHorizontal: 8, marginBottom: 6 }}>
      <Text style={{ color: "#000", fontWeight: "bold", fontSize: 15, flex: 1 }} numberOfLines={horizontal ? 2 : 1}>
        {setInfo?.modelo}
        {setInfo?.lado ? ` · ${setInfo.lado}` : ""}
        {setInfo?.secuencia ? ` · SEC ${setInfo.secuencia}` : ""}
        {pieza ? ` · ${pieza.nombre}` : ""}
      </Text>
      {piezasCandidatas.length > 1 ? (
        <TouchableOpacity onPress={() => setStep(STEP_PIEZA)} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
          <Text style={{ color: "#000", fontWeight: "bold", fontSize: 13, marginLeft: 8 }}>CAMBIAR PIEZA</Text>
        </TouchableOpacity>
      ) : null}
    </View>
  );
  const navVistas =
    views.length > 0 ? (
      <View style={{ flexDirection: "row", alignItems: "center", marginBottom: 6 }}>
        <TouchableOpacity onPress={() => goView(-1)} disabled={views.length <= 1} style={[flechaChica, views.length <= 1 && { opacity: 0.4 }]}>
          <Text style={arrowTxt}>◀</Text>
        </TouchableOpacity>
        <Text style={{ flex: 1, textAlign: "center", color: colors.text, fontWeight: "bold", fontSize: 15 }} numberOfLines={2}>
          {view?.nombre || `Vista ${viewIdx + 1}`}  {viewIdx + 1}/{views.length}
          {marcasVista.length > 0 ? ` · ${marcasVista.length} marca(s)` : ""}
          {fallaSel ? " · tocá la foto" : ""}
        </Text>
        <TouchableOpacity onPress={() => goView(1)} disabled={views.length <= 1} style={[flechaChica, views.length <= 1 && { opacity: 0.4 }]}>
          <Text style={arrowTxt}>▶</Text>
        </TouchableOpacity>
      </View>
    ) : null;
  const fotoMarcar =
    views.length > 0 ? (
      <FotoConMarcas view={view} defectos={marcasVista} onAddMark={marcarEnFoto} estilo={{ flex: 1 }} />
    ) : (
      <View style={[styles.card, { borderColor: colors.orange, flex: 1, justifyContent: "center" }]}>
        <Text style={[styles.cardMeta, { color: colors.orange, textAlign: "center" }]}>
          Este modelo no tiene foto cargada en /config: elegí el defecto y queda cargado sin marcar la posición.
        </Text>
      </View>
    );
  const chipsMarcas =
    circles.length > 0 ? (
      <ScrollView
        horizontal={!horizontal}
        style={horizontal ? { flex: 1, marginTop: 6 } : { flexGrow: 0, marginTop: 6 }}
        contentContainerStyle={{ gap: 6 }}
      >
        {circles.map((c) => (
          <View key={c.id} style={{ flexDirection: "row", alignItems: "center", backgroundColor: colors.card, borderColor: colors.border, borderWidth: 1, borderRadius: 16, paddingVertical: 4, paddingHorizontal: 10 }}>
            <Text style={{ color: colors.text, fontWeight: "bold", fontSize: 13, flexShrink: 1 }} numberOfLines={1}>
              {c.falla?.codigo} · {c.viewName}
            </Text>
            <TouchableOpacity onPress={() => borrarMarca(c.id)} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
              <Text style={{ color: colors.red, fontWeight: "bold", fontSize: 16, marginLeft: 8 }}>✕</Text>
            </TouchableOpacity>
          </View>
        ))}
      </ScrollView>
    ) : horizontal ? (
      <View style={{ flex: 1 }} />
    ) : null;
  const avisoNueva = piezaNueva ? (
    <View style={{ backgroundColor: colors.orange, borderRadius: 6, paddingVertical: 6, paddingHorizontal: 8, marginTop: 6 }}>
      <Text style={{ color: "#000", fontWeight: "bold", fontSize: 14 }} numberOfLines={3}>
        LLEGÓ OTRA PIEZA ({piezaNueva}). Cargá o cancelá esta y se abre sola.
      </Text>
    </View>
  ) : null;
  const botonesMarcar = (
    <View style={{ flexDirection: horizontal ? "column" : "row", gap: 8, marginTop: 6 }}>
      <TouchableOpacity
        onPress={() => setShowFallas(true)}
        style={[styles.bigButton, { flex: horizontal ? 0 : 1, paddingVertical: 12, backgroundColor: fallaSel ? colors.green : colors.blue }]}
      >
        <Text style={[styles.bigButtonText, { fontSize: 16, color: fallaSel ? "#000" : "#fff" }]} numberOfLines={2}>
          {fallaSel ? `${fallaSel.codigo} - ${fallaSel.nombre}` : "ELEGIR DEFECTO"}
        </Text>
      </TouchableOpacity>
      <TouchableOpacity
        onPress={guardar}
        disabled={circles.length === 0 || guardando}
        style={[styles.bigButton, { flex: horizontal ? 0 : 1, paddingVertical: 12, backgroundColor: colors.green, opacity: circles.length === 0 || guardando ? 0.5 : 1 }]}
      >
        {guardando ? <ActivityIndicator color="#000" /> : <Text style={[styles.bigButtonText, { fontSize: 16 }]}>CARGAR DEFECTO ({circles.length})</Text>}
      </TouchableOpacity>
    </View>
  );

  // Horizontal: foto a la izquierda ocupando casi todo, controles en una columna a
  // la derecha, sin la barra de titulo.
  if (compacto && horizontal) {
    return (
      <SafeAreaView style={styles.screen}>
        <StatusBar style="light" />
        <View style={{ flex: 1, flexDirection: "row", padding: 6, gap: 8 }}>
          <View style={{ flex: 1 }}>{fotoMarcar}</View>
          <View style={{ width: Math.min(320, Math.round(ventana.width * 0.3)) }}>
            <View style={[styles.topbar, { marginBottom: 6 }]}>
              <Text style={[styles.userTag, { flexShrink: 1 }]} numberOfLines={1}>
                {usuarioPieza ? `Cargó: ${usuarioPieza}` : user?.name || user?.email || "Calidad"}
              </Text>
              <TouchableOpacity onPress={pedirCancelar} focusable={false} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
                <Text style={[styles.userTag, { color: colors.red, fontWeight: "bold" }]}>✕ CANCELAR</Text>
              </TouchableOpacity>
            </View>
            {infoPieza}
            {navVistas}
            {chipsMarcas}
            {avisoNueva}
            {botonesMarcar}
          </View>
        </View>
        {modales()}
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.screen}>
      <StatusBar style="light" />
      <View style={[styles.header, compacto && { paddingVertical: 4 }]}>
        <Text style={[styles.headerTitle, compacto && { fontSize: 16 }]}>DOOR TRIM · MARCAR DEFECTO</Text>
      </View>

      <View style={[styles.body, compacto && { padding: 8 }]}>
        <View style={[styles.topbar, compacto && { marginBottom: 4 }]}>
          <Text style={styles.userTag}>{usuarioPieza ? `Cargó: ${usuarioPieza}` : user?.name || user?.email || "Calidad"}</Text>
          {compacto ? (
            <TouchableOpacity onPress={pedirCancelar} focusable={false} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
              <Text style={[styles.userTag, { color: colors.red, fontWeight: "bold" }]}>✕ CANCELAR</Text>
            </TouchableOpacity>
          ) : null}
          <TouchableOpacity onPress={onLogout} focusable={false}>
            <Text style={[styles.userTag, { color: colors.orange }]}>{textoLogout || "Cambiar usuario"}</Text>
          </TouchableOpacity>
        </View>

        {step === STEP_SET ? (
          <>
            <Text style={styles.label}>Escaneá el DOOR TRIM</Text>
            <TextInput
              ref={qrRef}
              style={styles.input}
              value={qr}
              onChangeText={onScanChange}
              placeholder="Escaneá el QR"
              placeholderTextColor="#9ca3af"
              autoFocus
              // El lector de QR escribe igual; sin teclado en pantalla (en horizontal
              // tapaba media pantalla).
              showSoftInputOnFocus={false}
              blurOnSubmit={false}
              onSubmitEditing={(e) => procesarScan(e.nativeEvent.text)}
              returnKeyType="search"
            />
          </>
        ) : null}

        {loading ? <ActivityIndicator color={colors.green} size="large" style={{ marginTop: 16 }} /> : null}
        {error ? <Text style={[styles.emptyText, { color: colors.orange }]}>{error}</Text> : null}

        {compacto ? (
          <View style={{ flex: 1 }}>
            {infoPieza}
            {navVistas}
            {fotoMarcar}
            {chipsMarcas}
            {avisoNueva}
            {botonesMarcar}
          </View>
        ) : (
        <ScrollView style={{ flex: 1, marginTop: 8 }} keyboardShouldPersistTaps="handled">
          {setInfo ? (
            <View style={{ backgroundColor: colors.yellow, padding: 10, borderRadius: 6, marginBottom: 10 }}>
              <Text style={{ color: "#000", fontWeight: "bold", fontSize: 16 }}>
                {setInfo.modelo} {setInfo.lado ? `· ${setInfo.lado}` : ""}
                {setInfo.secuencia ? ` · SEC ${setInfo.secuencia}` : ""}
              </Text>
              <Text style={{ color: "#000", fontSize: 13 }}>
                {setInfo.qr}
                {pieza ? `   ·   ${pieza.nombre}` : ""}
              </Text>
            </View>
          ) : null}

          {/* 2. (arm rest) con o sin cover */}
          {step === STEP_PIEZA ? (
            <>
              <Text style={[styles.label, { textAlign: "center", fontSize: 18 }]}>
                {FAMILIA_A_TIPO[setInfo?.familia] === "ARM_REST" ? "¿El arm rest es con o sin cover?" : "¿Qué pieza tiene el defecto?"}
              </Text>
              {piezasCandidatas.map((p) => (
                <TouchableOpacity
                  key={p.id}
                  onPress={() => elegirPieza(p)}
                  style={[
                    styles.bigButton,
                    { backgroundColor: pieza?.id === p.id ? colors.green : colors.blue, marginBottom: 12, paddingVertical: 22 },
                  ]}
                >
                  <Text style={[styles.bigButtonText, { color: pieza?.id === p.id ? "#000" : "#fff", fontSize: 24 }]}>
                    {p.nombre}
                  </Text>
                </TouchableOpacity>
              ))}
              {!catalogo ? (
                <View style={[styles.card, { borderColor: colors.red }]}>
                  <Text style={[styles.cardMeta, { color: colors.red }]}>
                    {errorCatalogo || "Cargando la lista de defectos…"}
                  </Text>
                  <TouchableOpacity onPress={cargarCatalogo} style={[styles.bigButton, { backgroundColor: colors.blue, marginTop: 10 }]}>
                    <Text style={[styles.bigButtonText, { color: "#fff" }]}>REINTENTAR</Text>
                  </TouchableOpacity>
                </View>
              ) : null}
            </>
          ) : null}

          {step === STEP_SET && qrSinModelo && !loading ? (
            <View style={[styles.card, { borderColor: colors.orange }]}>
              <Text style={styles.cardMeta}>QR: {qrSinModelo}</Text>
              <TouchableOpacity
                onPress={() => {
                  setBusquedaModelo("");
                  setShowModelos(true);
                }}
                style={[styles.bigButton, { backgroundColor: colors.orange, marginTop: 10 }]}
              >
                <Text style={styles.bigButtonText}>ELEGIR EL MODELO A MANO</Text>
              </TouchableOpacity>
            </View>
          ) : null}

          {step === STEP_SET && !loading && !error ? (
            <Text style={styles.emptyText}>
              ESPERANDO ESCANEO
            </Text>
          ) : null}
        </ScrollView>
        )}

        {step !== STEP_SET && !compacto ? (
          <TouchableOpacity
            onPress={pedirCancelar}
            style={[styles.bigButton, { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.red, marginTop: 8 }]}
          >
            <Text style={[styles.bigButtonText, { color: colors.red }]}>CANCELAR</Text>
          </TouchableOpacity>
        ) : null}
      </View>

      {modales()}
    </SafeAreaView>
  );

  // Ventanas que se abren encima (las dos orientaciones las usan).
  function modales() {
    return (
      <>
      {/* Defectos que ya tiene este door trim (como en asientos) */}
      <Modal visible={showExistentes} animationType="slide" transparent onRequestClose={() => setShowExistentes(false)}>
        <View style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.8)", justifyContent: "flex-end" }}>
          <View style={{ backgroundColor: colors.bg, borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 16, maxHeight: "92%" }}>
            <View style={{ backgroundColor: colors.yellow, padding: 10, borderRadius: 6, marginBottom: 10 }}>
              <Text style={{ color: "#000", fontWeight: "bold", fontSize: 18 }}>
                {existentes.length > 0 ? `ESTE DOOR TRIM YA TIENE ${existentes.length} DEFECTO(S)` : "NO QUEDAN DEFECTOS ABIERTOS"}
              </Text>
              <Text style={{ color: "#000", fontSize: 13 }}>
                {setInfo?.modelo} {setInfo?.lado || ""} · {setInfo?.qr}
              </Text>
            </View>
            <ScrollView>
              {existentes.map((d) => {
                const paraValidar = d.estado === "RETRABAJADO" || d.estado === "CAMBIADO";
                const esLaNueva = d.cambio && d.cambio.entra === setInfo?.qr;
                const que = esLaNueva
                  ? `Esta pieza reemplaza a ${d.cambio.sale} · cambiada por ${d.cambio.por || "-"}${d.cambio.fecha ? " · " + d.cambio.fecha : ""}`
                  : d.cambio
                  ? `Cambiada por ${d.cambio.entra} · por ${d.cambio.por || "-"}${d.cambio.fecha ? " · " + d.cambio.fecha : ""}`
                  : d.estado === "RETRABAJADO"
                  ? `Retrabajado por ${d.reparado_por_nombre || "-"}${d.reparado_at ? " · " + d.reparado_at : ""}`
                  : "ESPERANDO RETRABAJO DE PRODUCCIÓN";
                return (
                  <View key={d.id} style={[styles.card, { marginBottom: 8 }]}>
                    <View style={{ flexDirection: "row", alignItems: "center" }}>
                      <Text style={[styles.cardFalla, { flex: 1 }]}>
                        {d?.falla?.codigo} - {String(d?.falla?.nombre || "").toUpperCase()}
                      </Text>
                      <TouchableOpacity onPress={() => setVerDefecto(d)} style={{ paddingHorizontal: 8, paddingVertical: 6 }}>
                        <Text style={{ color: colors.cyan, fontWeight: "bold" }}>VER FOTO</Text>
                      </TouchableOpacity>
                    </View>
                    <Text style={styles.cardMeta}>
                      {tipoLabel(d?.tipo?.tipo)} · Marcó: {d.marcado_por_nombre || "-"} · {d.created_at || ""}
                    </Text>
                    <View style={[styles.estadoBanner, { backgroundColor: paraValidar ? colors.blue : colors.orange }]}>
                      <Text style={[styles.estadoText, { fontSize: 14, color: paraValidar ? "#fff" : "#000", textAlign: "center", paddingHorizontal: 8 }]}>
                        {que}
                      </Text>
                    </View>
                    {paraValidar ? (
                      <View style={{ flexDirection: "row", gap: 8, marginTop: 10 }}>
                        <TouchableOpacity
                          onPress={() => resolverDefecto(d, "validar")}
                          disabled={busyId === d.id}
                          style={{ flex: 2, backgroundColor: colors.green, borderRadius: 6, paddingVertical: 14, opacity: busyId === d.id ? 0.6 : 1 }}
                        >
                          {busyId === d.id ? (
                            <ActivityIndicator color="#000" />
                          ) : (
                            <Text style={{ color: "#000", fontWeight: "bold", textAlign: "center", fontSize: 16 }}>VALIDAR</Text>
                          )}
                        </TouchableOpacity>
                        <TouchableOpacity
                          onPress={() => resolverDefecto(d, "rechazar")}
                          disabled={busyId === d.id}
                          style={{ flex: 1, backgroundColor: colors.red, borderRadius: 6, paddingVertical: 14 }}
                        >
                          <Text style={{ color: "#fff", fontWeight: "bold", textAlign: "center", fontSize: 16 }}>RECHAZAR</Text>
                        </TouchableOpacity>
                      </View>
                    ) : null}
                  </View>
                );
              })}
            </ScrollView>
            <View style={{ flexDirection: "row", gap: 8, marginTop: 12 }}>
              <TouchableOpacity onPress={() => setShowExistentes(false)} style={[styles.bigButton, { flex: 1, backgroundColor: colors.blue }]}>
                <Text style={[styles.bigButtonText, { color: "#fff" }]}>CARGAR NUEVO DEFECTO</Text>
              </TouchableOpacity>
              <TouchableOpacity onPress={reset} style={[styles.bigButton, { flex: 1, backgroundColor: colors.orange }]}>
                <Text style={styles.bigButtonText}>SALIR</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Foto del defecto existente con su marca */}
      <DefectoInfoModal
        visible={!!verDefecto}
        defecto={verDefecto ? { ...verDefecto, imagen: null, imagen_marcada: null } : null}
        views={views}
        onClose={() => setVerDefecto(null)}
      />

      {/* Selector de defecto: lista de la planilla para la pieza elegida */}
      <Modal visible={showFallas} animationType="slide" transparent onRequestClose={() => setShowFallas(false)}>
        <View style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.8)", justifyContent: "flex-end" }}>
          <View style={{ backgroundColor: colors.bg, borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 16, maxHeight: "85%" }}>
            <View style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginBottom: 10 }}>
              <Text style={[styles.headerTitle, { fontSize: 20 }]}>DEFECTOS · {pieza?.nombre || ""}</Text>
              <TouchableOpacity onPress={() => setShowFallas(false)} hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}>
                <Text style={{ color: colors.orange, fontSize: 22, fontWeight: "bold" }}>✕</Text>
              </TouchableOpacity>
            </View>
            <View style={{ flexDirection: "row", gap: 8, marginBottom: 12 }}>
              {categorias.map((c) => {
                const selCat = catSel?.id === c.id;
                const catColor = `#${c.color || "FACC15"}`;
                return (
                  <TouchableOpacity
                    key={c.id}
                    onPress={() => setCatSel(c)}
                    style={{
                      flex: 1,
                      paddingVertical: 14,
                      borderRadius: 8,
                      borderWidth: 2,
                      alignItems: "center",
                      borderColor: selCat ? catColor : "#404040",
                      backgroundColor: selCat ? catColor : colors.card,
                    }}
                  >
                    <Text style={{ color: selCat ? "#000" : colors.text, fontWeight: "bold", fontSize: 16 }}>
                      {String(c.nombre || "").toUpperCase()}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </View>
            <ScrollView>
              <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
                {defectosFiltrados.map((d) => {
                  const sel = fallaSel?.id === d.id;
                  return (
                    <TouchableOpacity
                      key={d.id}
                      onPress={() => {
                        setFallaSel(d);
                        setShowFallas(false);
                        if (views.length === 0) agregarMarca(d, { x: null, y: null }, null);
                      }}
                      style={{
                        paddingHorizontal: 12,
                        paddingVertical: 12,
                        borderRadius: 8,
                        borderWidth: 2,
                        borderColor: sel ? colors.green : "#404040",
                        backgroundColor: sel ? colors.green : colors.card,
                        minWidth: "47%",
                      }}
                    >
                      <Text style={{ color: sel ? "#000" : colors.text, fontWeight: "bold", fontSize: 15 }}>
                        {d.codigo} - {d.nombre}
                      </Text>
                      <Text style={{ color: sel ? "#000" : colors.muted, fontSize: 12, marginTop: 2 }}>Rango {d.rango}</Text>
                    </TouchableOpacity>
                  );
                })}
                {defectosPieza.length === 0 ? (
                  <Text style={styles.cardMeta}>Esta pieza no tiene defectos cargados.</Text>
                ) : defectosFiltrados.length === 0 ? (
                  <Text style={styles.cardMeta}>No hay defectos en esta categoría.</Text>
                ) : null}
              </View>
            </ScrollView>
          </View>
        </View>
      </Modal>

      {/* Plan B: elegir el modelo del door trim a mano (QR que no se resuelve) */}
      <Modal visible={showModelos} animationType="slide" transparent onRequestClose={() => setShowModelos(false)}>
        <View style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.85)", justifyContent: "flex-end" }}>
          <View style={{ backgroundColor: colors.bg, borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 16, maxHeight: "90%" }}>
            <View style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginBottom: 10 }}>
              <Text style={[styles.headerTitle, { fontSize: 20 }]}>ELEGIR MODELO</Text>
              <TouchableOpacity onPress={() => setShowModelos(false)} hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}>
                <Text style={{ color: colors.orange, fontSize: 22, fontWeight: "bold" }}>✕</Text>
              </TouchableOpacity>
            </View>
            <TextInput
              style={styles.input}
              value={busquedaModelo}
              onChangeText={setBusquedaModelo}
              placeholder="Buscar (ej: DOOR, A1A, LH)"
              placeholderTextColor="#9ca3af"
              autoCapitalize="characters"
            />
            <ScrollView style={{ marginTop: 10 }} keyboardShouldPersistTaps="handled">
              {modelosFiltrados.map((m) => (
                <TouchableOpacity key={m.id} onPress={() => elegirModelo(m)} style={[styles.card, { marginBottom: 6, paddingVertical: 10 }]}>
                  <Text style={{ color: colors.text, fontWeight: "bold", fontSize: 15 }}>{m.name || m.code}</Text>
                  <Text style={styles.cardMeta}>{[m.code, m.jitms?.sd, m.jitms?.codigoTasa].filter(Boolean).join("  ·  ")}</Text>
                </TouchableOpacity>
              ))}
              {modelosFiltrados.length === 0 ? <Text style={styles.cardMeta}>No hay modelos que coincidan.</Text> : null}
            </ScrollView>
          </View>
        </View>
      </Modal>
      </>
    );
  }
}

const arrowBtn = {
  paddingHorizontal: 16,
  paddingVertical: 12,
  borderRadius: 8,
  borderWidth: 2,
  borderColor: colors.blue,
  backgroundColor: colors.card,
};
const arrowTxt = { color: colors.text, fontSize: 22, fontWeight: "bold" };
// Flecha mas baja para la pantalla de marcar (deja lugar a la foto).
const flechaChica = { ...arrowBtn, paddingHorizontal: 18, paddingVertical: 4 };
