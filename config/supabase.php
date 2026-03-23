<?php
define('SUPABASE_URL', 'https://inzdmudzlcgsevcwchyq.supabase.co');
define('SUPABASE_ANON_KEY', 'sb_publishable_Qdgj_ieHCw9LEqtAHpeXmQ_2mIaXi2E');

function supabaseRequest($path, $method = 'GET', $payload = null, $headers = []) {
    $url = rtrim(SUPABASE_URL, '/') . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    $baseHeaders = [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json',
    ];
    $allHeaders = array_merge($baseHeaders, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    switch (strtoupper($method)) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload ? json_encode($payload) : '{}');
            break;
        case 'PATCH':
        case 'PUT':
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            if ($payload) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
            break;
        default:
            curl_setopt($ch, CURLOPT_HTTPGET, true);
    }
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) {
        return ['error' => $err, 'status' => 0];
    }
    $data = json_decode($response, true);
    return ['status' => $httpcode, 'data' => $data ?? $response];
}
