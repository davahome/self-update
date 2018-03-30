<?php

namespace DavaHome\SelfUpdate;

class AssetFileDownloader
{
    const DEFAULT_RELEASE_VERSION = 'latest';

    /** @var string */
    protected $owner;

    /** @var string */
    protected $repository;

    /** @var string */
    protected $token;

    /** @var array */
    protected $requestCache = [];

    /**
     * @param string      $owner
     * @param string      $repository
     * @param string|null $token
     */
    public function __construct($owner, $repository, $token = null)
    {
        $this->owner = $owner;
        $this->repository = $repository;
        $this->token = $token;
    }

    /**
     * @param array  $headers
     * @param string $requestMethod
     *
     * @return array
     */
    protected function getStreamContextOptions(array $headers = [], $requestMethod = 'GET')
    {
        $headers[] = sprintf('User-Agent: %s/%s', $this->owner, $this->repository);

        return [
            'http' => [
                'method' => $requestMethod,
                'header' => $headers,
            ],
        ];
    }

    /**
     * @param string $releaseVersion
     *
     * @return array|false
     */
    public function getReleaseInformation($releaseVersion = self::DEFAULT_RELEASE_VERSION)
    {
        $url = sprintf('https://api.github.com/repos/%s/%s/releases/%s', $this->owner, $this->repository, $releaseVersion);
        if (isset($this->requestCache[$url])) {
            return $this->requestCache[$url];
        }

        $headers = [];
        if (!empty($this->token)) {
            $headers[] = sprintf('Authorization: token %s', $this->token);
        }
        $context = stream_context_create($this->getStreamContextOptions($headers));
        $json = file_get_contents($url, false, $context);
        $data = json_decode($json, true);

        if (is_array($data)) {
            return $this->requestCache[$url] = $data;
        }

        return false;
    }

    /**
     * @param string $assetFileName
     * @param string $releaseVersion
     *
     * @return bool|string
     */
    public function downloadAsset($assetFileName, $releaseVersion = self::DEFAULT_RELEASE_VERSION)
    {
        $releaseInformation = $this->getReleaseInformation($releaseVersion);

        $downloadAsset = null;
        foreach ($releaseInformation['assets'] as $asset) {
            if ($asset['name'] == $assetFileName) {
                $downloadAsset = $asset;
                break;
            }
        }

        if (!$downloadAsset) {
            return false;
        }

        $url = vsprintf('https://%sapi.github.com/repos/%s/%s/releases/assets/%s', [
            (!empty($this->token)) ? $this->token . '@' : '',
            $this->owner,
            $this->repository,
            $downloadAsset['id'],
        ]);
        $context = stream_context_create($this->getStreamContextOptions([
            'Accept: application/octet-stream',
        ]));

        return file_get_contents($url, false, $context);
    }
}
