<?php
/**
 * WeRide Web Push VAPID Configuration & Sender
 * Direct, self-hosted Web Push implementation (RFC 8291 / RFC 8292)
 */

if (!defined('VAPID_PUBLIC_KEY')) {
    define('VAPID_PUBLIC_KEY', 'BKWkXjQLYw-ZyqjMlhMAEg0yI9CB56DTHjW7UciMG-VSnio36NYHF7z4wvSo4F4YENfEspH3_zfqk8zj_V8mjko');
    define('VAPID_PRIVATE_KEY', 'fwGIWDlPgt921_k_gPah0KKqYb7Sw5PUzr0QylU2B04');
    define('VAPID_PRIVATE_PEM', "-----BEGIN PRIVATE KEY-----\nMIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgfwGIWDlPgt921/k/\ngPah0KKqYb7Sw5PUzr0QylU2B06hRANCAASlpF40C2MPmcqozJYTABINMiPQgeeg\n0x41u1HIjBvlUp4qN+jWBxe8+ML0qOBeGBDXxLKR9/836pPM4/1fJo5K\n-----END PRIVATE KEY-----\n");
    define('VAPID_SUBJECT', 'mailto:admin@weride.space');
}

class WebPushSender {
    public static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function getOpenSSLConfig() {
        $cfg = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        if (file_exists('C:/xampp/php/extras/ssl/openssl.cnf')) {
            $cfg['config'] = 'C:/xampp/php/extras/ssl/openssl.cnf';
        }
        return $cfg;
    }

    public static function rawPubKeyToPem($rawPub) {
        $der = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00" . $rawPub;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    public static function derToRAndS($der) {
        $pos = 2;
        if (ord($der[1]) & 0x80) {
            $pos += (ord($der[1]) & 0x7f);
        }
        $pos++; // skip 0x02
        $rLen = ord($der[$pos++]);
        $r = substr($der, $pos, $rLen);
        $pos += $rLen;
        
        $pos++; // skip 0x02
        $sLen = ord($der[$pos++]);
        $s = substr($der, $pos, $sLen);
        
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);
        return $r . $s;
    }

    public static function createVapidJwt($endpointUrl) {
        $urlParts = parse_url($endpointUrl);
        $audience = $urlParts['scheme'] . '://' . $urlParts['host'];
        
        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = self::base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 86400,
            'sub' => VAPID_SUBJECT
        ]));
        
        $dataToSign = $header . '.' . $payload;
        $derSig = '';
        openssl_sign($dataToSign, $derSig, VAPID_PRIVATE_PEM, OPENSSL_ALGO_SHA256);
        $rawSig = self::derToRAndS($derSig);
        
        return $dataToSign . '.' . self::base64UrlEncode($rawSig);
    }

    public static function encryptPayload($payloadJson, $userPubKeyRaw, $userAuthRaw) {
        $cfg = self::getOpenSSLConfig();
        $serverEphemeral = openssl_pkey_new($cfg);
        if (!$serverEphemeral) {
            return false;
        }
        $details = openssl_pkey_get_details($serverEphemeral);
        $serverEphemeralPubRaw = "\x04" . $details['ec']['x'] . $details['ec']['y'];
        
        $userPubPem = self::rawPubKeyToPem($userPubKeyRaw);
        $sharedSecret = openssl_pkey_derive($userPubPem, $serverEphemeral, 32);
        if (!$sharedSecret) {
            return false;
        }

        // IKM derivation (RFC 8291 section 3.4)
        $keyInfo = "WebPush: info\0" . $userPubKeyRaw . $serverEphemeralPubRaw;
        $ikm = hash_hkdf('sha256', $sharedSecret, 32, $keyInfo, $userAuthRaw);
        
        // Salt (16 bytes)
        $salt = random_bytes(16);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        
        // CEK and Nonce
        $cek = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\0", '');
        $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\0", '');
        
        // Padding (\x02 delimiter)
        $padded = $payloadJson . "\x02";
        $tag = '';
        $ciphertext = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        
        // RFC 8188 Header
        $header = $salt . pack('N', 4096) . pack('C', 65) . $serverEphemeralPubRaw;
        return $header . $ciphertext . $tag;
    }

    /**
     * Send Web Push to a single subscription
     */
    public static function sendToSubscription($subscription, $title, $body, $url = './', $tag = null) {
        $endpoint = $subscription['endpoint'];
        $userPub = self::base64UrlDecode($subscription['p256dh']);
        $userAuth = self::base64UrlDecode($subscription['auth']);
        
        if (strlen($userPub) !== 65 || strlen($userAuth) !== 16) {
            return ['success' => false, 'error' => 'Invalid keys'];
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => './pic/logo_only.png',
            'badge' => './pic/logo_only.png',
            'tag' => $tag ?: ('weride-' . time()),
            'url' => $url,
            'timestamp' => time() * 1000
        ]);

        $encryptedBody = self::encryptPayload($payload, $userPub, $userAuth);
        if (!$encryptedBody) {
            return ['success' => false, 'error' => 'Encryption failed'];
        }

        $jwt = self::createVapidJwt($endpoint);

        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400',
            'Urgency: high',
            'Authorization: vapid t=' . $jwt . ', k=' . VAPID_PUBLIC_KEY,
            'Crypto-Key: p256ecdsa=' . VAPID_PUBLIC_KEY
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encryptedBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $isSuccess = ($statusCode >= 200 && $statusCode < 300);
        return [
            'success' => $isSuccess,
            'statusCode' => $statusCode,
            'response' => $response,
            'error' => $err
        ];
    }

    /**
     * Send Web Push to an employee ID, array of IDs, or 'all'
     */
    public static function sendToEmployee($conn, $empId, $title, $body, $url = './', $tag = null) {
        if (!$conn) return false;

        $whereClause = "";
        if ($empId === 'all') {
            $whereClause = "1=1";
        } elseif (is_array($empId)) {
            $escapedIds = array_map(function($id) use ($conn) {
                return "'" . mysqli_real_escape_string($conn, $id) . "'";
            }, $empId);
            $whereClause = "emp_id IN (" . implode(',', $escapedIds) . ")";
        } else {
            $escaped = mysqli_real_escape_string($conn, $empId);
            $whereClause = "emp_id = '$escaped'";
        }

        $res = mysqli_query($conn, "SELECT id, emp_id, endpoint, p256dh, auth FROM push_subscriptions WHERE $whereClause");
        if (!$res || mysqli_num_rows($res) === 0) {
            return 0; // No subscriptions found
        }

        $sentCount = 0;
        $deadIds = [];

        while ($sub = mysqli_fetch_assoc($res)) {
            $result = self::sendToSubscription($sub, $title, $body, $url, $tag);
            if ($result['success']) {
                $sentCount++;
            } elseif (in_array($result['statusCode'], [404, 410])) {
                // Subscription is expired or uninstalled, prune it
                $deadIds[] = (int)$sub['id'];
            }
        }

        if (!empty($deadIds)) {
            $idList = implode(',', $deadIds);
            mysqli_query($conn, "DELETE FROM push_subscriptions WHERE id IN ($idList)");
        }

        return $sentCount;
    }
}
