<?php

namespace App\Support;

class PositionName {
    public static function normalize(?string $value): string {
        $normalized = str_replace("'", '-', strval($value));
        $normalized = trim($normalized);
        return strtoupper($normalized);
    }

    public static function candidates(?string $value): array {
        $normalized = self::normalize($value);

        if ($normalized === '') {
            return [];
        }

        // En convivencia de formatos, la búsqueda debe respetar exactamente
        // la posición informada para no mezclar ubicaciones viejas/nuevas.
        return [$normalized];
    }

    public static function isRackPosition(?string $value): bool {
        $normalized = self::normalize($value);
        return self::parseRackPosition($normalized);
    }

    private static function parseRackPosition(string $normalized): bool {
        $parts = explode('-', $normalized);

        if (count($parts) !== 3) {
            return false;
        }

        [$rack, $middle, $level] = $parts;

        if (!preg_match('/^[A-H]$/', $rack) || !preg_match('/^[0-4]$/', $level)) {
            return false;
        }

        if (preg_match('/^(?:[1-9]|[12]\d|3[0-4])$/', $middle)) {
            return true;
        }

        if (preg_match('/^[A-X]$/', $middle)) {
            return true;
        }

        return false;
    }
}
