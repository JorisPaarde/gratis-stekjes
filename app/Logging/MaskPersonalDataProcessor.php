<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Maskeert e-mailadressen en IP-adressen in logberichten en context.
 */
final class MaskPersonalDataProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: self::mask($record->message),
            context: self::maskArray($record->context),
            extra: self::maskArray($record->extra),
        );
    }

    public static function mask(string $value): string
    {
        $value = (string) preg_replace(
            '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/',
            '[email]',
            $value
        );
        $value = (string) preg_replace(
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            '[ip]',
            $value
        );
        // IPv6 (vereenvoudigd)
        $value = (string) preg_replace(
            '/\b(?:[0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}\b/',
            '[ip]',
            $value
        );

        return $value;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function maskArray(array $data): array
    {
        $out = [];
        foreach ($data as $key => $item) {
            if (is_string($item)) {
                $out[$key] = self::mask($item);
            } elseif (is_array($item)) {
                $out[$key] = self::maskArray($item);
            } else {
                $out[$key] = $item;
            }
        }

        return $out;
    }
}
