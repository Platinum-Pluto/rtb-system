# BidHandler PHP Class Documentation

## How to Set It Up

To set up the RTB system using XAMPP, follow these steps:

1. Open the **XAMPP Control Panel**.
2. Click on the **Explorer** button to navigate to the XAMPP installation directory.
3. Locate the `htdocs` folder within the XAMPP directory.
4. Copy this repository into the `htdocs` folder.
5. Start the **Apache** and **MySQL** services from the XAMPP Control Panel.



## Example Usage (Navigate to the test folder and then run the following command in cmd) : 
```bash
curl -v -H "Content-Type: application/json" --data-binary @test-cases/test-request-1.json http://localhost/rtb-system/public/index.php
```
## To run all the test cases at once navigate to the tests folder and run the following command in cmd : 
```bash
curl -v -H "Content-Type: application/json" http://localhost/rtb-system/tests/run_test.php
```



## Methods
### `validateAndSetBidRequest(string $bidRequestJson): void`
- Validates and sanitizes the bid request JSON.
- Throws an exception if the JSON is invalid.

### `sanitizeInput($data)`
- Recursively sanitizes the input data to prevent XSS and injection attacks.

### `validateBidRequest(): bool`
- Ensures the bid request contains the required fields: `imp`, `banner`, `device`, and `bidfloor`.
- Logs errors for missing fields.

### `isDimensionCompatible(array $campaign): bool`
- Compares the campaign’s banner dimensions with the dimensions in the bid request.
- Checks for compatibility with alternative formats if provided.

### `isCountryMatch($requestCountry, $campaignCountry): bool`
- Matches the country in the bid request against the campaign’s targeted country.
- Normalizes country codes to their full names for comparison.

### `isEligibleCampaign(array $campaign): bool`
- Evaluates the campaign’s compatibility based on:
  - Bid floor compliance.
  - Dimension compatibility.
  - OS compatibility.
  - Geographic targeting.
- Logs specific errors for any incompatibility.

### `findEligibleCampaigns(): array`
- Filters and returns a list of campaigns that meet the bid request’s criteria.
- Sorts eligible campaigns by price in descending order.

### `processBidRequest(): ?array`
- Validates the bid request and identifies eligible campaigns.
- Selects the highest-paying campaign.
- Generates and returns the bid response.
- Returns `null` if no eligible campaigns are found.

### `generateBidResponse(array $campaign): array`
- Constructs a detailed bid response for the selected campaign.
- Includes fields such as `price`, `adid`, `iurl`, `campaignname`, and more.

### `getErrors(): array`
- Returns the list of errors encountered during processing.

## Bid Request Format
The incoming bid request JSON should follow this structure:
```json
{
  "id": "unique_request_id",
  "imp": [
    {
      "id": "impression_id",
      "banner": {
        "w": 300,
        "h": 250,
        "format": [
          {"w": 300, "h": 250},
          {"w": 320, "h": 50}
        ]
      },
      "bidfloor": 1.5
    }
  ],
  "device": {
    "os": "iOS",
    "geo": {
      "country": "USA"
    }
  },
  "app": {
    "bundle": "com.example.app"
  }
}
```

## Campaign Format
Campaigns should be provided as an array with each campaign following this structure:
```php
[
  'dimension' => '300x250',
  'price' => 2.0,
  'hs_os' => 'iOS,Android',
  'country' => 'USA',
  'code' => 'campaign_code',
  'url' => 'https://example.com',
  'image_url' => 'https://example.com/image.jpg',
  'creative_id' => 'creative123',
  'campaignname' => 'Example Campaign',
  'advertiser' => 'Example Advertiser',
  'creative_type' => 'banner',
  'billing_id' => 'billing123',
  'tld' => 'example.com'
]
```



## Test cases informations
- Test case 1 is perfect match so its a success ✅
- Test case 2 is the format match ✅
- Test case 3 is IOS device ✅
- Test case 4 is invalid Dimention ❌
- Test case 5 is high bid floor ❌
- Test case 6 is wrong country ❌
- Test case 7 is missing required fields ❌

## Performance test was also done along with the test cases in the run_test script 

## The test cases and performance test output in my cmd is shown below

```plaintext
* Host localhost:80 was resolved.
* IPv6: ::1
* IPv4: 127.0.0.1
*   Trying [::1]:80...
* Connected to localhost (::1) port 80
> GET /rtb-system/tests/run_test.php HTTP/1.1
> Host: localhost
> User-Agent: curl/8.9.1
> Accept: */*
> Content-Type: application/json
>
* Request completely sent off
< HTTP/1.1 200 OK
< Date: Sat, 11 Jan 2025 14:01:53 GMT
< Server: Apache/2.4.54 (Win64) OpenSSL/1.1.1p PHP/8.1.10
< X-Powered-By: PHP/8.1.10
< Content-Length: 4686
< Content-Type: text/html; charset=UTF-8
<

Running test: test-request-1.json
----------------------------------------
✅ Bid generated
{
    "id": "success_bid_1",
    "bidid": "bid_678279d1a9516",
    "seatbid": [
        {
            "bid": [
                {
                    "id": "678279d1a9519",
                    "impid": "1",
                    "price": 0.1,
                    "adid": "118965F12BE33FB7E",
                    "nurl": "https://adplaytechnology.com/",
                    "iurl": "https://s3-ap-southeast-1.amazonaws.com/elasticbeanstalk-ap-southeast-1-5410920200615/CampaignFile/20240117030213/D300x250/e63324c6f222208f1dc66d3e2daaaf06.png",
                    "cid": 167629,
                    "crid": 167629,
                    "adm": null,
                    "adomain": [
                        "adplaytechnology.com"
                    ],
                    "bundle": "com.ludo.king",
                    "campaignname": "Test_Banner_13th-31st_march_Developer",
                    "advertiser": "TestGP",
                    "creative_type": "1",
                    "w": 320,
                    "h": 480,
                    "ext": {
                        "billing_id": "123456789"
                    }
                }
            ]
        }
    ],
    "cur": "USD"
}

Running test: test-request-2.json
----------------------------------------
✅ Bid generated
{
    "id": "success_bid_2",
    "bidid": "bid_678279d1a95dd",
    "seatbid": [
        {
            "bid": [
                {
                    "id": "678279d1a95de",
                    "impid": "1",
                    "price": 0.1,
                    "adid": "118965F12BE33FB7E",
                    "nurl": "https://adplaytechnology.com/",
                    "iurl": "https://s3-ap-southeast-1.amazonaws.com/elasticbeanstalk-ap-southeast-1-5410920200615/CampaignFile/20240117030213/D300x250/e63324c6f222208f1dc66d3e2daaaf06.png",
                    "cid": 167629,
                    "crid": 167629,
                    "adm": null,
                    "adomain": [
                        "adplaytechnology.com"
                    ],
                    "bundle": "",
                    "campaignname": "Test_Banner_13th-31st_march_Developer",
                    "advertiser": "TestGP",
                    "creative_type": "1",
                    "w": 320,
                    "h": 480,
                    "ext": {
                        "billing_id": "123456789"
                    }
                }
            ]
        }
    ],
    "cur": "USD"
}

Running test: test-request-3.json
----------------------------------------
✅ Bid generated
{
    "id": "success_bid_3",
    "bidid": "bid_678279d1a9673",
    "seatbid": [
        {
            "bid": [
                {
                    "id": "678279d1a9674",
                    "impid": "1",
                    "price": 0.1,
                    "adid": "118965F12BE33FB7E",
                    "nurl": "https://adplaytechnology.com/",
                    "iurl": "https://s3-ap-southeast-1.amazonaws.com/elasticbeanstalk-ap-southeast-1-5410920200615/CampaignFile/20240117030213/D300x250/e63324c6f222208f1dc66d3e2daaaf06.png",
                    "cid": 167629,
                    "crid": 167629,
                    "adm": null,
                    "adomain": [
                        "adplaytechnology.com"
                    ],
                    "bundle": "",
                    "campaignname": "Test_Banner_13th-31st_march_Developer",
                    "advertiser": "TestGP",
                    "creative_type": "1",
                    "w": 320,
                    "h": 480,
                    "ext": {
                        "billing_id": "123456789"
                    }
                }
            ]
        }
    ],
    "cur": "USD"
}

Running test: test-request-4.json
----------------------------------------
❌ No bid. Errors:
Dimension mismatch. Campaign: 320x480, Request: 100x100
No eligible campaigns found

Running test: test-request-5.json
----------------------------------------
❌ No bid. Errors:
Campaign price (0.10) below bid floor (1.00)
No eligible campaigns found

Running test: test-request-6.json
----------------------------------------
❌ No bid. Errors:
Geographic targeting mismatch
No eligible campaigns found

Running test: test-request-7.json
----------------------------------------
❌ No bid. Errors:
Missing banner impression data

=== Test Results Summary ===
Total Tests: 7
Passed: 3
Failed: 4

=== Starting Performance Test ===
Performance Test Completed
Total Iterations: 7000
Total Time: 1.03 seconds
Average Time per Request: 0.15 ms
* Connection #0 to host localhost left intact
```

