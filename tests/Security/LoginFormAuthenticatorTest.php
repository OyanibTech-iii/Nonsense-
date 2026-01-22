<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\LoginFormAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class LoginFormAuthenticatorTest extends TestCase
{
    private LoginFormAuthenticator $authenticator;
    private UrlGeneratorInterface $urlGenerator;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->authenticator = new LoginFormAuthenticator($this->urlGenerator);
    }

    public function testRoleBasedRedirects(): void
    {
        // Test admin redirect
        $adminUser = $this->createUser(['ROLE_ADMIN']);
        $adminToken = $this->createToken($adminUser);
        
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_admin_dashboard')
            ->willReturn('/admin');

        $request = new Request();
        $response = $this->authenticator->onAuthenticationSuccess($request, $adminToken, 'main');
        
        $this->assertEquals('/admin', $response->getTargetUrl());

        // Test regular user redirect
        $regularUser = $this->createUser(['ROLE_USER']);
        $regularToken = $this->createToken($regularUser);
        
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_user_page')
            ->willReturn('/userpage');

        $response = $this->authenticator->onAuthenticationSuccess($request, $regularToken, 'main');
        
        $this->assertEquals('/userpage', $response->getTargetUrl());
    }

    private function createUser(array $roles): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setRoles($roles);
        return $user;
    }

    private function createToken(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }
}
