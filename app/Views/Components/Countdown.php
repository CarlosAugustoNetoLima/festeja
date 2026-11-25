<?php
// Contagem de dias até à data indicada. Calculada no servidor: sem JavaScript a mudar números.
// Props: target (ISO 8601 com fuso, ex.: 2027-07-23T16:00:00+01:00). Depois do início não mostra nada.

$tz = new DateTimeZone('Europe/Lisbon');
$hoje = (new DateTimeImmutable('now', $tz))->setTime(0, 0);
$alvo = (new DateTimeImmutable($target))->setTimezone($tz)->setTime(0, 0);
$dias = (int) $hoje->diff($alvo)->format('%r%a');

if ($dias >= 0) {
    $texto = match (true) {
        $dias === 0 => t('contagem.hoje'),
        $dias === 1 => t('contagem.um_dia'),
        default => t('contagem.dias', ['n' => $dias]),
    };
    ?>
<p class="fj-countdown"><time datetime="<?= e($target) ?>"><?= e($texto) ?></time></p>
<?php
}
