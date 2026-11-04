// Persistencia del PUESTO de control de calidad asignado a esta tablet.
// Cada tablet es un puesto fijo: se elige una sola vez y queda guardado en el
// dispositivo (AsyncStorage), no depende del usuario que loguea.
import AsyncStorage from "@react-native-async-storage/async-storage";

const KEY = "eol.puesto";

// Devuelve { id, name } o null si todavia no se eligio puesto en esta tablet.
export async function getPuestoGuardado() {
  try {
    const raw = await AsyncStorage.getItem(KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (parsed && parsed.id != null) return parsed;
    return null;
  } catch (e) {
    return null;
  }
}

// Persiste el puesto elegido para esta tablet. puesto = { id, name }.
export async function guardarPuesto(puesto) {
  try {
    if (!puesto || puesto.id == null) return false;
    const data = { id: puesto.id, name: puesto.name };
    await AsyncStorage.setItem(KEY, JSON.stringify(data));
    return true;
  } catch (e) {
    return false;
  }
}

// Borra el puesto guardado (por si hace falta resetear la tablet).
export async function borrarPuesto() {
  try {
    await AsyncStorage.removeItem(KEY);
  } catch (e) {
    // noop
  }
}
