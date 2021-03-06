<?php

namespace SmoDav\Mpesa\C2B;

use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use SmoDav\Mpesa\Engine\Core;
use SmoDav\Mpesa\Repositories\EndpointsRepository;

class STK
{
    protected $pushEndpoint;
    protected $engine;
    protected $number;
    protected $amount;
    protected $reference;
    protected $description;

    /**
     * STK constructor.
     *
     * @param Core $engine
     */
    public function __construct(Core $engine)
    {
        $this->engine       = $engine;
        $this->pushEndpoint = EndpointsRepository::build(MPESA_STK_PUSH);
    }

    /**
     * Set the request amount to be deducted.
     *
     * @param int $amount
     *
     * @return $this
     */
    public function request($amount)
    {
        if (!\is_numeric($amount)) {
            throw new \InvalidArgumentException('The amount must be numeric');
        }

        $this->amount = $amount;

        return $this;
    }

    /**
     * Set the Mobile Subscriber Number to deduct the amount from.
     * Must be in format 2547XXXXXXXX.
     *
     * @param int $number
     *
     * @return $this
     */
    public function from($number)
    {
        if (! starts_with($number, '2547')) {
            throw new \InvalidArgumentException('The subscriber number must start with 2547');
        }

        $this->number = $number;

        return $this;
    }

    /**
     * Set the product reference number to bill the account.
     *
     * @param int    $reference
     * @param string $description
     *
     * @return $this
     */
    public function usingReference($reference, $description)
    {
        \preg_match('/[^A-Za-z0-9]/', $reference, $matches);

        if (\count($matches)) {
            throw new \InvalidArgumentException('Reference should be alphanumeric.');
        }

        $this->reference   = $reference;
        $this->description = $description;

        return $this;
    }

    public function push($amount = null, $number = null, $reference = null, $description = null)
    {
        $time      = Carbon::now()->format('YmdHis');
        $shortCode = $this->engine->config->get('mpesa.short_code');
        $passkey   = $this->engine->config->get('mpesa.passkey');
        $callback  = $this->engine->config->get('mpesa.stk_callback');
        $password  = \base64_encode($shortCode . $passkey . $time);

        $body = [
            'BusinessShortCode' => $shortCode,
            'Password'          => $password,
            'Timestamp'         => $time,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => $amount ?: $this->amount,
            'PartyA'            => $number ?: $this->number,
            'PartyB'            => $shortCode,
            'PhoneNumber'       => $number ?: $this->number,
            'CallBackURL'       => $callback,
            'AccountReference'  => $reference ?: $this->reference,
            'TransactionDesc'   => $description ?: $this->description,
        ];

        try {
            $response = $this->makeRequest($body);

            return \json_decode($response->getBody());
        } catch (RequestException $exception) {
            return \json_decode($exception->getResponse()->getBody());
        }
    }

    /**
     * Initiate the request.
     *
     * @param array $body
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    private function makeRequest($body = [])
    {
        return $this->engine->client->request('POST', $this->pushEndpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->engine->auth->authenticate(),
                'Content-Type'  => 'application/json',
            ],
            'json' => $body,
        ]);
    }
}
