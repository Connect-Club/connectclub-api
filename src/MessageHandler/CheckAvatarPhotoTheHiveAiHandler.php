<?php

namespace App\MessageHandler;

use App\Message\CheckAvatarPhotoTheHiveAiMessage;
use App\Repository\Photo\AbstractPhotoRepository;
use App\Service\SlackClient;
use App\Service\TheHiveAiClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Throwable;

final class CheckAvatarPhotoTheHiveAiHandler implements MessageHandlerInterface
{
    const CLASS_NEED_DETECT = [
        'general_nsfw',
        'general_suggestive',
        'yes_female_underwear',
        'yes_male_underwear',
        'yes_sex_toy',
        'yes_female_nudity',
        'yes_male_nudity',
        'yes_female_swimwear',
        'yes_male_shirtless',
        'gun_in_hand',
        'gun_not_in_hand',
        'animated_gun',
        'knife_in_hand',
        'culinary_knife_in_hand',
        'very_bloody',
        'a_little_bloody',
        'other_blood',
        'yes_pills',
        'yes_illicit_injectables',
        'yes_medical_injectables',
        'yes_smoking',
        'yes_nazi',
        'yes_terrorist',
        'yes_kkk',
        'yes_middle_finger',
    ];

    private TheHiveAiClient $client;
    private SlackClient $slackClient;
    private AbstractPhotoRepository $abstractPhotoRepository;
    private LoggerInterface $logger;

    public function __construct(
        TheHiveAiClient $client,
        SlackClient $slackClient,
        AbstractPhotoRepository $abstractPhotoRepository,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->slackClient = $slackClient;
        $this->abstractPhotoRepository = $abstractPhotoRepository;
        $this->logger = $logger;
    }

    public function __invoke(CheckAvatarPhotoTheHiveAiMessage $message)
    {
        $photo = null;
        if ($photoId = $message->getPhotoId()) {
            $photo = $this->abstractPhotoRepository->find($photoId);
            if ($photo->checkedAt !== null) {
                return;
            }
        }

        $photoUrl = $message->getPhotoUrl();

        if (mb_strpos($photoUrl, 'pics-ng.connect.club') !== false && mb_strpos($photoUrl, '.gif') !== false) {
            throw new UnrecoverableMessageHandlingException();
        }

        try {
            $aiParameters = $this->client->checkPhotoSrc($message->getPhotoUrl());
        } catch (ClientException $exception) {
            $this->logger->error($exception, [
                'exception' => $exception,
                'response' => $exception->getResponse()->getBody()->getContents(),
                'photo' => $message->getPhotoUrl(),
            ]);

            return;
        } catch (Throwable $exception) {
            $this->logger->error($exception, [
                'exception' => $exception,
                'photo' => $message->getPhotoUrl(),
            ]);

            return;
        }

        $code = $aiParameters['status'][0]['status']['code'] ?? null;
        if ($code != 0) {
            $this->logger->error('Incorrect status code the hive ai', [
                'response' => json_encode($aiParameters),
                'photo' => $message->getPhotoUrl(),
            ]);

            return;
        }

        $detectWarnings = [];
        foreach ($aiParameters['status'][0]['response']['output'][0]['classes'] as $classInfo) {
            $class = $classInfo['class'];
            $score = $classInfo['score'];

            if (in_array($class, self::CLASS_NEED_DETECT)) {
                $minScore = $class === 'general_suggestive' ? 0.998 : 0.9;

                if ($score >= $minScore) {
                    $detectWarnings[$class] = $score;
                }
            }
        }

        if ($detectWarnings) {
            $warnings = implode(',', array_map(
                fn($class, $score) => $class . ':' . round($score, 3),
                array_keys($detectWarnings),
                $detectWarnings
            ));
            $this->logger->warning('User id ' . $message->getUserId() . ' ' . $warnings);

            $videoRoomLink = $message->getVideoRoomLink();

            $message = [
                'Detect abuser id: ' . $message->getUserId(),
                'Reasons:',
            ];

            if ($videoRoomLink) {
                $message[] = 'Uploaded prohibited image object to ' . $videoRoomLink;
            }

            foreach ($detectWarnings as $class => $score) {
                $message[] = $class . ': '.round($score, 3);
            }

            $threadId = $this->slackClient->sendMessage(
                $_ENV['SLACK_CHANNEL_SUSPICIOUS_USER'],
                implode(PHP_EOL, $message),
                null,
                false
            )['ts'] ?? null;

            if ($threadId) {
                $this->slackClient->sendMessage(
                    $_ENV['SLACK_CHANNEL_SUSPICIOUS_USER'],
                    'Photo URL: ' . $photoUrl,
                    $threadId,
                    true
                );
            }
        }

        if ($photo) {
            $photo->checkedAt = time();
            $this->abstractPhotoRepository->save($photo);
        }
    }
}
