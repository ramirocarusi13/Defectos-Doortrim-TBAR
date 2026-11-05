// Persistencia del MINI BUFFER asignado a esta tablet (mismo criterio que
// puesto.js): se elige una sola vez por dispositivo y queda guardado en
// AsyncStorage. Lo usan tanto la pantalla de DOOR (a que buffer va la pieza
// defectuosa / de donde sale el reemplazo) como la de BUFFER (retrabajador).
import AsyncStorage from "@react-native-async-storage/async-storage";

const KEY = "eol.buffer";

// Devuelve { id, name } o null si todavia no se eligio buffer en esta tablet.
export async function getBufferGuardado() {
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

// Persiste el buffer elegido para esta tablet. buffer = { id, name }.
export async function guardarBuffer(buffer) {
  try {
    if (!buffer || buffer.id == null) return false;
    const data = { id: buffer.id, name: buffer.name };
    await AsyncStorage.setItem(KEY, JSON.stringify(data));
    return true;
  } catch (e) {
    return false;
  }
}

// Borra el buffer guardado (por si hace falta resetear la tablet).
export async function borrarBuffer() {
  try {
    await AsyncStorage.removeItem(KEY);
  } catch (e) {
    // noop
  }
}
