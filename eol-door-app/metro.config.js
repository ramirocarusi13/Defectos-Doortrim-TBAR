// Config de Metro (bundler). Excluye del rastreo de archivos las carpetas
// build/ dentro de node_modules: en esta PC quedo un
// node_modules/react-native/build/reports/problems/problems-report.html con
// permisos que no se pueden leer ni borrar (EPERM), y Metro abortaba el bundle
// de release al intentar leerlo. Esas carpetas no forman parte del bundle.
const { getDefaultConfig } = require("expo/metro-config");

const config = getDefaultConfig(__dirname);

const previos = Array.isArray(config.resolver.blockList)
  ? config.resolver.blockList
  : config.resolver.blockList
    ? [config.resolver.blockList]
    : [];

config.resolver.blockList = [
  ...previos,
  /node_modules[\\/]react-native[\\/]build[\\/].*/,
  /[\\/]build[\\/]reports[\\/].*/,
];

module.exports = config;
