<?php
require dirname(__FILE__) . '/vendor/autoload.php';
use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Promise;
use Phpclient\HLCurlHandler;

class HeyLoyaltyAPI{
    public $client;
    public function __construct($key, $secret)
    {
        $host = 'https://api.heyloyalty.com';
        $requestSignature = base64_encode(hash_hmac('sha256', @$requestTimestamp, $secret));

        $this->client = new Client(['base_uri' => $host,'timeout'  => 5.0,'auth' => [$key,$requestSignature],
            // For Backwards Compatibility
            'base_url' => $host,
            'defaults' => ['auth' => [
                $key,
                $requestSignature]]]);
    }

    public function lists()
    {
        $response = $this->client->get('/loyalty/v1/lists/', ['verify' => false]);
        return json_decode($this->client->get('/loyalty/v1/lists/', ['verify' => false])->getBody(), true);
    }

    public function add_member($listId, $params)
    {
        return $this->client->post('https://api.heyloyalty.com/loyalty/v1/lists/' . $listId . '/members/', ['json' => $params]);
    }

    public function getMemberByEmail($listId, $email)
    {
        $filter = ['filter' => ['email' => ['eq' => [$email]]]];
        return json_decode($this->client->get('https://api.heyloyalty.com/loyalty/v1/lists/' . $listId . '/members?' . http_build_query($filter))->getBody(), true);
    }

    public function updateMember($memberId, $listId, $member)
    {
        return $this->client->put('https://api.heyloyalty.com/loyalty/v1/lists/' . $listId . '/members/' . $memberId, ['json' => $member]);
    }
}
