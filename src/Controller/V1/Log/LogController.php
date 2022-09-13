<?php

namespace App\Controller\V1\Log;

use App\Client\GoogleCloudStorageClient;
use App\Controller\BaseController;
use App\Service\SlackClient;
use App\Swagger\ViewResponse;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/mobile-app-log")
 */
class LogController extends BaseController
{
    private GoogleCloudStorageClient $googleCloudStorageClient;

    public function __construct(GoogleCloudStorageClient $googleCloudStorageClient)
    {
        $this->googleCloudStorageClient = $googleCloudStorageClient;
    }

    /**
     * @SWG\Post(
     *     consumes={"multipart/form-data"},
     *     summary="Upload mobile app log",
     *     description="Upload mobile app log",
     *     @SWG\Parameter(in="formData", type="string", name="body", description="Body context"),
     *     @SWG\Parameter(in="formData", type="file", name="file", description="Log file"),
     *     @SWG\Response(response="200", description="Success response"),
     *     tags={"System"}
     * )
     * @ViewResponse()
     * @Route("", methods={"POST"})
     */
    public function uploadLog(Request $request, SlackClient $slack, LoggerInterface $logger): JsonResponse
    {
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get('file');

        $log[] = $uploadedFile->getClientMimeType();
        $log[] = $uploadedFile->getClientOriginalExtension();
        $log[] = $uploadedFile->getClientOriginalName();
        $log[] = $uploadedFile->getMimeType();
        $log[] = $uploadedFile->getExtension();

        $logger->warning(implode(' -- ', $log));

        $originalGoogleCloudFileName = Uuid::uuid4()->toString().'.'.$uploadedFile->getClientOriginalExtension();

        $bucket = $_ENV['GOOGLE_CLOUD_STORAGE_BUCKET_MOBILE_APP_LOGS'];

        $fileName = $this->googleCloudStorageClient->uploadFile(
            $bucket,
            $uploadedFile->getRealPath(),
            $originalGoogleCloudFileName
        )['object'];

        $message = $request->request->get('body') ?? '<Empty Body>';
        $message .= PHP_EOL.PHP_EOL;
        $message .= 'https://storage.cloud.google.com/'.$bucket.'/'.$fileName.'?authuser=1';

        $slack->sendMessage($_ENV['SLACK_CHANNEL_MOBILE_APP_LOGS_NAME'], $message);

        return $this->handleResponse([]);
    }
}
