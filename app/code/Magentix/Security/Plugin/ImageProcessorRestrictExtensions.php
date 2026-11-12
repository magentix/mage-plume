<?php

declare(strict_types=1);

namespace Magentix\Security\Plugin;

use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageProcessor;
use Magento\Framework\Api\Uploader;

/**
 * Enforces an image-only extension allowlist on the Uploader before ImageProcessor saves files (PolyShell mitigation).
 */
class ImageProcessorRestrictExtensions
{
    private const array ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'gif', 'png'];

    private Uploader $uploader;

    public function __construct(Uploader $uploader)
    {
        $this->uploader = $uploader;
    }

    public function beforeProcessImageContent(
        ImageProcessor $subject,
        string $entityType,
        ImageContentInterface $imageContent
    ): null {
        $this->uploader->setAllowedExtensions(self::ALLOWED_EXTENSIONS);
        return null;
    }
}
