import React, { useEffect, useRef } from "react";
import { View, Image, Text, Animated, Easing } from "react-native";

// Splash de arranque de DOORTRIM DEFECTOS: fondo negro, logo TBAR rojo
// (inverso de EOL Defectos, que es fondo blanco) y una barra de carga
// indeterminada debajo. Se muestra ~2s al abrir la app.
export default function SplashTbar() {
  const anim = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.loop(
      Animated.timing(anim, {
        toValue: 1,
        duration: 1100,
        easing: Easing.inOut(Easing.ease),
        useNativeDriver: true,
      })
    ).start();
  }, [anim]);

  const BAR_W = 180;
  const CHUNK = 70;
  const translateX = anim.interpolate({
    inputRange: [0, 1],
    outputRange: [-CHUNK, BAR_W],
  });

  return (
    <View style={{ flex: 1, backgroundColor: "#000000", alignItems: "center", justifyContent: "center" }}>
      <Image
        source={require("../../assets/logo-tbar-sinfondo.png")}
        resizeMode="contain"
        style={{ width: 220, height: 112 }}
      />
      <Text style={{ color: "#ffffff", fontSize: 26, fontWeight: "bold", letterSpacing: 2, marginTop: 24 }}>
        DOORTRIM DEFECTOS
      </Text>

      <View
        style={{
          width: BAR_W,
          height: 5,
          borderRadius: 999,
          backgroundColor: "#262626",
          overflow: "hidden",
          marginTop: 28,
        }}
      >
        <Animated.View
          style={{
            width: CHUNK,
            height: "100%",
            borderRadius: 999,
            backgroundColor: "#dc2626",
            transform: [{ translateX }],
          }}
        />
      </View>
    </View>
  );
}
