<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Services\TickettService;

/**
 * JSON-LD (schema.org), robots.txt, sitemap.xml e llms.txt, gerados de content/ e do catálogo Tickett.
 * Todas as URLs saem absolutas com config('base_url'). Ver docs/plano-implementacao.md, secção 6.
 */
final class Seo
{
    private const TZ = 'Europe/Lisbon';

    private const AVAILABILITY = [
        'available' => 'https://schema.org/InStock',
        'low' => 'https://schema.org/LimitedAvailability',
        'sold_out' => 'https://schema.org/SoldOut',
        'upcoming' => 'https://schema.org/PreOrder',
    ];

    private const BOTS_IA = [
        'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-User', 'Claude-SearchBot',
        'PerplexityBot', 'Google-Extended', 'Applebot-Extended', 'CCBot',
    ];

    private const PAGINAS = [
        '/bilhetes' => 'Bilhetes',
        '/cartaz' => 'Cartaz',
        '/informacoes' => 'Informações',
        '/como-chegar' => 'Como chegar',
    ];

    private const LEGAIS = [
        '/acessibilidade' => 'Acessibilidade',
        '/privacidade' => 'Privacidade',
        '/cookies' => 'Cookies',
        '/termos' => 'Termos',
    ];

    /**
     * MusicFestival com datas, local, artistas e um Offer por bilhete.
     * $catalog é o resultado de TickettService::getCatalog(). Sem catálogo válido, omite offers.
     */
    public static function event(array $festival, array $artistas, array $catalog): array
    {
        $event = [
            '@context' => 'https://schema.org',
            '@type' => 'MusicFestival',
            'name' => self::nome($festival),
            'description' => $festival['resumo'],
            'startDate' => self::inicio($festival),
            'endDate' => self::fim($festival),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => self::local($festival['local']),
            'organizer' => [
                '@type' => 'Organization',
                'name' => $festival['organizador']['nome'],
                'url' => $festival['organizador']['url'],
            ],
            'performer' => array_map(
                static fn (array $artista): array => [
                    '@type' => 'PerformingGroup',
                    'name' => $artista['nome'],
                    'url' => self::abs(Rotas::url('artista', ['slug' => $artista['slug']])),
                ],
                $artistas
            ),
            'image' => self::abs('/assets/img/og.jpg'),
            'url' => self::abs(Rotas::url('inicio')),
            'inLanguage' => self::linguagem(),
        ];

        $offers = self::offers($catalog);
        if ($offers !== []) {
            $event['offers'] = $offers;
        }

        return $event;
    }

    /**
     * FAQPage a partir de uma lista de pares ['p' => pergunta, 'r' => resposta].
     */
    public static function faq(array $perguntas): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(
                static fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['p'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['r']],
                ],
                array_values($perguntas)
            ),
        ];
    }

    /**
     * MusicGroup de um artista, com referência simples ao festival.
     */
    public static function artist(array $artista, array $festival): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'MusicGroup',
            'name' => $artista['nome'],
            'url' => self::abs(Rotas::url('artista', ['slug' => $artista['slug']])),
            'event' => [
                '@type' => 'MusicFestival',
                'name' => self::nome($festival),
                'startDate' => self::inicio($festival),
                'url' => self::abs(Rotas::url('inicio')),
            ],
        ];
    }

    /**
     * BreadcrumbList. Cada item é ['name' => ..., 'url' => caminho ou URL absoluta].
     */
    public static function breadcrumbs(array $items): array
    {
        $lista = [];
        foreach (array_values($items) as $i => $item) {
            $lista[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => self::abs($item['url']),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $lista,
        ];
    }

    /**
     * robots.txt: tudo permitido exceto /kit, com blocos explícitos para os crawlers de IA e o sitemap.
     */
    public static function robots(): string
    {
        $linhas = ['User-agent: *', 'Allow: /', 'Disallow: /kit', 'Disallow: /checkout', ''];
        foreach (self::BOTS_IA as $bot) {
            $linhas[] = 'User-agent: ' . $bot;
            $linhas[] = 'Allow: /';
            $linhas[] = 'Disallow: /kit';
            $linhas[] = 'Disallow: /checkout';
            $linhas[] = '';
        }
        $linhas[] = 'Sitemap: ' . self::abs('/sitemap.xml');

        return implode("\n", $linhas) . "\n";
    }

    /**
     * sitemap.xml com as duas versões de cada página e os alternates (xhtml:link). lastmod é a data do ficheiro de content/ mais recente.
     */
    public static function sitemap(array $artistas): string
    {
        $slugs = array_map(static fn (array $artista): string => (string) $artista['slug'], $artistas);
        $lastmod = date('Y-m-d', self::conteudoMaisRecente());

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach (Rotas::pares($slugs) as $par) {
            $alternates = '    <xhtml:link rel="alternate" hreflang="pt-PT" href="' . e(self::abs($par['pt'])) . '"/>' . "\n"
                . '    <xhtml:link rel="alternate" hreflang="en" href="' . e(self::abs($par['en'])) . '"/>' . "\n"
                . '    <xhtml:link rel="alternate" hreflang="x-default" href="' . e(self::abs($par['pt'])) . '"/>' . "\n";
            foreach ([$par['pt'], $par['en']] as $caminho) {
                $xml .= '  <url>' . "\n"
                    . '    <loc>' . e(self::abs($caminho)) . '</loc>' . "\n"
                    . '    <lastmod>' . $lastmod . '</lastmod>' . "\n"
                    . $alternates
                    . '  </url>' . "\n";
            }
        }

        return $xml . '</urlset>' . "\n";
    }

    /**
     * llms.txt em markdown: factos, cartaz, FAQ e páginas. Campos por confirmar ficam marcados.
     */
    public static function llms(array $festival, array $artistas, array $faq, array $catalog): string
    {
        $confirmar = ' (Informação a confirmar)';
        $local = $festival['local'];

        $linhas = [
            '# ' . self::nome($festival),
            '',
            '> ' . $festival['resumo'],
            '',
            'Os campos marcados com "(Informação a confirmar)" dependem ainda da organização.',
            '',
            '## Factos',
            '',
            '- Datas: ' . \App\Helpers\Format::range($festival['datas']['inicio'], $festival['datas']['fim']) . $confirmar,
        ];

        foreach ($festival['dias'] as $dia) {
            $linha = '- Portas (' . Format::date($dia['data'], 'weekday') . '): ' . Format::time($dia['portas']);
            $linhas[] = $linha . (!empty($dia['rever']) ? $confirmar : '');
        }

        $linhas[] = '- Local: ' . $local['nome'] . ', ' . $local['zona'];
        $linhas[] = '- Morada: ' . ($local['morada'] ?? '(Informação a confirmar)');
        $linhas[] = '- Idade mínima: ' . (isset($festival['idade_minima'])
            ? (string) $festival['idade_minima']
            : '(Informação a confirmar)');
        $linhas[] = '- Organizador: ' . $festival['organizador']['nome'] . ' (' . $festival['organizador']['url'] . ')';
        if (!empty($festival['producao'])) {
            $linhas[] = '- Produção: ' . implode(', ', $festival['producao']);
        }

        $minimo = self::precoMinimo($catalog);
        $bilhetes = self::abs('/bilhetes');
        $taxa = $minimo !== null && !(new TickettService(config('tickett')))->taxaIncluida() ? ' + taxa de serviço' : '';
        $linhas[] = $minimo !== null
            ? '- Bilhetes: desde ' . Format::price($minimo) . $taxa . ', em ' . $bilhetes
            : '- Bilhetes: ' . $bilhetes;
        $checkout = $catalog['event']['checkout_url'] ?? (new TickettService(config('tickett')))->checkoutUrl();
        $linhas[] = '- Onde comprar: Tickett, em ' . $checkout;

        $linhas[] = '';
        $linhas[] = '## Cartaz';
        foreach ($festival['dias'] as $dia) {
            $nomes = array_values(array_filter(
                $artistas,
                static fn (array $artista): bool => $artista['dia'] === $dia['id']
                    || in_array($dia['id'], $artista['dias'] ?? [], true)
            ));
            usort($nomes, static fn (array $a, array $b): int => $a['destaque'] <=> $b['destaque']);

            $linhas[] = '';
            $linhas[] = '### ' . Format::date($dia['data'], 'weekday');
            $linhas[] = '';
            foreach ($nomes as $artista) {
                $linhas[] = '- ' . $artista['nome'];
            }
        }

        $linhas[] = '';
        $linhas[] = '## Perguntas frequentes';
        foreach ($faq['grupos'] as $grupo) {
            $linhas[] = '';
            $linhas[] = '### ' . $grupo['titulo'];
            $linhas[] = '';
            foreach ($grupo['perguntas'] as $pergunta) {
                $resposta = $pergunta['r'] . (!empty($pergunta['rever']) ? $confirmar : '');
                $linhas[] = '**' . $pergunta['p'] . '** ' . $resposta;
                $linhas[] = '';
            }
        }

        $linhas[] = '## Páginas';
        $linhas[] = '';
        $linhas[] = '- Início: ' . self::abs('/');
        foreach (self::PAGINAS as $caminho => $nome) {
            $linhas[] = '- ' . $nome . ': ' . self::abs($caminho);
        }
        foreach ($artistas as $artista) {
            $linhas[] = '- ' . $artista['nome'] . ': ' . self::abs('/artistas/' . $artista['slug']);
        }
        foreach (self::LEGAIS as $caminho => $nome) {
            $linhas[] = '- ' . $nome . ': ' . self::abs($caminho);
        }

        return rtrim(implode("\n", $linhas)) . "\n";
    }

    /** Valor de inLanguage (schema.org) para o idioma da página. */
    private static function linguagem(): string
    {
        return idioma() === 'en' ? 'en' : 'pt-PT';
    }

    private static function nome(array $festival): string
    {
        return $festival['nome'] . ' ' . $festival['edicao'];
    }

    private static function inicio(array $festival): string
    {
        return self::instante($festival['datas']['inicio'], $festival['dias'][0]['portas'] ?? null);
    }

    private static function fim(array $festival): string
    {
        $dias = $festival['dias'];
        $ultimo = $dias === [] ? null : $dias[count($dias) - 1];

        return self::instante($festival['datas']['fim'], $ultimo['portas'] ?? null);
    }

    /**
     * Data (e hora das portas, se houver) no fuso de Lisboa, com offset: 2027-07-23T16:00:00+01:00.
     */
    private static function instante(string $data, ?string $hora): string
    {
        if ($hora === null || preg_match('/^\d{1,2}:\d{2}$/', $hora) !== 1) {
            return $data;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $data . ' ' . $hora, new \DateTimeZone(self::TZ));

        return $dt === false ? $data : $dt->format('c');
    }

    private static function local(array $local): array
    {
        $morada = $local['morada'] ?? null;
        $endereco = [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Lisboa',
            'addressRegion' => $local['zona'],
            'addressCountry' => 'PT',
        ];
        if ($morada !== null) {
            $endereco['streetAddress'] = $morada;
        }

        $place = [
            '@type' => 'Place',
            'name' => $local['nome'],
            'address' => $endereco,
        ];
        $geo = self::geo($local['geo'] ?? null);
        if ($geo !== null) {
            $place['geo'] = $geo;
        }

        return $place;
    }

    /**
     * Aceita {"lat", "lng"} ou {"latitude", "longitude"}. Outro formato devolve null.
     */
    private static function geo(mixed $geo): ?array
    {
        if (!is_array($geo)) {
            return null;
        }
        $lat = $geo['lat'] ?? $geo['latitude'] ?? null;
        $lng = $geo['lng'] ?? $geo['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }

        return ['@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng];
    }

    private static function offers(array $catalog): array
    {
        if (($catalog['ok'] ?? false) !== true || !is_array($catalog['products'] ?? null)) {
            return [];
        }

        $moeda = $catalog['event']['currency'] ?? 'EUR';
        $offers = [];
        foreach ($catalog['products'] as $produto) {
            // Sem valor conhecido não há Offer (o schema exige price).
            $valor = $produto['total'] ?? $produto['price'] ?? null;
            if ($valor === null) {
                continue;
            }
            $offer = [
                '@type' => 'Offer',
                'name' => $produto['name'],
                'price' => (float) $valor,
                'priceCurrency' => $moeda,
                'availability' => self::AVAILABILITY[$produto['status'] ?? 'available'] ?? 'https://schema.org/InStock',
                'url' => $produto['checkout_url'],
            ];
            $validFrom = self::validFrom($produto, $catalog['event'] ?? null);
            if ($validFrom !== null) {
                $offer['validFrom'] = $validFrom;
            }
            $offers[] = $offer;
        }

        return $offers;
    }

    /**
     * Abertura da venda, só para bilhetes que ainda não estão à venda.
     */
    private static function validFrom(array $produto, ?array $evento): ?string
    {
        if (($produto['status'] ?? '') !== 'upcoming') {
            return null;
        }
        $abre = $produto['next_batch']['opens_at'] ?? $evento['sale']['opens_at'] ?? null;

        return is_string($abre) && $abre !== '' ? $abre : null;
    }

    private static function precoMinimo(array $catalog): ?float
    {
        $precos = [];
        foreach ($catalog['products'] ?? [] as $produto) {
            $valor = $produto['total'] ?? $produto['price'] ?? null;
            if (($produto['status'] ?? '') !== 'sold_out' && $valor !== null) {
                $precos[] = (float) $valor;
            }
        }

        return $precos === [] ? null : min($precos);
    }

    private static function conteudoMaisRecente(): int
    {
        $datas = array_map('filemtime', glob(ROOT . '/content/*.json') ?: []);

        return $datas === [] ? time() : max($datas);
    }

    /**
     * URL absoluta com base em config('base_url'). Caminhos começados por "/" são acrescentados à base.
     */
    private static function abs(string $caminho): string
    {
        if (preg_match('#^https?://#', $caminho) === 1) {
            return $caminho;
        }

        return rtrim((string) config('base_url'), '/') . '/' . ltrim($caminho, '/');
    }
}
