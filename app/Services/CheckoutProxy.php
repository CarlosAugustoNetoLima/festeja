<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Proxy do checkout da Tickett no domínio do site (versão endurecida do proxy do festival-crato).
 *
 * - GET /checkout[?produto=ID_TIPO][&lang=pt|en]: busca {base}/{evento}?l=pt_pt|en_us[&produto=] e devolve o HTML
 *   reescrito, com o tema e a ponte. O idioma é o mecanismo nativo da Tickett: ?l= grava o idioma na sessão do checkout.
 * - GET|POST /checkout/relay/{caminho}: reencaminha as chamadas AJAX do checkout.js para {base}/{caminho}. Não leva
 *   ?l=: os fragmentos seguem o idioma da sessão (PHPSESSID, tk_PHPSESSID no browser), gravado na página acima.
 *
 * Cookies: os Set-Cookie do checkout chegam ao browser com prefixo tk_, Path=/checkout e HttpOnly.
 * Nos pedidos ao checkout só seguem os cookies tk_* (sem o prefixo). Nunca se enviam cookies do site.
 *
 * Limitações conhecidas: pagamentos com redirecionamento externo e FNAC (window.top) saem do proxy.
 */
final class CheckoutProxy
{
    private const PREFIXO_COOKIE = 'tk_';
    private const CAMINHO_COOKIE = '/checkout';
    private const PREFIXO_RELAY = '/checkout/relay/';
    private const MAX_CORPO = 65536;
    private const TIMEOUT_PAGINA = 15;
    private const TIMEOUT_RELAY = 10;
    private const MAX_REDIRECIONAMENTOS = 2;

    /** Idioma do site (?lang=) → código de idioma do checkout (?l=, o mesmo do rodapé real: en_us). Predefinido pt. */
    private const IDIOMAS = ['pt' => 'pt_pt', 'en' => 'en_us'];

    /** Caminhos globais (sem id de evento) usados com BASE_URL no checkout.js. */
    private const GLOBAIS = ['waiting-list'];

    private const CONTENT_TYPES_PEDIDO = ['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data', 'text/plain'];
    private const CONTENT_TYPES_RESPOSTA = ['text/html', 'application/json', 'text/plain'];

    private string $base;
    private string $eventId;

    public function __construct(array $cfg)
    {
        $this->base = rtrim((string) ($cfg['base'] ?? ''), '/');
        $this->eventId = preg_replace('/\D/', '', (string) ($cfg['event_id'] ?? '')) ?? '';
    }

    /**
     * Página do checkout (GET /checkout). $produto é ID_TIPO (ex.: 9001_1) e segue como ?produto= ao checkout.
     *
     * @return array{status: int, headers: list<string>, body: string}
     */
    public function pagina(string $metodo, mixed $produto, string $cookieHeader, bool $https): array
    {
        if ($metodo !== 'GET') {
            return $this->semCorpo(405, ['Allow: GET']);
        }
        // Idioma da página de erro (o proxy corre antes do router): o mesmo ?lang= do iframe, predefinido pt.
        $lang = ($_GET['lang'] ?? null) === 'en' ? 'en' : 'pt';
        if ($this->base === '' || $this->eventId === '') {
            return $this->paginaErro($https, $lang);
        }

        $produto = is_string($produto) && preg_match('/^[0-9]+_[0-9]+$/', $produto) === 1 ? $produto : null;
        // ?lang= vem do iframe (CheckoutModal, data-src); lido aqui como $_GET['produto'] é lido em public/index.php.
        $query = ['l' => $this->idioma($_GET['lang'] ?? null)];
        if ($produto !== null) {
            $query['produto'] = $produto;
        }
        $url = $this->base . '/' . $this->eventId . '?' . http_build_query($query);

        $resultado = $this->pedir('GET', $url, '', [
            'Accept: text/html',
            'Accept-Language: pt-PT,pt;q=0.9',
        ], $this->cookiesParaCheckout($cookieHeader, null), self::TIMEOUT_PAGINA);

        if ($resultado === null || $resultado['status'] !== 200) {
            return $this->paginaErro($https, $lang);
        }

        $html = $this->reescrever($resultado['body'], $produto ?? '');

        return [
            'status' => 200,
            'headers' => array_merge(
                ['Content-Type: text/html; charset=utf-8'],
                $this->cabecalhosSeguranca($https),
                $this->cookiesParaBrowser($resultado['setCookies'], $https)
            ),
            'body' => $html,
        ];
    }

    /**
     * Relay de uma chamada AJAX (GET|POST /checkout/relay/{caminho}).
     *
     * @return array{status: int, headers: list<string>, body: string}
     */
    public function relay(string $metodo, string $caminho, string $contentType, string $cookieHeader, bool $https): array
    {
        if ($metodo !== 'GET' && $metodo !== 'POST') {
            return $this->json(405, ['error' => 'method not allowed'], ['Allow: GET, POST']);
        }
        if (!$this->caminhoPermitido($caminho)) {
            return $this->json(400, ['error' => 'invalid path']);
        }

        $corpo = '';
        if ($metodo === 'POST') {
            $lido = $this->lerCorpo();
            if ($lido === null) {
                return $this->json(413, ['error' => 'payload too large']);
            }
            $corpo = $lido;
        }

        $ct = 'application/x-www-form-urlencoded';
        foreach (self::CONTENT_TYPES_PEDIDO as $permitido) {
            if (stripos($contentType, $permitido) === 0) {
                $ct = $permitido;
                break;
            }
        }

        $sid = null;
        if ($corpo !== '' && $ct === 'application/x-www-form-urlencoded') {
            parse_str($corpo, $dados);
            $sid = is_string($dados['sid'] ?? null) ? $dados['sid'] : null;
        }

        $cabecalhos = [
            'Accept: */*',
            'X-Requested-With: XMLHttpRequest',
            'Origin: ' . $this->base,
            'Referer: ' . $this->base . '/' . $this->eventId,
        ];
        if ($metodo === 'POST') {
            $cabecalhos[] = 'Content-Type: ' . $ct;
        }

        $resultado = $this->pedir($metodo, $this->base . '/' . $caminho, $corpo, $cabecalhos,
            $this->cookiesParaCheckout($cookieHeader, $sid), self::TIMEOUT_RELAY);

        if ($resultado === null) {
            return $this->json(502, ['error' => 'relay failed']);
        }

        $tipo = 'text/html; charset=utf-8';
        foreach (self::CONTENT_TYPES_RESPOSTA as $permitido) {
            if (stripos($resultado['contentType'], $permitido) === 0) {
                $tipo = $resultado['contentType'];
                break;
            }
        }

        return [
            'status' => $resultado['status'],
            'headers' => array_merge(
                ['Content-Type: ' . $tipo],
                $this->cabecalhosSeguranca($https),
                $this->cookiesParaBrowser($resultado['setCookies'], $https)
            ),
            'body' => $resultado['body'],
        ];
    }

    /**
     * Envia a resposta devolvida por pagina() ou relay(). Chamado por public/index.php.
     *
     * @param array{status: int, headers: list<string>, body: string} $resposta
     */
    public static function enviar(array $resposta): void
    {
        http_response_code($resposta['status']);
        foreach ($resposta['headers'] as $cabecalho) {
            // Vários Set-Cookie são permitidos; os restantes cabeçalhos substituem os anteriores.
            header($cabecalho, !str_starts_with($cabecalho, 'Set-Cookie:'));
        }
        echo $resposta['body'];
    }

    /** Só caminhos com id de evento (o do config) ou globais da lista. Sem '.', para não haver travessia. */
    private function caminhoPermitido(string $caminho): bool
    {
        if (in_array($caminho, self::GLOBAIS, true)) {
            return true;
        }
        if ($this->eventId === '' || preg_match('#^[0-9]+/[A-Za-z0-9/_-]+$#', $caminho) !== 1) {
            return false;
        }
        return str_starts_with($caminho, $this->eventId . '/');
    }

    /** Corpo do pedido com limite; null se passar de MAX_CORPO. */
    private function lerCorpo(): ?string
    {
        $entrada = fopen('php://input', 'rb');
        if ($entrada === false) {
            return '';
        }
        $corpo = stream_get_contents($entrada, self::MAX_CORPO + 1);
        fclose($entrada);
        if ($corpo === false) {
            return '';
        }
        return strlen($corpo) > self::MAX_CORPO ? null : $corpo;
    }

    /**
     * Cookies tk_* do browser → Cookie do checkout (sem prefixo). Se houver sid válido no corpo, é o PHPSESSID
     * (como o proxy do Crato, porque o checkout.js envia sid em cada POST).
     */
    private function cookiesParaCheckout(string $cookieHeader, ?string $sid): string
    {
        $pares = [];
        foreach (explode(';', $cookieHeader) as $par) {
            $par = trim($par);
            if (!str_starts_with($par, self::PREFIXO_COOKIE)) {
                continue;
            }
            $pos = strpos($par, '=');
            if ($pos === false) {
                continue;
            }
            $nome = substr($par, strlen(self::PREFIXO_COOKIE), $pos - strlen(self::PREFIXO_COOKIE));
            $valor = substr($par, $pos + 1);
            if (preg_match('/^[A-Za-z0-9_-]+$/', $nome) !== 1 || preg_match('/^[A-Za-z0-9%._~+\/=,-]*$/', $valor) !== 1) {
                continue;
            }
            $pares[$nome] = $valor;
        }

        if ($sid !== null && preg_match('/^[a-zA-Z0-9,-]{8,128}$/', $sid) === 1) {
            $pares['PHPSESSID'] = $sid;
        }

        $saida = [];
        foreach ($pares as $nome => $valor) {
            $saida[] = $nome . '=' . $valor;
        }
        return implode('; ', $saida);
    }

    /**
     * Set-Cookie do checkout → Set-Cookie do site: prefixo tk_, Path=/checkout, HttpOnly, SameSite=Lax,
     * Secure só em HTTPS. Mantém Expires/Max-Age; Domain e outros atributos são descartados.
     *
     * @param list<string> $setCookies
     * @return list<string>
     */
    private function cookiesParaBrowser(array $setCookies, bool $https): array
    {
        $saida = [];
        foreach ($setCookies as $linha) {
            $partes = array_map('trim', explode(';', $linha));
            $pos = strpos($partes[0], '=');
            if ($pos === false) {
                continue;
            }
            $nome = substr($partes[0], 0, $pos);
            $valor = substr($partes[0], $pos + 1);
            if (preg_match('/^[A-Za-z0-9_-]+$/', $nome) !== 1 || preg_match('/^[A-Za-z0-9%._~+\/=,-]*$/', $valor) !== 1) {
                continue;
            }

            $atributos = ['Path=' . self::CAMINHO_COOKIE, 'HttpOnly', 'SameSite=Lax'];
            foreach (array_slice($partes, 1) as $atributo) {
                if (preg_match('/^(Max-Age|Expires)=/i', $atributo) === 1) {
                    $atributos[] = $atributo;
                }
            }
            if ($https) {
                $atributos[] = 'Secure';
            }
            $saida[] = 'Set-Cookie: ' . self::PREFIXO_COOKIE . $nome . '=' . $valor . '; ' . implode('; ', $atributos);
        }
        return $saida;
    }

    /** Só pt e en passam; qualquer outro valor (ou ausência) cai no predefinido pt_pt. */
    private function idioma(mixed $lang): string
    {
        return is_string($lang) && isset(self::IDIOMAS[$lang]) ? self::IDIOMAS[$lang] : self::IDIOMAS['pt'];
    }

    /** @return list<string> */
    private function cabecalhosSeguranca(bool $https): array
    {
        return [
            'X-Frame-Options: SAMEORIGIN',
            "Content-Security-Policy: frame-ancestors 'self'",
            'Referrer-Policy: same-origin',
            'X-Content-Type-Options: nosniff',
            'Cache-Control: no-store',
            'X-Robots-Tag: noindex',
        ];
    }

    /**
     * Pedido HTTP ao checkout com cURL. Redirecionamentos só para o mesmo host (seguidos à mão, para que
     * os cookies nunca sigam para outro host). Devolve null em erro de rede, TLS ou redirect externo.
     *
     * @param list<string> $cabecalhos
     * @return array{status: int, setCookies: list<string>, contentType: string, body: string}|null
     */
    private function pedir(string $metodo, string $url, string $corpo, array $cabecalhos, string $cookies, int $timeout): ?array
    {
        $hostBase = parse_url($this->base, PHP_URL_HOST);
        $setCookies = [];

        for ($salto = 0; $salto <= self::MAX_REDIRECIONAMENTOS; $salto++) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }

            $cab = $cabecalhos;
            $cab[] = 'User-Agent: Mozilla/5.0';
            if ($cookies !== '') {
                $cab[] = 'Cookie: ' . $cookies;
            }

            $location = '';
            $opcoes = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => $cab,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADERFUNCTION => function ($curl, string $linha) use (&$setCookies, &$location): int {
                    if (stripos($linha, 'Set-Cookie:') === 0) {
                        $setCookies[] = trim(substr($linha, 11));
                    } elseif (stripos($linha, 'Location:') === 0) {
                        $location = trim(substr($linha, 9));
                    }
                    return strlen($linha);
                },
            ];
            if ($metodo === 'POST') {
                $opcoes[CURLOPT_POST] = true;
                $opcoes[CURLOPT_POSTFIELDS] = $corpo;
            }
            curl_setopt_array($ch, $opcoes);

            $body = curl_exec($ch);
            if ($body === false) {
                return null;
            }
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $tipo = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

            if ($status >= 300 && $status < 400 && $location !== '') {
                $destino = $this->resolverLocation($location);
                if ($destino === null || parse_url($destino, PHP_URL_HOST) !== $hostBase) {
                    return null;
                }
                $url = $destino;
                if (in_array($status, [301, 302, 303], true)) {
                    $metodo = 'GET';
                    $corpo = '';
                }
                continue;
            }

            return [
                'status' => $status,
                'setCookies' => $setCookies,
                'contentType' => $tipo,
                'body' => (string) $body,
            ];
        }

        return null;
    }

    private function resolverLocation(string $location): ?string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }
        if (str_starts_with($location, '/')) {
            return $this->base . $location;
        }
        return null;
    }

    /**
     * Reescreve a página do checkout: sem o widget do Google Translate, sem integrity/crossorigin, URLs absolutas,
     * BASE_URL para o relay, classe de tema no html e as duas injeções (CSS antes de </head>, ponte antes de </body>).
     */
    private function reescrever(string $html, string $produto): string
    {
        $pagina = $this->base . '/' . $this->eventId;

        $html = $this->removerGoogleTranslate($html);

        $html = (string) preg_replace('/\s+(?:integrity|crossorigin)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

        $html = (string) preg_replace_callback(
            '/(?<![\w-])(src|href|action)\s*=\s*(["\'])([^"\']*)\2/i',
            function (array $m) use ($pagina): string {
                $atributo = strtolower($m[1]);
                return $m[1] . '=' . $m[2] . $this->destino($atributo, $m[3], $pagina) . $m[2];
            },
            $html
        );

        $html = (string) preg_replace(
            '/var\s+BASE_URL\s*=\s*(["\'])[^"\']*\1\s*;/',
            'var BASE_URL = "' . self::PREFIXO_RELAY . '";',
            $html,
            1
        );

        $html = (string) preg_replace_callback('/<html\b([^>]*)>/i', function (array $m): string {
            $attrs = $m[1];
            if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/i', $attrs, $c) === 1) {
                $novo = 'class=' . $c[1] . trim($c[2] . ' fj-checkout-tema') . $c[1];
                $attrs = str_replace($c[0], $novo, $attrs);
            } else {
                $attrs .= ' class="fj-checkout-tema"';
            }
            return '<html' . $attrs . '>';
        }, $html, 1);

        $css = '<link rel="stylesheet" href="' . e(asset('css/checkout-tema.css')) . '">';
        $js = '<script src="' . e(asset('js/checkout-ponte.js')) . '" data-product="' . e($produto) . '"></script>';

        $html = $this->inserirAntes($html, '</head>', $css, false);
        $html = $this->inserirAntes($html, '</body>', $js, true);

        return $html;
    }

    /**
     * Remove o widget do Google Translate do checkout da Tickett: não é preciso (o idioma vem do ?l= nativo).
     * Recarregava o iframe ~1 s depois de abrir, e os pedidos à Google eram bloqueados por captcha.
     * Três padrões: o <script> externo, o <script> inline que define googleTranslateElementInit (não atravessa
     * </script>) e o contentor google_translate_element. Se o preg_replace falhar, fica o HTML tal como veio.
     */
    private function removerGoogleTranslate(string $html): string
    {
        // <script src="//translate.google.com/translate_a/element.js?cb=…"></script>
        $html = preg_replace('#<script\b[^>]*\bsrc\s*=\s*(["\'])(?:https?:)?//translate\.google\.[a-z.]+/translate_a/element\.js[^"\']*\1[^>]*>\s*</script>#i', '', $html) ?? $html;
        // <script type="text/javascript"> function googleTranslateElementInit() { … } </script>
        $html = preg_replace('#<script\b[^>]*>(?:(?!</script>).)*?googleTranslateElementInit(?:(?!</script>).)*</script>#is', '', $html) ?? $html;
        // <div id="google_translate_element" style="display:none;"></div>
        $html = preg_replace('#<div\b[^>]*\bid\s*=\s*(["\'])google_translate_element\1[^>]*>\s*</div>#i', '', $html) ?? $html;

        return $html;
    }

    /** Destino de um src/href/action do checkout. Links para a página do evento ficam no proxy. */
    private function destino(string $atributo, string $url, string $pagina): string
    {
        if ($url === '' || $url[0] === '#' || preg_match('#^(?:javascript|data|mailto|tel):#i', $url) === 1) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            $absoluto = parse_url($this->base, PHP_URL_SCHEME) . ':' . $url;
        } elseif (preg_match('#^https?://#i', $url) === 1) {
            $absoluto = $url;
        } else {
            $absoluto = $this->base . '/' . ltrim($url, '/');
        }

        if ($atributo !== 'src' && preg_match('#^' . preg_quote($pagina, '#') . '(?:/([A-Za-z0-9/_-]+))?(?:[?\#].*)?$#', $absoluto, $m) === 1) {
            if (!isset($m[1]) || $m[1] === '') {
                return '/checkout';
            }
            return self::PREFIXO_RELAY . $this->eventId . '/' . $m[1];
        }

        return $absoluto;
    }

    private function inserirAntes(string $html, string $marca, string $conteudo, bool $ultima): string
    {
        $pos = $ultima ? strripos($html, $marca) : stripos($html, $marca);
        if ($pos === false) {
            return $html;
        }
        return substr($html, 0, $pos) . $conteudo . substr($html, $pos);
    }

    /** @return array{status: int, headers: list<string>, body: string} */
    private function paginaErro(bool $https, string $lang): array
    {
        \idioma($lang);
        $link = e(TickettService::comIdioma($this->base . '/' . $this->eventId, $lang));
        $html = '<!DOCTYPE html><html lang="' . (\idioma() === 'en' ? 'en' : 'pt-PT') . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
            . '<title>' . e(\t('erro.checkout.titulo')) . '</title></head><body>'
            . '<p>' . e(\t('erro.checkout.texto')) . '</p>'
            . '<p><a href="' . $link . '" target="_blank" rel="noopener">' . e(\t('erro.checkout.link')) . '</a>.</p>'
            . '</body></html>';

        return [
            'status' => 502,
            'headers' => array_merge(['Content-Type: text/html; charset=utf-8'], $this->cabecalhosSeguranca($https)),
            'body' => $html,
        ];
    }

    /**
     * @param array<string, mixed> $dados
     * @param list<string> $extra
     * @return array{status: int, headers: list<string>, body: string}
     */
    private function json(int $status, array $dados, array $extra = []): array
    {
        return [
            'status' => $status,
            'headers' => array_merge(['Content-Type: application/json; charset=utf-8', 'Cache-Control: no-store', 'X-Content-Type-Options: nosniff'], $extra),
            'body' => (string) json_encode($dados, JSON_UNESCAPED_UNICODE),
        ];
    }

    /** @param list<string> $extra */
    private function semCorpo(int $status, array $extra): array
    {
        return ['status' => $status, 'headers' => $extra, 'body' => ''];
    }
}
