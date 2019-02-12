<?php

namespace autoxloo\apns;

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
    const RESPONSE_PARAM_MESSAGE = 'message';
    const RESPONSE_PARAM_STATUS_SUCCESS = 200;
    const RESPONSE_PARAM_MESSAGE_SUCCESS = 'OK';
    const CURL_HTTP_VERSION_2_0_KEY = 'CURL_HTTP_VERSION_2_0';
    const CURL_HTTP_VERSION_2_0_VALUE = 3;

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
     * AppleNotificationServer constructor.
     *
     * @param string $appleCertPath Path to apple .pem certificate.
     * @param string $apiUrl Apple API notification url.
     * @param string $apiUrlDev Apple API notification development url.
     * @param int $apnsPort APNS posrt.
     * @param int $pushTimeOut Push timeout.
     */
    public function __construct(
        $appleCertPath,
        $apiUrl = 'https://api.push.apple.com/3/device',
        $apiUrlDev = 'https://api.development.push.apple.com/3/device',
        $apnsPort = 443,
        $pushTimeOut = 10
    )
    {
        if (!is_string($appleCertPath) || !file_exists($appleCertPath)) {
            throw new \InvalidArgumentException('Argument "$appleCertPath" must be a valid string path to file');
        }

        $this->appleCertPath = $appleCertPath;
        $this->apiUrl = $apiUrl;
        $this->apiUrlDev = $apiUrlDev;
        $this->apnsPort = $apnsPort;
        $this->pushTimeOut = $pushTimeOut;

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

        curl_setopt_array($http2ch, [
            CURLOPT_URL => $url,
            CURLOPT_PORT => $this->apnsPort,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $message,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->pushTimeOut,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSLCERT => $this->appleCertPath,
        ]);

        $result = curl_exec($http2ch);
        if ($result === false) {
            $errorMessage = 'Curl failed with error: ' . curl_error($http2ch);
            throw new \RuntimeException($errorMessage);
        }

        $status = curl_getinfo($http2ch, CURLINFO_HTTP_CODE);

        $responseMessage = '';
        if (\is_string($result)) {
            $responseMessage = empty($result) ? self::RESPONSE_PARAM_MESSAGE_SUCCESS : $result;
        }

        return [
            self::RESPONSE_PARAM_STATUS => $status,
            self::RESPONSE_PARAM_MESSAGE => $responseMessage,
        ];
    }
}
