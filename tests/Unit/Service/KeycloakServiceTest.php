<?php

namespace App\Tests\Unit\Service;

use App\Service\KeycloakService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class KeycloakServiceTest extends TestCase
{
    private function makeService(array $env = []): KeycloakService
    {
        $defaults = [
            'KEYCLOAK_URL'          => 'http://keycloak:8080',
            'KEYCLOAK_INTERNAL_URL' => 'http://keycloak-internal:8080',
            'KEYCLOAK_REALM'        => 'lpmde',
            'KEYCLOAK_CLIENT_ID'    => 'symfony-app',
            'KEYCLOAK_CLIENT_SECRET'=> 'secret123',
            'KEYCLOAK_REDIRECT_URI' => 'http://localhost:8000/login/keycloak/callback',
        ];

        foreach (array_merge($defaults, $env) as $key => $value) {
            $_SERVER[$key] = $value;
        }

        $httpClient = $this->createMock(HttpClientInterface::class);

        return new KeycloakService($httpClient);
    }

    protected function tearDown(): void
    {
        foreach (['KEYCLOAK_URL', 'KEYCLOAK_INTERNAL_URL', 'KEYCLOAK_REALM',
                  'KEYCLOAK_CLIENT_ID', 'KEYCLOAK_CLIENT_SECRET', 'KEYCLOAK_REDIRECT_URI'] as $key) {
            unset($_SERVER[$key]);
        }
    }

    public function testGetAuthorizationUrlContainsClientId(): void
    {
        $service = $this->makeService();
        $url = $service->getAuthorizationUrl('state-abc');

        $this->assertStringContainsString('client_id=symfony-app', $url);
    }

    public function testGetAuthorizationUrlContainsState(): void
    {
        $service = $this->makeService();
        $url = $service->getAuthorizationUrl('my-state-123');

        $this->assertStringContainsString('state=my-state-123', $url);
    }

    public function testGetAuthorizationUrlContainsOpenIdScope(): void
    {
        $service = $this->makeService();
        $url = $service->getAuthorizationUrl('state');

        $this->assertStringContainsString('scope=', $url);
        $this->assertStringContainsString('openid', $url);
    }

    public function testGetAuthorizationUrlContainsResponseTypeCode(): void
    {
        $service = $this->makeService();
        $url = $service->getAuthorizationUrl('state');

        $this->assertStringContainsString('response_type=code', $url);
    }

    public function testGetAuthorizationUrlUsesCorrectRealm(): void
    {
        $service = $this->makeService(['KEYCLOAK_REALM' => 'myrealm']);
        $url = $service->getAuthorizationUrl('state');

        $this->assertStringContainsString('/realms/myrealm/', $url);
    }

    public function testGetAuthorizationUrlUsesKeycloakUrl(): void
    {
        $service = $this->makeService(['KEYCLOAK_URL' => 'http://auth.example.com']);
        $url = $service->getAuthorizationUrl('state');

        $this->assertStringStartsWith('http://auth.example.com', $url);
    }

    public function testGetAccessTokenCallsHttpClient(): void
    {
        foreach ([
            'KEYCLOAK_URL'           => 'http://keycloak:8080',
            'KEYCLOAK_INTERNAL_URL'  => 'http://keycloak-internal:8080',
            'KEYCLOAK_REALM'         => 'lpmde',
            'KEYCLOAK_CLIENT_ID'     => 'symfony-app',
            'KEYCLOAK_CLIENT_SECRET' => 'secret123',
            'KEYCLOAK_REDIRECT_URI'  => 'http://localhost:8000/login/keycloak/callback',
        ] as $k => $v) {
            $_SERVER[$k] = $v;
        }

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'access_token'  => 'tok123',
            'token_type'    => 'Bearer',
            'expires_in'    => 300,
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->stringContains('/realms/lpmde/protocol/openid-connect/token'),
                $this->arrayHasKey('body')
            )
            ->willReturn($response);

        $service = new KeycloakService($httpClient);
        $result  = $service->getAccessToken('auth-code-xyz');

        $this->assertSame('tok123', $result['access_token']);
    }

    public function testGetUserInfoCallsHttpClientWithBearerToken(): void
    {
        foreach ([
            'KEYCLOAK_URL'           => 'http://keycloak:8080',
            'KEYCLOAK_INTERNAL_URL'  => 'http://keycloak-internal:8080',
            'KEYCLOAK_REALM'         => 'lpmde',
            'KEYCLOAK_CLIENT_ID'     => 'symfony-app',
            'KEYCLOAK_CLIENT_SECRET' => 'secret123',
            'KEYCLOAK_REDIRECT_URI'  => 'http://localhost:8000/login/keycloak/callback',
        ] as $k => $v) {
            $_SERVER[$k] = $v;
        }

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'sub'   => 'user-uuid-123',
            'email' => 'malphas@lpmde.fr',
            'name'  => 'Malphas LaMort',
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('/realms/lpmde/protocol/openid-connect/userinfo'),
                $this->callback(function (array $options) {
                    return isset($options['headers']['Authorization'])
                        && str_starts_with($options['headers']['Authorization'], 'Bearer ');
                })
            )
            ->willReturn($response);

        $service = new KeycloakService($httpClient);
        $result  = $service->getUserInfo('my-access-token');

        $this->assertSame('malphas@lpmde.fr', $result['email']);
    }
}
