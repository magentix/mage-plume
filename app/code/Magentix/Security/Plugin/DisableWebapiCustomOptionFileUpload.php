<?php
/**
 * Copyright © 2026 Pixel Development. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Magentix\Security\Plugin;

use Magento\Catalog\Model\Webapi\Product\Option\Type\File\Processor;
use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Phrase;

/**
 * Blocks custom option file uploads sent as base64 through the REST/GraphQL API (PolyShell mitigation).
 */
class DisableWebapiCustomOptionFileUpload
{
    /**
     * @throws InputException
     */
    public function beforeProcessFileContent(
        Processor $subject,
        ImageContentInterface $imageContent
    ): void {
        throw new InputException(
            new Phrase('File upload for custom options is not allowed through the API.')
        );
    }
}
