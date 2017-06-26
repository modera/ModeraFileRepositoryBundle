<?php

namespace Modera\FileRepositoryBundle\Tests\Unit\Authoring;

use Modera\FileRepositoryBundle\Authoring\AuthoringInterceptor;
use Modera\FileRepositoryBundle\Entity\Repository;
use Modera\FileRepositoryBundle\Entity\StoredFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class DummyUser
{
    public $id;

    public function getId()
    {
        return $this->id;
    }
}

/**
 * @group MPFE-1012
 *
 * @author Sergei Lissovski <sergei.lissovski@nowinnovations.com>
 */
class AuthoringInterceptorTest extends \PHPUnit_Framework_TestCase
{
    public function testOnPut_happyPath()
    {
        $dummyUser = new DummyUser();
        $dummyUser->id = 777;

        $tokenMock = \Phake::mock(UsernamePasswordToken::class);
        \Phake::when($tokenMock)
            ->getUser()
            ->thenReturn($dummyUser)
        ;

        $tokenStorageMock = \Phake::mock(TokenStorageInterface::class);
        \Phake::when($tokenStorageMock)
            ->getToken()
            ->thenReturn($tokenMock)
        ;

        $ai = new AuthoringInterceptor($tokenStorageMock);

        $storedFile = \Phake::mock(StoredFile::class);

        $ai->onPut($storedFile, new \SplFileInfo(__FILE__), \Phake::mock(Repository::class));

        \Phake::verify($storedFile)
            ->setAuthor(777)
        ;
    }

    public function testOnPut_userNotAuthenticated()
    {
        $tokenMock = \Phake::mock(UsernamePasswordToken::class);

        $tokenStorageMock = \Phake::mock(TokenStorageInterface::class);
        \Phake::when($tokenStorageMock)
            ->getToken()
            ->thenReturn($tokenMock)
        ;

        $ai = new AuthoringInterceptor($tokenStorageMock);

        $storedFile = \Phake::mock(StoredFile::class);

        $ai->onPut($storedFile, new \SplFileInfo(__FILE__), \Phake::mock(Repository::class));

        \Phake::verifyNoInteraction($storedFile);
    }

    public function testOnPut_userObjectHasNoId()
    {
        $dummyUser = new \stdClass();

        $tokenMock = \Phake::mock(UsernamePasswordToken::class);
        \Phake::when($tokenMock)
            ->getUser()
            ->thenReturn($dummyUser)
        ;

        $tokenStorageMock = \Phake::mock(TokenStorageInterface::class);
        \Phake::when($tokenStorageMock)
            ->getToken()
            ->thenReturn($tokenMock)
        ;

        $ai = new AuthoringInterceptor($tokenStorageMock);

        $storedFile = \Phake::mock(StoredFile::class);

        $ai->onPut($storedFile, new \SplFileInfo(__FILE__), \Phake::mock(Repository::class));

        \Phake::verifyNoInteraction($storedFile);
    }

    public function testOnPut_authorIsAlreadySpecified()
    {
        $dummyUser = new DummyUser();
        $dummyUser->id = 777;

        $tokenMock = \Phake::mock(UsernamePasswordToken::class);
        \Phake::when($tokenMock)
            ->getUser()
            ->thenReturn($dummyUser)
        ;

        $tokenStorageMock = \Phake::mock(TokenStorageInterface::class);
        \Phake::when($tokenStorageMock)
            ->getToken()
            ->thenReturn($tokenMock)
        ;

        $ai = new AuthoringInterceptor($tokenStorageMock);

        $storedFile = \Phake::mock(StoredFile::class);
        \Phake::verifyNoFurtherInteraction($storedFile);

        $ai->onPut(
            $storedFile,
            new \SplFileInfo(__FILE__),
            \Phake::mock(Repository::class),
            array('author' => 'bob')
        );
    }
}