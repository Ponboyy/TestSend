<?php

class APIRequestHandler
{
    private $endpoint1 = 'https://api.xn--80ajbekothchmme5j.xn--p1ai/gis-gate/api/v3/gis/search';
    private $endpoint2 = 'https://api.xn--80ajbekothchmme5j.xn--p1ai/gis-gate/api/v1/gis/search';
    private $headers = [
        'Content-Type: application/json',
        'Authorization: Basic YWRtaW46c2RmakpLRkhzZGYyMzRkZHNzdw=='
    ];
    
    private $postData1;
    private $postData2;

    public function __construct()
    {
        $this->postData1 = json_encode([
            "only_active" => true,
            "priority" => 8,
            "timeout" => 25,
            "page" => 1,
            "allow_pagination" => true,
            "accrual_type" => "gibdd",
            "gate" => "moneta",
            "give_raw" => false,
            "use_cache" => false,
            "requisites" => [
                [
                    "document_type" => "ctc",
                    "document_value" => "9949041957"
                ]
            ]
        ]);

        $this->postData2 = json_encode([
            "only_active" => false,
            "priority" => 8,
            "timeout" => 25,
            "page" => 1,
            "allow_pagination" => false,
            "accrual_type" => "gibdd",
            "gate" => "a3",
            "requisites" => [
                [
                    "document_type" => "ctc",
                    "document_value" => "9945585636"
                ]
            ]
        ]);
    }

    public function sendRequestsWithPriority()
    {
        $multiHandle = curl_multi_init();

        $ch1 = curl_init($this->endpoint1);
        curl_setopt($ch1, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch1, CURLOPT_POST, true);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, $this->postData1);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_TIMEOUT, 30);

        // Настройка быстрого, но плохого запроса
        $ch2 = curl_init($this->endpoint2);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, $this->postData2);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 30); 


        curl_multi_add_handle($multiHandle, $ch1);
        curl_multi_add_handle($multiHandle, $ch2);

        $active = null;
        $responses = [
            'ch1' => null,
            'ch2' => null
        ];
        $errors = [
            'ch1' => null,
            'ch2' => null
        ];
        $httpCodes = [
            'ch1' => null,
            'ch2' => null
        ];
        $completed = [
            'ch1' => false,
            'ch2' => false
        ];

        do {
            $mrc = curl_multi_exec($multiHandle, $active);
            curl_multi_select($multiHandle);

            while ($info = curl_multi_info_read($multiHandle)) {
                $handle = $info['handle'];
                $content = curl_multi_getcontent($handle);
                $error = curl_error($handle);
                $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

                if ($handle === $ch1) {
                    $responses['ch1'] = $content;
                    $errors['ch1'] = $error;
                    $httpCodes['ch1'] = $httpCode;
                    $completed['ch1'] = true;

                    if ($httpCode == 200 && !$error) {
                        $this->outputResponse($content);
                        curl_multi_remove_handle($multiHandle, $ch2);
                        curl_close($ch2);
                        curl_multi_remove_handle($multiHandle, $ch1);
                        curl_multi_close($multiHandle);
                        return;
                    }
                }

                if ($handle === $ch2) {
                    $responses['ch2'] = $content;
                    $errors['ch2'] = $error;
                    $httpCodes['ch2'] = $httpCode;
                    $completed['ch2'] = true;
                }

                curl_multi_remove_handle($multiHandle, $handle);
                curl_close($handle);
            }
        } while ($active && $mrc == CURLM_OK);

        curl_multi_close($multiHandle);

        if (($httpCodes['ch1'] !== 200 || $errors['ch1']) && $responses['ch2']) {
            if ($httpCodes['ch2'] == 200 && !$errors['ch2']) {
                $this->outputResponse($responses['ch2']);
            } else {
                $this->outputResponse([
                    'error' => 'Оба запроса завершились с ошибкой.',
                    'details' => [
                        'endpoint1' => $errors['ch1'] ?: 'HTTP код: ' . $httpCodes['ch1'],
                        'endpoint2' => $errors['ch2'] ?: 'HTTP код: ' . $httpCodes['ch2'],
                    ],
                ]);
            }
        }
    }

    private function outputResponse($response)
    {
        if (is_array($response)) {
            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            echo $response;
        }
    }
}

$apiHandler = new APIRequestHandler();
$apiHandler->sendRequestsWithPriority();

?>
