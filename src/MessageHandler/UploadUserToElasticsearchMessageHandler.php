<?php

namespace App\MessageHandler;

use App\Client\ElasticSearchClientBuilder;
use App\Entity\Interest\Interest;
use App\Entity\User;
use App\Message\UploadUserToElasticsearchMessage;
use App\Repository\UserRepository;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class UploadUserToElasticsearchMessageHandler implements MessageHandlerInterface
{
    private ElasticSearchClientBuilder $elasticSearchClientBuilder;
    private UserRepository $userRepository;

    public function __construct(ElasticSearchClientBuilder $elasticSearchClientBuilder, UserRepository $userRepository)
    {
        $this->elasticSearchClientBuilder = $elasticSearchClientBuilder;
        $this->userRepository = $userRepository;
    }

    public function __invoke(UploadUserToElasticsearchMessage $message)
    {
        $client = $this->elasticSearchClientBuilder->createClient();

        $user = $this->userRepository->find($message->getUserId());
        if (!$user || $user->state !== User::STATE_VERIFIED || $user->isTester) {
            return;
        }

        $mapping = [
            'properties' => [
                'name' => ElasticSearchClientBuilder::DEFAULT_TEXT_MAPPING,
                'surname' => ElasticSearchClientBuilder::DEFAULT_TEXT_MAPPING,
                'username' => ElasticSearchClientBuilder::DEFAULT_TEXT_MAPPING,
                'fullName' => ElasticSearchClientBuilder::DEFAULT_TEXT_MAPPING,
                'about' => ElasticSearchClientBuilder::DEFAULT_TEXT_MAPPING,
                'languages' => ['type' => 'text'],
            ],
        ];

        $settings = [
            'analysis' => [
                'filter' => [
                    'autocomplete_filter' => [
                        'type' => 'edge_ngram',
                        'min_gram' => 1,
                        'max_gram' => 20,
                    ],
                ],
                'analyzer' => [
                    'autocomplete' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => [
                            'lowercase',
                            'autocomplete_filter',
                        ],
                    ],
                ],
            ],
        ];

        try {
            $client
                ->indices()
                ->get(['index' => 'user']);

            $client
                ->indices()
                ->putMapping(['index' => 'user', 'body' => $mapping]);
        } catch (Missing404Exception $elasticsearchException) {
            $client
                ->indices()
                ->create(['index' => 'user', 'body' => ['mappings' => $mapping, 'settings' => $settings]]);
        }

        $body = [
            'name' => $user->name,
            'surname' => $user->surname,
            'username' => $user->username,
            'about' => $user->about,
            'languages' => $user->languages,
            'fullName' => $user->name . ' ' . $user->surname
        ];

        try {
            $client->get(['index' => 'user', 'id' => $user->id]);
            $client->update(['index' => 'user', 'id' => $user->id, 'body' => ['doc' => $body]]);
        } catch (Missing404Exception $exception) {
            //Create if not exists
            $client->index(['index' => 'user', 'id' => $user->id, 'body' => $body]);
        }

        $user->uploadToElasticSearchAt = time();
        $this->userRepository->save($user);
    }
}
