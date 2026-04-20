<?php

namespace Cardlink_Checkout;

/**
 * Cardlink VPOS XML API Client
 *
 * Standalone library for handling Cardlink payment gateway secondary XML requests:
 * - Capture (settle pre-authorized transactions)
 * - Refund (refund captured transactions)
 * - Cancel/Void (cancel pre-authorized transactions)
 * - Status (query transaction status)
 *
 * Based on Cardlink VPOS XML API v2.1
 * @see https://developer.cardlink.gr/api_products_categories/vpos-xml-requests/#Secondary-requests
 *
 * @author Cardlink S.A.
 */
class CardlinkXmlApi
{
    /**
     * XML Namespace for VPOS API
     */
    const XML_NAMESPACE = 'http://www.modirum.com/schemas/vposxmlapi41';
    const XML_NAMESPACE_NS2 = 'http://www.w3.org/2000/09/xmldsig#';

    /**
     * API Version
     */
    const API_VERSION = '2.1';

    /**
     * Business Partners
     */
    const PARTNER_CARDLINK = 'cardlink';
    const PARTNER_NEXI = 'nexi';
    const PARTNER_WORLDLINE = 'worldline';

    /**
     * Environments
     */
    const ENV_PRODUCTION = 'production';
    const ENV_SANDBOX = 'sandbox';

    /**
     * Transaction Statuses
     */
    const STATUS_CAPTURED = 'CAPTURED';
    const STATUS_AUTHORIZED = 'AUTHORIZED';
    const STATUS_CANCELED = 'CANCELED';
    const STATUS_REFUSED = 'REFUSED';
    const STATUS_ERROR = 'ERROR';
    const STATUS_PROCESSING = 'PROCESSING';

    /**
     * @var string Merchant ID
     */
    private $merchantId;

    /**
     * @var string Shared Secret for digest calculation
     */
    private $sharedSecret;

    /**
     * @var string Business Partner (cardlink, nexi, worldline)
     */
    private $businessPartner;

    /**
     * @var string Environment (production, sandbox)
     */
    private $environment;

    /**
     * @var int cURL timeout in seconds
     */
    private $timeout = 60;

    /**
     * @var bool Enable debug logging
     */
    private $debug = false;

    /**
     * @var callable|null Debug logger callback
     */
    private $debugLogger = null;

    /**
     * @var array Last request data for debugging
     */
    private $lastRequest = [];

    /**
     * @var array Last response data for debugging
     */
    private $lastResponse = [];

    /**
     * Constructor.
     *
     * @param string $merchantId Merchant ID
     * @param string $sharedSecret Shared secret for digest calculation
     * @param string $businessPartner Business partner (cardlink, nexi, worldline)
     * @param string $environment Environment (production, sandbox)
     */
    public function __construct(
        string $merchantId,
        string $sharedSecret,
        string $businessPartner = self::PARTNER_CARDLINK,
        string $environment = self::ENV_SANDBOX
    ) {
        $this->merchantId = $merchantId;
        $this->sharedSecret = $sharedSecret;
        $this->businessPartner = $businessPartner;
        $this->environment = $environment;
    }

    /**
     * Set cURL timeout.
     *
     * @param int $timeout Timeout in seconds
     * @return self
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Enable debug mode.
     *
     * @param bool $debug Enable debug
     * @param callable|null $logger Optional logger callback
     * @return self
     */
    public function setDebug(bool $debug, ?callable $logger = null): self
    {
        $this->debug = $debug;
        $this->debugLogger = $logger;
        return $this;
    }

    /**
     * Get the API endpoint URL based on business partner and environment.
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        $isProduction = ($this->environment === self::ENV_PRODUCTION);

        switch ($this->businessPartner) {
            case self::PARTNER_CARDLINK:
                return $isProduction
                    ? 'https://ecommerce.cardlink.gr/vpos/xmlpayvpos'
                    : 'https://ecommerce-test.cardlink.gr/vpos/xmlpayvpos';

            case self::PARTNER_NEXI:
                return $isProduction
                    ? 'https://www.alphaecommerce.gr/vpos/xmlpayvpos'
                    : 'https://alphaecommerce-test.cardlink.gr/vpos/xmlpayvpos';

            case self::PARTNER_WORLDLINE:
                return $isProduction
                    ? 'https://vpos.eurocommerce.gr/vpos/xmlpayvpos'
                    : 'https://eurocommerce-test.cardlink.gr/vpos/xmlpayvpos';

            default:
                return 'https://ecommerce-test.cardlink.gr/vpos/xmlpayvpos';
        }
    }

    /**
     * Capture a pre-authorized transaction.
     *
     * @param string $orderId The original order ID
     * @param float $amount The amount to capture
     * @param string $currency Currency code (default: EUR)
     * @return CardlinkXmlResponse
     */
    public function capture(string $orderId, float $amount, string $currency = 'EUR'): CardlinkXmlResponse
    {
        $messageId = $this->generateMessageId();
        $timestamp = $this->generateTimestamp();

        $messageContent = $this->buildCaptureRequestContent($orderId, $amount, $currency);
        $xml = $this->buildXmlRequest($messageId, $timestamp, $messageContent);

        return $this->sendRequest($xml, 'CaptureResponse');
    }

    /**
     * Refund a captured transaction.
     *
     * @param string $orderId The original order ID
     * @param float $amount The amount to refund
     * @param string $currency Currency code (default: EUR)
     * @return CardlinkXmlResponse
     */
    public function refund(string $orderId, float $amount, string $currency = 'EUR'): CardlinkXmlResponse
    {
        $messageId = $this->generateMessageId();
        $timestamp = $this->generateTimestamp();

        $messageContent = $this->buildRefundRequestContent($orderId, $amount, $currency);
        $xml = $this->buildXmlRequest($messageId, $timestamp, $messageContent);

        return $this->sendRequest($xml, 'RefundResponse');
    }

    /**
     * Cancel/Void a pre-authorized transaction.
     *
     * @param string $orderId The original order ID
     * @param float $amount The amount to cancel
     * @param string $currency Currency code (default: EUR)
     * @return CardlinkXmlResponse
     */
    public function cancel(string $orderId, float $amount, string $currency = 'EUR'): CardlinkXmlResponse
    {
        $messageId = $this->generateMessageId();
        $timestamp = $this->generateTimestamp();

        $messageContent = $this->buildCancelRequestContent($orderId, $amount, $currency);
        $xml = $this->buildXmlRequest($messageId, $timestamp, $messageContent);

        return $this->sendRequest($xml, 'CancelResponse');
    }

    /**
     * Alias for cancel() - void a pre-authorized transaction.
     *
     * @param string $orderId The original order ID
     * @param float $amount The amount to void
     * @param string $currency Currency code (default: EUR)
     * @return CardlinkXmlResponse
     */
    public function void(string $orderId, float $amount, string $currency = 'EUR'): CardlinkXmlResponse
    {
        return $this->cancel($orderId, $amount, $currency);
    }

    /**
     * Get the status of a transaction.
     *
     * @param string $orderId The order ID to query
     * @return CardlinkXmlResponse
     */
    public function status(string $orderId): CardlinkXmlResponse
    {
        $messageId = $this->generateMessageId();
        $timestamp = $this->generateTimestamp();

        $messageContent = $this->buildStatusRequestContent($orderId);
        $xml = $this->buildXmlRequest($messageId, $timestamp, $messageContent);

        return $this->sendRequest($xml, 'StatusResponse');
    }

    // =========================================================================
    //  WALLET METHODS (Google Pay / Apple Pay Direct)
    // =========================================================================

    /**
     * Send a wallet sale request (first request for Google Pay / Apple Pay).
     *
     * @param string $orderId      Unique order identifier.
     * @param string $amount       Amount as decimal string (e.g., "55.55").
     * @param string $currency     ISO 4217 currency code (e.g., "EUR").
     * @param string $walletPaymentData  Tokenised card data JSON from wallet SDK.
     * @param string $payMethod    Wallet type: "googlepay" or "applepay".
     * @param string $orderDesc    Order description.
     * @return CardlinkXmlResponse Status: CAPTURED, AUTHORIZED, PROCESSING (3DS), or error.
     */
    public function walletSale(
        string $orderId,
        string $amount,
        string $currency,
        string $walletPaymentData,
        string $payMethod,
        string $orderDesc = ''
    ): CardlinkXmlResponse {
        $messageId = $this->generateMessageId();
        $timestamp = $this->generateTimestamp();

        $messageContent = $this->buildWalletSaleRequestContent(
            $orderId, $amount, $currency, $walletPaymentData, $payMethod, $orderDesc
        );
        $xml = $this->buildXmlRequest($messageId, $timestamp, $messageContent);

        return $this->sendRequest($xml, 'SaleResponse');
    }

    /**
     * Send a wallet sale request with 3DS authentication results (second request after 3DS).
     *
     * @param string $orderId      Original order ID from first request.
     * @param string $amount       Same amount as first request.
     * @param string $currency     Same currency as first request.
     * @param string $preparedTxId TxId from first walletSale() response.
     * @param string $payMethod    Card brand (e.g., "visa", "mastercard").
     * @param array  $threeDSData  3DS authentication data with keys:
     *                             enrollmentStatus, authenticationStatus, cavv, xid, eci, protocol.
     * @param string $orderDesc    Order description.
     * @return CardlinkXmlResponse Expected status: CAPTURED or AUTHORIZED.
     */
    public function walletSaleWith3DS(
        string $orderId,
        string $amount,
        string $currency,
        string $preparedTxId,
        string $payMethod,
        array $threeDSData,
        string $orderDesc = ''
    ): CardlinkXmlResponse {
        $messageId = $this->generateMessageId();
        $timestamp = $this->generateTimestamp();

        $messageContent = $this->buildWalletSale3DSRequestContent(
            $orderId, $amount, $currency, $preparedTxId, $payMethod, $threeDSData, $orderDesc
        );
        $xml = $this->buildXmlRequest($messageId, $timestamp, $messageContent);

        return $this->sendRequest($xml, 'SaleResponse');
    }

    /**
     * Send a wallet session request (Apple Pay merchant validation).
     *
     * @param string $orderId      Order identifier.
     * @param string $amount       Amount as decimal string.
     * @param string $currency     ISO 4217 currency code.
     * @param string $validationUrl Apple Pay validation URL.
     * @param string $orderDesc    Order description.
     * @return CardlinkXmlResponse Contains wallet session data.
     */
    public function walletSession(
        string $orderId,
        string $amount,
        string $currency,
        string $validationUrl,
        string $orderDesc = ''
    ): CardlinkXmlResponse {
        $messageId = $this->generateMessageId();
        $timestamp = $this->generateTimestamp();

        $messageContent = $this->buildWalletSessionRequestContent(
            $orderId, $amount, $currency, $validationUrl, $orderDesc
        );
        $xml = $this->buildXmlRequest($messageId, $timestamp, $messageContent);

        return $this->sendRequest($xml, 'WalletResponse');
    }

    /**
     * Build wallet sale XML content.
     */
    private function buildWalletSaleRequestContent(
        string $orderId,
        string $amount,
        string $currency,
        string $walletPaymentData,
        string $payMethod,
        string $orderDesc
    ): string {
        $mid = $this->merchantId;

        // Escape only &, <, > for XML text content. Do NOT escape quotes —
        // they are legal in text content, and the VPOS canonicalizer does not
        // escape them either, so &quot; would cause an HMAC digest mismatch.
        $escapedWalletData = htmlspecialchars($walletPaymentData, ENT_XML1, 'UTF-8');

        $attrName = ($payMethod === 'applepay') ? 'applePaymentData' : 'googlePaymentData';

        return "    <SaleRequest>\n" .
               "        <Authentication>\n" .
               "            <Mid>{$mid}</Mid>\n" .
               "        </Authentication>\n" .
               "        <OrderInfo>\n" .
               "            <OrderId>{$orderId}</OrderId>\n" .
               "            <OrderDesc>{$orderDesc}</OrderDesc>\n" .
               "            <OrderAmount>{$amount}</OrderAmount>\n" .
               "            <Currency>{$currency}</Currency>\n" .
               "        </OrderInfo>\n" .
               "        <PaymentInfo>\n" .
               "            <PayMethod>{$payMethod}</PayMethod>\n" .
               "        </PaymentInfo>\n" .
               "        <WalletInfo>\n" .
               "            <Attribute name=\"{$attrName}\">{$escapedWalletData}</Attribute>\n" .
               "        </WalletInfo>\n" .
               "    </SaleRequest>";
    }

    /**
     * Build wallet sale with 3DS XML content.
     */
    private function buildWalletSale3DSRequestContent(
        string $orderId,
        string $amount,
        string $currency,
        string $preparedTxId,
        string $payMethod,
        array $threeDSData,
        string $orderDesc
    ): string {
        $mid = $this->merchantId;

        $enrollmentStatus     = htmlspecialchars($threeDSData['enrollmentStatus'] ?? '', ENT_XML1, 'UTF-8');
        $authenticationStatus = htmlspecialchars($threeDSData['authenticationStatus'] ?? '', ENT_XML1, 'UTF-8');
        $cavv                 = htmlspecialchars($threeDSData['cavv'] ?? '', ENT_XML1, 'UTF-8');
        $xid                  = htmlspecialchars($threeDSData['xid'] ?? '', ENT_XML1, 'UTF-8');
        $eci                  = htmlspecialchars($threeDSData['eci'] ?? '', ENT_XML1, 'UTF-8');
        $protocol             = htmlspecialchars($threeDSData['protocol'] ?? '', ENT_XML1, 'UTF-8');

        $protocolElement = $protocol !== ''
            ? "                <Protocol>{$protocol}</Protocol>\n"
            : '';

        return "    <SaleRequest>\n" .
               "        <Authentication>\n" .
               "            <Mid>{$mid}</Mid>\n" .
               "        </Authentication>\n" .
               "        <OrderInfo>\n" .
               "            <OrderId>{$orderId}</OrderId>\n" .
               "            <OrderDesc>{$orderDesc}</OrderDesc>\n" .
               "            <OrderAmount>{$amount}</OrderAmount>\n" .
               "            <Currency>{$currency}</Currency>\n" .
               "        </OrderInfo>\n" .
               "        <PaymentInfo preparedTxId=\"{$preparedTxId}\">\n" .
               "            <PayMethod>{$payMethod}</PayMethod>\n" .
               "            <ThreeDSecure>\n" .
               "                <EnrollmentStatus>{$enrollmentStatus}</EnrollmentStatus>\n" .
               "                <AuthenticationStatus>{$authenticationStatus}</AuthenticationStatus>\n" .
               "                <CAVV>{$cavv}</CAVV>\n" .
               "                <XID>{$xid}</XID>\n" .
               "                <ECI>{$eci}</ECI>\n" .
               $protocolElement .
               "            </ThreeDSecure>\n" .
               "        </PaymentInfo>\n" .
               "        <WalletInfo>\n" .
               "            <Attribute></Attribute>\n" .
               "        </WalletInfo>\n" .
               "    </SaleRequest>";
    }

    /**
     * Build wallet session XML content (Apple Pay).
     */
    private function buildWalletSessionRequestContent(
        string $orderId,
        string $amount,
        string $currency,
        string $validationUrl,
        string $orderDesc
    ): string {
        $mid = $this->merchantId;
        $escapedValidationUrl = htmlspecialchars($validationUrl, ENT_XML1, 'UTF-8');

        return "    <WalletRequest>\n" .
               "        <Authentication>\n" .
               "            <Mid>{$mid}</Mid>\n" .
               "        </Authentication>\n" .
               "        <OrderInfo>\n" .
               "            <OrderId>{$orderId}</OrderId>\n" .
               "            <OrderDesc>{$orderDesc}</OrderDesc>\n" .
               "            <OrderAmount>{$amount}</OrderAmount>\n" .
               "            <Currency>{$currency}</Currency>\n" .
               "        </OrderInfo>\n" .
               "        <WalletId>ApplePay</WalletId>\n" .
               "        <Mid>{$mid}</Mid>\n" .
               "        <ValidationURL>{$escapedValidationUrl}</ValidationURL>\n" .
               "    </WalletRequest>";
    }

    /**
     * Build Capture request content.
     *
     * @param string $orderId
     * @param float $amount
     * @param string $currency
     * @return string
     */
    private function buildCaptureRequestContent(string $orderId, float $amount, string $currency): string
    {
        $mid = $this->merchantId;
        $orderAmount = $this->formatAmount($amount);
        
        return "    <CaptureRequest>\n" .
               "        <Authentication>\n" .
               "            <Mid>{$mid}</Mid>\n" .
               "        </Authentication>\n" .
               "        <OrderInfo>\n" .
               "            <OrderId>{$orderId}</OrderId>\n" .
               "            <OrderAmount>{$orderAmount}</OrderAmount>\n" .
               "            <Currency>{$currency}</Currency>\n" .
               "        </OrderInfo>\n" .
               "    </CaptureRequest>";
    }

    /**
     * Build Refund request content.
     *
     * @param string $orderId
     * @param float $amount
     * @param string $currency
     * @return string
     */
    private function buildRefundRequestContent(string $orderId, float $amount, string $currency): string
    {
        $mid = $this->merchantId;
        $orderAmount = $this->formatAmount($amount);
        
        return "    <RefundRequest>\n" .
               "        <Authentication>\n" .
               "            <Mid>{$mid}</Mid>\n" .
               "        </Authentication>\n" .
               "        <OrderInfo>\n" .
               "            <OrderId>{$orderId}</OrderId>\n" .
               "            <OrderAmount>{$orderAmount}</OrderAmount>\n" .
               "            <Currency>{$currency}</Currency>\n" .
               "        </OrderInfo>\n" .
               "    </RefundRequest>";
    }

    /**
     * Build Cancel request content.
     *
     * @param string $orderId
     * @param float $amount
     * @param string $currency
     * @return string
     */
    private function buildCancelRequestContent(string $orderId, float $amount, string $currency): string
    {
        $mid = $this->merchantId;
        $orderAmount = $this->formatAmount($amount);
        
        return "    <CancelRequest>\n" .
               "        <Authentication>\n" .
               "            <Mid>{$mid}</Mid>\n" .
               "        </Authentication>\n" .
               "        <OrderInfo>\n" .
               "            <OrderId>{$orderId}</OrderId>\n" .
               "            <OrderAmount>{$orderAmount}</OrderAmount>\n" .
               "            <Currency>{$currency}</Currency>\n" .
               "        </OrderInfo>\n" .
               "    </CancelRequest>";
    }

    /**
     * Build Status request content.
     *
     * @param string $orderId
     * @return string
     */
    private function buildStatusRequestContent(string $orderId): string
    {
        $mid = $this->merchantId;
        
        return "    <StatusRequest>\n" .
               "        <Authentication>\n" .
               "            <Mid>{$mid}</Mid>\n" .
               "        </Authentication>\n" .
               "        <TransactionInfo>\n" .
               "            <OrderId>{$orderId}</OrderId>\n" .
               "        </TransactionInfo>\n" .
               "    </StatusRequest>";
    }

    /**
     * Build the complete XML request with Message wrapper and Digest.
     *
     * @param string $messageId
     * @param string $timestamp
     * @param string $requestContent
     * @return string
     */
    private function buildXmlRequest(string $messageId, string $timestamp, string $requestContent): string
    {
        // Assign constants to variables for heredoc interpolation
        $xmlNamespace = self::XML_NAMESPACE;
        $xmlNamespaceNs2 = self::XML_NAMESPACE_NS2;
        $apiVersion = self::API_VERSION;

        // Build the Message element for digest calculation
        // Attribute order MUST be: xmlns, xmlns:ns2, messageId, timeStamp, version (per Cardlink docs)
        // Whitespace inside is preserved as shown in Cardlink's canonicalized examples
        $messageXml = '<Message xmlns="' . $xmlNamespace . '" xmlns:ns2="' . $xmlNamespaceNs2 . '" messageId="' . $messageId . '" timeStamp="' . $timestamp . '" version="' . $apiVersion . '">';
        $messageXml .= "\n" . $requestContent . "\n";
        $messageXml .= '</Message>';

        // Calculate digest from canonicalized message
        $digest = $this->calculateDigest($messageXml);

        // Build the complete VPOS request
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<VPOS xmlns="' . $xmlNamespace . '" xmlns:ns2="' . $xmlNamespaceNs2 . '">' . "\n";
        $xml .= '<Message messageId="' . $messageId . '" timeStamp="' . $timestamp . '" version="' . $apiVersion . '">' . "\n";
        $xml .= $requestContent . "\n";
        $xml .= '</Message>' . "\n";
        $xml .= '<Digest>' . $digest . '</Digest>' . "\n";
        $xml .= '</VPOS>';

        return $xml;
    }

    /**
     * Calculate the digest for the message.
     * The digest is calculated as: base64(SHA256(canonicalizedMessage + sharedSecret))
     *
     * @param string $canonicalizedMessage
     * @return string
     */
    private function calculateDigest(string $messageXml): string
    {
        // Canonicalize the XML
        $canonicalized = $this->canonicalizeXml($messageXml);
        
        $this->log('=== DIGEST CALCULATION ===');
        $this->log('Original Message XML:');
        $this->log($messageXml);
        $this->log('Canonicalized Message:');
        $this->log($canonicalized);
        $this->log('Shared Secret (masked): ' . substr($this->sharedSecret, 0, 4) . '****');
        
        $dataToHash = $canonicalized . $this->sharedSecret;
        $digest = base64_encode(hash('sha256', $dataToHash, true));
        
        $this->log('Digest: ' . $digest);
        $this->log('=== END DIGEST CALCULATION ===');
        
        return $digest;
    }

    /**
     * Canonicalize XML for digest calculation.
     * Removes extra whitespace and normalizes the XML structure.
     *
     * According to Cardlink documentation, the digest is calculated on the canonicalized
     * Message element. This implementation normalizes whitespace between tags.
     *
     * @param string $xml
     * @return string
     */
    private function canonicalizeXml(string $xml): string
    {
        // Cardlink canonicalization: just return the XML as-is (whitespace preserved)
        // The only transformation is removing XML declaration if present
        $xml = preg_replace('/<\?xml[^?]*\?>\s*/i', '', $xml);
        return trim($xml);
    }

    /**
     * String-based XML canonicalization.
     * Removes XML declaration only, preserves all whitespace.
     *
     * @param string $xml
     * @return string
     */
    private function canonicalizeXmlString(string $xml): string
    {
        // Remove XML declaration if present
        $xml = preg_replace('/<\?xml[^?]*\?>\s*/i', '', $xml);
        
        // Trim leading/trailing whitespace only
        return trim($xml);
    }

    /**
     * Send the XML request to the API.
     *
     * @param string $xml The XML request
     * @param string $expectedResponseType The expected response element name
     * @return CardlinkXmlResponse
     */
    private function sendRequest(string $xml, string $expectedResponseType): CardlinkXmlResponse
    {
        $url = $this->getApiUrl();

        $this->lastRequest = [
            'url' => $url,
            'xml' => $xml,
            'timestamp' => date('Y-m-d H:i:s'),
            'expectedResponseType' => $expectedResponseType
        ];

        $this->log('=== CARDLINK XML API REQUEST ===' );
        $this->log('URL: ' . $url);
        $this->log('Expected Response Type: ' . $expectedResponseType);
        $this->log('Request XML:');
        $this->log($xml);
        $this->log('=== END REQUEST ===');

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/xml; charset=UTF-8',
                'Accept: application/xml',
                'Content-Length: ' . strlen($xml)
            ]
        ]);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        $this->lastResponse = [
            'http_code' => $httpCode,
            'body' => $responseBody,
            'curl_error' => $curlError,
            'curl_errno' => $curlErrno,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->log('=== CARDLINK XML API RESPONSE ===');
        $this->log('HTTP Code: ' . $httpCode);
        $this->log('cURL Error Code: ' . $curlErrno);
        $this->log('cURL Error: ' . ($curlError ?: 'none'));
        $this->log('Response Body:');
        $this->log($responseBody ?: '(empty)');
        $this->log('=== END RESPONSE ===');

        if ($curlErrno !== 0) {
            $this->log('cURL Error: ' . $curlError);
            return new CardlinkXmlResponse(false, [
                'error' => 'cURL Error: ' . $curlError,
                'curl_errno' => $curlErrno,
                'http_code' => $httpCode
            ]);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return new CardlinkXmlResponse(false, [
                'error' => 'HTTP Error',
                'http_code' => $httpCode,
                'response_body' => $responseBody
            ]);
        }

        return $this->parseResponse($responseBody, $expectedResponseType);
    }

    /**
     * Parse the XML response.
     *
     * @param string $responseBody
     * @param string $expectedResponseType
     * @return CardlinkXmlResponse
     */
    private function parseResponse(string $responseBody, string $expectedResponseType): CardlinkXmlResponse
    {
        if (empty($responseBody)) {
            return new CardlinkXmlResponse(false, ['error' => 'Empty response body']);
        }

        $previousErrorState = libxml_use_internal_errors(true);
        
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($responseBody);

        if (!$loaded) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorState);
            
            return new CardlinkXmlResponse(false, [
                'error' => 'Failed to parse XML response',
                'xml_errors' => array_map(function($e) { return $e->message; }, $errors),
                'response_body' => $responseBody
            ]);
        }

        libxml_use_internal_errors($previousErrorState);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('v', self::XML_NAMESPACE);

        // Extract response data
        $data = [];

        // Check for the expected response type
        $responseNodes = $xpath->query('//v:' . $expectedResponseType);
        
        if ($responseNodes->length === 0) {
            // Try without namespace
            $responseNodes = $xpath->query('//' . $expectedResponseType);
        }

        // Handle StatusResponse which has TransactionDetails wrapper
        if ($expectedResponseType === 'StatusResponse') {
            $detailsNodes = $xpath->query('//v:TransactionDetails | //TransactionDetails');
            if ($detailsNodes->length > 0) {
                $responseNodes = $detailsNodes;
            }
        }

        if ($responseNodes->length > 0) {
            $responseNode = $responseNodes->item(0);
            $data = $this->extractNodeData($responseNode, $xpath);
        }

        // Check for ErrorMessage if no response data was extracted.
        if (empty($data)) {
            $errorNodes = $xpath->query('//v:ErrorMessage | //ErrorMessage');
            if ($errorNodes->length > 0) {
                $data = $this->extractNodeData($errorNodes->item(0), $xpath);
            }
        }

        // Validate response digest
        $digestNodes = $xpath->query('//v:Digest | //Digest');
        if ($digestNodes->length > 0) {
            $data['digest'] = $digestNodes->item(0)->nodeValue;
        }

        $this->log('=== PARSED RESPONSE DATA ===');
        $this->log('Parsed data: ' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->log('=== END PARSED DATA ===');

        // Determine success based on status
        $status = $data['Status'] ?? $data['status'] ?? '';
        $this->log('Extracted Status: "' . $status . '"');
        
        $isSuccess = in_array(strtoupper($status), [
            self::STATUS_CAPTURED,
            self::STATUS_AUTHORIZED,
            'CAPTUREDPARTIALLY',
            'APPROVED',
            'SUCCESS',
            'REFUNDED',
            'REFUNDEDPARTIALLY',
            'PARTIALLY_REFUNDED',
            'CANCELED',
            'REVERSED'
        ], true);

        $this->log('Is Success (based on status): ' . ($isSuccess ? 'YES' : 'NO'));

        // Check for error responses
        if (isset($data['ErrorCode']) || isset($data['errorCode'])) {
            $isSuccess = false;
            $this->log('Error detected - ErrorCode present: ' . ($data['ErrorCode'] ?? $data['errorCode']));
        }

        $this->log('Final isSuccess: ' . ($isSuccess ? 'YES' : 'NO'));

        return new CardlinkXmlResponse($isSuccess, $data);
    }

    /**
     * Extract data from XML node recursively.
     *
     * @param \DOMNode $node
     * @param \DOMXPath $xpath
     * @return array
     */
    private function extractNodeData(\DOMNode $node, \DOMXPath $xpath): array
    {
        $data = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $nodeName = $child->localName;

            // Handle Attribute elements specially
            if ($nodeName === 'Attribute') {
                $attrName = $child->getAttribute('name');
                $data['attributes'][$attrName] = $child->nodeValue;
                continue;
            }

            // Check if node has children elements
            $hasChildElements = false;
            foreach ($child->childNodes as $grandChild) {
                if ($grandChild->nodeType === XML_ELEMENT_NODE) {
                    $hasChildElements = true;
                    break;
                }
            }

            if ($hasChildElements) {
                $data[$nodeName] = $this->extractNodeData($child, $xpath);
            } else {
                $data[$nodeName] = $child->nodeValue;
            }
        }

        return $data;
    }

    /**
     * Generate a unique message ID.
     *
     * @return string
     */
    private function generateMessageId(): string
    {
        return 'M' . (int)(microtime(true) * 1000);
    }

    /**
     * Generate timestamp in ISO 8601 format.
     *
     * @return string
     */
    private function generateTimestamp(): string
    {
        $dt = new \DateTime('now', new \DateTimeZone('Europe/Athens'));
        return $dt->format('Y-m-d\TH:i:s.vP');
    }

    /**
     * Format amount for API (decimal with two decimal places).
     *
     * @param float $amount
     * @return string
     */
    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Log debug message.
     *
     * @param string $message
     */
    private function log(string $message): void
    {
        if (!$this->debug) {
            return;
        }

        if ($this->debugLogger !== null) {
            call_user_func($this->debugLogger, $message);
        } else {
            error_log('[CardlinkXmlApi] ' . $message);
        }
    }

    /**
     * Get last request data for debugging.
     *
     * @return array
     */
    public function getLastRequest(): array
    {
        return $this->lastRequest;
    }

    /**
     * Get last response data for debugging.
     *
     * @return array
     */
    public function getLastResponse(): array
    {
        return $this->lastResponse;
    }
}

/**
 * Response object for Cardlink XML API calls.
 */
class CardlinkXmlResponse
{
    /**
     * @var bool Whether the request was successful
     */
    private $success;

    /**
     * @var array Response data
     */
    private $data;

    /**
     * Constructor.
     *
     * @param bool $success
     * @param array $data
     */
    public function __construct(bool $success, array $data)
    {
        $this->success = $success;
        $this->data = $data;
    }

    /**
     * Check if the request was successful.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get all response data.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get a specific field from the response.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get the transaction status.
     *
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->data['Status'] ?? $this->data['status'] ?? null;
    }

    /**
     * Get the transaction ID.
     *
     * @return string|null
     */
    public function getTransactionId(): ?string
    {
        return $this->data['TxId'] ?? $this->data['txId'] ?? null;
    }

    /**
     * Get the order ID.
     *
     * @return string|null
     */
    public function getOrderId(): ?string
    {
        return $this->data['OrderId'] ?? $this->data['orderId'] ?? null;
    }

    /**
     * Get the payment reference.
     *
     * @return string|null
     */
    public function getPaymentRef(): ?string
    {
        return $this->data['PaymentRef'] ?? $this->data['paymentRef'] ?? null;
    }

    /**
     * Get the order amount.
     *
     * @return float|null
     */
    public function getOrderAmount(): ?float
    {
        $amount = $this->data['OrderAmount'] ?? $this->data['orderAmount'] ?? null;
        return $amount !== null ? (float)$amount : null;
    }

    /**
     * Get the payment total.
     *
     * @return float|null
     */
    public function getPaymentTotal(): ?float
    {
        $total = $this->data['PaymentTotal'] ?? $this->data['paymentTotal'] ?? null;
        return $total !== null ? (float)$total : null;
    }

    /**
     * Get the currency.
     *
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        return $this->data['Currency'] ?? $this->data['currency'] ?? null;
    }

    /**
     * Get the description/message.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->data['Description'] ?? $this->data['description'] ?? null;
    }

    /**
     * Get the error message if failed.
     *
     * @return string|null
     */
    public function getError(): ?string
    {
        if ($this->success) {
            return null;
        }

        return $this->data['error'] 
            ?? $this->data['ErrorMessage'] 
            ?? $this->data['Description'] 
            ?? 'Unknown error';
    }

    /**
     * Get additional attributes from the response.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->data['attributes'] ?? [];
    }

    /**
     * Get a specific attribute.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getAttribute(string $name, $default = null)
    {
        return $this->data['attributes'][$name] ?? $default;
    }

    /**
     * Get the settlement status.
     *
     * Settlement statuses:
     * - 0: Not settled (use void/cancel)
     * - 10: In settlement transit (wait and retry later)
     * - 20+: Settled (use refund)
     *
     * @return int|null
     */
    public function getSettlementStatus(): ?int
    {
        $settlStatus = $this->data['attributes']['SettlStatus'] 
            ?? $this->data['SettlStatus'] 
            ?? $this->data['settlstatus']
            ?? null;
        
        return $settlStatus !== null ? (int)$settlStatus : null;
    }

    /**
     * Check if the transaction is settled.
     *
     * @return bool
     */
    public function isSettled(): bool
    {
        $settlStatus = $this->getSettlementStatus();
        return $settlStatus !== null && $settlStatus >= 20;
    }

    /**
     * Check if the transaction is in settlement transit.
     *
     * @return bool
     */
    public function isInSettlementTransit(): bool
    {
        return $this->getSettlementStatus() === 10;
    }

    /**
     * Check if the transaction can be voided (not settled yet).
     *
     * @return bool
     */
    public function canVoid(): bool
    {
        return $this->getSettlementStatus() === 0;
    }
}
