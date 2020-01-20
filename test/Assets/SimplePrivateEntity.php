<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Assets;

class SimplePrivateEntity
{
    private function setPrivate($value)
    {
        throw new \Exception('Should never be called');
    }

    private function getPrivate()
    {
        throw new \Exception('Should never be called');
    }

    protected function setProtected($value)
    {
        throw new \Exception('Should never be called');
    }

    protected function getProtected()
    {
        throw new \Exception('Should never be called');
    }
}
