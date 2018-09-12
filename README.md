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


Sending push notification:

```php
$appleCertPath = __DIR__ . '/wxv_cert.pem';
$token = 'some device token';
$payload = [
    'some key1' => 'some value1',
    'some key2' => 'some value2',
];

$apns = new AppleNotificationServer($appleCertPath);
$response = $apns->send($token, $payload);
```

See [Generating a Remote Notification](https://developer.apple.com/documentation/usernotifications/setting_up_a_remote_notification_server/generating_a_remote_notification)
and [Sending Notification Requests to APNs](https://developer.apple.com/documentation/usernotifications/setting_up_a_remote_notification_server/sending_notification_requests_to_apns)
for more details.