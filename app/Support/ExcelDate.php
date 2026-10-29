<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Excel keeps dates as day numbers (39783.229 = 04 Dec 2008, 05:30 AM). Uploaded files therefore
 * arrive with numbers in date columns; this turns them back into dates — only in columns whose
 * title is about a date / time, so real numbers (age, salary, score) are never touched.
 */
class ExcelDate
{
    private const DATE_TITLE = '/date|dob|birth|\btime\b|timestamp|applied|joining|joined|\bstart|\bend\b|created|updated|\bon\b|\bat\b|\bday\b|expiry|valid/i';

    public static function format(string $title, mixed $value): mixed
    {
        if (! is_scalar($value) || ! is_numeric($value) || ! preg_match(self::DATE_TITLE, $title)) {
            return $value;
        }
        $serial = (float) $value;
        if ($serial < 20000 || $serial > 80000) {   // 1954 … 2119
            return $value;
        }
        // Day 25569 = 1 Jan 1970; Excel times have no zone, so read them as they are
        $at = Carbon::createFromTimestampUTC((int) round(($serial - 25569) * 86400));
        $hasTime = abs($serial - floor($serial)) > 0.0007;   // more than about a minute into the day

        return $hasTime ? $at->format('d M Y, h:i A') : $at->format('d M Y');
    }

    /** title => value with Excel dates converted. */
    public static function fields(array $fields): array
    {
        foreach ($fields as $title => $value) {
            $fields[$title] = self::format((string) $title, $value);
        }

        return $fields;
    }
}
