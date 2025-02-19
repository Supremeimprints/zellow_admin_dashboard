<?php
class ApiTester {
    private $base_url = 'http://localhost/zellow_admin/api';
    private $token = '';

    private function sendRequest($endpoint, $method = 'GET', $data = null, $headers = []) {
        $curl = curl_init();
        $url = $this->base_url . $endpoint;
        
        $default_headers = [
            'Content-Type: application/json'
        ];
        
        if ($this->token) {
            $default_headers[] = 'Authorization: Bearer ' . $this->token;
        }
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array_merge($default_headers, $headers)
        ];

        if ($data && ($method === 'POST' || $method === 'PUT')) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return [
            'code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }

    public function testLogin() {
        echo "\nTesting Login...\n";
        $result = $this->sendRequest('/auth/login', 'POST', [
            'email' => 'gitau.magana@zellow.com',
            'password' => '12345678'
        ]);
        
        if ($result['code'] === 200 && isset($result['response']['token'])) {
            $this->token = $result['response']['token'];
            echo "Login successful! Token received.\n";
        } else {
            echo "Login failed: " . print_r($result, true) . "\n";
        }
    }

    public function testServiceRequest() {
        echo "\nTesting Service Request...\n";
        $result = $this->sendRequest('/services/request', 'POST', [
            'service_type' => 1,
            'description' => 'Test service request'
        ]);
        echo "Response: " . print_r($result, true) . "\n";
    }

    public function testFeedback() {
        echo "\nTesting Feedback Submission...\n";
        $result = $this->sendRequest('/feedback', 'POST', [
            'type' => 'general',
            'content' => 'Test feedback',
            'rating' => 5
        ]);
        echo "Response: " . print_r($result, true) . "\n";
    }

    public function runAllTests() {
        $this->testLogin();
        $this->testServiceRequest();
        $this->testFeedback();
    }
}

// Run the tests
$tester = new ApiTester();
$tester->runAllTests();
