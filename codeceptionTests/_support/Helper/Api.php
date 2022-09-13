<?php

namespace App\Tests\Helper;

use App\Service\MatchingClient;
use Codeception\Module\REST;
use Codeception\Module\Symfony;
use Codeception\Util\JsonArray;
use Faker\Factory;
use Faker\Generator;
use Mockery;
use Mockery\MockInterface;
use Sms\DataFixtures\Settings;

class Api extends \Codeception\Module
{
    private array $mockedServices = [];
    private ?Generator $faker = null;

    public function seeResponseMatchesJsonTypeStrict(array $jsonType, bool $withServiceDefaultFields = true)
    {
        $this->getRest()->seeResponseIsJson();
        $jsonResponse = new JsonArray($this->getRest()->grabResponse());
        $arrayResponse = $jsonResponse->toArray();
        if ($withServiceDefaultFields) {
            $serviceJsonType = [
                'errors' => 'array',
                'response' => $jsonType,
                'requestId' => 'string',
            ];
            $diff = array_diff_key_recursive($serviceJsonType, $arrayResponse);
            $this->assertEmpty($diff, 'Response data keys mismatch ' . print_r($diff, true));
            try {
                $this->getRest()->seeResponseMatchesJsonType($serviceJsonType);
            } catch (\Exception $exception) {
                echo $exception->getTraceAsString();
            }
            $this->getRest()->seeResponseMatchesJsonType($jsonType, '$.response');
        } else {
            $diff = array_diff_key_recursive($jsonType, $arrayResponse);
            $this->assertEmpty($diff, 'Response data keys mismatch ' . print_r($diff, true));
            $this->getRest()->seeResponseMatchesJsonType($jsonType);
        }
    }

    public function mockService($id, $mock)
    {
        $container = $this->getSymfony()->kernel->getContainer();

        $this->mockedServices[$id] = $id;

        $reflectionClass = new \ReflectionClass($container);
        $serviceProperty = $reflectionClass->getProperty('services');
        $serviceProperty->setAccessible(true);
        $services = $serviceProperty->getValue($container);
        if (isset($services[$id])) {
            unset($services[$id]);
        }
        $serviceProperty->setValue($container, $services);

        $container->set($id, $mock);
        $this->getSymfony()->persistService($id);
    }

    public function cleanupMockedServices(): void
    {
        if (!$this->mockedServices) {
            return;
        }

        foreach ($this->mockedServices as $serviceId) {
            $this->getSymfony()->unpersistService($serviceId);
        }

        $this->mockedServices = [];
    }

    public function disableSoftDeleteableFilter()
    {
        $this->getSymfony()
            ->kernel
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getFilters()
            ->disable('softdeleteable');
    }

    public function mockElasticSearchClientBuilder(): MockElasticSearchClientBuilder
    {
        return new MockElasticSearchClientBuilder();
    }

    public function mockMatchingClient(MockInterface $mock = null): MockMatchingClient
    {
        if (null === $mock) {
            $mock = Mockery::mock(MatchingClient::class);
        }

        return new MockMatchingClient($this->getFaker(), $mock);
    }

    public function enableSoftDeleteableFilter()
    {
        $this->getSymfony()
            ->kernel
            ->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getFilters()
            ->enable('softdeleteable');
    }

    public function grabDataFromResponse(string $path)
    {
        return $this->getRest()->grabDataFromResponseByJsonPath('$.response.'.$path)[0] ?? null;
    }

    public function getFaker(): Generator
    {
        if (!$this->faker) {
            $this->faker = Factory::create();
        }

        return $this->faker;
    }

    private function getSymfony(): Symfony
    {
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        /** @var Symfony $module */
        $module = $this->getModule('Symfony');

        return $module;
    }

    private function getRest(): REST
    {
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        /** @var REST $module */
        $module = $this->getModule('REST');

        return $module;
    }
}

function array_diff_key_recursive(array $arr1, array $arr2)
{
    $first = $arr1;
    $second = $arr2;
    if (count($arr2) > count($arr1)) {
        $first = $arr2;
        $second = $arr1;
    }

    $diff = array_diff_key($first, $second);
    foreach ($first as $k => $v) {
        if (isset($arr1[$k]) && isset($arr2[$k]) && is_array($arr1[$k]) && is_array($arr2[$k])) {
            $d = array_diff_key_recursive($arr1[$k], $arr2[$k]);
            if ($d) {
                $diff[$k] = $d;
            }
        }
    }
    return $diff;
}
