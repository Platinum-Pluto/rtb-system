<?php
class BidHandler
{
    private $bidRequest;
    private $campaigns;
    private $errors = [];

    private $countryMapping = [
        'BGD' => 'Bangladesh',
        'USA' => 'United States',
        'GB' => 'United Kingdom'
    ];

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

        return true;
    }

    private function isDimensionCompatible(array $campaign): bool
    {
        $banner = $this->bidRequest['imp'][0]['banner'];
        list($campaignWidth, $campaignHeight) = array_map('intval', explode('x', $campaign['dimension']));


        if ($banner['w'] == $campaignWidth && $banner['h'] == $campaignHeight) {
            return true;
        }

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

    private function isCountryMatch($requestCountry, $campaignCountry): bool
    {
        $normalizedRequestCountry = $this->countryMapping[$requestCountry] ?? $requestCountry;
        $normalizedCampaignCountry = $this->countryMapping[$campaignCountry] ?? $campaignCountry;
        return strtoupper($normalizedRequestCountry) === strtoupper($normalizedCampaignCountry);
    }

    private function isEligibleCampaign(array $campaign): bool
    {
        $bidFloor = $this->bidRequest['imp'][0]['bidfloor'];
        if ($campaign['price'] < $bidFloor) {
            $this->errors[] = sprintf('Campaign price (%.2f) below bid floor (%.2f)', $campaign['price'], $bidFloor);
            return false;
        }

        if (!$this->isDimensionCompatible($campaign)) {
            return false;
        }

        $requestOS = strtolower($this->bidRequest['device']['os']);
        $supportedOS = array_map('trim', array_map('strtolower', explode(',', $campaign['hs_os'])));
        if (!in_array($requestOS, $supportedOS)) {
            $this->errors[] = 'OS not compatible';
            return false;
        }

        if (!empty($campaign['country'])) {
            $requestCountry = $this->bidRequest['device']['geo']['country'] ?? '';
            if (!$this->isCountryMatch($requestCountry, $campaign['country'])) {
                $this->errors[] = 'Geographic targeting mismatch';
                return false;
            }
        }

        return true;
    }

    private function findEligibleCampaigns(): array
    {
        $eligibleCampaigns = [];
        foreach ($this->campaigns as $campaign) {
            if ($this->isEligibleCampaign($campaign)) {
                $eligibleCampaigns[] = $campaign;
            }
        }

        usort($eligibleCampaigns, function ($a, $b) {
            return $b['price'] <=> $a['price'];
        });

        return $eligibleCampaigns;
    }

    public function processBidRequest(): ?array
    {
        if (!$this->validateBidRequest()) {
            return null;
        }

        $eligibleCampaigns = $this->findEligibleCampaigns();
        if (empty($eligibleCampaigns)) {
            $this->errors[] = 'No eligible campaigns found';
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