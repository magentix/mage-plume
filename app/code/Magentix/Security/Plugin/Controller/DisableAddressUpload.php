<?php

declare(strict_types=1);

namespace Magentix\Security\Plugin\Controller;

use Magento\Customer\Controller\Address\File\Upload;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Blocks the customer address file upload endpoint (SessionReaper mitigation).
 */
class DisableAddressUpload
{
    private ResultFactory $resultFactory;

    public function __construct(ResultFactory $resultFactory)
    {
        $this->resultFactory = $resultFactory;
    }

    public function aroundExecute(Upload $subject, callable $proceed): ResultInterface
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData([
            'error' => (string)__('File upload for customer address attributes is disabled.'),
            'errorcode' => 403,
        ]);

        return $result;
    }
}
