<?php

// добавил поддержку CORS, т.к. тестировал в Chrome

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }

    exit(0);
}

function exit_json($response, int $status = 200) {
    header('Content-Type: application/json; charset=utf8');
    echo json_encode($response);
    http_response_code($status);
    die();
}

$city = $_GET['city'] ?? '';

if (!$city) {
    exit_json(['error' => 'Город не указан'], 422);
}

function get_request(string $url, array $query, ?int &$status) {
    $url = $url . '?' . http_build_query($query);
    $context = stream_context_create(['http' => ['ignore_errors' => true]]);
    $response = @file_get_contents($url, false, $context);

    preg_match('/HTTP\/\d\.\d (\d+) /', $http_response_header[0], $matches);
    $status = (int)$matches[1];
    return $response;
}

$response = get_request('https://api.openweathermap.org/data/2.5/weather', [
    'appid' => 'ac8cb22384ab03715b24ecf3d83ea5e6', // понятно что в репозитории токены лучше не хранить, но как-то передать же надо
    'lang' => 'ru',
    'units' => 'metric',
    'mode' => 'xml', // через xml больше информации даёт
    'q' => $city,
], $status);

if ($status == 404) {
    exit_json(['error' => 'Город не найден'], 404);
}

if (!$response) {
    exit_json(['error' => 'Невозможно подключиться к сервису погоды'], 500);
}

$xml = new SimpleXMLElement($response);

if (!empty($xml->cod)) {
    exit_json(['error' => $xml->message ?? 'Неизвестная ошибка сервиса погоды'], (int)$xml->cod);
}

function round_int($value): int {
    return (int)round((float)$value);
}

$sun = $xml->city->sun->attributes();
$wind = $xml->wind;
$precipitation = $xml->precipitation->attributes();

exit_json([
    'sun' => [
        'rise' => (string)$sun->rise,
        'set' => (string)$sun->set,
    ],
    'temperature' => [
        'current' => round_int($xml->temperature->attributes()->value),
        'feels_like' => round_int($xml->feels_like->attributes()->value),
    ],
    'humidity' => (int)$xml->humidity->attributes()->value,
    'pressure' => (int)$xml->pressure->attributes()->value,
    'wind' => [
        'speed' => (int)$wind->speed->attributes()->value,
        'gusts' => (int)$wind->gusts->attributes()->value,
        'direction' => (string)$wind->direction->attributes()->name,
    ],
    'clouds' => (string)$xml->clouds->attributes()->name,
    'visibility' => (int)$xml->visibility->attributes()->value,
    'precipitation' => [
        'value' => round_int($precipitation->value),
        'mode' => (string)$precipitation->mode,
        'description' => (string)$xml->weather->attributes()->value,
    ],
]);
