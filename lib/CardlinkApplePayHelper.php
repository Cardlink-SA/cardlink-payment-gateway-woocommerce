<?php
/**
 * Cardlink Apple Pay Direct Helper
 * Provides configuration accessors, URL builders, XID calculation, MPI signing,
 * and transaction info management for Apple Pay Direct payments.
 */
class CardlinkApplePayHelper
{
    const CACHE_TTL = 1800; // 30 minutes

    /** In-memory transaction map: XID => info array */
    private static $transactionMap = [];

    /** OpenCart Registry instance, set by the controller */
    private static $registry = null;

    public static function setRegistry($registry)
    {
        self::$registry = $registry;
    }

    // =========================================================================
    //  CONFIGURATION ACCESSORS
    // =========================================================================

    public static function getMerchantId()
    {
        return self::getConfig('payment_cardlink_applepay_merchantid');
    }

    public static function getSharedSecret()
    {
        return self::getConfig('payment_cardlink_applepay_sharedSecret');
    }

    public static function getBusinessPartner()
    {
        return 'worldline';
    }

    public static function getTransactionEnvironment()
    {
        return self::getConfig('payment_cardlink_applepay_mode') ?: 'sandbox';
    }

    // =========================================================================
    //  URL BUILDERS
    // =========================================================================

    public static function getVposDomain()
    {
        $isProduction = (self::getTransactionEnvironment() === 'production');
        return $isProduction
            ? 'https://vpos.eurocommerce.gr'
            : 'https://eurocommerce-test.cardlink.gr';
    }

    public static function getDirectScriptUrl()
    {
        return self::getVposDomain() . '/vpos/js/applepaydirect.js';
    }

    public static function getMpiUrl()
    {
        return self::getVposDomain() . '/mdpaympi/MerchantServer';
    }

    // =========================================================================
    //  SCRIPT AUTHENTICATION
    // =========================================================================

    /**
     * Build query string for loading applepaydirect.js.
     * digest = base64(sha256(version + mid + timestamp + sharedSecret, raw=true))
     * Timestamp in Europe/Athens timezone, format YYYYMMDDHHmm.
     */
    public static function getScriptInitData()
    {
        $mid          = self::getMerchantId();
        $sharedSecret = self::getSharedSecret();
        $vposVersion  = '2';

        $athensTime = new DateTime('now', new DateTimeZone('Europe/Athens'));
        $timestamp  = $athensTime->format('YmdHi');
        $hashData   = $vposVersion . $mid . $timestamp . $sharedSecret;
        $hash       = base64_encode(hash('sha256', $hashData, true));

        $queryString = '?' . http_build_query([
            'version' => $vposVersion,
            'mid'     => $mid,
            'date'    => $timestamp,
            'digest'  => $hash,
        ]);

        return [
            'mid'         => $mid,
            'queryString' => $queryString,
            'vposVersion' => $vposVersion,
        ];
    }

    // =========================================================================
    //  XID CALCULATION (3D-Secure Transaction Identifier)
    // =========================================================================

    public static function calculateXID($txId, $trExtId, $trMpiCounts)
    {
        $raw = 'VPOS'
            . self::padCutLeft($txId, 7)
            . '-'
            . self::padCutLeft($trExtId, 6)
            . self::padCutLeft($trMpiCounts, 2);

        return base64_encode($raw);
    }

    private static function padCutLeft($str, $length)
    {
        $len = strlen($str);
        if ($len > $length) {
            return substr($str, -$length);
        }
        if ($len < $length) {
            return str_pad($str, $length, '0', STR_PAD_LEFT);
        }
        return $str;
    }

    // =========================================================================
    //  MPI SIGNING (3D-Secure form data)
    // =========================================================================

    /**
     * Sign MPI form data using HMAC-SHA1 with shared secret (v2.0).
     */
    public static function signMpiData(array $data)
    {
        $purchaseAmountFormatted = self::formatPurchaseAmount(
            $data['purchAmount'] ?? '0',
            (int)($data['exponent'] ?? 2)
        );

        $mpiVersion = '2.0';
        $signature  = self::signWithHmac($data, $purchaseAmountFormatted, self::getSharedSecret(), $mpiVersion);

        return [
            'signature'               => $signature,
            'purchaseAmountFormatted' => $purchaseAmountFormatted,
            'mpiVersion'              => $mpiVersion,
        ];
    }

    private static function formatPurchaseAmount($amount, $exponent)
    {
        $numericAmount = (float)$amount;
        $multiplier    = pow(10, $exponent);
        $intAmount     = (int)round($numericAmount * $multiplier);
        return (string)$intAmount;
    }

    private static function buildSignPayload(array $data, $formattedAmount, $mpiVersion = null)
    {
        $fields = [
            $mpiVersion ?? $data['mpiVersion'] ?? '',
            $data['pan'] ?? '',
            $data['expiry'] ?? '',
            $data['cardEncData'] ?? '',
            $data['devCat'] ?? '0',
            $formattedAmount,
            $data['exponent'] ?? '2',
            $data['description'] ?? '',
            $data['currMpi'] ?? '978',
            $data['merchantID'] ?? '',
            $data['xidb64'] ?? '',
            $data['okUrl'] ?? '',
            $data['failUrl'] ?? '',
        ];

        if (!empty($data['recurFreq'])) {
            $fields[] = $data['recurFreq'];
        }
        if (!empty($data['recurEnd'])) {
            $fields[] = $data['recurEnd'];
        }
        if (!empty($data['installments'])) {
            $fields[] = $data['installments'];
        }

        return implode('', $fields);
    }

    private static function signWithRsa(array $data, $formattedAmount, $privateKeyPem, $mpiVersion)
    {
        $payload    = self::buildSignPayload($data, $formattedAmount, $mpiVersion);
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            return 'Error: Invalid private key - ' . openssl_error_string();
        }
        $signatureBytes = '';
        $success = openssl_sign($payload, $signatureBytes, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$success) {
            return 'Error: Signing failed - ' . openssl_error_string();
        }
        return base64_encode($signatureBytes);
    }

    private static function signWithHmac(array $data, $formattedAmount, $sharedSecret, $mpiVersion)
    {
        $payload = self::buildSignPayload($data, $formattedAmount, $mpiVersion);
        return base64_encode(hash('sha1', $payload . $sharedSecret, true));
    }

    // =========================================================================
    //  TRANSACTION INFO MANAGEMENT (session + file cache)
    // =========================================================================

    public static function storeTransactionInfo($xid, array $info)
    {
        self::$transactionMap[$xid] = $info;
    }

    public static function getTransactionInfo($xid)
    {
        return self::$transactionMap[$xid] ?? null;
    }

    public static function removeTransactionInfo($xid)
    {
        unset(self::$transactionMap[$xid]);
    }

    public static function persistToSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['cardlink_apay_transaction_map'] = self::$transactionMap;
    }

    public static function restoreFromSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (isset($_SESSION['cardlink_apay_transaction_map']) && is_array($_SESSION['cardlink_apay_transaction_map'])) {
            self::$transactionMap = $_SESSION['cardlink_apay_transaction_map'];
        }
    }

    public static function persistToCache()
    {
        foreach (self::$transactionMap as $xid => $info) {
            $info['_cache_time'] = time();
            $filePath = self::getCacheFilePath($xid);
            if ($filePath) {
                @file_put_contents($filePath, json_encode($info), LOCK_EX);
            }
        }
    }

    public static function restoreFromCache($xid)
    {
        $filePath = self::getCacheFilePath($xid);
        if (!$filePath || !file_exists($filePath)) {
            return null;
        }

        $raw = @file_get_contents($filePath);
        if ($raw === false) {
            return null;
        }

        $info = json_decode($raw, true);
        if (!is_array($info)) {
            return null;
        }

        $cacheTime = $info['_cache_time'] ?? 0;
        if ((time() - $cacheTime) > self::CACHE_TTL) {
            @unlink($filePath);
            return null;
        }

        unset($info['_cache_time']);
        self::$transactionMap[$xid] = $info;
        return $info;
    }

    public static function removeFromCache($xid)
    {
        $filePath = self::getCacheFilePath($xid);
        if ($filePath && file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    private static function getCacheFilePath($xid)
    {
        $cacheDir = defined('DIR_CACHE') ? DIR_CACHE . 'cardlink_apay/' : sys_get_temp_dir() . '/cardlink_apay/';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        if (!is_dir($cacheDir)) {
            return null;
        }
        return $cacheDir . md5($xid) . '.json';
    }

    // =========================================================================
    //  CURRENCY HELPERS
    // =========================================================================

    public static function getCurrencyNumericCode($isoCode)
    {
        $map = [
            'EUR' => '978', 'USD' => '840', 'GBP' => '826', 'CHF' => '756',
            'SEK' => '752', 'NOK' => '578', 'DKK' => '208', 'PLN' => '985',
            'CZK' => '203', 'HUF' => '348', 'RON' => '946', 'BGN' => '975',
            'HRK' => '191', 'TRY' => '949',
        ];
        return $map[strtoupper($isoCode)] ?? '978';
    }

    // =========================================================================
    //  INTERNAL
    // =========================================================================

    private static function getConfig($key)
    {
        if (self::$registry) {
            $config = self::$registry->get('config');
            if ($config) {
                return $config->get($key) ?: '';
            }
        }
        return '';
    }
}