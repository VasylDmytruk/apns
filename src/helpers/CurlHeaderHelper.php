<?php

namespace autoxloo\apns\helpers;

/**
 * Class CurlHeaderHelper
 */
class CurlHeaderHelper
{
    /**
     * Gets headers and body from curl result.
     *
     * @param resource $http2ch
     * @param string $result Result of curl_exec().
     *
     * @return array [headers, body].
     */
    public static function getHeadersAndBody($http2ch, $result)
    {
        $header_size = curl_getinfo($http2ch, CURLINFO_HEADER_SIZE);
        $header = substr($result, 0, $header_size);

        $body = substr($result, $header_size);

        $headers = [];
        $data = explode("\r\n", $header);
        $headers['status'] = $data[0];
        array_shift($data);

        foreach ($data as $part) {
            if (empty($part)) {
                continue;
            }

            $middle = explode(":", $part);
            $headers[trim($middle[0])] = trim($middle[1]);
        }

        return [$headers, $body];
    }
}
