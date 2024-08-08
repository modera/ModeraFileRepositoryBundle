<?php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class ModeraFileRepositoryAppKernel extends \Modera\FoundationBundle\Testing\AbstractFunctionalKernel
{
    public function registerBundles()
    {
        return array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),

            new Knp\Bundle\GaufretteBundle\KnpGaufretteBundle(),
            new Modera\FileRepositoryBundle\ModeraFileRepositoryBundle(),
            new Modera\FileRepositoryBundle\Tests\Fixtures\Bundle\ModeraDummyBundle(),
        );
    }
}
