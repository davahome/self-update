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
    protected function getStreamContextOptions(array $headers = [], $requestMethod = 'GET', array $postData = [])
    {
        $headers[] = sprintf('User-Agent: %s/%s', $this->owner, $this->repository);

        $options = [
            'http' => [
                'method' => $requestMethod,
                'header' => $headers,
            ],
        ];

        if (!empty($postData)) {
            $options['http']['content'] = http_build_query($postData);

            $options['http']['header'][] = 'Content-type: application/x-www-form-urlencoded';
            $options['http']['header'][] = 'Content-Length: ' . strlen($options['http']['content']);
        }

        $options['http']['header'] = implode("\r\n", $options['http']['header']) . "\r\n";

        return $options;
    }

    protected function requestData($url, array $streamContext)
    {
        if (isset($this->requestCache[$url])) {
            return $this->requestCache[$url];
        }

        $headers = [];
        if (!empty($this->token)) {
            $headers[] = sprintf('Authorization: token %s', $this->token);
        }
        $context = stream_context_create($streamContext);
        $json = file_get_contents($url, false, $context);
        $data = json_decode($json, true);

        return is_array($data)
            ? $this->requestCache[$url] = $data
            : false;
    }

    protected function getDefaultHeaders(array $headers = [])
    {
        if (!empty($this->token)) {
            $headers[] = sprintf('Authorization: token %s', $this->token);
        }

        return $headers;
    }

    /**
     * @param string $releaseVersion
     *
     * @return array|false
     */
    public function getReleaseInformation($releaseVersion = self::DEFAULT_RELEASE_VERSION)
    {
        return $this->requestData(
            sprintf('https://api.github.com/repos/%s/%s/releases/%s', $this->owner, $this->repository, $releaseVersion),
            $this->getStreamContextOptions($this->getDefaultHeaders())
        );
    }

    /**
     * @param bool $includePreReleases
     * @param bool $includeDrafts
     *
     * @return array|false
     */
    public function getMostRecentReleaseInformation($includePreReleases = true, $includeDrafts = false)
    {
        $list = $this->requestData(
            sprintf('https://api.github.com/repos/%s/%s/releases', $this->owner, $this->repository),
            $this->getStreamContextOptions($this->getDefaultHeaders())
        );

        // Determine the latest non-draft release
        $release = false;
        foreach ($list as $entry) {
            if (!$includeDrafts && $entry['draft']) {
                continue;
            }

            if (!$includePreReleases && $entry['prerelease']) {
                continue;
            }

            $release = $entry;
            break;
        }

        return $release;
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
