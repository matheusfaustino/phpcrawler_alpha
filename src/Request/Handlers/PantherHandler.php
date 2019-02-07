<?php

namespace Phrawl\Request\Handlers;

use function Amp\call;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\Pool;
use function Amp\ParallelFunctions\parallel;
use Amp\Promise;
use Phrawl\Request\Types\PantherRequest;
use Phrawl\Request\Types\RequestInterface;
use Symfony\Component\Panther\Client;

/**
 * Class PantherHandler
 *
 * @package Phrawl\Request\Handlers
 */
final class PantherHandler implements HandlerInterface
{
    /**
     * It is fixed because panther does not work well with more than one client at the same time
     */
    private const WORKER_COUNT = 1;

    /**
     * @var Pool
     */
    private $pool;

    /**
     * @var Client
     */
    private $client;

    /**
     * PantherHandler constructor.
     *
     * @param null|Pool $pool
     */
    public function __construct(?Pool $pool = null)
    {
        $this->pool = $pool ?? new DefaultPool(self::WORKER_COUNT);
    }

    /**
     * Handle request object
     *
     * @todo should this method return the client and the crawler?
     * @todo verify the behavior of the client object after the function resolves
     *
     * @param RequestInterface $request
     *
     * @return Promise|null
     */
    public function handle(RequestInterface $request): ?Promise
    {
        if ( ! ($request instanceof PantherRequest)) {
            return null;
        }

        $this->client = $this->client ?? Client::createChromeClient();

        return call(parallel(function () use ($request) {
            $crawler = $this->client->request($request->getMethod(), $request->getUri());

            $waitFor = $request->getWaitFor();
            $waitFor and $this->client->waitFor($waitFor);

            /* @todo the client cant be sent because cant be serializable */
            return [$crawler, $request];
        }, $this->pool));
    }
}