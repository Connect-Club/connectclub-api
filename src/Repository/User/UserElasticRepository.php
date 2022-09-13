<?php

namespace App\Repository\User;

use App\Client\ElasticSearchClientBuilder;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EventLogManager;

class UserElasticRepository
{
    private ElasticSearchClientBuilder $elasticSearchClientBuilder;
    private EventLogManager $eventLogManager;
    private UserRepository $userRepository;

    public function __construct(
        ElasticSearchClientBuilder $elasticSearchClientBuilder,
        EventLogManager $eventLogManager,
        UserRepository $userRepository
    ) {
        $this->elasticSearchClientBuilder = $elasticSearchClientBuilder;
        $this->eventLogManager = $eventLogManager;
        $this->userRepository = $userRepository;
    }

    public function findIdsByQuery(string $query, ?string $lastValue = null, ?int $limit = null): array
    {
        $searchBody = [
            'sort' => [
                '_score' => 'desc',
                '_id' => 'asc'
            ],
            'query' => [
                'multi_match' => [
                    'query' => $query,
                    'operator' => 'or',
                    'fields' => ["name^5", "surname^6", "fullName^10", "username^10", "about^1"],
                ],
            ]
        ];

        if ($limit !== null) {
            $searchBody['size'] = $limit;
        }

        if ($lastValue) {
            $searchBody['search_after'] = json_decode($lastValue, true);
        }

        $elasticSearchClient = $this->elasticSearchClientBuilder->createClient();

        $items = $elasticSearchClient->search(['index' => 'user', 'type' => '_doc', 'body' => $searchBody]);

        $this->eventLogManager->logEventCustomObject('elasticsearch_request', 'elasticsearch_request', '', [
            'request' => $searchBody,
            'response' => $items,
        ]);

        $usersIds = array_map(fn(array $item) => $item['_id'], $items['hits']['hits']);

        $hits = $items['hits']['hits'];
        if ($hits) {
            $lastValueFromElasticSearch = json_encode($hits[count($hits) - 1]['sort']);
            if ($lastValueFromElasticSearch == $lastValue || $lastValueFromElasticSearch === json_encode([0, '0'])) {
                $lastValue = null;
            } else {
                $lastValue = $lastValueFromElasticSearch;
            }
        } else {
            $lastValue = null;
        }

        if ($lastValue) {
            $searchBodyForCheckingNextPage = $searchBody;
            $searchBodyForCheckingNextPage['search_after'] = json_decode($lastValue, true);

            $itemsNextPage = $elasticSearchClient->search([
                'index' => 'user',
                'type' => '_doc',
                'body' => $searchBodyForCheckingNextPage
            ]);

            $this->eventLogManager->logEventCustomObject('elasticsearch_request_next_page', 'request', $query, [
                'request' => $searchBody,
                'response' => $itemsNextPage,
            ]);

            $hits = $itemsNextPage['hits']['hits'] ?? [];
            if (!$hits) {
                $lastValue = null;
            } else {
                $nextPageUserIds = array_map(fn(array $item) => $item['_id'], $itemsNextPage['hits']['hits']);
                $nextPageUserIdsDiff = array_diff($nextPageUserIds, $usersIds);

                if (!$nextPageUserIdsDiff) { //Found only duplicates (compared with first request)
                    //Fix search_after by dynamic value of _score field
                    //See https://connectclub.atlassian.net/browse/API-1082?focusedCommentId=15508
                    $lastValue = null;
                } elseif (!$this->userRepository->findUsersByIds($nextPageUserIds)) { //Found deleted users
                    $lastValue = null;
                }
            }
        }

        return [$usersIds, $lastValue];
    }
}
