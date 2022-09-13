<?php

namespace App\Tests\Subscription;

use App\Controller\V1\SubscriptionController;
use App\Entity\Subscription\PaidSubscription;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Service\SubscriptionService;
use App\Service\Transaction\TransactionManager;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use App\Tests\Fixture\SubscriptionPaymentFixture;
use Codeception\Example;
use App\Tests\Module\Doctrine2;
use Codeception\Util\HttpCode;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Generator;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Mockery\MockInterface;
use Ramsey\Uuid\Uuid;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\Price;
use Stripe\Product;
use Stripe\Service\CustomerService;
use Stripe\SetupIntent;
use Stripe\Subscription as StripeSubscription;
use Stripe\Service\PriceService;
use Stripe\Service\ProductService;
use Stripe\Service\SubscriptionService as StripeClientSubscriptionService;
use Stripe\StripeClient;
use Symfony\Bridge\PhpUnit\ClockMock;

class SubscriptionCest extends BaseCest
{
    public const STRIPE_PRICE_ID = 'price_1JIpf8CbRTc2e9DZeYmMz2bb';
    public const STRIPE_PRODUCT_ID = 'prod_JrcDdsXXtSiH2N';
    public const STRIPE_PAYMENT_INTENT_ID = 'pi_3JKLVnCbRTc2e9DZ1jVxAZH3';
    public const STRIPE_SUBSCRIPTION_ID = 'sub_JyILw57oJZTiRT';
    public const STRIPE_CUSTOMER_ID = 'cus_JjJ2g4fAJBTLqs';
    public const STRIPE_CLIENT_SECRET = 'pi_3JKLVnCbRTc2e9DZ1jVxAZH3_secret_qoi37E2smY01YW14mFseK2M6e';
    public const STRIPE_INVOICE_ID = 'in_1JKlMECbRTc2e9DZL2lMq8Qp';
//
//    /** @var MockInterface|PriceService  */
//    private MockInterface $stripePriceService;
//    /** @var MockInterface|ProductService */
//    private MockInterface $stripeProductService;
//    /** @var MockInterface|CustomerService */
//    private MockInterface $stripeCustomerService;
//    /** @var MockInterface|StripeClientSubscriptionService */
//    private MockInterface $stripeSubscriptionService;
//
//    /** @noinspection PhpSignatureMismatchDuringInheritanceInspection */
//    //phpcs:ignore
//    public function _before(ApiTester $I): void
//    {
//        parent::_before();
//
//        $stripeClientMock = $this->mockStripeClient($I);
//
//        $this->stripeProductService = $this->mockStripeProductService($stripeClientMock);
//        $this->stripePriceService = $this->mockStripePriceService($stripeClientMock);
//        $this->stripeCustomerService = $this->mockStripeCustomerService($stripeClientMock);
//        $this->stripeSubscriptionService = $this->mockStripeSubscriptionService($stripeClientMock);
//    }
//
//    //phpcs:ignore
//    public function _after(ApiTester $I, Doctrine2 $module): void
//    {
//        if (!$module->_getConfig('cleanup')) {
//            $this->cleanupSubscriptions($I);
//        }
//
//        parent::_after($I, $module);
//    }
//
//    public function testMy(ApiTester $I): void
//    {
//        $I->loadFixtures(new class extends Fixture {
//            private ObjectManager $manager;
//
//            public function load(ObjectManager $manager): void
//            {
//                $this->manager = $manager;
//
//                $userRepository = $manager->getRepository(User::class);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
//
//                $this->createSubscriptionsForAuthor($main, 3);
//                $this->createSubscriptionsForAuthor($alice, 1);
//
//                $manager->flush();
//            }
//
//            private function createSubscriptionsForAuthor(User $author, int $quantity): void
//            {
//                $uuids = [];
//                for ($i = 0; $i < $quantity; $i++) {
//                    $uuids[] = Uuid::uuid4();
//                }
//
//                usort($uuids, fn($uuid1, $uuid2) => strcmp((string) $uuid1, (string) $uuid2));
//
//                foreach ($uuids as $i => $uuid) {
//                    $subscription = new Subscription(
//                        "Subscription {$i} of {$author->email}",
//                        ($i % 3 + 1) * 500,
//                        $i,
//                        $i,
//                        $author
//                    );
//                    $subscription->description = "Description {$i} of {$author->email}";
//                    $subscription->id = $uuid;
//                    $this->manager->persist($subscription);
//                }
//            }
//        });
//
//        $I->sendGet('/v1/subscription/my');
//        $I->seeResponseCodeIs(HttpCode::UNAUTHORIZED);
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $I->sendGet('/v1/subscription/my', [
//            'limit' => 2,
//        ]);
//        $I->seeResponseCodeIs(HttpCode::OK);
//
//        $lastValue = $this->assertItems($I, [
//            [
//                'name' => 'Subscription 0 of ' . self::MAIN_USER_EMAIL,
//                'price' => 500,
//                'isActive' => false,
//                'description' => 'Description 0 of ' . self::MAIN_USER_EMAIL,
//            ],
//            [
//                'name' => 'Subscription 1 of ' . self::MAIN_USER_EMAIL,
//                'price' => 1000,
//                'isActive' => false,
//                'description' => 'Description 1 of ' . self::MAIN_USER_EMAIL,
//            ],
//        ]);
//        $this->assertItemsHasFields($I, ['id', 'createdAt']);
//
//        $I->sendGet('/v1/subscription/my', [
//            'limit' => 2,
//            'lastValue' => $lastValue,
//        ]);
//        $lastValue = $this->assertItems($I, [
//            [
//                'name' => 'Subscription 2 of ' . self::MAIN_USER_EMAIL,
//                'price' => 1500,
//                'isActive' => false,
//                'description' => 'Description 2 of ' . self::MAIN_USER_EMAIL,
//            ]
//        ]);
//
//        $I->assertNull($lastValue);
//    }
//
//    /**
//     * @dataProvider transactionalTestDataProvider
//     * @prepare noCleanup
//     */
//    public function testCreate(ApiTester $I, Example $example): void
//    {
//        $stripePrice = new Price(self::STRIPE_PRICE_ID);
//        $stripePrice->product = self::STRIPE_PRODUCT_ID;
//
//        $stripeProduct = new Product(self::STRIPE_PRODUCT_ID);
//
//        $this->assertProductWillBeCreated([
//            'name' => 'Test main user subscription',
//            'description' => 'Test main user description',
//            'metadata' => [
//                'backend' => 'test',
//            ],
//        ], $stripeProduct);
//
//        $this->assertPriceWillBeCreated([
//            'unit_amount' => 500,
//            'currency' => 'USD',
//            'product' => $stripeProduct->id,
//            'recurring' => [
//                'interval' => 'month',
//            ],
//            'metadata' => [
//                'backend' => 'test',
//            ],
//        ], $stripePrice);
//
//        if ($example['throwException']) {
//            $this->throwExceptionBeforeCommit($I);
//            $this->assertProductWillBeUpdated([
//                'active' => false,
//            ]);
//            $this->assertPriceWillBeUpdated([
//                'active' => false,
//            ]);
//        }
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $I->sendPost('/v1/subscription', json_encode([
//            'name' => 'Test main user subscription',
//            'description' => 'Test main user description',
//            'price' => 500,
//            'isActive' => true,
//        ]));
//
//        if ($example['throwException']) {
//            $I->seeResponseCodeIs(HttpCode::INTERNAL_SERVER_ERROR);
//
//            $I->cantSeeInRepository(Subscription::class, [
//                'name' => 'Test main user subscription',
//            ]);
//        } else {
//            $I->seeResponseCodeIs(HttpCode::OK);
//
//            $I->canSeeInRepository(Subscription::class, [
//                'author' => $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]),
//                'name' => 'Test main user subscription',
//                'description' => 'Test main user description',
//                'price' => 500,
//                'isActive' => true,
//                'stripeId' => self::STRIPE_PRODUCT_ID,
//                'stripePriceId' => self::STRIPE_PRICE_ID,
//            ]);
//
//            $I->seeResponseContainsJson([
//                'response' => [
//                    'id' => $this->findSubscription($I, 'Test main user subscription')->id->toString(),
//                ],
//            ]);
//        }
//    }
//
//    public function testCreateWithEmptyDescription(ApiTester $I): void
//    {
//        $stripePrice = new Price(self::STRIPE_PRICE_ID);
//        $stripePrice->product = self::STRIPE_PRODUCT_ID;
//
//        $stripeProduct = new Product(self::STRIPE_PRODUCT_ID);
//
//        $this->assertProductWillBeCreated([
//            'name' => 'Test main user subscription',
//            'metadata' => [
//                'backend' => 'test',
//            ],
//        ], $stripeProduct);
//
//        $this->assertPriceWillBeCreated([
//            'unit_amount' => 500,
//            'currency' => 'USD',
//            'product' => $stripeProduct->id,
//            'recurring' => [
//                'interval' => 'month',
//            ],
//            'metadata' => [
//                'backend' => 'test',
//            ],
//        ], $stripePrice);
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $I->sendPost('/v1/subscription', json_encode([
//            'name' => 'Test main user subscription',
//            'description' => '',
//            'price' => 500,
//            'isActive' => true,
//        ]));
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//    }
//
//    public function testCreateValidationErrors(ApiTester $I): void
//    {
//        $I->amBearerAuthenticated(BaseCest::MAIN_ACCESS_TOKEN);
//
//        $I->sendPost('/v1/subscription', json_encode([]));
//        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
//        $I->seeResponseContainsJson([
//            'errors' => [
//                "name:cannot_be_empty",
//                "isActive:cannot_be_empty",
//                "price:cannot_be_empty",
//            ],
//        ]);
//
//        $I->sendPost('/v1/subscription', json_encode([
//            'name' => 'Test name',
//            'isActive' => true,
//            'price' => 2300,
//        ]));
//        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
//        $I->seeResponseContainsJson([
//            'errors' => [
//                "price:is_not_a_valid_choice",
//            ],
//        ]);
//
//        $I->sendPost('/v1/subscription', json_encode([
//            'name' => 'Test name',
//            'isActive' => 'true',
//            'price' => 500,
//        ]));
//        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
//    }
//
//    public function testCreateActiveLimit(ApiTester $I): void
//    {
//        $I->loadFixtures(new class extends Fixture {
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $subscription = new Subscription(
//                    'Active subscription',
//                    5,
//                    SubscriptionCest::STRIPE_PRODUCT_ID,
//                    SubscriptionCest::STRIPE_PRICE_ID,
//                    $main
//                );
//                $subscription->isActive = true;
//                $manager->persist($subscription);
//
//                $manager->flush();
//            }
//        });
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $I->sendPost('/v1/subscription', json_encode([
//            'name' => 'Test main user subscription',
//            'description' => 'Test main user description',
//            'price' => 500,
//            'isActive' => true,
//        ]));
//
//        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
//
//        $I->seeResponseContainsJson([
//            'errors' => ['v1.subscription.active_limit'],
//        ]);
//    }
//
//    /**
//     * @dataProvider transactionalTestDataProvider
//     * @prepare noCleanup
//     */
//    public function testUpdate(ApiTester $I, Example $example): void
//    {
//        $I->loadFixtures(new class extends Fixture {
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $subscription = new Subscription(
//                    'Subscription to update',
//                    500,
//                    SubscriptionCest::STRIPE_PRODUCT_ID,
//                    SubscriptionCest::STRIPE_PRICE_ID,
//                    $main
//                );
//                $manager->persist($subscription);
//
//                $subscription = new Subscription(
//                    'Subscription to not update',
//                    500,
//                    SubscriptionCest::STRIPE_PRODUCT_ID,
//                    SubscriptionCest::STRIPE_PRICE_ID,
//                    $main
//                );
//                $manager->persist($subscription);
//
//                $manager->flush();
//            }
//        });
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $subscriptionToUpdate = $this->findSubscription($I, 'Subscription to update');
//
//        $main = $this->findMainUser($I);
//
//        $this->assertProductWillBeUpdated([
//            'name' => 'Updated subscription',
//            'description' => 'Description of the updated subscription',
//        ]);
//
//        if ($example['throwException']) {
//            $this->assertProductWillBeUpdated([
//                'name' => 'Subscription to update',
//                'description' => '',
//            ]);
//            $this->throwExceptionBeforeCommit($I);
//        }
//
//        $I->sendPatch("/v1/subscription/{$subscriptionToUpdate->id}", json_encode([
//            'name' => 'Updated subscription',
//            'description' => 'Description of the updated subscription',
//            'price' => 1000,
//            'isActive' => true,
//        ]));
//
//        if ($example['throwException']) {
//            $I->seeResponseCodeIs(HttpCode::INTERNAL_SERVER_ERROR);
//
//            $I->seeInRepository(Subscription::class, [
//                'author' => $main,
//                'name' => 'Subscription to update',
//                'description' => '',
//                'price' => 500,
//                'isActive' => false,
//            ]);
//        } else {
//            $I->seeResponseCodeIs(HttpCode::OK);
//
//            $I->seeInRepository(Subscription::class, [
//                'author' => $main,
//                'name' => 'Updated subscription',
//                'description' => 'Description of the updated subscription',
//                'price' => 500,
//                'isActive' => true,
//            ]);
//        }
//
//        $I->seeInRepository(Subscription::class, [
//            'name' => 'Subscription to not update',
//        ]);
//    }
//
//    public function testUpdateValidationErrors(ApiTester $I): void
//    {
//        $I->loadFixtures(new class extends Fixture {
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $subscription = new Subscription(
//                    'Subscription to update',
//                    5,
//                    SubscriptionCest::STRIPE_PRODUCT_ID,
//                    SubscriptionCest::STRIPE_PRICE_ID,
//                    $main
//                );
//                $manager->persist($subscription);
//
//                $manager->flush();
//            }
//        });
//
//        $I->amBearerAuthenticated(BaseCest::MAIN_ACCESS_TOKEN);
//
//        $subscriptionToUpdate = $this->findSubscription($I, 'Subscription to update');
//
//        $I->sendPatch("/v1/subscription/{$subscriptionToUpdate->id}", json_encode([]));
//        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
//        $I->seeResponseContainsJson([
//            'errors' => [
//                "name:cannot_be_empty",
//                "isActive:cannot_be_empty",
//                "description:cannot_be_null",
//            ],
//        ]);
//
//        $I->sendPatch("/v1/subscription/{$subscriptionToUpdate->id}", json_encode([
//            'name' => 'Test name',
//            'isActive' => 'true',
//            'price' => 500,
//        ]));
//        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
//    }
//
//    public function testUpdatePriceNotUpdating(ApiTester $I): void
//    {
//        $I->loadFixtures(new class extends Fixture {
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $subscription = new Subscription(
//                    'Subscription to update',
//                    500,
//                    SubscriptionCest::STRIPE_PRODUCT_ID,
//                    SubscriptionCest::STRIPE_PRICE_ID,
//                    $main
//                );
//                $manager->persist($subscription);
//
//                $manager->flush();
//            }
//        });
//
//        $I->amBearerAuthenticated(BaseCest::MAIN_ACCESS_TOKEN);
//
//        $this->assertProductWillBeUpdated([
//            'name' => 'Subscription to update',
//            'description' => 'Description',
//        ]);
//
//        $subscriptionToUpdate = $this->findSubscription($I, 'Subscription to update');
//
//        $I->sendPatch("/v1/subscription/{$subscriptionToUpdate->id}", json_encode([
//            'name' => 'Subscription to update',
//            'description' => 'Description',
//            'isActive' => true,
//            'price' => 1000,
//        ]));
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//        $updatedSubscriptionPrice = $I->grabFromRepository(Subscription::class, 'price', [
//            'name' => 'Subscription to update',
//        ]);
//        $I->assertEquals(500, $updatedSubscriptionPrice);
//    }
//
//    public function testUpdateActiveLimit(ApiTester $I): void
//    {
//        $I->loadFixtures(new class extends Fixture {
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $subscription = new Subscription(
//                    'Active subscription',
//                    5,
//                    SubscriptionCest::STRIPE_PRODUCT_ID,
//                    SubscriptionCest::STRIPE_PRICE_ID,
//                    $main
//                );
//                $subscription->isActive = true;
//                $manager->persist($subscription);
//
//                $subscription = new Subscription(
//                    'Not active subscription',
//                    5,
//                    SubscriptionCest::STRIPE_PRODUCT_ID,
//                    SubscriptionCest::STRIPE_PRICE_ID,
//                    $main
//                );
//                $manager->persist($subscription);
//
//                $manager->flush();
//            }
//        });
//
//        $notActiveSubscription = $this->findSubscription($I, 'Not active subscription');
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $I->sendPatch("/v1/subscription/{$notActiveSubscription->id}", json_encode([
//            'name' => 'Not active subscription',
//            'description' => 'Description',
//            'isActive' => true,
//        ]));
//
//        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
//    }
//
//    /**
//     * @dataProvider transactionalTestDataProvider
//     * @prepare noCleanup
//     */
//    public function testDelete(ApiTester $I, Example $example)
//    {
//        $I->loadFixtures(new class extends Fixture {
//            public function load(ObjectManager $manager)
//            {
//                $main = $manager->getRepository(User::class)->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $subscription = new Subscription(
//                    'Subscription to delete',
//                    5,
//                    SubscriptionCest::STRIPE_PRODUCT_ID,
//                    SubscriptionCest::STRIPE_PRICE_ID,
//                    $main
//                );
//
//                $manager->persist($subscription);
//                $manager->flush();
//            }
//        });
//
//        $this->assertPriceWillBeUpdated([
//            'active' => false,
//        ]);
//        $this->assertProductWillBeUpdated([
//            'active' => false,
//        ]);
//
//        if ($example['throwException']) {
//            $this->throwExceptionBeforeCommit($I);
//
//            $this->assertPriceWillBeUpdated([
//                'active' => true,
//            ]);
//            $this->assertProductWillBeUpdated([
//                'active' => true,
//            ]);
//        }
//
//        $subscriptionToDelete = $this->findSubscription($I, 'Subscription to delete');
//
//        $I->amBearerAuthenticated(BaseCest::MAIN_ACCESS_TOKEN);
//
//        $I->sendDelete("/v1/subscription/{$subscriptionToDelete->id}");
//
//        if ($example['throwException']) {
//            $I->seeResponseCodeIs(HttpCode::INTERNAL_SERVER_ERROR);
//            $I->canSeeInRepository(Subscription::class, [
//                'name' => 'Subscription to delete',
//            ]);
//        } else {
//            $I->seeResponseCodeIs(HttpCode::OK);
//            $I->cantSeeInRepository(Subscription::class, [
//                'name' => 'Subscription to delete',
//            ]);
//        }
//
//        $this->cleanupSubscriptions($I);
//    }
//
//    /**
//     * @dataProvider transactionalTestDataProvider
//     * @prepare noCleanup
//     */
//    public function testBuyCustomerNotExists(ApiTester $I, Example $example): void
//    {
//        $main = $this->findMainUser($I);
//
//        $originalPhone = $main->phone ? clone $main->phone : null;
//        $originalUsername = $main->username;
//
//        $I->loadFixtures(new class extends Fixture {
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
//
//                $main->username = 'test_username';
//                $main->phone = PhoneNumberUtil::getInstance()->parse('+79636417683');
//
//                $subscription = new Subscription(
//                    'To buy',
//                    500,
//                    SubscriptionCest::STRIPE_PRODUCT_ID,
//                    SubscriptionCest::STRIPE_PRICE_ID,
//                    $alice
//                );
//                $manager->persist($subscription);
//
//                $subscription = new Subscription(
//                    'To not buy',
//                    500,
//                    '12312312',
//                    '123123',
//                    $alice
//                );
//                $manager->persist($subscription);
//
//                $manager->flush();
//            }
//        });
//
//        $subscription = $this->findSubscription($I, 'To buy');
//
//        $this->assertCustomerWillBeCreated($main);
//
//        if ($example['throwException']) {
//            $this->throwExceptionBeforeCommit($I);
//            $this->assertCustomerWillBeDeleted();
//        } else {
//            $this->assertSubscriptionWillBeCreated($main, $subscription);
//        }
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $I->sendPost("/v1/subscription/{$subscription->id}/buy");
//
//        $main = $this->findMainUser($I);
//        $I->refreshEntities($main);
//
//        if ($example['throwException']) {
//            $I->seeResponseCodeIs(HttpCode::INTERNAL_SERVER_ERROR);
//
//            $I->assertNull($main->stripeCustomerId);
//
//            $I->dontSeeInRepository(PaidSubscription::class, [
//                'subscription' => $subscription,
//                'subscriber' => $main,
//            ]);
//        } else {
//            $I->seeResponseCodeIs(HttpCode::OK);
//            $I->seeResponseContainsJson([
//                'response' => [
//                    'clientSecret' => self::STRIPE_CLIENT_SECRET,
//                    'publicKey' => 'pk_test_example_key',
//                ],
//            ]);
//
//            $I->assertEquals(self::STRIPE_CUSTOMER_ID, $main->stripeCustomerId);
//
//            $I->seeInRepository(PaidSubscription::class, [
//                'subscription' => $subscription,
//                'subscriber' => $main,
//            ]);
//        }
//
//        $main->phone = $originalPhone;
//        $main->username = $originalUsername;
//        $main->stripeCustomerId = null;
//        $I->flushToDatabase();
//    }
//
//    public function testBuyCustomerExists(ApiTester $I): void
//    {
//        $I->loadFixtures(new class extends Fixture {
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $subscription = new Subscription(
//                    'To buy',
//                    500,
//                    SubscriptionCest::STRIPE_PRODUCT_ID,
//                    SubscriptionCest::STRIPE_PRICE_ID,
//                    $main
//                );
//                $manager->persist($subscription);
//
//                $main->stripeCustomerId = SubscriptionCest::STRIPE_CUSTOMER_ID;
//
//                $subscription = new Subscription(
//                    'To not buy',
//                    500,
//                    '12312312',
//                    '123123',
//                    $main
//                );
//                $manager->persist($subscription);
//
//                $manager->flush();
//            }
//        });
//
//        $subscription = $this->findSubscription($I, 'To buy');
//
//        $this->assertSubscriptionWillBeCreated($this->findMainUser($I), $subscription);
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $I->sendPost("/v1/subscription/{$subscription->id}/buy");
//        $I->seeResponseCodeIs(HttpCode::OK);
//        $I->seeResponseContainsJson([
//            'response' => [
//                'clientSecret' => self::STRIPE_CLIENT_SECRET,
//                'publicKey' => 'pk_test_example_key',
//            ],
//        ]);
//
//        $main = $this->findMainUser($I);
//        $I->assertEquals(self::STRIPE_CUSTOMER_ID, $main->stripeCustomerId);
//
//        $I->seeInRepository(PaidSubscription::class, [
//            'subscription' => $subscription,
//            'subscriber' => $main,
//        ]);
//    }
//
//    public function testBuySubscriptionNotFound(ApiTester $I): void
//    {
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $I->sendPost('/v1/subscription/' . Uuid::uuid4() . '/buy');
//
//        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
//
//        $I->seeResponseContainsJson([
//            'errors' => [
//                'v1.not_found',
//            ],
//        ]);
//    }
//
//    public function testGet(ApiTester $I): void
//    {
//        $I->loadFixtures(new class extends Fixture {
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
//
//                $subscription = new Subscription(
//                    'Alice subscription',
//                    500,
//                    'test-stripe-product-id-2',
//                    'test-stripe-price-id-2',
//                    $alice
//                );
//                $subscription->description = 'Alice subscription description';
//                $manager->persist($subscription);
//
//                $manager->flush();
//            }
//        });
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $aliceSubscription = $this->findSubscription($I, 'Alice subscription');
//
//        $I->sendGet("/v1/subscription/{$aliceSubscription->id}");
//        $I->seeResponseCodeIs(HttpCode::OK);
//
//        $I->assertEquals([
//            'id' => $aliceSubscription->id->toString(),
//            'name' => 'Alice subscription',
//            'description' => 'Alice subscription description',
//            'price' => 500,
//            'isActive' => false,
//            'authorId' => $I->grabFromRepository(User::class, 'id', ['email' => self::ALICE_USER_EMAIL]),
//            'createdAt' => $aliceSubscription->createdAt,
//        ], $I->grabDataFromResponseByJsonPath('$.response')[0]);
//    }
//
//    public function testGetNotFound(ApiTester $I)
//    {
//        $I->loadFixtures(new class extends Fixture {
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $manager->persist(new Subscription(
//                    'Subscription',
//                    500,
//                    'test-stripe-product-id',
//                    'test-stripe-price-id',
//                    $main
//                ));
//
//                $manager->flush();
//            }
//        });
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $I->sendGet("/v1/subscription/d6cd84af-aaaa-4dc3-9b33-80b150bc55e4");
//        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
//    }
//
//    public function testPaymentSent(ApiTester $I): void
//    {
//        ClockMock::withClockMock(10000);
//
//        $I->loadFixtures(new class extends Fixture {
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $subscription = new Subscription(
//                    'Subscription',
//                    500,
//                    'test-stripe-product-id',
//                    'test-stripe-price-id',
//                    $alice
//                );
//                $subscription->isActive = true;
//                $manager->persist($subscription);
//
//                $manager->persist(new PaidSubscription($main, $subscription, PaidSubscription::STATUS_INCOMPLETE));
//
//                $manager->flush();
//            }
//        });
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $subscription = $this->findSubscription($I, 'Subscription');
//
//        $I->sendPost("/v1/subscription/{$subscription->id}/payment-sent");
//        $I->seeResponseCodeIs(HttpCode::OK);
//
//        $I->seeInRepository(PaidSubscription::class, [
//            'subscription' => $subscription,
//            'subscriber' => $this->findMainUser($I),
//            'waitingForPaymentConfirmationUpTo' => time() + 300,
//        ]);
//    }
//
//    public function testPaymentSentWrongStatus(ApiTester $I): void
//    {
//        $I->loadFixtures(new class extends Fixture {
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $subscription = new Subscription(
//                    'Subscription',
//                    500,
//                    'test-stripe-product-id',
//                    'test-stripe-price-id',
//                    $alice
//                );
//                $subscription->isActive = true;
//                $manager->persist($subscription);
//
//                $manager->persist(new PaidSubscription(
//                    $main,
//                    $subscription,
//                    PaidSubscription::STATUS_INCOMPLETE_EXPIRED
//                ));
//
//                $manager->flush();
//            }
//        });
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $subscription = $this->findSubscription($I, 'Subscription');
//
//        $I->sendPost("/v1/subscription/{$subscription->id}/payment-sent");
//        $I->seeResponseCodeIs(HttpCode::OK);
//
//        $I->seeInRepository(PaidSubscription::class, [
//            'subscription' => $subscription,
//            'subscriber' => $this->findMainUser($I),
//            'waitingForPaymentConfirmationUpTo' => null,
//        ]);
//    }
//
//    public function testPaymentSentNoPaidSubscription(ApiTester $I): void
//    {
//        $I->loadFixtures(new class extends Fixture {
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
//
//                $subscription = new Subscription(
//                    'Subscription',
//                    500,
//                    'test-stripe-product-id',
//                    'test-stripe-price-id',
//                    $alice
//                );
//                $subscription->isActive = true;
//                $manager->persist($subscription);
//
//                $manager->flush();
//            }
//        });
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $subscription = $this->findSubscription($I, 'Subscription');
//
//        $I->sendPost("/v1/subscription/{$subscription->id}/payment-sent");
//        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
//    }
//
//    /**
//     * @dataProvider paymentStatusDataProvider
//     */
//    public function testPaymentStatus(ApiTester $I, Example $example): void
//    {
//        ClockMock::withClockMock(100);
//
//        $I->loadFixtures(new class($example['subscriptionStatus'], $example['waitingUpTo'] ?? null) extends Fixture {
//            private ?int $waitingUpTo;
//            private string $subscriptionStatus;
//
//            public function __construct(string $subscriptionStatus, ?int $waitingUpTo)
//            {
//                $this->waitingUpTo = $waitingUpTo;
//                $this->subscriptionStatus = $subscriptionStatus;
//            }
//
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $subscription = new Subscription(
//                    'Subscription',
//                    500,
//                    'test-stripe-product-id',
//                    'test-stripe-price-id',
//                    $alice
//                );
//                $subscription->isActive = true;
//                $manager->persist($subscription);
//
//                $paidSubscription = new PaidSubscription(
//                    $main,
//                    $subscription,
//                    $this->subscriptionStatus
//                );
//                $paidSubscription->waitingForPaymentConfirmationUpTo = $this->waitingUpTo;
//                $manager->persist($paidSubscription);
//
//                $manager->flush();
//            }
//        });
//
//        $subscription = $this->findSubscription($I, 'Subscription');
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//        $I->sendGet("/v1/subscription/$subscription->id/payment-status");
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//        $I->seeResponseContainsJson([
//            'response' => [
//                'status' => $example['expectedPaymentStatus'],
//            ],
//        ]);
//    }
//
//    public function testSummary(ApiTester $I): void
//    {
//        ClockMock::withClockMock(strtotime('2000-01-01 00:00:00'));
//
//        $I->loadFixtures(new class extends SubscriptionPaymentFixture {
//            public function load(ObjectManager $manager)
//            {
//                $this->entityManager = $manager;
//
//                $userRepository = $manager->getRepository(User::class);
//
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
//                $mike = $userRepository->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);
//                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
//
//                $mainSubscription = new Subscription(
//                    'Main subscription',
//                    500,
//                    'stripe-id',
//                    'stripe-price-id',
//                    $main
//                );
//                $manager->persist($mainSubscription);
//
//                $aliceSubscription = new Subscription(
//                    'Alice subscription',
//                    500,
//                    'stripe-id',
//                    'stripe-price-id',
//                    $main
//                );
//                $manager->persist($aliceSubscription);
//
//                $this->createPayments($mike, $aliceSubscription, new DateTimeImmutable('2000-01-01 00:00:00'), 1);
//
//                $this->createPayments($alice, $mainSubscription, new DateTimeImmutable('2000-01-01 00:00:00'), 12);
//                $this->createPayments($mike, $mainSubscription, new DateTimeImmutable('2000-01-01 00:00:00'), 25);
//
//                $this->createPayments(
//                    $bob,
//                    $mainSubscription,
//                    new DateTimeImmutable('2000-01-01 00:00:00'),
//                    1,
//                    PaidSubscription::STATUS_CANCELED
//                );
//
//                $manager->flush();
//            }
//        });
//
//        $subscription = $this->findSubscription($I, 'Main subscription');
//
//        $I->amBearerAuthenticated(self::ALICE_ACCESS_TOKEN);
//
//        $I->sendGet("/v1/subscription/$subscription->id/summary");
//        $I->seeResponseCodeIs(HttpCode::OK);
//
//        $I->seeResponseContainsJson([
//            'response' => [
//                'totalSalesCount' => 38,
//                'totalSalesAmount' => 19000,
//                'activeSubscriptions' => 2
//            ],
//        ]);
//    }
//
//    public function testChart(ApiTester $I): void
//    {
//        $I->loadFixtures(new class extends SubscriptionPaymentFixture {
//            public function load(ObjectManager $manager)
//            {
//                $this->entityManager = $manager;
//
//                $userRepository = $manager->getRepository(User::class);
//
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
//                $mike = $userRepository->findOneBy(['email' => BaseCest::MIKE_USER_EMAIL]);
//                $bob = $userRepository->findOneBy(['email' => BaseCest::BOB_USER_EMAIL]);
//
//                $mainSubscription = new Subscription(
//                    'Main subscription',
//                    500,
//                    'stripe-id',
//                    'stripe-price-id',
//                    $main
//                );
//                $manager->persist($mainSubscription);
//
//                $aliceSubscription = new Subscription(
//                    'Alice subscription',
//                    500,
//                    'stripe-id',
//                    'stripe-price-id',
//                    $main
//                );
//                $manager->persist($aliceSubscription);
//
//                $this->createPayments($mike, $aliceSubscription, new DateTimeImmutable('2000-01-01 00:00:00'), 1);
//
//                $this->createPayments($alice, $mainSubscription, new DateTimeImmutable('2000-01-01 00:00:00'), 12);
//                $this->createPayments($bob, $mainSubscription, new DateTimeImmutable('2000-01-02 00:00:00'), 12);
//                $this->createPayments($mike, $mainSubscription, new DateTimeImmutable('2000-02-15 00:00:00'), 12);
//
//                $manager->flush();
//            }
//        });
//
//        $subscription = $this->findSubscription($I, 'Main subscription');
//
//        $I->amBearerAuthenticated(self::MAIN_ACCESS_TOKEN);
//
//        $I->sendGet("/v1/subscription/$subscription->id/chart");
//        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
//
//        $I->sendGet("/v1/subscription/$subscription->id/chart", [
//            'dateStart' => 'tttttt',
//            'dateEnd' => 'tttttt',
//            'timeZone' => '-12',
//        ]);
//        $I->seeResponseCodeIs(HttpCode::UNPROCESSABLE_ENTITY);
//
//        $I->sendGet("/v1/subscription/$subscription->id/chart", [
//            'dateStart' => strtotime('1999-01-01 00:00:00'),
//            'dateEnd' => strtotime('2000-01-01 00:00:00'),
//            'timeZone' => '-12',
//        ]);
//        $this->assertResponse($I, [
//            'dateStart' => 0,
//            'dateEnd' => 0,
//            'values' => [],
//        ]);
//
//        $I->sendGet("/v1/subscription/$subscription->id/chart", [
//            'dateStart' => strtotime('1999-01-01 00:00:00'),
//            'dateEnd' => strtotime('2000-01-30 00:00:00'),
//            'timeZone' => '-12',
//        ]);
//        $this->assertResponse($I, [
//            'dateStart' => strtotime('2000-01-01 00:00:00'),
//            'dateEnd' => strtotime('2000-01-02 00:00:00'),
//            'values' => [
//                [
//                    'x' => strtotime('2000-01-01 00:00:00'),
//                    'y' => 1,
//                ],
//                [
//                    'x' => strtotime('2000-01-02 00:00:00'),
//                    'y' => 1,
//                ],
//            ],
//        ]);
//
//        $I->sendGet("/v1/subscription/$subscription->id/chart", [
//            'dateStart' => strtotime('2000-01-01 00:00:00'),
//            'dateEnd' => strtotime('2000-01-30 00:00:00'),
//            'timeZone' => '-12',
//        ]);
//        $this->assertResponse($I, [
//            'dateStart' => strtotime('2000-01-01 00:00:00'),
//            'dateEnd' => strtotime('2000-01-02 00:00:00'),
//            'values' => [
//                [
//                    'x' => strtotime('2000-01-01 00:00:00'),
//                    'y' => 1,
//                ],
//                [
//                    'x' => strtotime('2000-01-02 00:00:00'),
//                    'y' => 1,
//                ],
//            ],
//        ]);
//
//        $I->sendGet("/v1/subscription/$subscription->id/chart", [
//            'dateStart' => strtotime('2000-01-01 00:00:00'),
//            'dateEnd' => strtotime('2000-01-30 00:00:00'),
//            'timeZone' => '+12',
//        ]);
//        $this->assertResponse($I, [
//            'dateStart' => strtotime('1999-12-31 00:00:00'),
//            'dateEnd' => strtotime('2000-01-01 00:00:00'),
//            'values' => [
//                [
//                    'x' => strtotime('1999-12-31 00:00:00'),
//                    'y' => 1,
//                ],
//                [
//                    'x' => strtotime('2000-01-01 00:00:00'),
//                    'y' => 1,
//                ],
//            ],
//        ]);
//
//        $I->sendGet("/v1/subscription/$subscription->id/chart", [
//            'overview' => 'month',
//            'dateStart' => strtotime('1999-01-01 00:00:00'),
//            'dateEnd' => strtotime('2005-01-01 00:00:00'),
//            'timeZone' => '-12',
//        ]);
//        $this->assertResponse($I, [
//            'dateStart' => strtotime('2000-01-01 00:00:00'),
//            'dateEnd' => strtotime('2001-01-01 00:00:00'),
//            'values' => array_merge(
//                [
//                    [
//                        'x' => strtotime('2000-01-01 00:00:00'),
//                        'y' => 2,
//                    ]
//                ],
//                $this->generateChartValues(
//                    strtotime('2000-02-01 00:00:00'),
//                    3,
//                    11,
//                    new DateInterval("P1M")
//                ),
//                [
//                    [
//                        'x' => strtotime('2001-01-01 00:00:00'),
//                        'y' => 1,
//                    ]
//                ],
//            ),
//        ]);
//
//        $I->sendGet("/v1/subscription/$subscription->id/chart", [
//            'overview' => 'month',
//            'type' => 'sum',
//            'dateStart' => strtotime('2000-02-01 00:00:00'),
//            'dateEnd' => strtotime('2005-01-01 00:00:00'),
//            'timeZone' => '-12',
//        ]);
//        $this->assertResponse($I, [
//            'dateStart' => strtotime('2000-02-01 00:00:00'),
//            'dateEnd' => strtotime('2001-01-01 00:00:00'),
//            'values' => array_merge(
//                $this->generateChartValues(
//                    strtotime('2000-02-01 00:00:00'),
//                    1500,
//                    11,
//                    new DateInterval("P1M")
//                ),
//                [
//                    [
//                        'x' => strtotime('2001-01-01 00:00:00'),
//                        'y' => 500,
//                    ]
//                ],
//            ),
//        ]);
//    }
//
//    protected function paymentStatusDataProvider(): Generator
//    {
//        yield [
//            'subscriptionStatus' => PaidSubscription::STATUS_INCOMPLETE,
//            'expectedPaymentStatus' => SubscriptionService::STATUS_UNPAID,
//        ];
//        yield [
//            'subscriptionStatus' => PaidSubscription::STATUS_INCOMPLETE,
//            'waitingUpTo' => 500,
//            'expectedPaymentStatus' => SubscriptionService::STATUS_PENDING,
//        ];
//        yield [
//            'subscriptionStatus' => PaidSubscription::STATUS_INCOMPLETE,
//            'waitingUpTo' => 50,
//            'expectedPaymentStatus' => SubscriptionService::STATUS_UNPAID,
//        ];
//
//        yield [
//            'subscriptionStatus' => PaidSubscription::STATUS_ACTIVE,
//            'expectedPaymentStatus' => SubscriptionService::STATUS_CONFIRMED,
//        ];
//        yield [
//            'subscriptionStatus' => PaidSubscription::STATUS_TRIALING,
//            'expectedPaymentStatus' => SubscriptionService::STATUS_CONFIRMED,
//        ];
//        yield [
//            'subscriptionStatus' => PaidSubscription::STATUS_PAST_DUE,
//            'expectedPaymentStatus' => SubscriptionService::STATUS_CONFIRMED,
//        ];
//
//        yield [
//            'subscriptionStatus' => PaidSubscription::STATUS_CANCELED,
//            'expectedPaymentStatus' => SubscriptionService::STATUS_UNPAID,
//        ];
//        yield [
//            'subscriptionStatus' => PaidSubscription::STATUS_INCOMPLETE_EXPIRED,
//            'expectedPaymentStatus' => SubscriptionService::STATUS_UNPAID,
//        ];
//        yield [
//            'subscriptionStatus' => PaidSubscription::STATUS_UNPAID,
//            'expectedPaymentStatus' => SubscriptionService::STATUS_UNPAID,
//        ];
//    }
//
//    private function assertItems(ApiTester $I, array $items): ?string
//    {
//        $I->seeResponseContainsJson([
//            'response' => [
//                'items' => $items,
//            ],
//        ]);
//
//        return $I->grabDataFromResponseByJsonPath('$.response.lastValue')[0];
//    }
//
//    private function assertItemsHasFields(ApiTester $I, array $fields): void
//    {
//        $items = $I->grabDataFromResponseByJsonPath('$.response.items')[0];
//
//        foreach ($items as $itemKey => $item) {
//            foreach ($fields as $field) {
//                $I->assertArrayHasKey($field, $item, "Response item {$itemKey} must has field {$field}");
//            }
//        }
//    }
//
//    private function findSubscription(ApiTester $I, string $name): ?Subscription
//    {
//        /** @noinspection PhpIncompatibleReturnTypeInspection */
//        return $I->grabEntityFromRepository(Subscription::class, ['name' => $name]);
//    }
//
//    private function findMainUser(ApiTester $I): ?User
//    {
//        /** @noinspection PhpIncompatibleReturnTypeInspection */
//        return $I->grabEntityFromRepository(User::class, ['email' => self::MAIN_USER_EMAIL]);
//    }
//
//    private function assertPriceWillBeCreated(array $priceData, $return)
//    {
//        $this->stripePriceService
//            ->shouldReceive('create')
//            ->once()
//            ->with($priceData)
//            ->andReturns($return);
//    }
//
//    private function assertPriceWillBeUpdated(array $priceData): void
//    {
//        $this->stripePriceService
//            ->shouldReceive('update')
//            ->with(self::STRIPE_PRICE_ID, $priceData)
//            ->once();
//    }
//
//    private function assertProductWillBeCreated(array $productData, $return): void
//    {
//        $this->stripeProductService
//            ->shouldReceive('create')
//            ->with($productData)
//            ->andReturns($return)
//            ->once();
//    }
//
//    private function assertProductWillBeUpdated(array $productData): void
//    {
//        $this->stripeProductService
//            ->shouldReceive('update')
//            ->with(self::STRIPE_PRODUCT_ID, $productData)
//            ->once();
//    }
//
//    /**
//     * @return MockInterface|StripeClient
//     */
//    private function mockStripeClient(ApiTester $I): MockInterface
//    {
//        $stripeClient = \Mockery::mock(StripeClient::class);
//
//        $I->mockService(StripeClient::class, $stripeClient);
//
//        return $stripeClient;
//    }
//
//    private function assertCustomerWillBeCreated(User $user): void
//    {
//        $this->stripeCustomerService->shouldReceive('create')
//            ->with(
//                [
//                    'email' => $user->email,
//                    'name' => $user->name . ' ' . $user->surname,
//                    'phone' => PhoneNumberUtil::getInstance()->format($user->phone, PhoneNumberFormat::E164),
//                    'metadata' => [
//                        'user_id' => $user->id,
//                        'username' => $user->username,
//                        'backend' => 'test',
//                    ],
//                ],
//                [
//                    'idempotency_key' => "test:{$user->id}",
//                ]
//            )
//            ->andReturns(new Customer(self::STRIPE_CUSTOMER_ID))
//            ->once();
//    }
//
//    private function assertCustomerWillBeDeleted(): void
//    {
//        $this->stripeCustomerService->shouldReceive('delete')
//            ->with(self::STRIPE_CUSTOMER_ID)
//            ->once();
//    }
//
//    private function assertSubscriptionWillBeCreated(User $user, Subscription $subscription): void
//    {
//        $this->stripeSubscriptionService->shouldReceive('create')
//            ->with(
//                [
//                    'customer' => self::STRIPE_CUSTOMER_ID,
//                    'payment_behavior' => 'default_incomplete',
//                    'items' => [
//                        [
//                            'price' => self::STRIPE_PRICE_ID,
//                        ],
//                    ],
//                    'metadata' => [
//                        'backend' => 'test',
//                        'username' => $user->username,
//                        'user_id' => $user->id,
//                    ],
//                    'expand' => ['latest_invoice.payment_intent'],
//                ],
//                [
//                    'idempotency_key' => "test:{$subscription->id->toString()}:{$user->id}"
//                ]
//            )
//            ->andReturns($this->createStripeSubscription())
//            ->once();
//    }
//
//    private function createStripeSubscription(): StripeSubscription
//    {
//        $paymentIntent = new SetupIntent(self::STRIPE_PAYMENT_INTENT_ID);
//        $paymentIntent->client_secret = self::STRIPE_CLIENT_SECRET;
//
//        $latestInvoice = new Invoice(self::STRIPE_INVOICE_ID);
//        $latestInvoice->payment_intent = $paymentIntent;
//
//        $subscription = new StripeSubscription(self::STRIPE_SUBSCRIPTION_ID);
//        $subscription->latest_invoice = $latestInvoice;
//        $subscription->customer = self::STRIPE_CUSTOMER_ID;
//
//        return $subscription;
//    }
//
//    public function transactionalTestDataProvider(): \Generator
//    {
//        yield [
//            'throwException' => false,
//        ];
//        yield [
//            'throwException' => true,
//        ];
//    }
//
//    /**
//     * @return PriceService|MockInterface
//     */
//    private function mockStripePriceService(StripeClient $stripeClientMock): PriceService
//    {
//        return $stripeClientMock->prices = \Mockery::mock(PriceService::class);
//    }
//
//    /**
//     * @return ProductService|MockInterface
//     */
//    private function mockStripeProductService(StripeClient $stripeClientMock): ProductService
//    {
//        return $stripeClientMock->products = \Mockery::mock(ProductService::class);
//    }
//
//    /**
//     * @return CustomerService|MockInterface
//     */
//    private function mockStripeCustomerService(StripeClient $stripeClientMock): CustomerService
//    {
//        return $stripeClientMock->customers = \Mockery::mock(CustomerService::class);
//    }
//
//    /**
//     * @return StripeClientSubscriptionService|MockInterface
//     */
//    private function mockStripeSubscriptionService(StripeClient $stripeClientMock): StripeClientSubscriptionService
//    {
//        return $stripeClientMock->subscriptions = \Mockery::mock(StripeClientSubscriptionService::class);
//    }
//
//    private function cleanupSubscriptions(ApiTester $I): void
//    {
//        /** @var EntityManagerInterface $em */
//        $em = $I->grabService(EntityManagerInterface::class);
//        $em->getConnection()->executeQuery('DELETE FROM paid_subscription');
//        $em->getConnection()->executeQuery('DELETE FROM subscription');
//    }
//
//    private function throwExceptionBeforeCommit(ApiTester $I): void
//    {
//        $I->grabService(TransactionManager::class)
//            ->throwExceptionBeforeCommit(new \RuntimeException());
//    }
//
//    private function generateChartValues(int $baseTimeStamp, int $y, int $count, DateInterval $interval): array
//    {
//        $payments = [];
//
//        $date = (new DateTime())->setTimestamp($baseTimeStamp);
//        for ($i = 0; $i < $count; $i++) {
//            $payments[] = [
//                'x' => $date->getTimestamp(),
//                'y' => $y,
//            ];
//
//            $date->add($interval);
//        }
//
//        return $payments;
//    }
//
//    private function assertResponse(ApiTester $I, array $expectedResponse): void
//    {
//        $I->seeResponseCodeIs(HttpCode::OK);
//
//        $response = $I->grabDataFromResponseByJsonPath('response')[0];
//        $I->assertEquals($expectedResponse, $response);
//    }
}
