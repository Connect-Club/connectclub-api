<?php

namespace App\Client;

use Google\Cloud\Storage\StorageClient;

/**
 * Class GoogleCloudStorageClient.
 */
class GoogleCloudStorageClient extends StorageClient
{
    /**
     * GoogleCloudStorageClient constructor.
     */
    public function __construct(array $config = [])
    {
        $config['projectId'] = $_ENV['GOOGLE_CLOUD_STORAGE_PROJECT_ID'];

        $storageKeyFile = getenv('GOOGLE_CLOUD_STORAGE_KEY_FILE');
        if ($storageKeyFile != false) {
            $config['keyFilePath'] = $storageKeyFile;
        }

        $storageEmulatorHost = getenv('GOOGLE_CLOUD_STORAGE_EMULATOR_HOST');
        if ($storageEmulatorHost != false) {
            $config['apiEndpoint'] = $storageEmulatorHost;
        }

        parent::__construct($config);
    }

    /** @deprecated */
    public function uploadImage(string $bucket, string $file, string $fileName = null): array
    {
        $options = [];

        if ($fileName) {
            $options['name'] = $fileName;
        }

        return $this->bucket($bucket)->upload(fopen($file, 'r'), $options)->identity();
    }

    public function uploadFile(string $bucket, string $file, string $fileName = null): array
    {
        $options = [];

        if ($fileName) {
            $options['name'] = $fileName;
        }

        return $this->bucket($bucket)->upload(fopen($file, 'r'), $options)->identity();
    }
}
