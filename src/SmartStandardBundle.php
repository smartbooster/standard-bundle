<?php

namespace Smart\StandardBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @author Mathieu Ducrot <mathieu.ducrot@smartbooster.io>
 */
class SmartStandardBundle extends Bundle
{
    /**
     * https://symfony.com/doc/current/bundles/best_practices.html#directory-structure
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
