<?php

namespace App\Console\Commands;

use App\Models\Analytics;
use Google_Service_SearchConsole;
use Google_Service_SearchConsole_InspectUrlIndexRequest;
use Google_Service_Webmasters_SearchAnalyticsQueryRequest;
use Illuminate\Console\Command;
use Google\Client;
use Google\Service\Webmasters;

use Illuminate\Support\Facades\Http;


class GoogleSearchConsoleTestApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:google-search-console-test-api {site?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Google SearchConsole Test Api';

    protected $client;

    public function __construct()
    {
        parent::__construct();

        // Inicialize o cliente do Google
        $this->client = new Client();
        $this->client->setApplicationName(config('google.application_name'));
        $this->client->setAuthConfig(config('google.service_account_credentials_json'));
        $this->client->setScopes(config('google.scopes'));

    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $site = $this->argument('site') ?? null;

        $this->listAllProperties($site);
//        $this->searchAnalytics('https://www.metaltampos.com.br', '2024-04-01', '2024-04-30');

    }

    private function searchAnalytics($siteUrl, $startDate = null, $endDate = null, $dimension = null, $rows = 10)
    {
        $res = [];
        try {
            if (is_null($startDate)) {
                $startDate = date('Y-m-d', strtotime('-1 day'));
                $endDate = date('Y-m-d');
            }

            $service = new Webmasters($this->client);

            $searchanalytics = $service->searchanalytics;

            $request = new Google_Service_Webmasters_SearchAnalyticsQueryRequest();
            $request->setStartDate($startDate);
            $request->setEndDate($endDate);

            switch ($dimension):
                case 'query':
                    $request->setDimensions(['query']);
                    break;
                case 'page':
                    $request->setDimensions(['page']);
                    break;
                case 'country':
                    $request->setDimensions(['country']);
                    break;
                case 'device':
                    $request->setDimensions(['device']);
                    break;
                case 'searchAppearance':
                    $request->setDimensions(['searchAppearance']);
                    break;
                default:
                    $request->setDimensions(['query', 'page', 'country', 'device']);
                    break;
            endswitch;

            $request->setRowLimit($rows);

            $dimensions = $request->getDimensions();
            $dimensionsQty = count($dimensions);

            $response = $searchanalytics->query($siteUrl, $request);

            foreach ($response as $r) {
                $k = [];
                for ($i = 0;
                     $i < $dimensionsQty;
                     $i++) {
                    $k[] = [
                        $dimensions[$i] => $r->keys[$i]
                    ];
                }

                $res[] = [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'clicks' => $r->clicks,
                    'ctr' => $r->ctr,
                    'impressions' => $r->impressions,
                    'keys' => $k,
                    'position' => $r->position
                ];
            }

        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }
        dd(json_encode($res));
        return json_encode($res);

    }

    private function listAllProperties($site = null)
    {
        $counter = 1;

        $data = [];
        // Inicialização do serviço do Google Search Console
        $webmastersService = new Webmasters($this->client);

        // Listar sites (propriedades)
        $sitesList = $webmastersService->sites->listSites();

        $sitesListQty = count($sitesList->getSiteEntry());

        $dataIndex = 0;

        foreach ($sitesList->getSiteEntry() as $siteEntry) {

            echo "$siteEntry->siteUrl - $counter/$sitesListQty \n\n";

            if ($site) {
                if ($site != $siteEntry->siteUrl) {
                    continue;
                } else {
                    $find = Analytics::where('details.site.url', $site)->first();
                    $find->delete();
                }
            } else {
//            VALIDA SE JÁ EXISTE ANTES DE COLETAR OS DADOS
                $find = Analytics::where('details.site.url', 'regexp', "/.*{$siteEntry->siteUrl}.*/i")->select('_id', 'details.site.url')
                    ->get();

                if (count($find) > 0) {
                    var_dump("$siteEntry->siteUrl - Dados já cadastrados");
                    continue;
                }
            }


            $siteMap = $this->getSiteMap($siteEntry->siteUrl);
            $urls = $this->getAllUrlsFromDomain(isset($siteMap['path']) ? $siteMap['path'] : '');

            $data[] = ['site' =>
                [
                    'url' => $siteEntry->siteUrl,
                    'name' => $siteEntry->permissionLevel,
                    'sitemap' => [
                        'path' => isset($siteMap['path']) ? $siteMap['path'] : 'not_found',
                        'lastdownloaded' => isset($siteMap['lastdownloaded']) ? $siteMap['lastdownloaded'] : 'not_found',
                        'type' => isset($siteMap['type']) ? $siteMap['type'] : 'not_found',
                        'urls' => []
                    ]
                ]
            ];

            $urlData = [];

            $totalUrls = count($urls);
            $urlCounter = 1;

            foreach ($urls as $url) {
                echo "Url Counter: $urlCounter/$totalUrls\n";
                $inspectUrl = $this->inspectUrl($siteEntry->siteUrl, $url['loc']);

                $urlData[] = [
                    'loc' => $url['loc'],
                    'lastmod' => $url['lastmod'],
                    'changefreq' => $url['changefreq'],
                    'priority' => $url['priority'],
                    'inspection_results' => [
                        'index_status' => isset($inspectUrl['index_status']) ? $inspectUrl['index_status'] : 'not_found',
                        'mobile_usability_status' => isset($inspectUrl['mobile_usability']) ? $inspectUrl['mobile_usability'] : 'not_found',
                        'rich_results' => isset($inspectUrl['rich_results']) && !empty($inspectUrl['rich_results']) ? $inspectUrl['rich_results'] : 'not_found'
                    ]
                ];
                $urlCounter++;
            }
            $data[$dataIndex]['site']['sitemap']['urls'] = $urlData;

            var_dump($data[$dataIndex]);

            if (Analytics::create(['details' => $data[$dataIndex]])) {
                var_dump("Analytics data created.");
            } else {
                var_dump("Analytics data error.");
            }

            $counter++;
            $dataIndex++;

//            if ($counter == 3) {
//                dd(json_encode($data));
//            }

        }

        return json_encode($data);

    }

    private function getSiteMap($siteUrl)
    {
        $sm = [];
        try {
            // Inicialização do serviço do Google Search Console
            $webmastersService = new Webmasters($this->client);

            // Obtendo os sitemaps
            $sitemaps = $webmastersService->sitemaps->listSitemaps($siteUrl);

            // Exibir informações dos sitemaps
            foreach ($sitemaps->getSitemap() as $sitemap) {
                $sm = ['path' => $sitemap->getPath(), 'lastdownloaded' => $sitemap->getLastDownloaded(), 'type' => $sitemap->getType()];
            }
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }

        return $sm;
    }

    private function getAllUrlsFromDomain($sitemapUrl)
    {
        $list = [];
        try {
            // Realiza a requisição GET para obter o conteúdo do sitemap
            $response = Http::get($sitemapUrl);

            // Verifica se a requisição foi bem-sucedida
            if ($response->ok()) {
                // Parseia o conteúdo XML
                $xml = simplexml_load_string($response->body());

                // Itera sobre as tags <url> dentro do sitemap
                foreach ($xml->url as $url) {
                    // Extrai a URL
                    $loc = (string)$url->loc;
                    // Extrai a data da última modificação
                    $lastmod = (string)$url->lastmod;
                    // Extrai a frequência de alteração
                    $changefreq = (string)$url->changefreq;
                    // Extrai a prioridade
                    $priority = (string)$url->priority;

                    $list[] = [
                        'loc' => $loc,
                        'lastmod' => $lastmod,
                        'changefreq' => $changefreq,
                        'priority' => $priority
                    ];

                }

            }
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }

        return $list;
    }

    private function inspectUrl($url, $loc)
    {
        $response = [];
        try {
            $service = new Google_Service_SearchConsole($this->client);
            $request = new Google_Service_SearchConsole_InspectUrlIndexRequest($this->client);
            $request->setSiteUrl($url);
            $request->setInspectionUrl($loc);

            var_dump("Inspecting URL: $url - $loc");

            $response = $service->urlInspection_index->inspect($request)->getInspectionResult();

            $richResultType = [];

            if ($response->getRichResultsResult()) {
                foreach ($response->getRichResultsResult()->getDetectedItems() as $detectedItem) {
                    $item_name = [];
                    foreach ($detectedItem->getItems() as $item) {
                        $issues = [];
                        foreach ($item->getIssues() as $issue) {
                            $issues[] = $issue->getSeverity() . ' - ' . $issue->getIssueMessage();
                        }
                        $item_name[] = [
                            $item->getName() => $issues
                        ];

                    }

                    $richResultType[] = [
                        $detectedItem->getRichResultType() =>
                            ['items' => $item_name]
                    ];
                }
            }

            var_dump($response);

            $index_result = $response->getIndexStatusResult();
            $mobile_usability = $response->getMobileUsabilityResult();
            $referringUrls = [];

            foreach ($index_result->getReferringUrls() as $ref_url) {
                $referringUrls[] = $ref_url;
            }

            $response = [
                'index_status' => [
                    'coverageState' => $index_result->getCoverageState(),
                    'crawledAs' => $index_result->getCrawledAs(),
                    'googleCanonical' => $index_result->getGoogleCanonical(),
                    'indexingState' => $index_result->getIndexingState(),
                    'lastCrawlTime' => $index_result->getLastCrawlTime(),
                    'pageFetchState' => $index_result->getPageFetchState(),
                    'referringUrls' => $referringUrls,
                    'robotsTxtState' => $index_result->getRobotsTxtState(),
                    'sitemap' => $index_result->getSitemap(),
                    'userCanonical' => $index_result->getUserCanonical(),
                    'verdict' => $index_result->getVerdict()
                ],
                'mobile_usability' => [
                    'verdict' => $mobile_usability->getVerdict()
                ],
                'rich_results' => $richResultType
            ];

        } catch (\Exception $e) {
            var_dump($e->getMessage());
            $response = ['error' => $e->getMessage()];
            return $response;
        }

        return $response;
    }

}
