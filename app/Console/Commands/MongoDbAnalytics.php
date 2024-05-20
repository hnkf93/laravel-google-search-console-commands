<?php

namespace App\Console\Commands;

use App\Models\Analytics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MongoDbAnalytics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:mongo-db-analytics {opt? : Command Option 1 - 6} {run? : Update Analytics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'MongoDb Projects - Execute 1-6 Options';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $opt = $this->argument('opt') ?? null;
        $run = $this->argument('run') ?? null;


        if ($opt) {
            switch ($opt):
                case 1:
                    $this->getAllProjectsWithEmptySitemapPath($run);
                    break;
                case 2:
                    $this->getAllProjectsWithEmptySitemapLastdownloaded($run);
                    break;
                case 3:
                    $this->getAllProjectsWithEmptySitemapType($run);
                    break;
                case 4:
                    $this->getAllProjectsWithEmptySitemapUrls($run);
                    break;
                case 5:
                    $this->getAllProjectsWithEmptySitemapUrlsInspectionResultsIndexStatus($run);
                    break;
                case 6:
                    $this->getAllProjectsWithEmptySitemapUrlsInspectionResultsIndexStatusCoverageState($run);
                    break;
                default:
                    dd('Opção Inválida');
            endswitch;
        } else {
            dd('Opção Inválida');
        }


    }

    private function getAllProjectsWithEmptySitemapPath($run = null)
    {
        $p = [];

        $projects = Analytics::where('details.site.sitemap.path', 'not_found')
            ->get();

        foreach ($projects as $project) {
            $p[] = [
                '_id' => $project->_id,
                'url' => $project['details']['site']['url'],
                'error' => 'Empty sitemap path'
            ];
            if ($run) {
                dd("Executar o comando com a url: " . $project['details']['site']['url']);
                Artisan::call('app:google-search-console-test-api', [
                    'site' => $project['details']['site']['url']
                ]);
            }
        }

        var_dump($p);

        return $p;

    }

    private function getAllProjectsWithEmptySitemapLastdownloaded($run = null)
    {
        $p = [];

        $projects = Analytics::where('details.site.sitemap.lastdownloaded', 'not_found')
            ->get();

        foreach ($projects as $project) {
            $p[] = [
                '_id' => $project->_id,
                'url' => $project['details']['site']['url'],
                'error' => 'Empty sitemap lastdownloaded'
            ];
            if ($run) {
                dd("Executar o comando com a url: " . $project['details']['site']['url']);
                Artisan::call('app:google-search-console-test-api', [
                    'site' => $project['details']['site']['url']
                ]);
            }
        }

        var_dump($p);

        return $p;

    }

    private function getAllProjectsWithEmptySitemapType($run = null)
    {
        $p = [];

        $projects = Analytics::where('details.site.sitemap.type', 'not_found')
            ->get();

        foreach ($projects as $project) {

            $p[] = [
                '_id' => $project->_id,
                'url' => $project['details']['site']['url'],
                'error' => 'Empty sitemap type'
            ];

            if ($run) {
                dd("Executar o comando com a url: " . $project['details']['site']['url']);
                Artisan::call('app:google-search-console-test-api', [
                    'site' => $project['details']['site']['url']
                ]);
            }

        }

        var_dump($p);

        return $p;

    }

    private function getAllProjectsWithEmptySitemapUrls($run = null)
    {
        $p = [];

        $projects = Analytics::where('details.site.sitemap.urls', '=', [])->get();

        foreach ($projects as $project) {
            $p[] = [
                '_id' => $project->_id,
                'url' => $project['details']['site']['url'],
                'error' => 'Empty sitemap urls'
            ];
            if ($run) {
                dd("Executar o comando com a url: " . $project['details']['site']['url']);
                Artisan::call('app:google-search-console-test-api', [
                    'site' => $project['details']['site']['url']
                ]);
            }
        }

        var_dump($p);

        return $p;

    }

    private function getAllProjectsWithEmptySitemapUrlsInspectionResultsIndexStatus($run = null)
    {
        $projects = Analytics::all();
        $p = [];

        foreach ($projects as $project) {
            $results = [];
            foreach ($project->details['site']['sitemap']['urls'] as $url) {
                if (isset($url['inspection_results']) && $url['inspection_results']['index_status'] === 'not_found') {
                    $results[] = $url['loc'] . ' - ' . $url['inspection_results']['index_status'];
                }
            }

            if (!empty($results)) {
                $p[] = [
                    '_id' => $project->_id,
                    'url' => $project['details']['site']['url'],
                    'inspection_results' => $results,
                    'error' => 'Empty sitemap urls inspection results index status'
                ];
                if ($run) {
                    dd("Executar o comando com a url: " . $project['details']['site']['url']);
                    Artisan::call('app:google-search-console-test-api', [
                        'site' => $project['details']['site']['url']
                    ]);
                }
            }
        }

        var_dump($p);

        return $p;
    }

    private function getAllProjectsWithEmptySitemapUrlsInspectionResultsIndexStatusCoverageState($run = null)
    {
        $projects = Analytics::all();
        $p = [];

        foreach ($projects as $project) {
            $results = [];
            foreach ($project->details['site']['sitemap']['urls'] as $url) {
                if (isset($url['inspection_results'])
                    && isset($url['inspection_results']['index_status'])
                    && $url['inspection_results']['index_status'] <> 'not_found'
                    && $url['inspection_results']['index_status']['coverageState'] <> 'Submitted and indexed'
                ) {
                    $results[] = $url['loc'] . ' - ' . $url['inspection_results']['index_status']['coverageState'];
                }
            }

            if (!empty($results)) {
                $p[] = [
                    '_id' => $project->_id,
                    'url' => $project['details']['site']['url'],
                    'inspection_results' => $results,
                    'error' => 'Empty sitemap urls inspection results index status coverage state'
                ];
                if ($run) {
                    dd("Executar o comando com a url: " . $project['details']['site']['url']);
                    Artisan::call('app:google-search-console-test-api', [
                        'site' => $project['details']['site']['url']
                    ]);
                }
            }
        }

        var_dump($p);

        return $p;
    }
}
