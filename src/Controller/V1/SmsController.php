<?php

namespace App\Controller\V1;

use App\Controller\BaseController;
use App\Service\JwtToken;
use App\Controller\ErrorCode;
use App\DTO\V1\User\VerificationRequest;
use App\Message\SendSmsMessage;
use App\Repository\User\SmsVerificationRepository;
use App\Repository\UserRepository;
use App\Service\EventLogManager;
use App\Service\PhoneNumberManager;
use App\Swagger\ViewResponse;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use MaxMind\Db\Reader;
use Nelmio\ApiDocBundle\Annotation\Model;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Redis;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

/** @Route("/sms") */
class SmsController extends BaseController
{
    /**
     * @SWG\Post(
     *     description="Send sms verification code",
     *     summary="Send sms verification code",
     *     tags={"User"},
     *     @SWG\Parameter(in="body", name="body", @SWG\Schema(ref=@Model(type=VerificationRequest::class))),
     *     @SWG\Response(response="201", description="Ok response"),
     *     @SWG\Response(response="409", description="Code already provided"),
     * )
     * @ViewResponse()
     * @Route("/verification", methods={"POST"})
     */
    public function verification(
        Request $request,
        MessageBusInterface $bus,
        LockFactory $lockFactory,
        UserRepository $userRepository,
        SmsVerificationRepository $smsVerificationRepository,
        EventLogManager $eventLogManager,
        JwtToken $tokenValidator,
        Reader $reader,
        Redis $redis,
        LoggerInterface $logger,
        bool $isStage
    ): JsonResponse {
        $ip = $request->getClientIp();

        $authHeader = $request->headers->get('Authorization');
        if (!$claim = $tokenValidator->getJWTClaim(str_replace('Bearer ', '', $authHeader))) {
            $logger->warning('Jwt incorrect');
            return $this->createErrorResponse(ErrorCode::V1_ACCESS_DENIED, Response::HTTP_FORBIDDEN);
        }

        $claim = trim(mb_strtolower($claim));

        if (!Uuid::isValid($claim)) {
            $logger->warning('Claim is invalid uuid');
            return $this->createErrorResponse(ErrorCode::V1_ACCESS_DENIED, Response::HTTP_FORBIDDEN);
        }

        $smsVerificationWithClaim = $smsVerificationRepository->findOneBy(['jwtClaim' => $claim]);
        if ($smsVerificationWithClaim) {
            $logger->warning('Claim already used for authorization '.$smsVerificationWithClaim->id->toString());
            return $this->createErrorResponse(ErrorCode::V1_ACCESS_DENIED, Response::HTTP_FORBIDDEN);
        }

        $key = new Key('sms_verification_jwt_claim_'.md5($claim));
        $lock = $lockFactory->createLock($key, 300, false);
        if (!$lock->acquire()) {
            $logger->warning('Claim '.$claim.' is locked');
            return $this->createErrorResponse(ErrorCode::V1_ERROR_ACTION_LOCK, Response::HTTP_LOCKED);
        }

        /** @var VerificationRequest $verificationRequest */
        $verificationRequest = $this->getEntityFromRequestTo($request, VerificationRequest::class);

        if ($verificationRequest->phone != '+18006927753') {
            $this->unprocessableUnlessValid($verificationRequest);
        }

        if (mb_strpos($verificationRequest->phone, '+371243214') !== false) {
            return $this->handleResponse([], Response::HTTP_CREATED);
        }

        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        $phoneNumberObject = $phoneNumberUtil->parse($verificationRequest->phone, PhoneNumberUtil::UNKNOWN_REGION);
        $phoneNumber = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::INTERNATIONAL);
        $phoneNumberE164 = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164);

        $phoneCountryIsoCode = PhoneNumberUtil::getInstance()->getRegionCodeForCountryCode(
            $phoneNumberObject->getCountryCode()
        );

        if (!$isStage) {
            $lock = $lockFactory->createLock('phone_number_sms_verification_'.md5($phoneNumber), 20, false);
            if (!$lock->acquire()) {
                $eventLogManager->logEventCustomObject('block_number_20_sec', 'phone', $phoneNumberE164, ['ip' => $ip]);
                return $this->createErrorResponse([ErrorCode::V1_ERROR_ACTION_LOCK], Response::HTTP_TOO_MANY_REQUESTS);
            }

            $ipsWhiteList = $_ENV['SMS_VERIFICATION_IP_WHITE_LIST'] ?? '';
            $ipsWhiteList = array_map('trim', explode(',', $ipsWhiteList));

            if ($ip && !in_array($ip, $ipsWhiteList)) {
                $lockByIp = $lockFactory->createLock('phone_number_sms_verification_'.md5($ip), 60, false);
                if (!$lockByIp->acquire()) {
                    $eventLogManager->logEventCustomObject('block_ip_60_sec', 'phone', $phoneNumberE164, ['ip' => $ip]);
                    return $this->createErrorResponse(
                        ErrorCode::V1_ERROR_ACTION_LOCK,
                        Response::HTTP_TOO_MANY_REQUESTS
                    );
                }

                $verifications = $smsVerificationRepository->findSmsVerificationsForIpLastDay($ip);
                if (count($verifications) >= 10) {
                    $eventLogManager->logEventCustomObject(
                        'block_ip_24_hours',
                        'phone',
                        $phoneNumberE164,
                        ['ip' => $ip]
                    );
                    return $this->handleResponse([], Response::HTTP_CREATED);
                }
            }
        }

        $ipCountryIsoCode = null;
        if ($ip) {
            try {
                $locationData = $reader->get($ip);
                $ipCountryIsoCode = $locationData['country']['iso_code'] ?? null;
            } catch (Throwable $exception) {
            }
        }

        if ($_ENV['DISABLE_SMS_IP_VERIFICATION'] != 1 && $ipCountryIsoCode !== $phoneCountryIsoCode) {
            try {
                $redisKey = 'sms_verification_'.md5($phoneNumberE164);
                if ($redis->get($redisKey) != 1) {
                    $redis->set($redisKey, 1);
                    $redis->expire($redisKey, 120);

                    return $this->handleResponse([], Response::HTTP_CREATED);
                }
            } catch (Throwable $exception) {
            }
        }

        if (in_array($phoneCountryIsoCode, ['BD', 'PK', 'AZ', 'KZ', 'UZ', 'PH', 'KG'])) {
            return $this->handleResponse([], Response::HTTP_CREATED);
        }

        $user = $userRepository->findOneBy(['phone' => $phoneNumberObject]);
        if ($user && $user->bannedAt !== null) {
            return $this->createErrorResponse($user->banComment ?? ErrorCode::V1_USER_BANNED, Response::HTTP_FORBIDDEN);
        }

        $stamps = [];
        if ($_ENV['STAGE'] == 1) {
            $stamps[] = new DelayStamp(5000);
        }
        $bus->dispatch(new SendSmsMessage($phoneNumberE164, $ip, $claim, $key), $stamps);

        return $this->handleResponse([], Response::HTTP_CREATED);
    }
}
