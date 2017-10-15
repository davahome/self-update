# self-update

Provides functionality for implementing a github api based self-update functionality (for private repositories)

# Installation

```bash
php composer.phar require davahome/self-update
```


# Usage

```php
use DavaHome\SelfUpdate\AssetFileDownloader;

$assetFileDownloader = new AssetFileDownloader('davahome', 'self-update', '<TOKEN>');

// Display some release information (optional)
$releaseInformation = $assetFileDownloader->getReleaseInformation();
$date = new \DateTime($releaseInformation['published_at']);
echo 'Version: ', $releaseInformation['tag_name'], PHP_EOL;
echo 'Published: ', $date->format('Y-m-d H:i:s'), PHP_EOL;

// Download the asset
$fileContent = $assetFileDownloader->downloadAsset('file.phar');
file_put_contents('file.phar', $fileContent);
```
