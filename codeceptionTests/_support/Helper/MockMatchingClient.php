<?php

namespace App\Tests\Helper;

use App\Entity\User;
use Faker\Generator;
use Mockery\MockInterface;

class MockMatchingClient
{
    private MockInterface $mock;
    private Generator $faker;

    public function __construct(Generator $faker, MockInterface $mock)
    {
        $this->mock = $mock;
        $this->faker = $faker;
    }

    public function getMock(): MockInterface
    {
        return $this->mock;
    }

    public function createFakeData(): array
    {
        return [
            [
                'name' => $this->faker->text(10),
                'tokenId' => '0x' . $this->faker->sha1,
                'image' => 'ipfs://'. $this->faker->sha1 . '/17.gif',
                'description' => $this->faker->text(100),
            ],
            [
                'name' => $this->faker->text(10),
                'tokenId' => '0x' . $this->faker->sha1,
                'image' => 'https://' . $this->faker->domainName . '/media/pods/pod_level_1.png',
                'description' => $this->faker->text(100),
            ],
        ];
    }

    public function findTokensForUser(int $times, User $user, array $returnedData)
    {
        $defaultReturnedData = [
            'data' => $this->createFakeData(),
            'code' => 200
        ];

        $returnedData = array_replace_recursive($defaultReturnedData, $returnedData);

        $this->mock->shouldReceive('findTokensForUser')->with($user)->times($times)->andReturn($returnedData);
    }
}
