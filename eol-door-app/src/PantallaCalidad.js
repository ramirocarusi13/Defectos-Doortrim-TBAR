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
} from "react-native";
import { StatusBar } from "expo-status-bar";
import { styles, colors } from "./styles";
import {
  getDefectosByQr,
  getEtiquetaData,
  getFactoryConfig,
  getFactoryConfigVersion,
  guardarDefectos,
  validarDefecto,
} from "./api";
import {
  getConfiguredModelImagesForKanban,
  getConfiguredDefects,
  getDefectCategories,
  resolveDefectCategoryId,
  defectoAplicaAPuesto,
} from "./factoryConfig";
import { getTipo } from "./utils";
import { FotoConMarcas, findViewForDefecto } from "./components/AsientoViewer";
import PuestoSelectorModal from "./components/PuestoSelectorModal";
import { getPuestoGuardado, guardarPuesto } from "./puesto";

// Colores de respaldo para las marcas cuando el defecto no trae color propio.
const PALETTE = ["F59E0B", "EF4444", "22C55E", "2563EB", "A855F7", "EC4899", "14B8A6", "F97316"];

// Pantalla de CALIDAD: misma idea que la web de fin de linea.
// - Escanea el kanban -> trae las fotos del modelo (todos los angulos).
// - Si la funda ya tiene defectos (reparados + pendientes) los muestra primero.
// - Toca la foto para marcar el defecto, elige el tipo de falla y confirma.
export default function PantallaCalidad({ user, config: configProp, onLogout }) {
  const [config, setConfig] = useState(configProp || null);
  const [qr, setQr] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const [kanban, setKanban] = useState(null); // datos de la etiqueta
  const [views, setViews] = useState([]); // TODAS las vistas del modelo (para marcar)
  const [existentes, setExistentes] = useState([]); // defectos ya cargados
  const [showExistentes, setShowExistentes] = useState(false);

  // Estado de marcado. Critico/leve NO se eligen a mano: salen de la config del
  // defecto en /config (canBeMinor = Leve; si no es leve, se marca como critico).
  const [circles, setCircles] = useState([]);
  const [fallaSel, setFallaSel] = useState(null);
  const [viewIdx, setViewIdx] = useState(0);
  const [showFallas, setShowFallas] = useState(false);
  const [catSel, setCatSel] = useState(null); // categoria elegida (Apariencia/Funcionalidad)
  const [guardando, setGuardando] = useState(false);
  const [verDefecto, setVerDefecto] = useState(null); // defecto existente a ver en la foto

  // Puesto de esta tablet (fijo por dispositivo, se elige una sola vez).
  const [puesto, setPuesto] = useState(null);
  const [showPuestoSelector, setShowPuestoSelector] = useState(false);

  const qrRef = useRef(null);
  const scanTimer = useRef(null);

  useEffect(() => {
    if (!config) {
      getFactoryConfig().then((c) => c?.data && setConfig(c.data));
    }
    setTimeout(() => qrRef.current?.focus(), 200);
  }, []);

  // REFRESCO EN VIVO de la config: cada 10s consulta una "version" liviana (timestamp)
  // y solo re-baja la config completa cuando cambio. Asi, cuando el group leader edita
  // /config (defectos, puestos, etc.), las tablets lo reflejan solas en <=10s SIN
  // reinstalar el APK ni reiniciar la app.
  const configVersionRef = useRef(null);
  useEffect(() => {
    let activo = true;
    const chequear = async () => {
      try {
        const v = (await getFactoryConfigVersion())?.data?.version;
        if (v == null) return;
        if (configVersionRef.current == null) { configVersionRef.current = v; return; }
        if (v !== configVersionRef.current) {
          const c = await getFactoryConfig();
          if (activo && c?.data) { setConfig(c.data); configVersionRef.current = v; }
        }
      } catch (e) { /* red intermitente: reintenta al proximo tick */ }
    };
    const t = setInterval(chequear, 10000);
    return () => { activo = false; clearInterval(t); };
  }, []);

  // Carga el puesto guardado en el dispositivo. Si no hay ninguno, obligamos
  // a elegirlo antes de poder marcar defectos.
  useEffect(() => {
    getPuestoGuardado().then((p) => {
      setPuesto(p);
      if (!p) setShowPuestoSelector(true);
    });
  }, []);

  const elegirPuesto = async (p) => {
    const ok = await guardarPuesto(p);
    if (!ok) {
      Alert.alert("Error", "No se pudo guardar el puesto en el dispositivo. Reintentá.");
      return;
    }
    setPuesto(p);
    setShowPuestoSelector(false);
  };

  const defectosConfig = getConfiguredDefects(config);
  const categorias = getDefectCategories(config);
  // Por defecto seleccionamos la primera categoria asi ya se ven defectos al abrir.
  useEffect(() => {
    if (!catSel && categorias.length > 0) setCatSel(categorias[0]);
  }, [config]);
  // Primero filtramos por PUESTO de esta tablet (sin puesto elegido, no hay
  // nada para marcar); despues por la categoria elegida.
  const defectosPuesto = puesto
    ? defectosConfig.filter((d) => defectoAplicaAPuesto(d, puesto.id))
    : [];
  const defectosFiltrados = catSel
    ? defectosPuesto.filter((d) => resolveDefectCategoryId(d, categorias) === catSel.id)
    : defectosPuesto;

  const resetFunda = () => {
    setKanban(null);
    setViews([]);
    setExistentes([]);
    setCircles([]);
    setFallaSel(null);
    setViewIdx(0);
    setQr("");
  };

  const buscar = async (scan) => {
    const value = String(scan || "").trim();
    if (!value) return;
    if (scanTimer.current) {
      clearTimeout(scanTimer.current);
      scanTimer.current = null;
    }

    setLoading(true);
    setError(null);
    setCircles([]);
    setFallaSel(null);
    setViewIdx(0);

    let cfg = config;
    if (!cfg) {
      const c = await getFactoryConfig();
      cfg = c?.data || null;
      if (cfg) setConfig(cfg);
    }

    const [rep, etq] = await Promise.all([getDefectosByQr(value), getEtiquetaData(value)]);
    setLoading(false);
    setQr("");
    setTimeout(() => qrRef.current?.focus(), 100);

    const d = etq?.data || null;
    const todos = rep?.data || [];

    // Datos del kanban para buscar las fotos del modelo.
    const kanbanData =
      d || {
        modelo: todos[0]?.modelo,
        tipo: todos[0]?.tipo?.tipo,
        lado: todos[0]?.lado?.lado,
      };

    if (!kanbanData?.modelo) {
      setError("No se pudo leer el kanban. Reintentá el escaneo.");
      return;
    }

    const todasLasVistas = getConfiguredModelImagesForKanban(kanbanData, cfg);
    if (todasLasVistas.length === 0) {
      setError(`El modelo ${kanbanData.modelo} no tiene fotos cargadas en /config.`);
    }

    setKanban({ ...kanbanData, qr: value });
    setViews(todasLasVistas);
    setExistentes(todos);
    if (todos.length > 0) setShowExistentes(true);
  };

  // CALIDAD valida o rechaza un retrabajo hecho por produccion.
  const validarRetrabajo = async (defecto, accion) => {
    const r = await validarDefecto(defecto.id, { user: user?.id, accion });
    if (r?.error) {
      Alert.alert("Error", r?.message || "No se pudo registrar la validación");
      return;
    }
    // Refresco la lista de defectos del asiento
    const rep = await getDefectosByQr(kanban?.qr);
    setExistentes(rep?.data || []);
  };

  const onScanChange = (text) => {
    setQr(text);
    if (scanTimer.current) clearTimeout(scanTimer.current);
    const v = String(text || "").trim();
    if (v.length >= 6) scanTimer.current = setTimeout(() => buscar(v), 300);
  };

  const view = views[Math.min(viewIdx, Math.max(0, views.length - 1))] || null;
  const goView = (delta) => {
    const total = views.length;
    if (!total) return;
    setViewIdx(((viewIdx + delta) % total + total) % total);
  };

  const marcarEnFoto = ({ x, y }) => {
    if (!puesto) {
      Alert.alert("Elegí el puesto", "Primero elegí el puesto de control de calidad de esta tablet.");
      setShowPuestoSelector(true);
      return;
    }
    if (!fallaSel) {
      Alert.alert("Elegí el defecto", "Primero seleccioná el tipo de defecto y después tocá la foto.");
      return;
    }
    const idx = circles.length;
    const color = fallaSel.color || PALETTE[idx % PALETTE.length];
    // Critico/leve segun la config del defecto: leve = canBeMinor; si no, critico.
    const esLeveAuto = Boolean(fallaSel.canBeMinor);
    setCircles((prev) => [
      ...prev,
      {
        id: `${Date.now()}-${idx}`,
        x,
        y,
        color,
        falla: { id: fallaSel.id, codigo: fallaSel.codigo, nombre: fallaSel.nombre },
        cantidad: 1,
        esCritico: !esLeveAuto,
        esLeve: esLeveAuto,
        imageId: view?.id,
        viewName: view?.nombre,
        typeName: view?.tipo?.tipo,
        lado: view?.lado?.lado,
        obs: "",
        // para dibujar en el visor:
        falla_codigo: fallaSel.codigo,
      },
    ]);
  };

  const borrarMarca = (id) => setCircles((prev) => prev.filter((c) => c.id !== id));

  const confirmar = async () => {
    if (circles.length === 0) return;
    Alert.alert("Confirmar control de calidad", `¿Guardar ${circles.length} defecto(s) marcado(s)?`, [
      { text: "Cancelar", style: "cancel" },
      {
        text: "Confirmar",
        onPress: async () => {
          setGuardando(true);
          const res = await guardarDefectos({
            circles,
            qr: kanban?.qr,
            user: user?.id,
            linea: kanban?.linea || (typeof user?.linea === "string" ? user.linea : user?.linea?.codigo),
            puesto_id: puesto?.id,
            puesto_nombre: puesto?.name,
            // Esta linea NO retrabaja en el puesto: el defecto se resuelve por
            // CAMBIO de la pieza contra el mini buffer. El backend lo enruta
            // distinto segun este flag.
            flujo: "DOOR",
          });
          setGuardando(false);
          if (res?.error) {
            Alert.alert("Error", res?.message || "No se pudo guardar. Reintentá.");
            return;
          }
          Alert.alert("Guardado", "Defectos cargados correctamente.");
          resetFunda();
        },
      },
    ]);
  };

  // Marcas de la vista actual (las que estoy cargando ahora).
  const marcasVista = circles
    .filter((c) => c.imageId === view?.id)
    .map((c) => ({ ...c, falla: { codigo: c.falla_codigo } }));

  // ---------- LOGIN GATE (lo maneja App.js, aca ya viene user) ----------
  return (
    <SafeAreaView style={styles.screen}>
      <StatusBar style="light" />
      <View style={styles.header}>
        <Text style={styles.headerTitle}>CALIDAD · LÍNEA DOOR</Text>
        <TouchableOpacity
          onPress={() => setShowPuestoSelector(true)}
          style={{ alignSelf: "center", marginTop: 6 }}
          hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
        >
          <Text style={{ color: "#fff", fontSize: 14, fontWeight: "700" }}>
            {puesto ? `PUESTO: ${puesto.name}` : "PUESTO: sin elegir"} ✎
          </Text>
        </TouchableOpacity>
      </View>

      <View style={styles.body}>
        <View style={styles.topbar}>
          <Text style={styles.userTag}>{user?.name || user?.email || "Calidad"}</Text>
          <TouchableOpacity onPress={onLogout}>
            <Text style={[styles.userTag, { color: colors.orange }]}>Cambiar usuario</Text>
          </TouchableOpacity>
        </View>

        <Text style={styles.label}>Escanear kanban / etiqueta</Text>
        <TextInput
          ref={qrRef}
          style={styles.input}
          value={qr}
          onChangeText={onScanChange}
          placeholder="Escaneá el QR"
          placeholderTextColor="#9ca3af"
          autoFocus
          blurOnSubmit={false}
          onSubmitEditing={(e) => buscar(e.nativeEvent.text)}
          returnKeyType="search"
        />

        {loading && <ActivityIndicator color={colors.green} size="large" style={{ marginTop: 24 }} />}
        {error ? <Text style={styles.emptyText}>{error}</Text> : null}

        {kanban && !loading ? (
          <ScrollView style={{ marginTop: 10 }} keyboardShouldPersistTaps="handled">
            {/* Info del kanban */}
            <View style={{ backgroundColor: colors.yellow, padding: 10, borderRadius: 6 }}>
              <Text style={{ color: "#000", fontWeight: "bold", fontSize: 16 }}>
                {kanban?.modelo} · {getTipo(kanban?.tipo)} {kanban?.lado || ""}
                {kanban?.secuencia ? ` · SEC ${kanban.secuencia}` : ""}
              </Text>
              {existentes.length > 0 ? (
                <TouchableOpacity onPress={() => setShowExistentes(true)}>
                  <Text style={{ color: "#1d4ed8", fontWeight: "bold", marginTop: 2 }}>
                    Este asiento ya tiene {existentes.length} defecto(s) · VER ›
                  </Text>
                </TouchableOpacity>
              ) : null}
            </View>

            {views.length > 0 ? (
              <>
                {/* Carrusel de TODAS las vistas (para marcar en cualquier angulo) */}
                <View style={{ flexDirection: "row", alignItems: "center", gap: 8, marginTop: 10, marginBottom: 8 }}>
                  <TouchableOpacity onPress={() => goView(-1)} disabled={views.length <= 1} style={[arrowBtn, views.length <= 1 && { opacity: 0.4 }]}>
                    <Text style={arrowTxt}>◀</Text>
                  </TouchableOpacity>
                  <View style={{ flex: 1, alignItems: "center" }}>
                    <Text style={{ color: colors.text, fontWeight: "bold", fontSize: 15 }} numberOfLines={1}>
                      {view?.nombre || `Vista ${viewIdx + 1}`}
                    </Text>
                    <Text style={{ color: colors.muted, fontSize: 12 }}>
                      {viewIdx + 1} / {views.length}
                      {marcasVista.length > 0 ? ` · ${marcasVista.length} marca(s)` : ""}
                    </Text>
                  </View>
                  <TouchableOpacity onPress={() => goView(1)} disabled={views.length <= 1} style={[arrowBtn, views.length <= 1 && { opacity: 0.4 }]}>
                    <Text style={arrowTxt}>▶</Text>
                  </TouchableOpacity>
                </View>

                {/* Foto: tocar para marcar */}
                <FotoConMarcas view={view} defectos={marcasVista} onAddMark={marcarEnFoto} />
                <Text style={{ color: colors.muted, fontSize: 13, textAlign: "center", marginTop: 4 }}>
                  {fallaSel ? "Tocá la foto donde está el defecto" : "Elegí un defecto abajo y después tocá la foto"}
                </Text>

                {/* Defecto seleccionado + toggles */}
                <TouchableOpacity
                  onPress={() => (puesto ? setShowFallas(true) : setShowPuestoSelector(true))}
                  style={[
                    styles.bigButton,
                    { backgroundColor: !puesto ? colors.orange : fallaSel ? colors.green : colors.blue, marginTop: 10 },
                  ]}
                >
                  <Text style={[styles.bigButtonText, { color: !puesto ? "#000" : fallaSel ? "#000" : "#fff" }]}>
                    {!puesto
                      ? "ELEGÍ EL PUESTO DE ESTA TABLET PRIMERO"
                      : fallaSel
                      ? `DEFECTO: ${fallaSel.codigo} - ${fallaSel.nombre}`
                      : "ELEGIR DEFECTO"}
                  </Text>
                </TouchableOpacity>

                {/* Lista de marcas cargadas */}
                {circles.length > 0 ? (
                  <View style={{ marginTop: 12 }}>
                    <Text style={styles.label}>MARCAS CARGADAS · {circles.length}</Text>
                    {circles.map((c) => (
                      <View key={c.id} style={[styles.card, { marginBottom: 6, paddingVertical: 8 }]}>
                        <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
                          <Text style={{ color: colors.text, fontWeight: "bold", flex: 1 }}>
                            {c.falla?.codigo} - {c.falla?.nombre}
                          </Text>
                          <Text style={{ color: colors.muted, fontSize: 12, marginRight: 10 }} numberOfLines={1}>
                            {c.viewName}
                          </Text>
                          <TouchableOpacity onPress={() => borrarMarca(c.id)} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
                            <Text style={{ color: colors.red, fontWeight: "bold", fontSize: 18 }}>✕</Text>
                          </TouchableOpacity>
                        </View>
                      </View>
                    ))}
                  </View>
                ) : null}

                {/* Acciones */}
                <View style={{ flexDirection: "row", gap: 8, marginTop: 12, marginBottom: 40 }}>
                  <TouchableOpacity
                    onPress={confirmar}
                    disabled={circles.length === 0 || guardando}
                    style={[styles.bigButton, { flex: 1, backgroundColor: colors.green, opacity: circles.length === 0 || guardando ? 0.5 : 1 }]}
                  >
                    {guardando ? <ActivityIndicator color="#000" /> : <Text style={styles.bigButtonText}>CONFIRMAR ({circles.length})</Text>}
                  </TouchableOpacity>
                  <TouchableOpacity onPress={resetFunda} style={[styles.bigButton, { flex: 1, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.border }]}>
                    <Text style={[styles.bigButtonText, { color: colors.text }]}>NUEVO ASIENTO</Text>
                  </TouchableOpacity>
                </View>
              </>
            ) : null}
          </ScrollView>
        ) : (
          !loading && !error && <Text style={styles.emptyText}>ESCANEE EL KANBAN PARA EMPEZAR</Text>
        )}
      </View>

      {/* Modal: defectos ya existentes (reparados + pendientes) */}
      <Modal visible={showExistentes} animationType="slide" transparent onRequestClose={() => setShowExistentes(false)}>
        <View style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.8)", justifyContent: "flex-end" }}>
          <View style={{ backgroundColor: colors.bg, borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 16, maxHeight: "90%" }}>
            <View style={{ backgroundColor: colors.yellow, padding: 10, borderRadius: 6, marginBottom: 10 }}>
              <Text style={{ color: "#000", fontWeight: "bold", fontSize: 18 }}>
                ESTE ASIENTO YA TIENE {existentes.length} DEFECTO(S)
              </Text>
              <Text style={{ color: "#000", fontSize: 13 }}>
                {kanban?.modelo} · {getTipo(kanban?.tipo)} {kanban?.lado || ""} · {kanban?.qr}
              </Text>
            </View>

            <ScrollView>
              {existentes.map((d) => {
                const reparado = d?.estado && d.estado !== "PENDIENTE";
                const esRetrabajado = d?.estado === "RETRABAJADO";
                const colorEstado = d?.estado === "VALIDADO" ? colors.green
                  : esRetrabajado ? colors.blue
                  : colors.orange;
                return (
                  <View key={d.id} style={[styles.card, { marginBottom: 8 }]}>
                    <TouchableOpacity activeOpacity={0.7} onPress={() => setVerDefecto(d)}>
                      <Text style={styles.cardFalla}>
                        {d?.falla?.codigo} - {String(d?.falla?.nombre || "").toUpperCase()}
                      </Text>
                      <Text style={styles.cardMeta}>
                        TIPO: {d?.tipo?.tipo || "-"} {d?.lado?.lado || ""}   ·   Abrió: {d?.marcado_por_nombre || "-"}
                      </Text>
                      <View style={[styles.estadoBanner, { backgroundColor: colorEstado }]}>
                        <Text style={styles.estadoText}>
                          {d?.estado}
                          {d?.reparado_por_nombre ? ` · retrabajó ${d.reparado_por_nombre}` : ""}
                        </Text>
                      </View>
                      <View style={{ backgroundColor: colors.card, borderWidth: 1, borderColor: colors.border, borderRadius: 6, marginTop: 8, paddingVertical: 8 }}>
                        <Text style={{ color: colors.text, fontWeight: "bold", textAlign: "center" }}>VER FOTO</Text>
                      </View>
                    </TouchableOpacity>

                    {/* CALIDAD valida el retrabajo (solo cuando produccion ya lo retrabajo) */}
                    {esRetrabajado ? (
                      <View style={{ flexDirection: "row", gap: 8, marginTop: 10 }}>
                        <TouchableOpacity
                          onPress={() => validarRetrabajo(d, "validar")}
                          style={{ flex: 1, backgroundColor: colors.green, borderRadius: 6, paddingVertical: 14 }}
                        >
                          <Text style={{ color: "#000", fontWeight: "bold", textAlign: "center", fontSize: 16 }}>VALIDAR</Text>
                        </TouchableOpacity>
                        <TouchableOpacity
                          onPress={() => validarRetrabajo(d, "rechazar")}
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
              <TouchableOpacity
                onPress={() => {
                  setShowExistentes(false);
                  resetFunda();
                }}
                style={[styles.bigButton, { flex: 1, backgroundColor: colors.orange }]}
              >
                <Text style={styles.bigButtonText}>SALIR</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal: selector de defecto (categorias + fallas) */}
      <Modal visible={showFallas} animationType="slide" transparent onRequestClose={() => setShowFallas(false)}>
        <View style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.8)", justifyContent: "flex-end" }}>
          <View style={{ backgroundColor: colors.bg, borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 16, maxHeight: "85%" }}>
            <View style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginBottom: 10 }}>
              <Text style={[styles.headerTitle, { fontSize: 20 }]}>ELEGIR DEFECTO</Text>
              <TouchableOpacity onPress={() => setShowFallas(false)} hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}>
                <Text style={{ color: colors.orange, fontSize: 22, fontWeight: "bold" }}>✕</Text>
              </TouchableOpacity>
            </View>
            {/* Categorias: primero se elige Apariencia / Funcionalidad para filtrar */}
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
                      {String(c.name || "").toUpperCase()}
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
                    </TouchableOpacity>
                  );
                })}
                {defectosConfig.length === 0 ? (
                  <Text style={styles.cardMeta}>No hay defectos configurados en /config.</Text>
                ) : defectosPuesto.length === 0 ? (
                  <Text style={styles.cardMeta}>No hay defectos configurados para este puesto.</Text>
                ) : defectosFiltrados.length === 0 ? (
                  <Text style={styles.cardMeta}>No hay defectos en esta categoría.</Text>
                ) : null}
              </View>
            </ScrollView>
          </View>
        </View>
      </Modal>

      {/* Modal: ver la FOTO donde esta marcado un defecto existente */}
      <Modal visible={!!verDefecto} animationType="slide" transparent onRequestClose={() => setVerDefecto(null)}>
        <View style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.85)", justifyContent: "flex-end" }}>
          <View style={{ backgroundColor: colors.bg, borderTopLeftRadius: 16, borderTopRightRadius: 16, padding: 16, maxHeight: "92%" }}>
            <View style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginBottom: 10 }}>
              <Text style={[styles.headerTitle, { fontSize: 20 }]}>DÓNDE ESTÁ EL DEFECTO</Text>
              <TouchableOpacity onPress={() => setVerDefecto(null)} hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}>
                <Text style={{ color: colors.orange, fontSize: 22, fontWeight: "bold" }}>✕</Text>
              </TouchableOpacity>
            </View>

            {verDefecto ? (
              <ScrollView>
                {/* Foto de la vista (por vista_id) con la marca dibujada en su posicion */}
                {views.length > 0 ? (
                  <FotoConMarcas view={findViewForDefecto(views, verDefecto)} defectos={[verDefecto]} />
                ) : (
                  <Text style={[styles.cardMeta, { textAlign: "center" }]}>
                    No hay fotos del modelo cargadas para mostrar la marca.
                  </Text>
                )}

                <View style={[styles.card, { marginTop: 12 }]}>
                  <Text style={styles.cardFalla}>
                    {verDefecto?.falla?.codigo} - {String(verDefecto?.falla?.nombre || "").toUpperCase()}
                  </Text>
                  <Text style={styles.cardMeta}>
                    TIPO: {verDefecto?.tipo?.tipo || "-"} {verDefecto?.lado?.lado || ""}
                    {verDefecto?.vista_nombre ? `   ·   Vista: ${verDefecto.vista_nombre}` : ""}
                  </Text>
                  {verDefecto?.observaciones ? (
                    <Text style={styles.cardMeta}>OBS: {verDefecto.observaciones}</Text>
                  ) : null}
                  <Text style={styles.cardMeta}>Marcó: {verDefecto?.marcado_por_nombre || "-"}</Text>
                  {verDefecto?.created_at ? (
                    <Text style={styles.cardMeta}>Fecha: {verDefecto.created_at}</Text>
                  ) : null}
                  <View
                    style={[
                      styles.estadoBanner,
                      { backgroundColor: verDefecto?.estado && verDefecto.estado !== "PENDIENTE" ? colors.green : colors.orange },
                    ]}
                  >
                    <Text style={styles.estadoText}>
                      {verDefecto?.estado}
                      {verDefecto?.reparado_por_nombre ? ` · ${verDefecto.reparado_por_nombre}` : ""}
                    </Text>
                  </View>
                </View>
              </ScrollView>
            ) : null}

            <TouchableOpacity
              onPress={() => setVerDefecto(null)}
              style={[styles.bigButton, { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.border, marginTop: 12 }]}
            >
              <Text style={[styles.bigButtonText, { color: colors.text }]}>CERRAR</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

      {/* Modal: elegir/cambiar el PUESTO de esta tablet (obligatorio si no hay ninguno guardado) */}
      <PuestoSelectorModal
        visible={showPuestoSelector}
        puestos={config?.puestos || []}
        puestoActual={puesto}
        onElegir={elegirPuesto}
        onClose={() => setShowPuestoSelector(false)}
        obligatorio={!puesto}
      />
    </SafeAreaView>
  );
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
