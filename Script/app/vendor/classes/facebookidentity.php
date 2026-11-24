<?php
final class FacebookIdentity {
    private static function request($endpoint, $parameters, $token = null) {
        $url = 'https://graph.facebook.com/'.$endpoint.'?'.http_build_query($parameters);
        $headers = $token === null ? [] : ['Authorization: Bearer '.$token];
        $context = stream_context_create(['http' => [
            'header' => $headers, 'timeout' => 5, 'follow_location' => 0,
        ]]);
        $response = @file_get_contents($url, false, $context, 0, 65536);
        return $response === false ? [] : (json_decode($response, true) ?: []);
    }

    public static function exchange($code, $redirect) {
        $response = self::request('oauth/access_token', [
            'client_id' => config('facebook-key'), 'client_secret' => config('facebook-secret'),
            'redirect_uri' => $redirect, 'code' => $code,
        ]);
        return $response['access_token'] ?? '';
    }

    public static function verify($token) {
        $app = config('facebook-key');
        $secret = config('facebook-secret');
        if (!$app || !$secret || !is_string($token) || !$token || preg_match('/[[:cntrl:]]/', $token)) return false;
        $details = self::request('debug_token', ['input_token' => $token], $app.'|'.$secret)['data'] ?? [];
        if (empty($details['is_valid']) || empty($details['user_id']) || (string)($details['app_id'] ?? '') !== (string)$app) return false;
        $profile = self::request('me', ['fields' => 'id,name,email', 'appsecret_proof' => hash_hmac('sha256', $token, $secret)], $token);
        if ((string)($profile['id'] ?? '') !== (string)$details['user_id']) return false;
        return $profile;
    }
}
