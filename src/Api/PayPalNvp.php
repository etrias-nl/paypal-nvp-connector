<?php

declare(strict_types=1);

namespace Etrias\PayPalNvpConnector\Api;

use Etrias\PayPalNvpConnector\Request\GetBalanceRequest;
use Etrias\PayPalNvpConnector\Request\TransactionSearchRequest;
use Etrias\PayPalNvpConnector\Type\Transaction;
use GuzzleHttp\Psr7\Uri;
use Http\Client\Common\HttpMethodsClientInterface;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\UriFactoryInterface;

class PayPalNvp
{
    protected const VERSION = '82';
    protected const PARAM_METHOD = 'METHOD';
    protected const PARAM_VERSION = 'VERSION';

    /** @var HttpMethodsClientInterface */
    protected $client;

    /** @var UriFactoryInterface */
    protected $uriFactory;

    public function __construct(
        HttpMethodsClientInterface $client,
        ?UriFactoryInterface $uriFactory = null
    ) {
        $this->client = $client;
        $this->uriFactory = $uriFactory ?? Psr17FactoryDiscovery::findUrlFactory();
    }

    public function getBalance(GetBalanceRequest $request): int
    {
        $data = $this->get(__FUNCTION__, $request->toQueryArray());
        $i = 0;

        while (isset($data['L_CURRENCYCODE'.$i])) {
            if ($request->getCurrency() === $data['L_CURRENCYCODE'.$i]) {
                return (int) $data['L_AMT'.$i];
            }
            ++$i;
        }

        return 0;
    }

    /**
     * @return Transaction[]
     */
    public function transactionSearch(TransactionSearchRequest $request): array
    {
        $data = $this->get(__FUNCTION__, $request->toQueryArray());
        $groups = [];

        foreach ($data as $key => $value) {
            if (preg_match('~^L_(.+?)(\d++)$~', $key, $matches)) {
                $groups[$matches[2]][$matches[1]] = $value;
            }
        }

        $transactions = [];
        foreach ($groups as $group) {
            $transactions[] = Transaction::fromQueryResult($group);
        }

        return $transactions;
    }

    protected function get(string $method, array $query): array
    {
        $query[self::PARAM_METHOD] = ucfirst($method);
        $query[self::PARAM_VERSION] = self::VERSION;

        $response = $this->client->get(Uri::withQueryValues($this->uriFactory->createUri(), $query));
        $result = [];

        parse_str((string) $response->getBody(), $result);

        return $result;
    }
}
