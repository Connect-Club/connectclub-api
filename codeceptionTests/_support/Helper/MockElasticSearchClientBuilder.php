<?php

namespace App\Tests\Helper;

use App\Client\ElasticSearchClientBuilder;
use Elasticsearch\Client;
use Mockery;
use Mockery\MockInterface;

class MockElasticSearchClientBuilder
{
    public function findIdsByQuery(int $times, string $query = null, array $returnedIds = []): MockInterface
    {
        $client = Mockery::mock(Client::class);

        $elasticCriteria = [
            'index' => 'user',
            'type' => '_doc',
            'body' => [
                'sort' => ['_score' => 'desc', '_id' => 'asc',],
                'query' => [
                    'multi_match' => [
                        'query' => $query,
                        'operator' => 'or',
                        'fields' => ['name^5', 'surname^6', 'fullName^10', 'username^10', 'about^1'],
                    ],
                ],
            ],
        ];


        $elasticResponse = [
            'hits' => [
                'total' => 1,
                'hits' => array_map(fn($id) => ['_id' => $id, 'sort' => [0, '0']], $returnedIds),
            ],
        ];

        $client->shouldReceive('search')->with($elasticCriteria)->times($times)->andReturn($elasticResponse);

        $elasticClientBuilder = Mockery::mock(ElasticSearchClientBuilder::class);
        $elasticClientBuilder->shouldReceive('createClient')->andReturn($client);

        return $elasticClientBuilder;
    }
}
