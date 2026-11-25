<?php

declare(strict_types=1);

namespace App\Services;

final class Content
{
    /** @var array<string, array> */
    private static array $cache = [];

    /**
     * Lê content/{$name}.json no idioma da página. Em inglês, funde content/en/{$name}.json por cima do português:
     * objetos fundem-se chave a chave; listas fundem-se por posição (mesma ordem); artistas.json funde por slug.
     * Cache estático por pedido.
     */
    public static function get(string $name): array
    {
        $lang = idioma();
        $chave = $lang . ':' . $name;
        if (isset(self::$cache[$chave])) {
            return self::$cache[$chave];
        }

        $base = self::ler($name, ROOT . '/content/' . $name . '.json');
        if ($lang === 'en') {
            $traduzido = self::ler($name, ROOT . '/content/en/' . $name . '.json');
            $base = $name === 'artistas'
                ? self::fundirArtistas($base, $traduzido)
                : array_replace_recursive($base, $traduzido);
        }

        return self::$cache[$chave] = $base;
    }

    /**
     * Texto de content/paginas/{$slug}.html no idioma da página (em inglês, content/en/paginas/).
     */
    public static function pagina(string $slug): string
    {
        if (preg_match('/^[a-z0-9-]+$/', $slug) !== 1) {
            throw new \InvalidArgumentException("Nome de página inválido: {$slug}");
        }
        $pasta = idioma() === 'en' ? 'content/en/paginas/' : 'content/paginas/';
        $ficheiro = ROOT . '/' . $pasta . $slug . '.html';
        if (!is_file($ficheiro)) {
            throw new \RuntimeException("Página legal em falta: {$pasta}{$slug}.html");
        }
        return (string) file_get_contents($ficheiro);
    }

    /**
     * Lê um JSON de content/. Ficheiro em falta ou inválido é erro. Ficheiro de tradução ausente devolve [].
     */
    private static function ler(string $name, string $file): array
    {
        if (preg_match('/^[a-z0-9-]+$/', $name) !== 1) {
            throw new \InvalidArgumentException("Nome de conteúdo inválido: {$name}");
        }

        if (!is_file($file)) {
            if (str_contains($file, '/content/en/')) {
                return [];
            }
            throw new \RuntimeException("Ficheiro de conteúdo em falta: content/{$name}.json");
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new \RuntimeException("Ficheiro de conteúdo em falta: content/{$name}.json");
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $ex) {
            throw new \RuntimeException("JSON inválido em {$file}: " . $ex->getMessage(), 0, $ex);
        }

        if (!is_array($data)) {
            throw new \RuntimeException("{$file} deve conter um objeto ou uma lista JSON.");
        }

        return $data;
    }

    /**
     * Artistas: a tradução é {slug: {campos}}. Campos traduzidos substituem os do português; o resto fica.
     */
    private static function fundirArtistas(array $base, array $traduzido): array
    {
        foreach ($base as $i => $artista) {
            $slug = $artista['slug'] ?? null;
            if ($slug !== null && isset($traduzido[$slug]) && is_array($traduzido[$slug])) {
                $base[$i] = array_replace($artista, $traduzido[$slug]);
            }
        }
        return $base;
    }
}
