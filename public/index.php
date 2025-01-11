<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../src/BidHandler.php';

try {

    $bidRequestJson = file_get_contents('php://input');
    file_put_contents(__DIR__ . '/../logs/debug.log', "Received request: " . $bidRequestJson . "\n", FILE_APPEND);
    $campaignsJson = file_get_contents(__DIR__ . '/../tests/campaigns.json');
    $campaigns = json_decode($campaignsJson, true);

    if (!$campaigns) {
        throw new Exception("Failed to load campaigns: " . json_last_error_msg());
    }

    file_put_contents(__DIR__ . '/../logs/debug.log', "Loaded campaigns: " . print_r($campaigns, true) . "\n", FILE_APPEND);

    $handler = new BidHandler($bidRequestJson, $campaigns);
    $response = $handler->processBidRequest();

    if ($response) {
        header('Content-Type: application/json');
        echo json_encode($response, JSON_PRETTY_PRINT);
        file_put_contents(__DIR__ . '/../logs/debug.log', "Sent response: " . json_encode($response) . "\n", FILE_APPEND);
    } else {
        $errors = $handler->getErrors();
        file_put_contents(__DIR__ . '/../logs/debug.log', "No bid. Errors: " . print_r($errors, true) . "\n", FILE_APPEND);
        http_response_code(204);
    }
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/../logs/debug.log', "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}