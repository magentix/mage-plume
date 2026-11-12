<?php

declare(strict_types=1);

namespace Magentix\Backend\App\Action\Plugin;

use Magento\Backend\App\Action\Plugin\Authentication as BaseAuthentication;
use Magento\Framework\App\RequestInterface;

class Authentication extends BaseAuthentication
{
    /**
     * Checks whether Magento requires redirection after a successful admin login and redirects the user, if needed
     * @override Direct redirect to dashboard after login to avoid double redirection and form key error message
     *
     * @param RequestInterface $request
     *
     * @return bool
     */
    protected function _redirectIfNeededAfterLogin(RequestInterface $request): bool
    {
        $result = parent::_redirectIfNeededAfterLogin($request);

        if ($result) {
            $this->_response->setRedirect(
                $this->backendUrl->getUrl($this->backendUrl->getStartupPageUrl())
            );
        }

        return $result;
    }
}
