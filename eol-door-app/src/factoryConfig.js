// Resolucion de imagenes desde la factory-config, PORTADO de la web
// (frontend/src/services/FactoryConfigService.js -> getConfiguredModelImagesForKanban).
// Asi la tablet muestra exactamente lo que se carga en /config (misma fuente que la web).

const labelTypeCode = (tipo) => {
  if (tipo === "BC") return "BACK";
  if (tipo === "CS") return "CUSHION";
  return String(tipo || "").toUpperCase();
};

const labelSideCode = (lado) => {
  if (lado === "00") return "100%";
  return String(lado || "").toUpperCase();
};

// Lista de defectos configurados (los mismos que carga la web en /config).
// Forma: { id, codigo, nombre, categoryId, categoria, color, canBeMinor, puestoIds }.
// puestoIds: ids de puesto donde aparece este defecto. Vacio/ausente = aparece
// en TODOS los puestos (mismo criterio que la web).
export function getConfiguredDefects(config) {
  const cats = Object.fromEntries(
    (config?.defectCategories || []).map((c) => [c.id, c])
  );
  return (config?.defects || [])
    .filter((d) => d.active !== false)
    .map((d) => ({
      id: d.id,
      codigo: d.code,
      nombre: d.name,
      categoryId: d.categoryId,
      categoria: cats[d.categoryId]?.name || d.category || "Otros",
      categoriaColor: cats[d.categoryId]?.color || d.color,
      color: d.color,
      canBeMinor: Boolean(d.canBeMinor),
      puestoIds: Array.isArray(d.puestoIds) ? d.puestoIds : [],
    }));
}

// Un defecto aparece en un puesto si no tiene puestoIds (aparece en todos)
// o si el puesto esta explicitamente en su lista.
export function defectoAplicaAPuesto(defecto, puestoId) {
  const ids = defecto?.puestoIds;
  if (!Array.isArray(ids) || ids.length === 0) return true;
  if (puestoId == null) return false;
  return ids.includes(puestoId);
}

// Categorias de defecto configuradas (Apariencia / Funcionalidad). Mismas que la web.
export function getDefectCategories(config) {
  const cats = (config?.defectCategories || []).filter((c) => c.active !== false);
  if (cats.length > 0) return cats;
  return [{ id: "sin-categoria", code: "DEFECTOS", name: "Defectos", color: "FACC15" }];
}

const normCat = (v) => String(v || "").trim().toUpperCase();

// A que categoria pertenece un defecto. Igual criterio que la web:
// usa categoryId si lo tiene; si no, matchea por code/name; si no, por palabras clave.
export function resolveDefectCategoryId(defect, categories = []) {
  if (defect?.categoryId) return defect.categoryId;

  const raw = normCat(defect?.category || defect?.categoria);
  const found = categories.find((c) => {
    const code = normCat(c.code);
    const name = normCat(c.name);
    return raw === code || raw === name || (code && raw.includes(code)) || (name && raw.includes(name));
  });
  if (found) return found.id;

  const text = normCat(`${defect?.codigo || ""} ${defect?.nombre || ""}`);
  const funcKw = ["ROT", "ROTO", "ROTURA", "FALTA", "FALTANTE", "QUEBR", "ENSAMBLE", "SUELT", "CORT", "DESPEG", "AIRBAG", "CLIP", "TRABA", "FUNC"];
  const fb = funcKw.some((k) => text.includes(k)) ? "FUNC" : "APAR";
  return categories.find((c) => normCat(c.code).includes(fb))?.id || categories[0]?.id || "sin-categoria";
}

// Devuelve las vistas/fotos configuradas para el modelo del kanban/defecto,
// en la misma forma que espera AsientoViewer ({ imagen, tipo:{tipo}, lado:{lado}, nombre }).
export function getConfiguredModelImagesForKanban(kanbanData, config) {
  if (!config) return [];

  const modelCode = String(kanbanData?.modelo || "").toUpperCase();
  const model = (config.models || []).find(
    (item) =>
      item.active !== false &&
      (String(item.code || "").toUpperCase() === modelCode ||
        String(item.name || "").toUpperCase() === modelCode)
  );

  if (!model) return [];

  const typeCode = labelTypeCode(kanbanData?.tipo);
  const sideCode = labelSideCode(kanbanData?.lado);
  const typeById = Object.fromEntries((config.types || []).map((t) => [t.id, t]));
  const sideById = Object.fromEntries((config.sides || []).map((s) => [s.id, s]));

  const forModel = (config.modelImages || []).filter(
    (image) => image.active !== false && image.modelId === model.id
  );

  const matched = forModel.filter((image) => {
    const type = typeById[image.typeId];
    const side = sideById[image.sideId];
    return (
      String(type?.code || "").toUpperCase() === typeCode &&
      String(side?.code || "").toUpperCase() === sideCode
    );
  });

  // Si no matchea tipo/lado exacto, mostramos todas las vistas del modelo
  // (para no ocultar la foto). Mismo criterio tolerante que el visor.
  const list = matched.length > 0 ? matched : forModel;

  return list
    .slice()
    .sort((a, b) => Number(a.sequence || 0) - Number(b.sequence || 0))
    .map((image) => {
      const type = typeById[image.typeId] || {};
      const side = sideById[image.sideId] || {};
      return {
        id: image.id,
        nombre: image.viewName || image.name,
        viewName: image.viewName || image.name,
        imagen: image.imageDataUrl || image.remotePath || "",
        tipo: { id: image.typeId, tipo: type.code },
        lado: { id: image.sideId, lado: side.code },
        defectIds: image.defectIds || [],
        configurable: true,
      };
    });
}

// Fotos de un door trim. Los modelos por numero de parte suelen tener solo el
// dibujo SVG generico de /config (Android no lo muestra); en ese caso se usan
// las fotos del modelo SD (ej: 67620-0KB80 -> P1A), que son las fotos reales.
export function vistasDoorTrim(d, cfg) {
  const sinSvg = (vs) => vs.filter((v) => !String(v.imagen || "").startsWith("data:image/svg"));
  let vistas = sinSvg(getConfiguredModelImagesForKanban(d, cfg));
  if (vistas.length === 0) {
    const codigo = String(d?.modelo || "").toUpperCase();
    const modelo = (cfg?.models || []).find((m) =>
      [m.code, m.name].some((s) => String(s || "").toUpperCase() === codigo)
    );
    const sd = modelo?.jitms?.sd;
    if (sd) vistas = sinSvg(getConfiguredModelImagesForKanban({ ...d, modelo: sd }, cfg));
  }
  return vistas;
}
