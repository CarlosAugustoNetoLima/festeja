<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Mapa único de rotas pt ↔ en. Usado pelo router (public/index.php), pelo seletor de idioma, pela navegação,
 * pelo sitemap e pelo hreflang. Os {slug} de artistas são iguais nos dois idiomas.
 * /kit e /checkout (e os ficheiros gerados) não têm versão en e ficam fora deste mapa.
 */
final class Rotas
{
    /**
     * 'page': ficheiro em app/Views/Pages/ (sem .php). 'slug': valor fixo das páginas legais.
     */
    private const PAGINAS = [
        'inicio' => ['pt' => '/', 'en' => '/en', 'page' => 'home'],
        'cartaz' => ['pt' => '/cartaz', 'en' => '/en/line-up', 'page' => 'cartaz'],
        'bilhetes' => ['pt' => '/bilhetes', 'en' => '/en/tickets', 'page' => 'bilhetes'],
        'informacoes' => ['pt' => '/informacoes', 'en' => '/en/info', 'page' => 'informacoes'],
        'como-chegar' => ['pt' => '/como-chegar', 'en' => '/en/getting-there', 'page' => 'como-chegar'],
        'artista' => ['pt' => '/artistas/{slug}', 'en' => '/en/artists/{slug}', 'page' => 'artista'],
        'acessibilidade' => ['pt' => '/acessibilidade', 'en' => '/en/accessibility', 'page' => 'legal', 'slug' => 'acessibilidade'],
        'privacidade' => ['pt' => '/privacidade', 'en' => '/en/privacy', 'page' => 'legal', 'slug' => 'privacidade'],
        'cookies' => ['pt' => '/cookies', 'en' => '/en/cookies', 'page' => 'legal', 'slug' => 'cookies'],
        'termos' => ['pt' => '/termos', 'en' => '/en/terms', 'page' => 'legal', 'slug' => 'termos'],
    ];

    /**
     * Idioma de um caminho: /en e /en/... são inglês; o resto é português.
     */
    public static function idiomaDoCaminho(string $caminho): string
    {
        return $caminho === '/en' || str_starts_with($caminho, '/en/') ? 'en' : 'pt';
    }

    /**
     * Caminho de uma página no idioma pedido (predefinido: o atual). $params['slug'] preenche {slug} dos artistas.
     */
    public static function url(string $chave, array $params = [], ?string $idioma = null): string
    {
        $modelo = self::PAGINAS[$chave][$idioma ?? idioma()] ?? null;
        if ($modelo === null) {
            throw new \InvalidArgumentException("Página sem rota: {$chave}");
        }
        return strtr($modelo, ['{slug}' => (string) ($params['slug'] ?? '')]);
    }

    /**
     * Resolve um caminho (em pt ou en) para a página do mapa. Devolve chave, page, slug, params e os dois caminhos
     * (pt e en) da mesma página. Null se o caminho não for uma página com versão nos dois idiomas.
     */
    public static function resolver(string $caminho): ?array
    {
        foreach (self::PAGINAS as $chave => $definicao) {
            foreach (['pt', 'en'] as $lang) {
                $params = self::corresponde($definicao[$lang], $caminho);
                if ($params === null) {
                    continue;
                }
                if (isset($definicao['slug'])) {
                    $params['slug'] = $definicao['slug'];
                }
                return [
                    'chave' => $chave,
                    'page' => $definicao['page'],
                    'slug' => $params['slug'] ?? null,
                    'params' => $params,
                    'pt' => self::url($chave, $params, 'pt'),
                    'en' => self::url($chave, $params, 'en'),
                ];
            }
        }
        return null;
    }

    /**
     * Caminhos pt e en para o seletor de idioma. Sem página equivalente (ex.: 404, /kit), cada idioma vai para a inicial.
     */
    public static function trocaIdioma(string $caminho): array
    {
        $resolvida = self::resolver($caminho);
        return $resolvida !== null
            ? ['pt' => $resolvida['pt'], 'en' => $resolvida['en']]
            : ['pt' => self::url('inicio', [], 'pt'), 'en' => self::url('inicio', [], 'en')];
    }

    /**
     * Todas as páginas com as duas versões, para o sitemap: lista de ['pt' => caminho, 'en' => caminho].
     */
    public static function pares(array $slugsArtistas): array
    {
        $pares = [];
        foreach (self::PAGINAS as $chave => $definicao) {
            if ($chave === 'artista') {
                foreach ($slugsArtistas as $slug) {
                    $pares[] = ['pt' => self::url($chave, ['slug' => $slug], 'pt'), 'en' => self::url($chave, ['slug' => $slug], 'en')];
                }
                continue;
            }
            $params = isset($definicao['slug']) ? ['slug' => $definicao['slug']] : [];
            $pares[] = ['pt' => self::url($chave, $params, 'pt'), 'en' => self::url($chave, $params, 'en')];
        }
        return $pares;
    }

    /**
     * Devolve os parâmetros do padrão se o caminho corresponder, senão null. {nome} é um parâmetro ([a-z0-9-]+).
     */
    public static function corresponde(string $padrao, string $caminho): ?array
    {
        $regex = preg_replace_callback(
            '#\{(\w+)\}|[^{}]+#',
            static fn (array $m): string => isset($m[1])
                ? '(?P<' . $m[1] . '>[a-z0-9-]+)'
                : preg_quote($m[0], '#'),
            $padrao
        );

        if (preg_match('#^' . $regex . '$#', $caminho, $m) !== 1) {
            return null;
        }
        return array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
