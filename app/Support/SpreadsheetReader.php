<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first sheet of a CSV or Excel (.xlsx) file into rows of strings.
 * Small and dependency-free — enough for calling-data uploads.
 */
class SpreadsheetReader
{
    /** @return array<int, array<int, string>> */
    public static function read(string $path, string $extension, int $maxRows = 10000): array
    {
        return match (strtolower($extension)) {
            'csv', 'txt' => self::csv($path, $maxRows),
            'xlsx' => self::xlsx($path, $maxRows),
            default => throw new RuntimeException('Only CSV and Excel (.xlsx) files are supported.'),
        };
    }

    private static function csv(string $path, int $maxRows): array
    {
        $handle = fopen($path, 'r');
        $first = fgets($handle) ?: '';
        // Excel "CSV UTF-8" files start with a BOM; semicolon CSVs are common too.
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false && count($rows) < $maxRows + 1) {
            if (count($rows) === 0 && isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
            }
            $rows[] = array_map(fn ($v) => trim((string) $v), $row);
        }
        fclose($handle);

        return $rows;
    }

    private static function xlsx(string $path, int $maxRows): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open the Excel file.');
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach (self::xml($xml)->si as $si) {
                // Plain <t> or rich text runs <r><t>
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map(fn ($r) => (string) $r->t, iterator_to_array($si->r, false)));
            }
        }

        $sheetPath = self::firstSheetPath($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('The Excel file has no readable sheet.');
        }

        $rows = [];
        foreach (self::xml($sheetXml)->sheetData->row as $row) {
            if (count($rows) >= $maxRows + 1) {
                break;
            }
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::columnIndex((string) $c['r']);
                $type = (string) $c['t'];
                $value = match ($type) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    default => (string) $c->v,
                };
                // Phone numbers stored as numbers may come back as 9.87E+9
                if ($type === '' && is_numeric($value) && stripos($value, 'e') !== false) {
                    $value = number_format((float) $value, 0, '.', '');
                }
                $cells[$col] = trim($value);
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $rows[] = array_map(fn ($i) => $cells[$i] ?? '', range(0, $max));
            }
        }

        return $rows;
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook && $rels) {
            $wb = self::xml($workbook);
            $firstSheet = $wb->sheets->sheet[0] ?? null;
            $rid = $firstSheet?->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

            foreach (self::xml($rels)->Relationship as $rel) {
                if ((string) $rel['Id'] === (string) $rid) {
                    $target = ltrim((string) $rel['Target'], '/');
                    return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function columnIndex(string $ref): int
    {
        $letters = preg_replace('/\d+/', '', strtoupper($ref));
        $index = 0;
        foreach (str_split($letters) as $ch) {
            $index = $index * 26 + (ord($ch) - 64);
        }

        return max(0, $index - 1);
    }

    private static function xml(string $content): SimpleXMLElement
    {
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false) {
            throw new RuntimeException('The Excel file is damaged or not a valid .xlsx file.');
        }

        return $xml;
    }
}
