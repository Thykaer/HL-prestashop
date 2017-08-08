<?php
class HeyLoyaltyAPI
{
    public $client;
    protected $headers = [];
    public function __construct($key, $secret)
    {
        $password = base64_encode(hash_hmac('sha256', @$requestTimestamp, $secret));
        $signature = base64_encode($key.':'.$password);
        $this->headers = [
            "authorization: Basic " . $signature . "",
            "x-request-timestamp: " . $requestTimestamp. "",
        ];
    }

    public function getLists()
    {
        $response = $this->callHL('GET', 'https://api.heyloyalty.com/loyalty/v1/lists/');
        return json_decode($response['response'], true);
    }

    public function addMember($listId, $params)
    {
        return $this->callHL('POST',
            'https://api.heyloyalty.com/loyalty/v1/lists/' . $listId . '/members',
            $params);
    }

    public function getMemberByEmail($listId, $email)
    {
        $filter = ['filter' => ['email' => ['eq' => [$email]]]];
        $response = $this->callHL(
            'GET',
            'https://api.heyloyalty.com/loyalty/v1/lists/' . $listId . '/members',
            $filter);
        return json_decode($response['response'], true);
    }

    public function updateMember($memberId, $listId, $member)
    {
        return $this->callHL(
            'PUT',
            'https://api.heyloyalty.com/loyalty/v1/lists/' . $listId . '/members/' . $memberId,
            $member);
    }

    protected function callHL($requestType, $url, $postFields=[])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $postFields = $this->buildOneDimensionArray($postFields);
        switch ($requestType) {
            case 'GET':
                curl_setopt($curl, CURLOPT_URL, $url.'?'.http_build_query($postFields));
                curl_setopt($curl, CURLOPT_HTTPGET, true);
                break;
            case 'PUT':
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST,'PUT');
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postFields));
                break;
            case 'POST':
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_SAFE_UPLOAD,false);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
                break;
        }
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        $response['response'] = curl_exec($curl);
        if (curl_errno($curl)) {
            $response['error'] = curl_error($curl);
        }
        curl_close($curl);
        return $response;
    }

    protected function buildOneDimensionArray($arrays, &$new = array(), $prefix = null)
    {
        if (is_object($arrays)) {
            $arrays = get_object_vars($arrays);
        }
        foreach ($arrays AS $key => $value) {
            $k = isset($prefix) ? $prefix . '[' . $key . ']' : $key;
            if (is_array($value) OR is_object($value)) {
                $this->buildOneDimensionArray($value, $new, $k);
            } else {
                $new[$k] = $value;
            }
        }
        return $new;
    }
}
