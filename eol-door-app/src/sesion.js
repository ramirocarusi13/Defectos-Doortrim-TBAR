// Recuerda el usuario logueado en el dispositivo. En las tablets con dos apps
// (EOL Defectos y DOORTRIM DEFECTOS) Android puede recrear la pantalla al
// volver de la otra app; sin esto habria que poner el codigo cada vez.
// La sesion termina con "Cambiar usuario" o a las 12 horas (un turno largo).
import AsyncStorage from "@react-native-async-storage/async-storage";

const KEY = "doortrim_sesion";
const DURACION_MS = 12 * 60 * 60 * 1000;

export async function getSesionGuardada() {
  try {
    const raw = await AsyncStorage.getItem(KEY);
    if (!raw) return null;
    const s = JSON.parse(raw);
    if (!s?.user || !s?.at || Date.now() - s.at > DURACION_MS) {
      await AsyncStorage.removeItem(KEY);
      return null;
    }
    return s.user;
  } catch (e) {
    return null;
  }
}

export async function guardarSesion(user) {
  try {
    await AsyncStorage.setItem(KEY, JSON.stringify({ user, at: Date.now() }));
  } catch (e) {
    /* sin almacenamiento: se vuelve a pedir el codigo, nada mas */
  }
}

export async function borrarSesion() {
  try {
    await AsyncStorage.removeItem(KEY);
  } catch (e) {
    /* nada */
  }
}
