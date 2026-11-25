<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Leitura do catálogo de bilhetes da Tickett a partir da página do checkout ({base}/{event_id}).
 * O checkout publicado não tem API de produtos: os bilhetes vêm no HTML que ele já renderiza no servidor
 * (app/views/products/group.volt). Não há products.json.
 */
final class TickettService
{
    private const USER_AGENT = 'FestejaSite/1.0';

    /** Idioma do site → código de idioma da bilheteira Tickett (?l=, o mesmo que o proxy usa em /checkout). */
    private const IDIOMAS_TICKETT = ['pt' => 'pt_pt', 'en' => 'en_us'];

    /** Bloco de bilhetes do checkout (group.volt). Sem ele, a página não é a bilheteira (erro ou alterada). */
    private const XPATH_BLOCO = '//*[@id="tickets-form-products"]';

    /** Cada bilhete é uma tabela ticket-box, dentro do bloco. */
    private const XPATH_CAIXA = ".//table[contains(concat(' ', normalize-space(@class), ' '), ' ticket-box ')]";

    /** Resultado de getCatalog() por event_id e idioma, para não repetir pedidos no mesmo pedido HTTP. */
    private static array $catalogos = [];

    private array $cfg;

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
    }

    /**
     * @return array{ok: bool, stale: bool, event: ?array, products: array}
     */
    public function getCatalog(): array
    {
        // Em inglês, os nomes e descrições dos bilhetes vêm da página da Tickett em en_us (?l=en_us): catálogo e cache próprios.
        $chave = $this->eventId() . ':' . idioma();
        self::$catalogos[$chave] ??= $this->lerCatalogo();

        return self::$catalogos[$chave];
    }

    /**
     * @return array{ok: bool, stale: bool, event: ?array, products: array}
     */
    private function lerCatalogo(): array
    {
        $cachePath = $this->cachePath();
        $cached = $this->readCache($cachePath);
        $ttl = (int) ($this->cfg['cache_ttl'] ?? 300);

        if ($cached !== null && (time() - $cached['saved_at']) < $ttl) {
            return $this->result(true, false, $cached['event'], $cached['products']);
        }

        $fresh = $this->fetch();
        if ($fresh !== null) {
            $this->writeCache($cachePath, $fresh);
            return $this->result(true, false, $fresh['event'], $fresh['products']);
        }

        if ($cached !== null) {
            return $this->result(true, true, $cached['event'], $cached['products']);
        }

        return $this->result(false, false, null, []);
    }

    /**
     * URL do checkout geral do evento. Com $productId, a URL do bilhete (?produto=) se estiver no catálogo.
     * O idioma (predefinido: o da página) vai como ?l= da Tickett (pt_pt ou en_us), ver comIdioma().
     */
    public function checkoutUrl(?int $productId = null, ?string $idioma = null): string
    {
        $produto = $this->produto($productId);
        $url = $produto !== null ? $produto['checkout_url'] : $this->checkoutPageUrl();

        return self::comIdioma($url, $idioma);
    }

    /**
     * Acrescenta o idioma da bilheteira (?l=pt_pt ou ?l=en_us) a um link direto da Tickett. Caminhos do próprio site
     * (sem http://) ficam como estão.
     */
    public static function comIdioma(string $url, ?string $idioma = null): string
    {
        if (preg_match('#^https?://#', $url) !== 1) {
            return $url;
        }
        $codigo = self::IDIOMAS_TICKETT[$idioma ?? idioma()] ?? self::IDIOMAS_TICKETT['pt'];

        return $url . (str_contains($url, '?') ? '&' : '?') . 'l=' . $codigo;
    }

    /**
     * URL do iframe da bilheteira. Aponta para o proxy do site (/checkout), que encaminha para o checkout da Tickett.
     * O modo embed e o tema são tratados pelo proxy, não aqui.
     */
    public function embedUrl(?int $productId = null): string
    {
        $produto = $this->produto($productId);
        $query = $produto !== null ? (string) parse_url($produto['checkout_url'], PHP_URL_QUERY) : '';

        return '/checkout' . ($query !== '' ? '?' . $query : '');
    }

    /**
     * Menor valor mostrado entre os produtos que não estão esgotados: total, ou o preço do HTML se não houver total.
     */
    public function minPrice(): ?float
    {
        $prices = [];
        foreach ($this->getCatalog()['products'] as $product) {
            $valor = $product['total'] ?? $product['price'];
            if ($product['status'] !== 'sold_out' && $valor !== null) {
                $prices[] = $valor;
            }
        }
        return $prices === [] ? null : min($prices);
    }

    /**
     * true só se todos os produtos à venda com preço trazem a taxa incluída. Com o HTML atual é sempre false.
     */
    public function taxaIncluida(): bool
    {
        $vendidos = array_filter(
            $this->getCatalog()['products'],
            static fn (array $product): bool => $product['status'] !== 'sold_out' && ($product['total'] ?? $product['price']) !== null
        );
        if ($vendidos === []) {
            return false;
        }
        foreach ($vendidos as $product) {
            if ($product['fee_included'] !== true) {
                return false;
            }
        }
        return true;
    }

    private function produto(?int $productId): ?array
    {
        if ($productId === null) {
            return null;
        }
        foreach ($this->getCatalog()['products'] as $product) {
            if ($product['id'] === $productId) {
                return $product;
            }
        }
        return null;
    }

    private function base(): string
    {
        return rtrim((string) ($this->cfg['base'] ?? ''), '/');
    }

    private function eventId(): int
    {
        return (int) ($this->cfg['event_id'] ?? 0);
    }

    private function checkoutPageUrl(): string
    {
        return $this->base() . '/' . $this->eventId();
    }

    private function cachePath(): string
    {
        $sufixo = idioma() === 'en' ? '-en' : '';
        return $this->cacheDir() . '/tickett-' . $this->eventId() . $sufixo . '.json';
    }

    /**
     * storage/cache, ou o diretório temporário na Vercel (código só de leitura, exceto /tmp) ou se não for gravável.
     */
    private function cacheDir(): string
    {
        $dir = ROOT . '/storage/cache';
        if (getenv('VERCEL') !== false || !is_writable($dir)) {
            return sys_get_temp_dir() . '/festeja-cache';
        }
        return $dir;
    }

    private function result(bool $ok, bool $stale, ?array $event, array $products): array
    {
        return ['ok' => $ok, 'stale' => $stale, 'event' => $event, 'products' => $products];
    }

    /**
     * Pedido à página do checkout. Devolve o catálogo normalizado ou null em caso de falha.
     */
    private function fetch(): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => (float) ($this->cfg['timeout'] ?? 3),
                'ignore_errors' => true,
                'user_agent' => self::USER_AGENT,
                'header' => "Accept: text/html\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $url = $this->checkoutPageUrl() . (idioma() === 'en' ? '?l=' . self::IDIOMAS_TICKETT['en'] : '');
        $html = @file_get_contents($url, false, $context);
        if ($html === false) {
            error_log("TickettService: sem resposta de {$url}");
            return null;
        }

        // Com redirecionamentos, o último estado HTTP é o da resposta final.
        $status = 0;
        foreach ($http_response_header ?? [] as $linha) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $linha, $m) === 1) {
                $status = (int) $m[1];
            }
        }
        if ($status !== 200) {
            error_log("TickettService: {$url} respondeu HTTP {$status}");
            return null;
        }

        $catalogo = $this->parseCatalogo($html);
        if ($catalogo === null) {
            error_log("TickettService: {$url} sem o bloco de bilhetes (tickets-form-products)");
            return null;
        }

        return [
            'saved_at' => time(),
            'event' => $catalogo['event'],
            'products' => $catalogo['products'],
        ];
    }

    /**
     * Lê o HTML da página do checkout. Devolve ['event' => ..., 'products' => ...] ou null se não houver o bloco de
     * bilhetes. Um bloco sem bilhetes à venda é válido: a venda fica fechada (ou por abrir).
     */
    private function parseCatalogo(string $html): ?array
    {
        $anterior = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        // O prefixo XML força a leitura como UTF-8 (a página declara charset utf-8).
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $xpath = new \DOMXPath($dom);
        $bloco = $xpath->query(self::XPATH_BLOCO)->item(0);
        if (!$bloco instanceof \DOMElement) {
            return null;
        }

        $produtos = [];
        foreach ($xpath->query(self::XPATH_CAIXA, $bloco) as $caixa) {
            $produto = $this->parseProduto($xpath, $caixa, count($produtos));
            if ($produto !== null) {
                $produtos[] = $produto;
            }
        }

        // O <title> é "{nome do evento} | {empresa} Checkout".
        $titulo = (string) $xpath->query('//title')->item(0)?->textContent;
        $nomeEvento = $this->texto(explode(' | ', $titulo)[0]);

        $estados = array_column($produtos, 'status');
        if (in_array('available', $estados, true)) {
            $venda = 'open';
        } elseif (in_array('upcoming', $estados, true)) {
            $venda = 'upcoming';
        } else {
            $venda = 'closed';
        }

        return [
            'event' => [
                'id' => $this->eventId(),
                'name' => $nomeEvento,
                'currency' => 'EUR',
                'checkout_url' => $this->checkoutPageUrl(),
                'sale' => ['status' => $venda, 'opens_at' => null],
            ],
            'products' => $produtos,
        ];
    }

    /**
     * Um bilhete a partir da sua caixa ticket-box. Devolve null se faltar o nome ou a linha de preço.
     *
     * Estrutura (group.volt): a 1.ª linha tem o h5 com o nome e <small><br>descrição</small>.
     * A 2.ª linha tem, na 1.ª célula, o lote ("Tier 1" ou o nome do lote) e, na 2.ª, o preço ("€ 14,25")
     * e o select product_{id_product}_{id_type} de quantidade. Sem select, o bilhete está esgotado ou por abrir
     * e a célula traz só a mensagem (<small>).
     */
    private function parseProduto(\DOMXPath $xpath, \DOMElement $caixa, int $ordem): ?array
    {
        $h5 = $xpath->query('.//h5', $caixa)->item(0);
        $celulas = $xpath->query('.//tr[2]/td', $caixa);
        if (!$h5 instanceof \DOMElement || $celulas->length < 2) {
            error_log('TickettService: bilhete sem h5 ou sem linha de preço, ignorado');
            return null;
        }

        // O nome é o texto do h5 sem o <small> (a descrição).
        $nome = '';
        foreach ($h5->childNodes as $no) {
            if ($no->nodeType === XML_TEXT_NODE) {
                $nome .= $no->nodeValue;
            }
        }
        $nome = $this->texto($nome);
        if ($nome === '') {
            error_log('TickettService: bilhete sem nome, ignorado');
            return null;
        }

        $descricaoNo = $xpath->query('.//small', $h5)->item(0);
        $descricao = $descricaoNo !== null ? $this->texto($descricaoNo->textContent) : '';

        $rotuloLote = $this->texto($celulas->item(0)->textContent);
        $lote = $this->lote($rotuloLote) ?? $this->lote($nome) ?? ($rotuloLote !== '' ? ['number' => null, 'name' => $rotuloLote] : null);

        $linhaPreco = $celulas->item(1);
        $textoPreco = $this->texto($linhaPreco->textContent);
        $select = $xpath->query('.//select[contains(@class, "quantity-select")]', $linhaPreco)->item(0);

        $id = null;
        $produto = null;
        $preco = null;
        $status = 'sold_out';
        $urlBilhete = $this->checkoutPageUrl();

        if ($select instanceof \DOMElement) {
            // O checkout pré-seleciona com ?produto={id_product}_{id_type} (index.volt).
            if (preg_match('/^product_(\d+)_(\d+)$/', $select->getAttribute('id'), $m) !== 1) {
                error_log('TickettService: id de select inesperado "' . $select->getAttribute('id') . '", bilhete ignorado');
                return null;
            }
            $preco = $this->preco($textoPreco);
            if ($preco === null) {
                error_log("TickettService: bilhete {$m[1]} sem preço, ignorado");
                return null;
            }
            $id = (int) $m[1];
            $produto = $m[1] . '_' . $m[2];
            $status = 'available';
            $urlBilhete .= '?' . http_build_query(['produto' => $produto]);
        } elseif ($this->estadoSemSeletor($textoPreco) === 'upcoming') {
            $status = 'upcoming';
        }

        return [
            'id' => $id,
            // Identificador nativo de pré-seleção do checkout ({id_product}_{id_type}), o mesmo de ?produto=.
            'produto' => $produto,
            'name' => $nome,
            'description' => $descricao,
            'days' => [],
            'price' => $preco,
            'fee' => null,
            'total' => null,
            // O HTML do checkout não mostra a taxa por bilhete, por isso a taxa nunca vem incluída.
            'fee_included' => false,
            'batch' => $lote,
            'next_batch' => null,
            'status' => $status,
            'highlight' => null,
            'sort' => $ordem,
            'checkout_url' => $urlBilhete,
        ];
    }

    /**
     * Sem select, a mensagem da célula diz o estado. Por omissão é esgotado, como o próprio checkout.
     */
    private function estadoSemSeletor(string $texto): string
    {
        return preg_match('/coming soon|em breve|brevemente/iu', $texto) === 1 ? 'upcoming' : 'sold_out';
    }

    /**
     * "Lote N" ou "Tier N" no texto. Devolve o número e o nome normalizado ("Lote N"), ou null.
     */
    private function lote(string $texto): ?array
    {
        if (preg_match('/\b(?:lote|tier)\s*(\d+)\b/iu', $texto, $m) !== 1) {
            return null;
        }
        return ['number' => (int) $m[1], 'name' => 'Lote ' . $m[1]];
    }

    /**
     * Preço com a vírgula decimal do checkout ("€ 1.250,00"). Devolve null se não houver.
     */
    private function preco(string $texto): ?float
    {
        if (preg_match('/€\s*(\d[\d.]*),(\d{2})/u', $texto, $m) !== 1) {
            return null;
        }
        return (float) (str_replace('.', '', $m[1]) . '.' . $m[2]);
    }

    /**
     * Espaços normalizados (incluindo o espaço não separável do HTML) e sem espaços nas pontas.
     */
    private function texto(string $valor): string
    {
        $valor = str_replace("\u{a0}", ' ', $valor);
        return trim((string) preg_replace('/\s+/u', ' ', $valor));
    }

    /**
     * @return ?array{saved_at: int, event: array, products: array}
     */
    private function readCache(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        try {
            $data = $raw === false ? null : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $ex) {
            error_log("TickettService: cache inválida em {$path}: " . $ex->getMessage());
            return null;
        }

        if (!is_array($data) || !isset($data['saved_at'], $data['event'], $data['products'])
            || !is_int($data['saved_at']) || !is_array($data['event']) || !is_array($data['products'])) {
            return null;
        }
        return $data;
    }

    private function writeCache(string $path, array $catalog): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $json = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            error_log("TickettService: não foi possível gravar a cache em {$path}");
        }
    }
}
