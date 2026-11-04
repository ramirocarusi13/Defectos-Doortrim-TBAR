<?php

namespace App\Support;

class RackPositionFormatter {
    private const RACK_MIN = 'A';
    private const RACK_MAX = 'H';
    private const NUMERIC_ROW_MIN = 1;
    private const NUMERIC_ROW_MAX = 34;
    private const NUMERIC_LEVEL_MIN = 0;
    private const NUMERIC_LEVEL_MAX = 4;

    private const RACK_ROW_LABEL_TO_NUMBER = [
        'A' => 1,
        'B' => 2,
        'C' => 3,
        'D' => 4,
        'E' => 5,
        'F' => 6,
        'G' => 7,
        'H' => 8,
        'I' => 9,
        'J' => 10,
        'K' => 11,
        'L' => 12,
        'M' => 13,
        'N' => 14,
        'O' => 15,
        'P' => 16,
        'Q' => 17,
        'R' => 18,
        'S' => 19,
        'T' => 20,
        'U' => 21,
        'V' => 22,
        'W' => 23,
        'X' => 24,
    ];

    private const RACK_ROW_NUMBER_TO_LABEL = [
        1 => 'A',
        2 => 'B',
        3 => 'C',
        4 => 'D',
        5 => 'E',
        6 => 'F',
        7 => 'G',
        8 => 'H',
        9 => 'I',
        10 => 'J',
        11 => 'K',
        12 => 'L',
        13 => 'M',
        14 => 'N',
        15 => 'O',
        16 => 'P',
        17 => 'Q',
        18 => 'R',
        19 => 'S',
        20 => 'T',
        21 => 'U',
        22 => 'V',
        23 => 'W',
        24 => 'X',
    ];

    public static function normalize($value): string {
        return strtoupper(trim(str_replace("'", "-", strval($value ?? ''))));
    }

    public static function toNumeric($value): string {
        $normalized = self::normalize($value);

        if (preg_match('/^([' . self::RACK_MIN . '-' . self::RACK_MAX . '])-([1-9][0-9]*)-([0-9])$/', $normalized, $matches)) {
            $row = intval($matches[2]);
            $level = intval($matches[3]);
            if ($row >= self::NUMERIC_ROW_MIN && $row <= self::NUMERIC_ROW_MAX
                && $level >= self::NUMERIC_LEVEL_MIN && $level <= self::NUMERIC_LEVEL_MAX
            ) {
                return $matches[1] . '-' . str_pad($row, 2, '0', STR_PAD_LEFT) . '-' . $level;
            }

            return $normalized;
        }

        if (preg_match('/^([' . self::RACK_MIN . '-' . self::RACK_MAX . '])-([A-X])-([0-9])$/', $normalized, $matches)) {
            $row = self::RACK_ROW_LABEL_TO_NUMBER[$matches[2]];
            return $matches[1] . '-' . str_pad($row, 2, '0', STR_PAD_LEFT) . '-' . intval($matches[3]);
        }

        return $normalized;
    }

    public static function toLegacy($value): string {
        $normalized = self::normalize($value);

        if (preg_match('/^([' . self::RACK_MIN . '-' . self::RACK_MAX . '])-([A-X])-([0-9])$/', $normalized)) {
            return $normalized;
        }

        if (preg_match('/^([' . self::RACK_MIN . '-' . self::RACK_MAX . '])-([1-9][0-9]*)-([0-9])$/', $normalized, $matches)) {
            $rowNumber = intval($matches[2]);
            $level = intval($matches[3]);
            if ($rowNumber >= self::NUMERIC_ROW_MIN && $rowNumber <= self::NUMERIC_ROW_MAX
                && $level >= self::NUMERIC_LEVEL_MIN && $level <= self::NUMERIC_LEVEL_MAX
                && array_key_exists($rowNumber, self::RACK_ROW_NUMBER_TO_LABEL)
            ) {
                return $matches[1] . '-' . self::RACK_ROW_NUMBER_TO_LABEL[$rowNumber] . '-' . intval($matches[3]);
            }
        }

        return $normalized;
    }

    public static function toNumericUnpadded($value): string {
        $numeric = self::toNumeric($value);
        if (preg_match('/^([A-H])-0*([1-9][0-9]*)-([0-9])$/', $numeric, $m)) {
            return $m[1] . '-' . intval($m[2]) . '-' . intval($m[3]);
        }
        return $numeric;
    }

    public static function lookupCandidates($value): array {
        $normalized = self::normalize($value);
        $numeric = self::toNumeric($normalized);
        $unpadded = self::toNumericUnpadded($normalized);
        $legacy = self::toLegacy($normalized);

        $candidates = array_filter([$normalized, $numeric, $unpadded, $legacy], function ($candidate) {
            return $candidate !== '';
        });

        return array_values(array_unique($candidates));
    }
}
