<?php

declare(strict_types=1);

namespace Magentix\Security\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\HttpInterface as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Adds the Content-Security-Policy header to frontend responses.
 *
 * The policy is read from magentix_security/csp/policy, default scope only (same policy on every store view).
 * An empty policy sends no header.
 */
class Csp implements ObserverInterface
{
    private const string XML_PATH_POLICY = 'magentix_security/csp/policy';

    private ScopeConfigInterface $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(Observer $observer): void
    {
        $policy = trim((string)preg_replace('/\s+/', ' ', (string)$this->scopeConfig->getValue(self::XML_PATH_POLICY)));
        if ($policy === '') {
            return;
        }

        /** @var HttpResponse $response */
        $response = $observer->getEvent()->getData('response');
        $response->setHeader('Content-Security-Policy', $policy);
    }
}
