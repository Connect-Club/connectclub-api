<?php

namespace App\Service;

use App\Client\ElasticSearchClientBuilder;
use App\Entity\User;
use App\Repository\User\PhoneContactRepository;
use App\Service\Transaction\TransactionManager;
use App\Service\ValueObject\ContactPhone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Ramsey\Uuid\Uuid;
use Throwable;

class PhoneContactManager
{
    private EntityManagerInterface $entityManager;
    private PhoneContactRepository $phoneContactRepository;
    private TransactionManager $transactionManager;
    private ElasticSearchClientBuilder $elasticSearchClientBuilder;

    public function __construct(
        EntityManagerInterface $entityManager,
        TransactionManager $transactionManager,
        ElasticSearchClientBuilder $elasticSearchClientBuilder,
        PhoneContactRepository $phoneContactRepository
    ) {
        $this->entityManager = $entityManager;
        $this->transactionManager = $transactionManager;
        $this->elasticSearchClientBuilder = $elasticSearchClientBuilder;
        $this->phoneContactRepository = $phoneContactRepository;
    }

    /** @param ContactPhone[] $contacts */
    public function uploadContacts(User $owner, array $contacts): TransactionManager
    {
        $this->entityManager->getConnection()->executeQuery(
            'DELETE FROM phone_contact_number WHERE phone_contact_id IN(
                SELECT id FROM phone_contact WHERE owner_id = ?
            )',
            [$owner->id],
            [Types::INTEGER]
        );

        $this->entityManager->getConnection()->executeQuery(
            'DELETE FROM phone_contact WHERE owner_id = ?',
            [$owner->id],
            [Types::INTEGER]
        );

        if (!$contacts) {
            return $this->transactionManager;
        }

        if ($owner->phone) {
            $region = PhoneNumberUtil::getInstance()->getRegionCodeForNumber($owner->phone);
        }
        $region = $region ?? PhoneNumberUtil::UNKNOWN_REGION;

        $newPhoneContacts = [];
        $phoneUtil = PhoneNumberUtil::getInstance();

        foreach ($contacts as $contact) {
            /** @var PhoneNumber[] $phoneContactNumbers */
            $phoneContactNumbers = [];

            foreach ($contact->phoneNumbers as $phoneNumber) {
                try {
                    $phone = $phoneUtil->parse($phoneNumber, $region);
                } catch (Throwable $e) {
                    continue;
                }

                $phoneContactNumbers[] = [
                    'originalPhone' => $phoneNumber,
                    'phone' => $phone,
                ];
            }

            if (!$phoneContactNumbers) {
                continue;
            }

            $mainPhoneNumber = $phoneContactNumbers[0]['phone'];

            $phone = $phoneUtil->format($mainPhoneNumber, PhoneNumberFormat::E164);
            $newPhoneContacts[$phone] = [
                'phone' => $phoneContactNumbers[0]['phone'],
                'originalPhone' => $phoneContactNumbers[0]['originalPhone'],
                'phoneNumbers' => $phoneContactNumbers,
                'fullName' => $contact->fullName,
                'thumbnail' => $contact->thumbnail,
            ];
        }

        if (!$newPhoneContacts) {
            return $this->transactionManager;
        }

        $sqlInsertPhoneContactNumber = 'INSERT INTO phone_contact_number (
                                          id, phone_contact_id, phone_number, original_phone
                                        ) VALUES ';

        $sqlInsertPhoneContact = 'INSERT INTO phone_contact (
                                          id, owner_id, phone_number, original_phone,
                                          full_name, sort, thumbnail, created_at
                                 ) VALUES ';

        $sqlInsertPhoneContactNumberValues = $sqlInsertPhoneContactValues = $types = $values = [];

        $sort = 0;
        foreach ($newPhoneContacts as $newPhoneContactFormattedNumber => $newPhoneContact) {
            $phoneContactGeneratedId = Uuid::uuid4()->toString();

            $sqlInsertPhoneContactValues[] = [
                $phoneContactGeneratedId,
                $owner->id,
                $newPhoneContactFormattedNumber,
                $newPhoneContact['originalPhone'],
                $newPhoneContact['fullName'],
                $sort,
                $newPhoneContact['thumbnail'],
                time()
            ];

            foreach ($newPhoneContact['phoneNumbers'] as $additionalPhoneNumber) {
                $additionalPhoneNumberFormatted = $phoneUtil->format(
                    $additionalPhoneNumber['phone'],
                    PhoneNumberFormat::E164
                );

                $sqlInsertPhoneContactNumberValues[] = [
                    Uuid::uuid4()->toString(),
                    $phoneContactGeneratedId,
                    $additionalPhoneNumberFormatted,
                    $additionalPhoneNumber['originalPhone']
                ];
            }

            ++$sort;
        }

        foreach ($sqlInsertPhoneContactValues as $k => $rowValues) {
            $isLast = $k == count($sqlInsertPhoneContactValues) - 1;
            $sqlInsertPhoneContact .= '('.implode(',', array_fill(0, count($rowValues), '?')).')'.($isLast ? '' : ',');

            foreach ($rowValues as $rowValue) {
                if (is_int($rowValue)) {
                    $type = Types::INTEGER;
                } else {
                    $type = Types::STRING;
                }

                $types[] = $type;
                $values[] = $rowValue;
            }
        }


        $sqlInsertPhoneContact .= ' ON CONFLICT (owner_id, phone_number) DO UPDATE SET 
                                  original_phone = excluded.original_phone, 
                                  full_name = excluded.full_name, id = excluded.id';

        $this->entityManager->getConnection()->executeQuery(
            $sqlInsertPhoneContact,
            $values,
            $types
        );

        $types = $values = [];
        foreach ($sqlInsertPhoneContactNumberValues as $k => $rowValues) {
            $isLast = $k == count($sqlInsertPhoneContactNumberValues) - 1;
            $sqlInsertPhoneContactNumber .= '('.implode(',', array_fill(0, count($rowValues), '?')).')'.(
                $isLast ? '' : ','
            );
            foreach ($rowValues as $rowValue) {
                if (is_int($rowValue)) {
                    $type = Types::INTEGER;
                } else {
                    $type = Types::STRING;
                }

                $types[] = $type;
                $values[] = $rowValue;
            }
        }
        $sqlInsertPhoneContactNumber .= ' ON CONFLICT DO NOTHING';
        $this->entityManager->getConnection()->executeQuery(
            $sqlInsertPhoneContactNumber,
            $values,
            $types
        );

        return $this->transactionManager;
    }

    public function uploadContactsToElasticSearch(User $user)
    {
        $client = $this->elasticSearchClientBuilder->createClient();

        $mapping = [
            'properties' => [
                'phoneNumber' => [
                    'type' => 'text',
                    'analyzer' => 'autocomplete',
                    'search_analyzer' => 'standard',
                ],
                'fullName' => [
                    'type' => 'text',
                    'analyzer' => 'autocomplete',
                    'search_analyzer' => 'standard',
                ],
                'ownerId' => [
                    'type' => 'text',
                ],
                'phoneContactId' => [
                    'type' => 'text',
                ],
                'sortNumber' => [
                    'type' => 'integer',
                ]
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
            $client->indices()->get(['index' => 'phone_contact']);
        } catch (Missing404Exception $elasticsearchException) {
            $client->indices()->create([
                'index' => 'phone_contact',
                'body' => [
                    'mappings' => $mapping,
                    'settings' => $settings,
                ]
            ]);
        }

        $client->deleteByQuery([
            'index' => 'phone_contact',
            'type' => '_doc',
            'body' => [
                'query' => [
                    'match' => [
                        'ownerId' => $user->id
                    ]
                ]
            ]
        ]);

        $phoneContactData = array_map(
            fn(array $data) => array_values($data),
            $this->phoneContactRepository->findPhoneContactsData($user)
        );

        if (!$phoneContactData) {
            return;
        }

        $bulk = [];
        foreach ($phoneContactData as $i => list($id, $fullName, $phoneNumber)) {
            $bulk['body'][] = [
                'create' => [
                    '_index' => 'phone_contact',
                    '_type' => '_doc',
                    '_id' => $id,
                ]
            ];

            $bulk['body'][] = [
                'fullName' => $fullName,
                'phoneNumber' => $phoneNumber,
                'ownerId' => (string) $user->id,
                'sortNumber' => $i,
            ];

            if ($i % 2000 === 0) {
                $client->bulk($bulk);
                $bulk = [];
            }
        }

        if ($bulk) {
            $client->bulk($bulk);
        }
    }
}
