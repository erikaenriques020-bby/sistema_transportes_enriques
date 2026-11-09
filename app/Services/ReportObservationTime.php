<?php

namespace App\Services;

use DateTimeImmutable;

class ReportObservationTime
{
    public static function parse(array $row, array $fields): ?string
    {
        $dateText = isset($fields['date']) ? ($row[$fields['date']] ?? '') : '';
        $timeText = isset($fields['time']) ? ($row[$fields['time']] ?? '') : '';
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateText)) {
            $dateText = substr($dateText, 0, 10);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} (\d{2}:\d{2}:\d{2})$/', $timeText, $matches)) {
            $timeText = $matches[1];
        }
        $text = isset($fields['datetime']) ? trim($row[$fields['datetime']] ?? '') : trim($dateText.' '.$timeText);
        if ($text === '' || (! isset($fields['datetime']) && (! isset($fields['date']) || ! isset($fields['time'])))) {
            return null;
        }
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y H:i:s', 'd-m-Y H:i'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $text);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        return null;
    }
}
