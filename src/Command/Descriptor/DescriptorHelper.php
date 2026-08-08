<?php

namespace Cog\Command\Descriptor;

use Symfony\Component\Console\Helper\DescriptorHelper as BaseDescriptorHelper;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;

/**
 * Vendored from symfony/framework-bundle so debug:container doesn't require the whole bundle.
 *
 * @internal
 */
class DescriptorHelper extends BaseDescriptorHelper
{
    public function __construct(?FileLinkFormatter $fileLinkFormatter = null)
    {
        $this
            ->register('txt', new TextDescriptor($fileLinkFormatter))
            ->register('xml', new XmlDescriptor())
            ->register('json', new JsonDescriptor())
            ->register('md', new MarkdownDescriptor())
        ;
    }
}
