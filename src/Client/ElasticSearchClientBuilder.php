<?php

namespace App\Client;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use GuzzleHttp\RequestOptions;

class ElasticSearchClientBuilder
{
    const DEFAULT_TEXT_MAPPING = [
        'type' => 'text',
        'analyzer' => 'autocomplete',
        'search_analyzer' => 'standard',
    ];

    public function createClient(): Client
    {
        return ClientBuilder::create()
                            ->setHosts([$_ENV['ELASTICSEARCH_HOST']])
                            ->setConnectionParams([
                                RequestOptions::CONNECT_TIMEOUT => 2,
                                RequestOptions::TIMEOUT => 3,
                                RequestOptions::READ_TIMEOUT => 1,
                            ])
                            ->build();
    }
}
