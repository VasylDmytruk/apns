<?php

namespace autoxloo\apns;

use autoxloo\apns\helpers\CurlHeaderHelper;

/**
 * Class AppleNotificationServer Sends push notification via Apple Notification Server.
 * >Note: For now it sends via production url if not success, tries via development url.
 *
 * @see https://developer.apple.com/documentation/usernotifications/setting_up_a_remote_notification_server/generating_a_remote_notification
 * @see https://developer.apple.com/documentation/usernotifications/setting_up_a_remote_notification_server/sending_notification_requests_to_apns
 */
class AppleNotificationServer
{
    const PARAM_TOKEN = 'token';
    const PARAM_DATA = 'data';
    const RESPONSE_PARAM_STATUS = 'status';
    const RESPONSE_PARAM_APNS_ID = 'apnsId';
    const RESPONSE_PARAM_MESSAGE = 'message';
    const RESPONSE_PARAM_STATUS_SUCCESS = 200;
    const RESPONSE_PARAM_MESSAGE_SUCCESS = 'OK';
    const RESPONSE_HEADER_APNS_ID = 'apns-id';
    const CURL_HTTP_VERSION_2_0_KEY = 'CURL_HTTP_VERSION_2_0';
    const CURL_HTTP_VERSION_2_0_VALUE = 3;
    const APN_TOPIC_HEADER = 'apns-topic';
    const APN_EXPIRATION_HEADER = 'apns-expiration';
    const APN_PUSH_TYPE_HEADER = 'apns-push-type';

    const PUSH_TYPE_ALERT = 'alert';
    const PUSH_TYPE_BACKGROUND = 'background';
    const PUSH_TYPE_VOIP = 'voip';
    const PUSH_TYPE_COMPLICATION = 'complication';
    const PUSH_TYPE_FILEPROVIDER = 'fileprovider';
    const PUSH_TYPE_MDM = 'mdm';

    /**
     * @var string Path to apple .pem certificate.
     */
    protected $appleCertPath;
    /**
     * @var string Apple API notification url.
     */
    protected $apiUrl;
    /**
     * @var string Apple API notification development url.
     */
    protected $apiUrlDev;
    /**
     * @var int APNS posrt.
     */
    protected $apnsPort;
    /**
     * @var int Push timeout.
     */
    protected $pushTimeOut;
    /**
     * @var string 'apns-topic' header
     * @see https://developer.apple.com/documentation/usernotifications/setting_up_a_remote_notification_server/sending_notification_requests_to_apns
     */
    protected $topic;
    /**
     * @var null|int 'apns-expiration' header value.
     * @see https://developer.apple.com/documentation/usernotifications/setting_up_a_remote_notification_server/sending_notification_requests_to_apns
     */
    protected $expiration;
    /**
     * @var string|null 'apns-push-type' header value.
     * @see https://developer.apple.com/documentation/usernotifications/setting_up_a_remote_notification_server/sending_notification_requests_to_apns
     */
    protected $pushType;


    /**
     * AppleNotificationServer constructor.
     *
     * @param string $appleCertPath Path to apple .pem certificate.
     * @param string $apiUrl Apple API notification url.
     * @param string $apiUrlDev Apple API notification development url.
     * @param int $apnsPort APNS posrt.
     * @param int $pushTimeOut Push timeout.
     * @param string|null $topic
     * @param null|int $expiration The date at which the notification is no longer valid.
     * This value is a Unix epoch expressed in seconds (UTC).
     * @param null|string $pushType Apns push type ('apns-push-type' header value).
     */
    public function __construct(
        $appleCertPath,
        $apiUrl = 'https://api.push.apple.com/3/device',
        $apiUrlDev = 'https://api.sandbox.push.apple.com/3/device',
        $apnsPort = 443,
        $pushTimeOut = 10,
        $topic = null,
        $expiration = null,
        $pushType = null
    ) {
        if (!is_string($appleCertPath) || !file_exists($appleCertPath)) {
            throw new \InvalidArgumentException('Argument "$appleCertPath" must be a valid string path to file');
        }

        $this->appleCertPath = $appleCertPath;
        $this->apiUrl = $apiUrl;
        $this->apiUrlDev = $apiUrlDev;
        $this->apnsPort = $apnsPort;
        $this->pushTimeOut = $pushTimeOut;
        $this->setTopic($topic);
        $this->setExpiration($expiration);
        $this->setPushType($pushType);

        if (!\defined(self::CURL_HTTP_VERSION_2_0_KEY)) {
            \define(self::CURL_HTTP_VERSION_2_0_KEY, self::CURL_HTTP_VERSION_2_0_VALUE);
        }
    }

    /**
     * Sends notification to many recipients (`$tokens`).
     *
     * @param array $tokens List of tokens of devices to send push notification on.
     * @param array $payload APNS payload data (will be json encoded).
     *
     * @return array List of status codes with response messages.
     */
    public function sendToMany(array $tokens, array $payload)
    {
        $sendResult = [];

        $http2ch = curl_init();
        curl_setopt($http2ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);

        $message = json_encode($payload);

        foreach ($tokens as $token) {
            $result = $this->sendHTTP2Push($http2ch, $message, $token);

            if ($this->apiUrlDev && self::RESPONSE_PARAM_STATUS_SUCCESS != $result[self::RESPONSE_PARAM_STATUS]) {
                $result = $this->sendHTTP2Push($http2ch, $message, $token, true);
            }

            $sendResult[$token] = $result;
        }

        curl_close($http2ch);

        return $sendResult;
    }

    /**
     * Sends notification to recipient (`$token`).
     *
     * @param string $token Token of device to send push notification on.
     * @param array $payload APNS payload data (will be json encoded).
     *
     * @return array Status code with response message.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function send($token, array $payload)
    {
        if (!is_string($token)) {
            throw new \InvalidArgumentException('Argument "$token" must be a string');
        }

        $http2ch = curl_init();
        curl_setopt($http2ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);

        $message = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $result = $this->sendHTTP2Push($http2ch, $message, $token);

        if ($this->apiUrlDev && self::RESPONSE_PARAM_STATUS_SUCCESS != $result[self::RESPONSE_PARAM_STATUS]) {
            $result = $this->sendHTTP2Push($http2ch, $message, $token, true);
        }

        curl_close($http2ch);

        return $result;
    }

    /**
     * Makes http v2 POST request to Apple Notification Server.
     *
     * @param resource $http2ch cURL handle.
     * @param string $message The payload to send (JSON).
     * @param string $token The token of the device.
     * @param bool $devApiUrl Whether to use development Apple API url. By default `false` - not use.
     *
     * @return array Status code with response message.
     *
     * @throws \RuntimeException
     */
    protected function sendHTTP2Push($http2ch, $message, $token, $devApiUrl = false)
    {
        $apiUrl = $devApiUrl ? $this->apiUrlDev : $this->apiUrl;
        $url = "$apiUrl/{$token}";

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_PORT => $this->apnsPort,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $message,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->pushTimeOut,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSLCERT => $this->appleCertPath,
            CURLOPT_HEADER => true,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ];

        $this->setHeaders($curlOptions);

        curl_setopt_array($http2ch, $curlOptions);

        $result = curl_exec($http2ch);
        if ($result === false) {
            $lastIp = curl_getinfo($http2ch, CURLINFO_PRIMARY_IP);
            $lastIp = is_string($lastIp) ? $lastIp : '';
            $errorMessage = 'Curl failed with error: ' . curl_error($http2ch) . ', ip: ' . $lastIp;
            throw new \RuntimeException($errorMessage);
        }

        $status = curl_getinfo($http2ch, CURLINFO_HTTP_CODE);

        list($headers, $result) = CurlHeaderHelper::getHeadersAndBody($http2ch, $result);
        $apnsId = isset($headers[self::RESPONSE_HEADER_APNS_ID]) ? $headers[self::RESPONSE_HEADER_APNS_ID] : null;

        $responseMessage = '';
        if (\is_string($result)) {
            $responseMessage = empty($result) ? self::RESPONSE_PARAM_MESSAGE_SUCCESS : $result;
        }

        return [
            self::RESPONSE_PARAM_STATUS => $status,
            self::RESPONSE_PARAM_MESSAGE => $responseMessage,
            self::RESPONSE_PARAM_APNS_ID => $apnsId,
        ];
    }

    /**
     * Sets curl headers.
     *
     * @param array $curlOptions
     */
    protected function setHeaders(array &$curlOptions)
    {
        $this->setHeader($curlOptions, self::APN_TOPIC_HEADER, $this->topic);
        $this->setHeader($curlOptions, self::APN_EXPIRATION_HEADER, $this->expiration);
        $this->setHeader($curlOptions, self::APN_PUSH_TYPE_HEADER, $this->pushType);
    }

    /**
     * Sets curl header.
     *
     * @param string $headerKey
     * @param string $headerValue
     */
    protected function setHeader(array &$curlOptions, $headerKey, $headerValue)
    {
        if (!empty($headerValue)) {
            $curlOptions[CURLOPT_HTTPHEADER][] = $headerKey . ': ' . $headerValue;
        }
    }

    /**
     * @param string $topic
     */
    public function setTopic($topic)
    {
        if (is_string($topic)) {
            $this->topic = $topic;
        }
    }

    /**
     * @param int $expiration 'apns-expiration' header value
     */
    public function setExpiration($expiration)
    {
        if ($expiration !== null && is_int($expiration)) {
            $this->expiration = $expiration;
        }
    }

    /**
     * Sets Apple cert path.
     *
     * @param string $appleCertPath
     */
    public function setAppleCertPath($appleCertPath)
    {
        if (is_string($appleCertPath) && file_exists($appleCertPath)) {
            $this->appleCertPath = $appleCertPath;
        }
    }

    /**
     * @param string $pushType
     */
    public function setPushType($pushType)
    {
        if (is_string($pushType)) {
            $this->pushType = $pushType;
        }
    }
}
