<?php

declare(strict_types=1);

namespace Magentix\Security\Plugin;

use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageContentValidator;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\Phrase;

/**
 * Rejects image uploads whose filename extension is not in the safe allowlist (PolyShell mitigation).
 */
class ImageContentValidatorExtension
{
    private const array ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'gif', 'png'];

    private IoFile $ioFile;

    public function __construct(IoFile $ioFile)
    {
        $this->ioFile = $ioFile;
    }

    /**
     * @throws InputException
     */
    public function afterIsValid(
        ImageContentValidator $subject,
        bool $result,
        ImageContentInterface $imageContent
    ): bool {
        $pathInfo = $this->ioFile->getPathInfo($imageContent->getName());
        $extension = strtolower($pathInfo['extension'] ?? '');

        if ($extension && !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InputException(
                new Phrase('The image file extension "%1" is not allowed.', [$extension])
            );
        }

        return $result;
    }
}
