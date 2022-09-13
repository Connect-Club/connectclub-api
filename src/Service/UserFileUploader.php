<?php

namespace App\Service;

use App\Client\GoogleCloudStorageClient;
use App\Entity\Photo\Image;
use App\Entity\Photo\UserPhoto;
use App\Entity\Photo\VideoRoomImageObjectPhoto;
use App\Entity\User;
use App\Entity\VideoChat\BackgroundPhoto;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Intervention\Image\ImageManagerStatic;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Security;

class UserFileUploader
{
    private GoogleCloudStorageClient $storage;
    private Security $security;
    private ClientInterface $httpClient;

    public function __construct(GoogleCloudStorageClient $storage, Security $security, ClientInterface $httpClient)
    {
        $this->storage = $storage;
        $this->security = $security;
        $this->httpClient = $httpClient;
    }

    public function downloadAvatarForUser(User $user, string $url): UserPhoto
    {
        $tempFile = tmpfile();
        $tempFileDestination = stream_get_meta_data($tempFile)['uri'];

        $this->httpClient->request('GET', $url, [
            RequestOptions::TIMEOUT => $_ENV['TIMEOUT_DOWNLOAD_USER_AVATAR'],
            RequestOptions::SINK => $tempFileDestination,
        ]);

        $mimeType = mime_content_type($tempFileDestination);
        $mimeTypeData = explode('image/', $mimeType);
        $originalFileName = pathinfo($tempFileDestination, PATHINFO_BASENAME).'.'.$mimeTypeData[1];

        $file = new UploadedFile($tempFileDestination, $originalFileName, $mimeType);

        return $this->uploadAvatarForUser($file, $user);
    }

    public function uploadAvatarForUser(UploadedFile $uploadedFile, User $user = null): UserPhoto
    {
        $bucket = $_ENV['GOOGLE_CLOUD_STORAGE_BUCKET'];

        list($originalImage, $processedImage) = $this->uploadImage($bucket, $uploadedFile);

        $photo = new UserPhoto(
            $bucket,
            $originalImage['object'],
            $processedImage['object'],
            $this->security->getUser() ?? $user
        );

        $photo->user = $user;

        return $photo;
    }

    public function uploadUserImage(UploadedFile $uploadedFile, User $user = null): Image
    {
        $bucket = $_ENV['GOOGLE_CLOUD_STORAGE_BUCKET'];

        list($originalImage, $processedImage) = $this->uploadImage($bucket, $uploadedFile);

        return new Image(
            $bucket,
            $originalImage['object'],
            $processedImage['object'],
            $this->security->getUser() ?? $user
        );
    }

    public function uploadVideoRoomBackground(User $owner, UploadedFile $uploadedFile): BackgroundPhoto
    {
        $bucket = $_ENV['GOOGLE_CLOUD_STORAGE_BUCKET'];

        list($width, $height, $type, $attr) = getimagesize($uploadedFile->getRealPath());

        $extOriginalFile = $uploadedFile->getClientOriginalExtension();
        $originalGoogleCloudFileName = Uuid::uuid4()->toString().'.'.$extOriginalFile;
        $originalImage = $this->storage->uploadImage(
            $bucket,
            $uploadedFile->getRealPath(),
            $originalGoogleCloudFileName
        );

        return new BackgroundPhoto(
            $bucket,
            $originalImage['object'],
            $originalImage['object'],
            $width,
            $height,
            $owner
        );
    }

    public function uploadVideoRoomImageObject(UploadedFile $uploadedFile): VideoRoomImageObjectPhoto
    {
        $bucket = $_ENV['GOOGLE_CLOUD_STORAGE_BUCKET'];

        $extOriginalFile = str_replace('image/', '', $uploadedFile->getMimeType());
        $originalGoogleCloudFileName = Uuid::uuid4()->toString().'.'.$extOriginalFile;

        $originalImage = $this->storage->uploadImage(
            $bucket,
            $uploadedFile->getRealPath(),
            $originalGoogleCloudFileName
        );

        return new VideoRoomImageObjectPhoto(
            $bucket,
            $originalImage['object'],
            $originalImage['object'],
            $this->security->getUser()
        );
    }

    public function uploadImage(string $bucket, UploadedFile $uploadedFile): array
    {
        //Upload original version file
        $extOriginalFile = str_replace('image/', '', $uploadedFile->getMimeType());
        $originalGoogleCloudFileName = Uuid::uuid4()->toString().'.'.$extOriginalFile;
        $originalImage = $this->storage->uploadImage(
            $bucket,
            $uploadedFile->getRealPath(),
            $originalGoogleCloudFileName
        );

        //Upload orientated and encoded to png file version
        $dir = pathinfo($uploadedFile->getRealPath(), PATHINFO_DIRNAME);
        $processedFileSource = $dir.'/'.Uuid::uuid4()->toString().'.png';
        ImageManagerStatic::make($uploadedFile->getRealPath())->orientate()->save($processedFileSource, null, 'png');
        $processedGoogleCloudFileName = Uuid::uuid4()->toString().'.png';
        $processedImage = $this->storage->uploadImage($bucket, $processedFileSource, $processedGoogleCloudFileName);

        return [$originalImage, $processedImage];
    }
}
