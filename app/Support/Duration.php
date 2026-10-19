<?php

namespace App\Support;

/** Formats second counts the way the portal UI shows them. */
class Duration
{
    /** 19215 => "05:20:15" */
    public static function hms(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /** 19215 => "05h 20m" */
    public static function hm(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf('%02dh %02dm', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    /** 480 (minutes) => "08h 00m" */
    public static function minutes(int $minutes): string
    {
        return self::hm($minutes * 60);
    }

    public static function percent(int|float $value, int|float $of): int
    {
        return $of > 0 ? (int) min(100, round($value / $of * 100)) : 0;
    }
}
