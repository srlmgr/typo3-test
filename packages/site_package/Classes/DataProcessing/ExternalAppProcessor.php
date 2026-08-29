<?php
namespace MyVendor\SitePackage\DataProcessing;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

class ExternalAppProcessor implements DataProcessorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const MAX_REDIRECTS = 5;
    private const EXTERNAL_SNIPPET_PATH = '/vrdb/snippet';
    private const PATH_SEASON_ID_MAP = [
        '/vrpcdb' => 1,
        '/vrgesdb' => 2,
        '/sub1/wertung' => 1,
    ];

    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requestFactory;

    public function __construct()
    {
        $this->client = GeneralUtility::makeInstance(ClientInterface::class);
        $this->requestFactory = GeneralUtility::makeInstance(RequestFactoryInterface::class);
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        $requestConfiguration = $this->buildExternalRequestConfiguration($cObj, $processorConfiguration);

        try {
            $processedData['externalHtml'] = $this->fetchExternalHtml(
                $requestConfiguration['endpoint'],
                $requestConfiguration['cmsBasePath']
            );
        } catch (\Throwable $e) {
            $processedData['externalHtml'] = '<p>Could not connect to external service: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }

        return $processedData;
    }

    private function buildExternalRequestConfiguration(ContentObjectRenderer $cObj, array $processorConfiguration): array
    {
        $externalBase = rtrim($processorConfiguration['endpoint'] ?? 'https://vrdb-http.ubtest3.homelab.mpapenbr.de', '/');

        $requestUri = GeneralUtility::getIndpEnv('REQUEST_URI');
        $parts = parse_url($requestUri ?: '/');

        $path = $parts['path'] ?? '/';
        $query = $parts['query'] ?? '';
        $this->debug('Building external URL for path: ' . $path . ' and query: ' . $query . ' with ' . json_encode($processorConfiguration));

        $basePath = $processorConfiguration['basePath'] ?? $cObj->data['tx_sitepackage_external_base_path'] ?? '/vrdb';
        $suffix = $path;

        if (str_starts_with($path, $basePath)) {
            $suffix = substr($path, strlen($basePath));
        }

        if ($suffix === '') {
            $suffix = '/';
        }

        $url = $externalBase . self::EXTERNAL_SNIPPET_PATH;

        parse_str($query, $queryParameters);
        if (!array_key_exists('seasonID', $queryParameters) && array_key_exists($suffix, self::PATH_SEASON_ID_MAP)) {
            $queryParameters['seasonID'] = self::PATH_SEASON_ID_MAP[$suffix];
        }

        $normalizedQuery = http_build_query($queryParameters);
        if ($normalizedQuery !== '') {
            $url .= '?' . $normalizedQuery;
        }

        $this->debug('Resolved external URL ' . $url . ' with CMS base path header ' . $suffix);

        return [
            'endpoint' => $url,
            'cmsBasePath' => $suffix,
        ];
    }

    private function fetchExternalHtml(string $endpoint, string $cmsBasePath, int $redirects = 0): string
    {
        if ($redirects > self::MAX_REDIRECTS) {
            throw new \RuntimeException('Too many redirects while fetching the external app.');
        }

        $this->debug('Fetching URL: ' . $endpoint . ' with CMS base path ' . $cmsBasePath . ' (redirect depth: ' . $redirects . ')');

        $request = $this->requestFactory
            ->createRequest('GET', $endpoint)
            ->withHeader('X-CMS-Base-Path', $cmsBasePath);
        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() >= 300 && $response->getStatusCode() < 400) {
            $location = $response->getHeaderLine('Location');
            if ($location !== '') {
                $nextUrl = $this->resolveRedirectUrl($location, $endpoint);
                $this->debug('Redirect from ' . $endpoint . ' to ' . $nextUrl . ' (status: ' . $response->getStatusCode() . ')');
                return $this->fetchExternalHtml($nextUrl, $cmsBasePath, $redirects + 1);
            }
        }

        $this->debug('Received status ' . $response->getStatusCode() . ' for ' . $endpoint);

        if ($response->getStatusCode() === 200) {
            return (string) $response->getBody();
        }

        return '<p>External service returned status: ' . $response->getStatusCode() . '</p>';
    }

    private function resolveRedirectUrl(string $location, string $endpoint): string
    {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//', $location) === 1) {
            return $location;
        }

        $endpointParts = parse_url($endpoint);
        $scheme = $endpointParts['scheme'] ?? 'https';
        $host = $endpointParts['host'] ?? '';

        if ($host === '') {
            return GeneralUtility::locationHeaderUrl($location, $endpoint);
        }

        $port = isset($endpointParts['port']) ? ':' . $endpointParts['port'] : '';
        $baseUrl = $scheme . '://' . $host . $port;

        if (str_starts_with($location, '/')) {
            return $baseUrl . $location;
        }

        return $baseUrl . '/' . ltrim($location, '/');
    }

    private function debug(string $message): void
    {
        $logMessage = '[' . date('c') . '] [ExternalAppProcessor] ' . $message . PHP_EOL;

        if ($this->logger !== null) {
            $this->logger->debug('[ExternalAppProcessor] ' . $message);
        }
    }
}