<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\Controller\ErrorCode;
use App\DTO\V1\Event\OnlineEventUserInfo;
use App\DTO\V1\PaginatedResponse;
use App\DTO\V1\Subscription\ChartRequest;
use App\DTO\V1\Subscription\ChartResponse;
use App\DTO\V1\Subscription\CreateRequest;
use App\DTO\V1\Subscription\BuyResponse;
use App\DTO\V1\Subscription\CreateResponse;
use App\DTO\V1\Subscription\Event;
use App\DTO\V1\Subscription\Response as SubscriptionResponse;
use App\DTO\V1\Subscription\Summary;
use App\DTO\V1\Subscription\UpdateRequest;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleParticipant;
use App\Entity\Subscription\Subscription;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Repository\Event\EventScheduleRepository;
use App\Repository\Subscription\SubscriptionRepository;
use App\Serializer\Normalizer\ObjectNormalizer;
use App\Service\EventService;
use App\Service\SubscriptionService;
use App\Service\SubscriptionWebhookService;
use App\Swagger\ListResponse;
use App\Swagger\ViewResponse;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use App\DTO\V1\Subscription\PaymentStatusResponse;
use App\DTO\V1\Event\OnlineEventItem;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @Route("/subscription")
 */
class SubscriptionController extends BaseController
{
    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionService $subscriptionService;
    private string $stripePublicKey;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        SubscriptionService $subscriptionService,
        string $stripePublicKey
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->subscriptionService = $subscriptionService;
        $this->stripePublicKey = $stripePublicKey;
    }

    /**
     * @SWG\Get(
     *     description="Get current user subscriptions",
     *     tags={"Subscription"},
     *     @SWG\Response(response="200", description="Success")
     * )
     * @ListResponse(
     *     entityClass=SubscriptionResponse::class,
     *     pagination=true,
     *     paginationByLastValue=true,
     *     enableOrderBy=false,
     * )
     * @Route("/my", methods={"GET"})
     */
    public function my(Request $request): JsonResponse
    {
        $user = $this->getUser();
        [$subscriptions, $lastValue] = $this->subscriptionRepository->findMy(
            $user,
            $request->get('lastValue', 0),
            $request->get('limit', 20)
        );

        $response = array_map(
            fn(Subscription $subscription) => new SubscriptionResponse($subscription),
            $subscriptions
        );

        return $this->handleResponse(new PaginatedResponse($response, $lastValue));
    }

    /**
     * @SWG\Get(
     *     description="Get current user subscription",
     *     tags={"Subscription"},
     *     @SWG\Response(response="200", description="Success"),
     *     @SWG\Response(response="404", description="Not found")
     * )
     * @ViewResponse(entityClass=SubscriptionResponse::class)
     * @Route("/{id}", methods={"GET"})
     */
    public function subscription(string $id): JsonResponse
    {
        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        return $this->handleResponse(new SubscriptionResponse($subscription));
    }

    /**
     * @SWG\Post(
     *     description="Create a subscription",
     *     tags={"Subscription"},
     *     @SWG\Response(response="200", description="Success"),
     *     @SWG\Response(response="422", description="Validation errors"),
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=CreateRequest::class)))
     * )
     * @ViewResponse(entityClass=CreateResponse::class)
     * @Route("", methods={"POST"})
     */
    public function create(Request $request): JsonResponse
    {
        /** @var CreateRequest $createRequest */
        $createRequest = $this->getEntityFromRequestTo($request, CreateRequest::class);

        $this->unprocessableUnlessValid($createRequest);

        $currentUser = $this->getUser();

        if ($createRequest->isActive) {
            if ($this->subscriptionRepository->findActive($currentUser)) {
                return $this->createErrorResponse(ErrorCode::V1_SUBSCRIPTION_ACTIVE_LIMIT, Response::HTTP_BAD_REQUEST);
            }
        }

        $subscription = $this->subscriptionService->createSubscription($createRequest, $currentUser);

        return $this->handleResponse(new CreateResponse($subscription));
    }

    /**
     * @SWG\Patch(
     *     description="Update a subscription",
     *     tags={"Subscription"},
     *     @SWG\Response(response="200", description="Success"),
     *     @SWG\Response(response="422", description="Validation errors"),
     *     @SWG\Response(response="404", description="Subscription not found or not belongs to current user"),
     *     @SWG\Response(response="400", description="The user already has one active subscription"),
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=UpdateRequest::class)))
     * )
     * @Route("/{id}", methods={"PATCH"})
     */
    public function update(string $id, Request $request): JsonResponse
    {
        $subscription = $this->getCurrentUserSubscription($id);

        if (!$subscription) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        /** @var UpdateRequest $updateRequest */
        $updateRequest = $this->getEntityFromRequestTo($request, UpdateRequest::class);

        $this->unprocessableUnlessValid($updateRequest);

        if (!$subscription->isActive && $updateRequest->isActive) {
            if ($this->subscriptionRepository->findActive($this->getUser(), $subscription->id->toString())) {
                return $this->createErrorResponse(ErrorCode::V1_SUBSCRIPTION_ACTIVE_LIMIT, Response::HTTP_BAD_REQUEST);
            }
        }

        $this->subscriptionService->updateSubscription($subscription, $updateRequest);

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Delete(
     *     description="Delete a subscription",
     *     tags={"Subscription"},
     *     @SWG\Response(response="200", description="Success"),
     *     @SWG\Response(response="404", description="Subscription not found"),
     * )
     * @Route("/{id}", methods={"DELETE"})
     */
    public function delete(string $id): JsonResponse
    {
        $subscription = $this->getCurrentUserSubscription($id);

        if (!$subscription) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $this->subscriptionService->deleteSubscription($subscription);

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Post(
     *     description="Create stripe session",
     *     tags={"Subscription"},
     *     @SWG\Response(response="200", description="Success"),
     *     @SWG\Response(response="404", description="Subscription not found"),
     * )
     * @ViewResponse(entityClass=BuyResponse::class)
     * @Route("/{id}/buy", methods={"POST"})
     */
    public function buy(string $id): JsonResponse
    {
        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $stripeSubscription = $this->subscriptionService->buySubscription($subscription, $this->getUser());

        return $this->handleResponse(new BuyResponse($stripeSubscription, $this->stripePublicKey));
    }

    /**
     * @SWG\Post(
     *     description="Confirm sending payment",
     *     tags={"Subscription"},
     *     @SWG\Response(response="200", description="Success"),
     *     @SWG\Response(response="404", description="Subscription not found")
     * )
     * @Route("/{id}/payment-sent", methods={"POST"})
     */
    public function paymentSent(string $id): JsonResponse
    {
        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $this->subscriptionService->markWaitingForPaymentConfirmation($subscription, $this->getUser());

        return $this->handleResponse([]);
    }

    /**
     * @SWG\Get(
     *     description="Returns a subscription payment status",
     *     tags={"Subscription"},
     *     @SWG\Response(response="200", description="Success"),
     *     @SWG\Response(response="404", description="Subscription not found")
     * )
     * @ViewResponse(entityClass=PaymentStatusResponse::class)
     * @Route("/{id}/payment-status", methods={"GET"})
     */
    public function paymentStatus(string $id): JsonResponse
    {
        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        return $this->handleResponse(new PaymentStatusResponse(
            $this->subscriptionService->getPaymentStatus($subscription, $this->getUser())
        ));
    }

    /**
     * @SWG\Post(
     *     description="Stripe webhook",
     *     tags={"Subscription"}
     * )
     * @SWG\Response(response="200", description="success")
     * @Route("/webhook", methods={"POST"})
     */
    public function webhook(
        Request $request,
        SubscriptionWebhookService $webhookService
    ): Response {
        $webhookService->handleEvent($request);

        return new Response();
    }

    /**
     * @SWG\Get(
     *     description="Returns subscription events",
     *     tags={"Subscription", "Event"}
     * )
     * @SWG\Response(response="200", description="success")
     * @SWG\Response(response="404", description="Subscription not found")
     * @ListResponse(
     *     entityClass=Event::class,
     *     pagination=true,
     *     paginationByLastValue=true,
     *     enableOrderBy=false
     * )
     * @Route("/{id}/events")
     */
    public function events(
        string $id,
        Request $request,
        EventScheduleRepository $eventScheduleRepository
    ): JsonResponse {
        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        /**
         * @var EventSchedule[] $eventSchedules
         * @var int $lastValue
         */
        [$eventSchedules, $lastValue] = $eventScheduleRepository->findBySubscription(
            $subscription,
            $request->query->getInt('lastValue'),
            $request->query->getInt('limit', 20)
        );

        $events = [];
        foreach ($eventSchedules as $eventSchedule) {
            $events[] = new Event($eventSchedule);
        }

        return $this->handleResponse(new PaginatedResponse($events, $lastValue));
    }

    /**
     * @SWG\Get(
     *     description="Returns summary",
     *     tags={"Subscription"},
     *     @SWG\Response(response="200", description="Success"),
     *     @SWG\Response(response="404", description="Subscription not found")
     * )
     * @ViewResponse(entityClass=Summary::class)
     * @Route("/{id}/summary")
     */
    public function summary(string $id): JsonResponse
    {
        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $summary = $this->subscriptionRepository->findSummary($subscription);

        return $this->handleResponse(new Summary(
            $summary['totalSalesCount'],
            $summary['totalSalesAmount'],
            $summary['activeSubscriptions']
        ));
    }

    /**
     * @SWG\Get(
     *     description="Returns chart data",
     *     tags={"Subscription"},
     *     @SWG\Parameter(in="query", name="dateStart", type="integer"),
     *     @SWG\Parameter(in="query", name="dateEnd", type="integer"),
     *     @SWG\Parameter(in="query", name="timeZone", type="string", description="timeZone name or code in postgres"),
     *     @SWG\Parameter(in="query", name="overview", type="string", enum={"day", "month"}, default="month"),
     *     @SWG\Parameter(in="query", name="type", type="string", enum={"quantity", "sum"}, default="quantity"),
     *     @SWG\Response(response="200", description="Success"),
     *     @SWG\Response(response="404", description="Subscription not found")
     * )
     * @ViewResponse(entityClass=ChartResponse::class)
     * @Route("/{id}/chart")
     */
    public function chart(
        string $id,
        Request $request,
        DenormalizerInterface $denormalizer
    ): JsonResponse {
        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription) {
            return $this->createErrorResponse(ErrorCode::V1_ERROR_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        /** @var ChartRequest $chartRequest */
        $chartRequest = $denormalizer->denormalize($request->query->all(), ChartRequest::class, 'array');

        $this->unprocessableUnlessValid($chartRequest);

        $chartData = $this->subscriptionRepository->findChartData(
            $subscription,
            (int) $chartRequest->dateStart,
            (int) $chartRequest->dateEnd,
            $chartRequest->overview,
            $chartRequest->type,
            $chartRequest->timeZone
        );

        return $this->handleResponse(new ChartResponse($chartData));
    }

    private function getCurrentUserSubscription(string $id): ?Subscription
    {
        return $this->subscriptionRepository->findOneBy([
            'id' => $id,
            'author' => $this->getUser(),
        ]);
    }
}
