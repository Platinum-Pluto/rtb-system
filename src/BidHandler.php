<?php
class BidHandler
{
    private $bidRequest;
    private $campaigns;
    private $errors = [];
    private $cache = [];

    public function __construct(string $bidRequestJson, array $campaigns)
    {
        $this->validateAndSetBidRequest($bidRequestJson);
        $this->campaigns = $campaigns;
    }


    private function validateAndSetBidRequest(string $bidRequestJson): void
    {
        $data = json_decode($bidRequestJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid bid request JSON: ' . json_last_error_msg());
        }
        $this->bidRequest = $this->sanitizeInput($data);
    }

    private function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    private function isDimensionCompatible(array $campaign): bool
    {
        $banner = $this->bidRequest['imp'][0]['banner'];
        list($campaignWidth, $campaignHeight) = array_map('intval', explode('x', $campaign['dimension']));

        // Check primary dimensions
        if ($banner['w'] == $campaignWidth && $banner['h'] == $campaignHeight) {
            return true;
        }

        // Check format array if available
        if (!empty($banner['format'])) {
            foreach ($banner['format'] as $format) {
                if ($format['w'] == $campaignWidth && $format['h'] == $campaignHeight) {
                    return true;
                }
            }
        }

        $this->errors[] = sprintf(
            'Dimension mismatch. Campaign: %dx%d, Request: %dx%d',
            $campaignWidth,
            $campaignHeight,
            $banner['w'],
            $banner['h']
        );
        return false;
    }



    private function validateBidRequest(): bool
    {
        error_log("Validating bid request...");

        if (empty($this->bidRequest['imp']) || empty($this->bidRequest['imp'][0]['banner'])) {
            $this->errors[] = 'Missing banner impression data';
            return false;
        }

        if (empty($this->bidRequest['device']) || empty($this->bidRequest['device']['os'])) {
            $this->errors[] = 'Missing device information';
            return false;
        }

        if (!isset($this->bidRequest['imp'][0]['bidfloor'])) {
            $this->errors[] = 'Missing bid floor';
            return false;
        }

        error_log("Bid request validation passed");
        return true;
    }

    private function isEligibleCampaign(array $campaign): bool
    {
        error_log("Checking campaign eligibility: " . $campaign['campaignname']);


        $bidFloor = $this->bidRequest['imp'][0]['bidfloor'];
        if ($campaign['price'] < $bidFloor) {
            error_log("Campaign price below bid floor");
            return false;
        }

        $requestBanner = $this->bidRequest['imp'][0]['banner'];
        $campaignDims = explode('x', $campaign['dimension']);
        $campaignWidth = (int) $campaignDims[0];
        $campaignHeight = (int) $campaignDims[1];

        if (!$this->isDimensionCompatible($campaign)) {
            return false;
        }

        if ($requestBanner['w'] == $campaignWidth && $requestBanner['h'] == $campaignHeight) {
            return true;
        }

        if (!empty($requestBanner['format'])) {
            foreach ($requestBanner['format'] as $format) {
                if ($format['w'] == $campaignWidth && $format['h'] == $campaignHeight) {
                    return true;
                }
            }
        }

        $requestOS = strtolower($this->bidRequest['device']['os']);
        $supportedOS = array_map('trim', array_map('strtolower', explode(',', $campaign['hs_os'])));
        if (!in_array($requestOS, $supportedOS)) {
            $this->errors[] = 'OS not compatible';
            return false;
        }

        if (!empty($campaign['country'])) {
            $requestCountry = $this->bidRequest['device']['geo']['country'] ?? '';
            if (strtoupper($requestCountry) !== strtoupper($campaign['country'])) {
                error_log("Country not matching");
                return false;
            }
        }

        if (!$this->isDimensionCompatible($campaign)) {
            $this->errors[] = 'Dimension mismatch';
            return false;
        }

        if (!empty($campaign['country'])) {
            $requestCountry = $this->bidRequest['device']['geo']['country'] ?? '';
            if (strtoupper($campaign['country']) !== strtoupper($requestCountry)) {
                $this->errors[] = 'Geographic targeting mismatch';
                return false;
            }
        }

        $requestOS = strtolower($this->bidRequest['device']['os']);
        $supportedOS = array_map('strtolower', explode(',', $campaign['hs_os']));
        if (!in_array($requestOS, $supportedOS)) {
            $this->errors[] = 'OS not compatible';
            return false;
        }
        error_log("Campaign is eligible");
        return true;
    }

    private function findEligibleCampaigns(): array
    {
        error_log("Finding eligible campaigns...");

        $eligibleCampaigns = [];
        foreach ($this->campaigns as $campaign) {
            if ($this->isEligibleCampaign($campaign)) {
                $eligibleCampaigns[] = $campaign;
            }
        }

        usort($eligibleCampaigns, function ($a, $b) {
            return $b['price'] <=> $a['price'];
        });

        error_log("Found " . count($eligibleCampaigns) . " eligible campaigns");
        return $eligibleCampaigns;
    }

    public function processBidRequest(): ?array
    {
        if (!$this->validateBidRequest()) {
            error_log("Bid request validation failed: " . implode(", ", $this->errors));
            return null;
        }

        $eligibleCampaigns = $this->findEligibleCampaigns();
        if (empty($eligibleCampaigns)) {
            $this->errors[] = 'No eligible campaigns found';
            error_log("No eligible campaigns found");
            return null;
        }

        $selectedCampaign = reset($eligibleCampaigns);
        return $this->generateBidResponse($selectedCampaign);
    }

    private function generateBidResponse(array $campaign): array
    {
        $imp = $this->bidRequest['imp'][0];
        list($width, $height) = explode('x', $campaign['dimension']);

        return [
            'id' => $this->bidRequest['id'],
            'bidid' => uniqid('bid_'),
            'seatbid' => [
                [
                    'bid' => [
                        [
                            'id' => uniqid(),
                            'impid' => $imp['id'],
                            'price' => $campaign['price'],
                            'adid' => $campaign['code'],
                            'nurl' => $campaign['url'],
                            'iurl' => $campaign['image_url'],
                            'cid' => $campaign['creative_id'],
                            'crid' => $campaign['creative_id'],
                            'adm' => null,
                            'adomain' => [parse_url($campaign['tld'], PHP_URL_HOST)],
                            'bundle' => $this->bidRequest['app']['bundle'] ?? '',
                            'campaignname' => $campaign['campaignname'],
                            'advertiser' => $campaign['advertiser'],
                            'creative_type' => $campaign['creative_type'],
                            'w' => (int) $width,
                            'h' => (int) $height,
                            'ext' => [
                                'billing_id' => $campaign['billing_id']
                            ]
                        ]
                    ]
                ]
            ],
            'cur' => 'USD'
        ];
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}