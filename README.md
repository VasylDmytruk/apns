Apple Notification Server
========================
Sends push notification via Apple Notification Server

>Note: This package is not supported properly

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist autoxloo/apns "*"
```

or

```php
composer require --prefer-dist autoxloo/apns "*"
```

or add

```
"autoxloo/apns": "*"
```

to the require section of your `composer.json` file.

Configuration
-------------

You have to install curl with http2 support:

```
cd ~
sudo apt-get install build-essential nghttp2 libnghttp2-dev libssl-dev
wget https://curl.haxx.se/download/curl-7.58.0.tar.gz
tar -xvf curl-7.58.0.tar.gz
cd curl-7.58.0
./configure --with-nghttp2 --prefix=/usr/local --with-ssl=/usr/local/ssl
make
sudo make install
sudo ldconfig
sudo reboot
```

Info from [https://askubuntu.com/questions/884899/how-do-i-install-curl-with-http2-support](https://askubuntu.com/questions/884899/how-do-i-install-curl-with-http2-support)

If not helped, try [https://serversforhackers.com/c/curl-with-http2-support](https://serversforhackers.com/c/curl-with-http2-support)

Usage
-----

To send push notification you should have apple .pem certificate.

Constructor params and default values:

```php
use autoxloo\apns\AppleNotificationServer;

$appleCertPath = __DIR__ . '/wxv_cert.pem';

$apns = new AppleNotificationServer(
    $appleCertPath,
    $apiUrl = 'https://api.push.apple.com/3/device',
    $apiUrlDev = 'https://api.sandbox.push.apple.com/3/device',
    $apnsPort = 443,
    $pushTimeOut = 10,
    $topic = null,
    $expiration = null,
    $pushType = null
);
```

Sending push notification:

```php
use autoxloo\apns\AppleNotificationServer;

$appleCertPath = __DIR__ . '/wxv_cert.pem';
$token = 'some device token';
$payload = [
    'some key1' => 'some value1',
    'some key2' => 'some value2',
];

$apns = new AppleNotificationServer($appleCertPath);
$response = $apns->send($token, $payload);
```

or if you want to send to many tokens:

```php
use autoxloo\apns\AppleNotificationServer;

$appleCertPath = __DIR__ . '/wxv_cert.pem';
$tokens = [
    'some device token',
    'some other device token',
];
$payload = [
    'some key1' => 'some value1',
    'some key2' => 'some value2',
];

$apns = new AppleNotificationServer($appleCertPath);
$response = $apns->sendToMany($tokens, $payload);
```

If you want to send push notification with some `apns-push-type`, you need certificate compilable 
with this push type and to set `AppleNotificationServer::$pushType` in constructor or with set method:

```php
use autoxloo\apns\AppleNotificationServer;

$appleCertPath = __DIR__ . '/wxv_cert.pem';
$token = 'some device token';
$payload = [
    'some key1' => 'some value1',
    'some key2' => 'some value2',
];

$apns = new AppleNotificationServer($appleCertPath);
$apns->setPushType(AppleNotificationServer::PUSH_TYPE_BACKGROUND);  // sets `apns-push-type` header.
// other available set methods:
$apns->setTopic('some topic');  // sets `apns-topic` header.
$apns->setExpiration(time() + 30);  // sets `apns-expiration` header.
$apns->setExpiration(0);  // sets `apns-expiration` header. If the value is 0, APNs attempts to deliver
                          // the notification only once and doesn’t store it.
$response = $apns->send($token, $payload);
``` 

`AppleNotificationServer` sends push notification first on `$apiUrl` (https://api.push.apple.com/3/device)
if not success (not status code `200`), then sends on `$apiUrlDev` (https://api.sandbox.push.apple.com/3/device).
If you don't want to send push notification on `$apiUrlDev` set it value to `false`.
Also, if you want to send push notification only on dev url, you can do so like this (set `$apiUrl` with dev url value):

```php
use autoxloo\apns\AppleNotificationServer;

$apns = new AppleNotificationServer($appleCertPath, 'https://api.sandbox.apple.com/3/device', false);
```

See [Generating a Remote Notification](https://developer.apple.com/documentation/usernotifications/setting_up_a_remote_notification_server/generating_a_remote_notification)
and [Sending Notification Requests to APNs](https://developer.apple.com/documentation/usernotifications/setting_up_a_remote_notification_server/sending_notification_requests_to_apns)
for more details.
