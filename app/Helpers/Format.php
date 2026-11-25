<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Datas, horas, preços e países no idioma da página (idioma(): pt em português de Portugal, en em inglês britânico).
 */
final class Format
{
    private const MESES = [
        1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];

    private const MESES_EN = [
        1 => 'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    // Índice: 0 = domingo (formato 'w' do PHP)
    private const DIAS = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

    private const DIAS_EN = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    // Países do cartaz (código ISO de 2 letras do artistas.json)
    private const PAISES = [
        'AO' => 'Angola', 'CV' => 'Cabo Verde', 'GW' => 'Guiné-Bissau', 'MZ' => 'Moçambique',
        'ST' => 'São Tomé e Príncipe', 'PT' => 'Portugal', 'BR' => 'Brasil', 'TL' => 'Timor-Leste',
        'GQ' => 'Guiné Equatorial',
    ];

    private const PAISES_EN = [
        'AO' => 'Angola', 'CV' => 'Cape Verde', 'GW' => 'Guinea-Bissau', 'MZ' => 'Mozambique',
        'ST' => 'São Tomé and Príncipe', 'PT' => 'Portugal', 'BR' => 'Brazil', 'TL' => 'Timor-Leste',
        'GQ' => 'Equatorial Guinea',
    ];

    /**
     * Data ISO (Y-m-d) no idioma da página.
     * pt: 'long' => "23 julho 2027"; 'weekday' => "Sexta, 23 julho".
     * en: 'long' => "23 July 2027"; 'weekday' => "Friday, 23 July".
     */
    public static function date(string $iso, string $style = 'long'): string
    {
        $date = self::parse($iso);
        $day = (int) $date->format('j');
        $month = self::mes((int) $date->format('n'));
        $year = $date->format('Y');

        return match ($style) {
            'long' => "{$day} {$month} {$year}",
            'weekday' => self::dia((int) $date->format('w')) . ", {$day} {$month}",
            default => throw new \InvalidArgumentException("Estilo de data desconhecido: {$style}"),
        };
    }

    /**
     * Intervalo de datas ISO.
     * pt, mesmo mês: "23 e 24 julho 2027". Meses diferentes: "23 julho a 1 agosto 2027".
     * en, mesmo mês: "23 and 24 July 2027". Meses diferentes: "23 July to 1 August 2027".
     */
    public static function range(string $startIso, string $endIso): string
    {
        $start = self::parse($startIso);
        $end = self::parse($endIso);
        $en = idioma() === 'en';

        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            return self::date($startIso);
        }

        $sameYear = $start->format('Y') === $end->format('Y');
        $sameMonth = $sameYear && $start->format('n') === $end->format('n');

        if ($sameMonth) {
            $month = self::mes((int) $end->format('n'));
            return $start->format('j') . ($en ? ' and ' : ' e ') . $end->format('j') . " {$month} " . $end->format('Y');
        }

        $from = $sameYear
            ? $start->format('j') . ' ' . self::mes((int) $start->format('n'))
            : self::date($startIso);

        return $from . ($en ? ' to ' : ' a ') . self::date($endIso);
    }

    /**
     * Preço em euros. pt: "30 €" ou "31,50 €". en: "€30" ou "€31.50".
     */
    public static function price(float|int $value): string
    {
        $value = round((float) $value, 2);
        $decimals = floor($value) === $value ? 0 : 2;

        if (idioma() === 'en') {
            return '€' . number_format($value, $decimals, '.', ',');
        }
        return number_format($value, $decimals, ',', ' ') . ' €';
    }

    /**
     * Hora HH:MM. pt: "16:00" => "16h00". en: "16:00" => "16:00".
     */
    public static function time(string $hhmm): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $m) !== 1) {
            throw new \InvalidArgumentException("Hora inválida (esperado HH:MM): {$hhmm}");
        }
        if (idioma() === 'en') {
            return sprintf('%02d:%s', (int) $m[1], $m[2]);
        }
        return (int) $m[1] . 'h' . $m[2];
    }

    /**
     * Instante ISO 8601 no fuso de Lisboa, sem ano: "2027-06-12T14:00:00+01:00" => "12 junho, 14h00" (en: "12 June, 14:00").
     */
    public static function dateTime(string $iso): string
    {
        try {
            $instant = new \DateTimeImmutable($iso);
        } catch (\Exception) {
            throw new \InvalidArgumentException("Data e hora inválidas (esperado ISO 8601): {$iso}");
        }
        $local = $instant->setTimezone(new \DateTimeZone('Europe/Lisbon'));

        return (int) $local->format('j') . ' ' . self::mes((int) $local->format('n'))
            . ', ' . self::time($local->format('H:i'));
    }

    /**
     * Nome de país no idioma da página a partir do código ISO de 2 letras. Null ou vazio dá ''.
     */
    public static function country(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '';
        }
        $tabela = idioma() === 'en' ? self::PAISES_EN : self::PAISES;
        return $tabela[$iso] ?? $iso;
    }

    /**
     * Iniciais de um nome: "Anna Joyce" => "AJ"; "Lura" => "LU".
     */
    public static function initials(string $nome): string
    {
        $words = preg_split('/\s+/u', trim($nome), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) >= 2) {
            $letters = mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1);
        } else {
            $letters = mb_substr($words[0] ?? '', 0, 2);
        }
        return mb_strtoupper($letters);
    }

    /**
     * Data ISO (Y-m-d) de há no máximo $dias dias, contados em Lisboa (hoje incluído). Datas futuras e vazias dão false.
     */
    public static function recente(?string $iso, int $dias = 14): bool
    {
        if ($iso === null || $iso === '') {
            return false;
        }
        $tz = new \DateTimeZone('Europe/Lisbon');
        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso, $tz);
        if ($data === false || $data->format('Y-m-d') !== $iso) {
            return false;
        }
        $hoje = new \DateTimeImmutable('today', $tz);
        $passados = (int) $data->diff($hoje)->format('%r%a');

        return $passados >= 0 && $passados <= $dias;
    }

    private static function mes(int $numero): string
    {
        return idioma() === 'en' ? self::MESES_EN[$numero] : self::MESES[$numero];
    }

    /**
     * Nome do dia da semana já com a inicial maiúscula (pt: "Sexta"; en: "Friday").
     */
    private static function dia(int $indice): string
    {
        return idioma() === 'en' ? self::DIAS_EN[$indice] : ucfirst(self::DIAS[$indice]);
    }

    private static function parse(string $iso): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
        if ($date === false || $date->format('Y-m-d') !== $iso) {
            throw new \InvalidArgumentException("Data inválida (esperado Y-m-d): {$iso}");
        }
        return $date;
    }
}
