<?php
require_once __DIR__ . '/../src/BidHandler.php';

class RTBTester
{
    private $testCases = [];
    private $campaigns;
    private $results = [];

    public function __construct()
    {
        $this->loadCampaigns();
        $this->loadTestCases();
    }

    private function loadCampaigns()
    {
        $campaignsJson = file_get_contents(__DIR__ . '/campaigns.json');
        $this->campaigns = json_decode($campaignsJson, true);
    }


    private function loadTestCases()
    {
        $testCaseDir = __DIR__ . '/';
        $files = glob($testCaseDir . 'test-request-*.json');
        foreach ($files as $file) {
            $this->testCases[basename($file)] = json_decode(file_get_contents($file), true);
        }
    }

    public function runTests()
    {
        foreach ($this->testCases as $testName => $testCase) {
            echo "\nRunning test: $testName\n";
            echo "----------------------------------------\n";

            try {
                $handler = new BidHandler(json_encode($testCase), $this->campaigns);
                $response = $handler->processBidRequest();

                if ($response) {
                    echo "✅ Bid generated:\n";
                    echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
                } else {
                    echo "❌ No bid. Errors:\n";
                    echo implode("\n", $handler->getErrors()) . "\n";
                }

                $this->results[$testName] = [
                    'success' => $response !== null,
                    'response' => $response,
                    'errors' => $handler->getErrors()
                ];
            } catch (Exception $e) {
                echo "🚫 Exception: " . $e->getMessage() . "\n";
                $this->results[$testName] = [
                    'success' => false,
                    'exception' => $e->getMessage()
                ];
            }
        }
    }

    public function generateReport()
    {
        echo "\n=== Test Results Summary ===\n";
        foreach ($this->results as $testName => $result) {
            echo "\n$testName: " . ($result['success'] ? '✅ PASS' : '❌ FAIL') . "\n";
        }
    }
}

// Run tests
$tester = new RTBTester();
$tester->runTests();
$tester->generateReport();