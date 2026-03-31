<?php

namespace App\Services\Microregiones;

/**
 * Encuadre aproximado del estado de Puebla (INEGI / vista de mapa estatal).
 */
final class PueblaStateBounds
{
    public const SOUTH = 17.862;

    public const NORTH = 20.895;

    public const WEST = -98.765;

    public const EAST = -96.708;

    /**
     * @return array{south: float, north: float, west: float, east: float}
     */
    public static function asArray(): array
    {
        return [
            'south' => self::SOUTH,
            'north' => self::NORTH,
            'west' => self::WEST,
            'east' => self::EAST,
        ];
    }
}
