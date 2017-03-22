<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Analytics\Model\Connector;

use Magento\Analytics\Model\AnalyticsToken;
use Magento\Analytics\Model\Connector\Http\ConverterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ZendClient;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;
use Zend_Http_Response as HttpResponse;

/**
 * Representation of an 'OTP' request.
 *
 * The request is responsible for obtaining of an OTP from the MBI service.
 *
 * OTP (One-Time Password) is a password that is valid for short period of time
 * and may be used only for one login session.
 */
class OTPRequest
{
    /**
     * Resource for handling MBI token value.
     *
     * @var AnalyticsToken
     */
    private $analyticsToken;

    /**
     * @var Http\ClientInterface
     */
    private $httpClient;

    /**
     * @var ConverterInterface
     */
    private $converter;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * Path to the configuration value which contains
     * an URL that provides an OTP.
     *
     * @var string
     */
    private $otpUrlConfigPath = 'analytics/url/otp';

    /**
     * @param AnalyticsToken $analyticsToken
     * @param Http\ClientInterface $httpClient
     * @param ConverterInterface $converter
     * @param ScopeConfigInterface $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        AnalyticsToken $analyticsToken,
        Http\ClientInterface $httpClient,
        ConverterInterface $converter,
        ScopeConfigInterface $config,
        LoggerInterface $logger
    ) {
        $this->analyticsToken = $analyticsToken;
        $this->httpClient = $httpClient;
        $this->converter = $converter;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Performs obtaining of an OTP from the MBI service.
     *
     * Returns received OTP or FALSE in case of failure.
     *
     * @return string|false
     */
    public function call()
    {
        $result = false;

        if ($this->analyticsToken->isTokenExist()) {
            $response = $this->httpClient->request(
                ZendClient::POST,
                $this->config->getValue($this->otpUrlConfigPath),
                [
                    "access-token" => $this->analyticsToken->getToken(),
                    "url" => $this->config->getValue(Store::XML_PATH_SECURE_BASE_URL),
                ]
            );

            $result = $this->parseResult($response);
        }

        return $result;
    }

    /**
     * @param \Zend_Http_Response $response
     *
     * @return false|string
     */
    private function parseResult($response)
    {
        $result = false;

        if ($response) {
            if ($response->getStatus() === 201) {
                $body = $this->converter->fromBody($response->getBody());
                $result = !empty($body['otp']) ? $body['otp'] : false;
            }

            if (!$result) {
                $this->logger->warning(
                    sprintf(
                        'Obtaining of an OTP from the MBI service has been failed: %s',
                        !empty($response->getBody()) ? $response->getBody() : 'Response body is empty.'
                    )
                );
            }
        }

        return $result;
    }
}
