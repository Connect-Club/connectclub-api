<?php

namespace App\Tests\Subscription;

use App\Entity\Subscription\PaidSubscription;
use App\Entity\Subscription\Payment;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Tests\ApiTester;
use App\Tests\BaseCest;
use Codeception\Example;
use Codeception\Util\HttpCode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Stripe\StripeClient;

class SubscriptionWebhookCest extends BaseCest
{
    public const STRIPE_PRICE_ID = 'price_1JIpf8CbRTc2e9DZeYmMz2bb';
    public const STRIPE_PRODUCT_ID = 'prod_JrcDdsXXtSiH2N';
    public const STRIPE_EVENT_ID = 'evt_1JLRJTCbRTc2e9DZ4n69oALN';
    public const STRIPE_SUBSCRIPTION_ID = 'sub_JyILw57oJZTiRT';
    public const STRIPE_CUSTOMER_ID = 'cus_JjJ2g4fAJBTLqs';
    public const STRIPE_PAYMENT_METHOD_ID = 'card_1IyHElCbRTc2e9DZKua1FtbW';
    public const STRIPE_INVOICE_ID = 'in_1JhvizCbRTc2e9DZRYVzMKA7';

//    public function testInvalidSignature(ApiTester $I): void
//    {
//        $I->sendPost('/v1/subscription/webhook', json_encode($this->createSubscriptionEventData()));
//
//        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
//        $I->seeResponseContainsJson([
//            'errors' => ['v1.stripe.invalid_signature']
//        ]);
//
//        $I->haveHttpHeader(
//            'stripe-signature',
//            't=' . time() . ',v1=6ffbb59b2300aae63f272406069a9788598b792a944a07aba816edb039989a39'
//        );
//        $I->sendPost('/v1/subscription/webhook', json_encode($this->createSubscriptionEventData()));
//
//        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
//        $I->seeResponseContainsJson([
//            'errors' => ['v1.stripe.invalid_signature']
//        ]);
//    }
//
//    public function testUnknownEvent(ApiTester $I): void
//    {
//        $requestData = json_encode($this->createSubscriptionEventData());
//        $I->haveHttpHeader('stripe-signature', $this->generateSignature($requestData));
//        $I->sendPost('/v1/subscription/webhook', $requestData);
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//    }
//
//    /**
//     * @dataProvider statusesDataProvider
//     */
//    public function testSubscriptionStatusUpdate(ApiTester $I, Example $example): void
//    {
//        $I->loadFixtures(new class extends Fixture{
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
//                $alice->stripeCustomerId = SubscriptionWebhookCest::STRIPE_CUSTOMER_ID;
//
//                $subscription = new Subscription(
//                    'To update',
//                    500,
//                    SubscriptionWebhookCest::STRIPE_PRODUCT_ID,
//                    SubscriptionWebhookCest::STRIPE_PRICE_ID,
//                    $main
//                );
//                $manager->persist($subscription);
//                $manager->persist(new PaidSubscription($alice, $subscription, PaidSubscription::STATUS_INCOMPLETE));
//
//                $subscription = new Subscription(
//                    'Not to update',
//                    500,
//                    'not_to_update',
//                    'not_to_update',
//                    $main
//                );
//                $manager->persist($subscription);
//                $manager->persist(new PaidSubscription($main, $subscription, PaidSubscription::STATUS_INCOMPLETE));
//
//                $manager->flush();
//            }
//        });
//
//        $requestData = json_encode($this->createSubscriptionEventData(
//            'customer.subscription.updated',
//            $example['statusCode']
//        ));
//        $I->haveHttpHeader('stripe-signature', $this->generateSignature($requestData));
//        $I->sendPost('/v1/subscription/webhook', $requestData);
//
//        /** @var User $alice */
//        $alice = $I->grabEntityFromRepository(User::class, ['email' => self::ALICE_USER_EMAIL]);
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//        $this->assertSubscriptionStatus($I, 'To update', $example['status'], $alice);
//        $this->assertSubscriptionStatus($I, 'Not to update', PaidSubscription::STATUS_INCOMPLETE);
//    }
//
//    public function statusesDataProvider(): \Generator
//    {
//        yield [
//            'status' => PaidSubscription::STATUS_INCOMPLETE,
//            'statusCode' => 'incomplete',
//            'event' => 'customer.subscription.created',
//        ];
//        yield [
//            'status' => PaidSubscription::STATUS_INCOMPLETE,
//            'statusCode' => 'incomplete',
//            'event' => 'customer.subscription.updated',
//        ];
//
//        yield [
//            'status' => PaidSubscription::STATUS_INCOMPLETE_EXPIRED,
//            'statusCode' => 'incomplete_expired',
//            'event' => 'customer.subscription.updated',
//        ];
//
//        yield [
//            'status' => PaidSubscription::STATUS_TRIALING,
//            'statusCode' => 'trialing',
//            'event' => 'customer.subscription.created',
//        ];
//        yield [
//            'status' => PaidSubscription::STATUS_TRIALING,
//            'statusCode' => 'trialing',
//            'event' => 'customer.subscription.updated',
//        ];
//
//        yield [
//            'status' => PaidSubscription::STATUS_ACTIVE,
//            'statusCode' => 'active',
//            'event' => 'customer.subscription.updated',
//        ];
//        yield [
//            'status' => PaidSubscription::STATUS_PAST_DUE,
//            'statusCode' => 'past_due',
//            'event' => 'customer.subscription.updated',
//        ];
//        yield [
//            'status' => PaidSubscription::STATUS_UNPAID,
//            'statusCode' => 'unpaid',
//            'event' => 'customer.subscription.updated',
//        ];
//
//        yield [
//            'status' => PaidSubscription::STATUS_CANCELED,
//            'statusCode' => 'canceled',
//            'event' => 'customer.subscription.updated',
//        ];
//        yield [
//            'status' => PaidSubscription::STATUS_CANCELED,
//            'statusCode' => 'canceled',
//            'event' => 'customer.subscription.deleted',
//        ];
//    }
//
//    public function testSubscriptionPaidFromAnotherBackend(ApiTester $I)
//    {
//        $I->loadFixtures(new class extends Fixture{
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
//                $alice->stripeCustomerId = SubscriptionWebhookCest::STRIPE_CUSTOMER_ID;
//
//                $subscription = new Subscription(
//                    'Collision',
//                    500,
//                    SubscriptionWebhookCest::STRIPE_PRODUCT_ID,
//                    SubscriptionWebhookCest::STRIPE_PRICE_ID,
//                    $main
//                );
//                $manager->persist($subscription);
//
//                $manager->flush();
//            }
//        });
//
//        $requestData = json_encode($this->createSubscriptionEventData(
//            'customer.subscription.updated',
//            'active',
//            'prod'
//        ));
//        $I->haveHttpHeader('stripe-signature', $this->generateSignature($requestData));
//        $I->sendPost('/v1/subscription/webhook', $requestData);
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//        $this->assertPaidSubscriptionNotExists($I, 'Collision');
//
//        $requestData = $this->createSubscriptionEventData('customer.subscription.updated', 'active', 'prod');
//        $requestData['data']['object']['id'] = 'test';
//        $requestData = json_encode($requestData);
//        $I->haveHttpHeader('stripe-signature', $this->generateSignature($requestData));
//        $I->sendPost('/v1/subscription/webhook', $requestData);
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//
//        $requestData = $this->createSubscriptionEventData('customer.subscription.updated', 'active', 'prod');
//        unset($requestData['data']['object']['metadata']);
//        $requestData = json_encode($requestData);
//        $I->haveHttpHeader('stripe-signature', $this->generateSignature($requestData));
//        $I->sendPost('/v1/subscription/webhook', $requestData);
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//        $this->assertPaidSubscriptionNotExists($I, 'Collision');
//    }
//
//    public function testSubscriptionUnpaidFromAnotherBackend(ApiTester $I)
//    {
//        $I->loadFixtures(new class extends Fixture{
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
//                $alice->stripeCustomerId = SubscriptionWebhookCest::STRIPE_CUSTOMER_ID;
//
//                $subscription = new Subscription(
//                    'Collision',
//                    500,
//                    SubscriptionWebhookCest::STRIPE_PRODUCT_ID,
//                    SubscriptionWebhookCest::STRIPE_PRICE_ID,
//                    $main
//                );
//                $manager->persist($subscription);
//
//                $manager->persist(new PaidSubscription($alice, $subscription, PaidSubscription::STATUS_ACTIVE));
//
//                $manager->flush();
//            }
//        });
//
//        $requestData = json_encode($this->createSubscriptionEventData(
//            'customer.subscription.updated',
//            'unpaid',
//            'prod'
//        ));
//        $I->haveHttpHeader('stripe-signature', $this->generateSignature($requestData));
//        $I->sendPost('/v1/subscription/webhook', $requestData);
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//        $this->assertSubscriptionStatus($I, 'Collision', PaidSubscription::STATUS_ACTIVE);
//
//        $requestData = $this->createSubscriptionEventData('customer.subscription.updated', 'unpaid', 'prod');
//        $requestData['data']['object']['id'] = 'test';
//        $requestData = json_encode($requestData);
//        $I->haveHttpHeader('stripe-signature', $this->generateSignature($requestData));
//        $I->sendPost('/v1/subscription/webhook', $requestData);
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//
//        $requestData = $this->createSubscriptionEventData('customer.subscription.updated', 'unpaid', 'prod');
//        unset($requestData['data']['object']['metadata']);
//        $requestData = json_encode($requestData);
//        $I->haveHttpHeader('stripe-signature', $this->generateSignature($requestData));
//        $I->sendPost('/v1/subscription/webhook', $requestData);
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//        $this->assertSubscriptionStatus($I, 'Collision', PaidSubscription::STATUS_ACTIVE);
//    }
//
//    public function testInvoicePaid(ApiTester $I): void
//    {
//        $I->loadFixtures(new class extends Fixture {
//            public function load(ObjectManager $manager): void
//            {
//                $userRepository = $manager->getRepository(User::class);
//                $main = $userRepository->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//                $alice = $userRepository->findOneBy(['email' => BaseCest::ALICE_USER_EMAIL]);
//                $alice->stripeCustomerId = SubscriptionWebhookCest::STRIPE_CUSTOMER_ID;
//
//                $subscription = new Subscription(
//                    'Paid',
//                    500,
//                    SubscriptionWebhookCest::STRIPE_PRODUCT_ID,
//                    SubscriptionWebhookCest::STRIPE_PRICE_ID,
//                    $main
//                );
//                $manager->persist($subscription);
//                $manager->persist(new PaidSubscription($alice, $subscription, PaidSubscription::STATUS_ACTIVE));
//
//                $subscription = new Subscription(
//                    'Not paid',
//                    500,
//                    'not_paid',
//                    'not_paid',
//                    $main
//                );
//                $manager->persist($subscription);
//                $manager->persist(new PaidSubscription($main, $subscription, PaidSubscription::STATUS_INCOMPLETE));
//
//                $manager->flush();
//            }
//        });
//
//        $requestData = json_encode($this->createInvoiceEventData('invoice.paid', 'paid'));
//        $I->haveHttpHeader('stripe-signature', $this->generateSignature($requestData));
//        $I->sendPost('/v1/subscription/webhook', $requestData);
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//        $this->assertPaymentExists($I, 'Paid', BaseCest::ALICE_USER_EMAIL);
//        $this->assertPaymentNotExists($I, 'Not paid', BaseCest::ALICE_USER_EMAIL);
//
//        $requestData = $this->createInvoiceEventData('invoice.paid', 'paid');
//        unset($requestData['data']['object']['lines']['data'][0]['price']['metadata']['backend']);
//        $requestData = json_encode($requestData);
//        $I->haveHttpHeader('stripe-signature', $this->generateSignature($requestData));
//        $I->sendPost('/v1/subscription/webhook', $requestData);
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//
//        $requestData = $this->createInvoiceEventData('invoice.paid', 'paid');
//        unset($requestData['data']['object']['lines']['data'][0]['price']['metadata']);
//        $requestData = json_encode($requestData);
//        $I->haveHttpHeader('stripe-signature', $this->generateSignature($requestData));
//        $I->sendPost('/v1/subscription/webhook', $requestData);
//
//        $I->seeResponseCodeIs(HttpCode::OK);
//    }
//
//    private function assertPaymentExists(ApiTester $I, string $subscriptionName, string $subscriberEmail): void
//    {
//        $I->seeInRepository(Payment::class, [
//            'paidSubscription' => [
//                'subscription' => [
//                    'name' => $subscriptionName,
//                ],
//                'subscriber' => [
//                    'email' => $subscriberEmail,
//                ]
//            ],
//        ]);
//    }
//
//    private function assertPaymentNotExists(ApiTester $I, string $subscriptionName, string $subscriberEmail): void
//    {
//        $I->dontSeeInRepository(Payment::class, [
//            'paidSubscription' => [
//                'subscription' => [
//                    'name' => $subscriptionName,
//                ],
//                'subscriber' => [
//                    'email' => $subscriberEmail,
//                ]
//            ],
//        ]);
//    }
//
//    private function assertSubscriptionStatus(ApiTester $I, string $name, string $status, ?User $user = null): void
//    {
//        $filter = [
//            'subscription' => $I->grabEntityFromRepository(Subscription::class, ['name' => $name]),
//            'status' => $status,
//        ];
//
//        if ($user) {
//            $filter['subscriber'] = $user;
//        }
//
//        $I->seeInRepository(PaidSubscription::class, $filter);
//    }
//
//    private function assertPaidSubscriptionNotExists(ApiTester $I, string $name, ?User $user = null): void
//    {
//        $filter = [
//            'subscription' => $I->grabEntityFromRepository(Subscription::class, ['name' => $name]),
//        ];
//
//        if ($user) {
//            $filter['subscriber'] = $user;
//        }
//
//        $I->dontSeeInRepository(PaidSubscription::class, $filter);
//    }
//
//    private function generateSignature(string $payload): string
//    {
//        $time = time();
//        return "t={$time},v1=" . hash_hmac('sha256', "{$time}.{$payload}", 'whsec_test_example_key');
//    }
//
//    private function createSubscriptionEventData(
//        string $type = '',
//        string $subscriptionStatus = '',
//        string $backendName = 'test'
//    ): array {
//        return [
//            'id' => self::STRIPE_EVENT_ID,
//            'object' => 'event',
//            'api_version' => '2020-03-02',
//            'created' => time(),
//            'type' => $type,
//            'data' => [
//                'object' => [
//                    'id' => self::STRIPE_SUBSCRIPTION_ID,
//                    'object' => 'subscription',
//                    'status' => $subscriptionStatus,
//                    'customer' => self::STRIPE_CUSTOMER_ID,
//                    'default_payment_method' => self::STRIPE_PAYMENT_METHOD_ID,
//                    'items' => [
//                        'object' => 'list',
//                        'data' => [
//                            [
//                                'id' => 'si_Jz3qBJ3UcV0mqg',
//                                'object' => 'subscription_item',
//                                'price' => [
//                                    'id' => self::STRIPE_PRICE_ID,
//                                    'object' => 'price',
//                                    'product' => self::STRIPE_PRODUCT_ID,
//                                ],
//                            ]
//                        ],
//                    ],
//                    'metadata' => [
//                        'backend' => $backendName,
//                    ],
//                ],
//            ],
//        ];
//    }
//
//    private function createInvoiceEventData(
//        string $type = '',
//        string $invoiceStatus = '',
//        string $backendName = 'test'
//    ): array {
//        return [
//            'id' => self::STRIPE_EVENT_ID,
//            'object' => 'event',
//            'api_version' => '2020-03-02',
//            'created' => time(),
//            'type' => $type,
//            'data' => [
//                'object' => [
//                    'id' => self::STRIPE_INVOICE_ID,
//                    'object' => 'invoice',
//                    'status' => $invoiceStatus,
//                    'customer' => self::STRIPE_CUSTOMER_ID,
//                    'amount_paid' => 1000,
//                    'lines' => [
//                        'object' => 'list',
//                        'data' => [
//                            [
//                                'id' => 'il_1JhvizCbRTc2e9DZC2JBJZgY',
//                                'object' => 'line_item',
//                                'price' => [
//                                    'id' => self::STRIPE_PRICE_ID,
//                                    'object' => 'price',
//                                    'product' => self::STRIPE_PRODUCT_ID,
//                                    'metadata' => [
//                                        'backend' => $backendName,
//                                    ],
//                                ],
//                            ]
//                        ],
//                    ],
//                    'status_transitions' => [
//                        'paid_at' => time() - 100,
//                    ],
//                ],
//            ],
//        ];
//    }
}
