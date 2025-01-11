<?php
require_once __DIR__ . '/../src/BidHandler.php';

class RTBTester
{
    private $testCases = [];
    private $campaigns;

    public function __construct()
    {
        $this->loadCampaigns();
        $this->loadTestCases();
    }

    private function loadCampaigns()
    {
        $campaignsJson = file_get_contents(__DIR__ . '/campaigns.json');
        $this->campaigns = json_decode($campaignsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error loading campaigns: " . json_last_error_msg());
        }
    }

    private function loadTestCases()
    {
        $testCaseDir = __DIR__ . '/test-cases/';
        $files = glob($testCaseDir . 'test-request-*.json');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->testCases[basename($file)] = json_decode($content, true);
        }
    }

    public function runTests()
    {
        $results = [
            'passed' => 0,
            'failed' => 0,
            'total' => count($this->testCases)
        ];

        foreach ($this->testCases as $testName => $testCase) {
            echo "\nRunning test: $testName\n";
            echo str_repeat("-", 40) . "\n";

            try {
                $handler = new BidHandler(json_encode($testCase), $this->campaigns);
                $response = $handler->processBidRequest();

                if ($response) {
                    echo "✅ Bid generated\n";
                    echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
                    $results['passed']++;
                } else {
                    echo "❌ No bid. Errors:\n";
                    echo implode("\n", $handler->getErrors()) . "\n";
                    $results['failed']++;
                }
            } catch (Exception $e) {
                echo "🚫 Exception: " . $e->getMessage() . "\n";
                $results['failed']++;
            }
        }

        return $results;
    }
}


$tester = new RTBTester();
$results = $tester->runTests();

echo "\n=== Test Results Summary ===\n";
echo "Total Tests: " . $results['total'] . "\n";
echo "Passed: " . $results['passed'] . "\n";
echo "Failed: " . $results['failed'] . "\n";