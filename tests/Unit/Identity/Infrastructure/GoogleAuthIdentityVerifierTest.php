<?php

namespace Tests\Unit\Identity\Infrastructure;

use Firebase\JWT\JWT;
use Google\Auth\AccessToken;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Src\Identity\Infrastructure\Auth\GoogleAuthIdentityVerifier;
use Src\Shared\Domain\Exceptions\AuthenticationFailed;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/** Uses a locally generated RSA key and a fake "Google certificates" endpoint, so signatures are really verified. */
final class GoogleAuthIdentityVerifierTest extends TestCase
{
    private const string CLIENT_ID = 'client-1.apps.googleusercontent.com';

    /** @var array{private: string, jwks: array<string, mixed>} */
    private array $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = $this->generateKey('k1');
    }

    /** @return array{private: string, jwks: array<string, mixed>} */
    private function generateKey(string $kid): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $private);
        $details = openssl_pkey_get_details($resource);
        $encode = fn (string $binary): string => rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');

        return [
            'private' => $private,
            'jwks' => ['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => $kid, 'n' => $encode($details['rsa']['n']), 'e' => $encode($details['rsa']['e'])],
        ];
    }

    /** @param  array<string, mixed>  $claims */
    private function token(array $claims = [], ?array $key = null): string
    {
        $claims = [
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT_ID,
            'sub' => 'google-subject-1',
            'email' => 'siti@gmail.test',
            'email_verified' => true,
            'name' => 'Siti Aminah',
            'iat' => time() - 10,
            'exp' => time() + 3600,
            ...$claims,
        ];
        $key ??= $this->key;

        return JWT::encode($claims, $key['private'], 'RS256', $key['jwks']['kid']);
    }

    /** @param  list<string>  $clientIds */
    private function verifier(array $clientIds = [self::CLIENT_ID], ?callable $http = null): GoogleAuthIdentityVerifier
    {
        $http ??= fn () => new Response(200, ['Content-Type' => 'application/json'], json_encode(['keys' => [$this->key['jwks']]]));

        return new GoogleAuthIdentityVerifier(new AccessToken($http), $clientIds);
    }

    public function test_a_valid_token_yields_the_identity(): void
    {
        $identity = $this->verifier()->verify($this->token());

        $this->assertSame('google-subject-1', $identity->subject);
        $this->assertSame('siti@gmail.test', $identity->email);
        $this->assertTrue($identity->emailVerified);
        $this->assertSame('Siti Aminah', $identity->name);
    }

    public function test_either_google_issuer_spelling_and_any_configured_client_id_is_accepted(): void
    {
        $verifier = $this->verifier(['other-client', self::CLIENT_ID]);

        $this->assertSame('google-subject-1', $verifier->verify($this->token(['iss' => 'accounts.google.com']))->subject);
    }

    public function test_the_email_verified_claim_may_be_a_string_and_defaults_to_false(): void
    {
        $this->assertTrue($this->verifier()->verify($this->token(['email_verified' => 'true']))->emailVerified);
        $this->assertFalse($this->verifier()->verify($this->token(['email_verified' => false]))->emailVerified);

        $claims = ['iss' => 'https://accounts.google.com', 'aud' => self::CLIENT_ID, 'sub' => 's', 'email' => 'a@b.test', 'exp' => time() + 60];
        $withoutClaim = JWT::encode($claims, $this->key['private'], 'RS256', 'k1');
        $this->assertFalse($this->verifier()->verify($withoutClaim)->emailVerified);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidClaims(): array
    {
        return [
            'wrong audience' => [['aud' => 'someone-elses-client']],
            'wrong issuer' => [['iss' => 'https://evil.example']],
            'expired' => [['exp' => time() - 60]],
            'missing subject' => [['sub' => null]],
            'missing email' => [['email' => null]],
        ];
    }

    /** @param  array<string, mixed>  $claims */
    #[DataProvider('invalidClaims')]
    public function test_a_token_with_invalid_claims_is_refused(array $claims): void
    {
        try {
            $this->verifier()->verify($this->token($claims));
            $this->fail('The token must be refused.');
        } catch (AuthenticationFailed $e) {
            $this->assertSame('invalid-google-token', $e->errorCode());
        }
    }

    public function test_a_token_signed_by_another_key_is_refused(): void
    {
        $attacker = $this->generateKey('k1');

        $this->expectException(AuthenticationFailed::class);

        $this->verifier()->verify($this->token([], $attacker));
    }

    public function test_garbage_is_refused(): void
    {
        foreach (['not-a-token', 'a.b.c', ''] as $garbage) {
            try {
                $this->verifier()->verify($garbage);
                $this->fail("'{$garbage}' must be refused.");
            } catch (AuthenticationFailed $e) {
                $this->assertSame('invalid-google-token', $e->errorCode());
            }
        }
    }

    public function test_without_a_configured_client_id_nothing_is_accepted(): void
    {
        $this->expectException(ExternalServiceUnavailable::class);

        $this->verifier([])->verify($this->token());
    }

    public function test_an_unreachable_certificate_endpoint_is_a_service_error_not_an_invalid_token(): void
    {
        $verifier = $this->verifier(http: fn () => throw new RuntimeException('connection refused'));

        $this->expectException(ExternalServiceUnavailable::class);

        $verifier->verify($this->token());
    }

    public function test_the_certificates_are_fetched_once_and_cached(): void
    {
        $calls = 0;
        $verifier = $this->verifier(http: function () use (&$calls) {
            $calls++;

            return new Response(200, [], json_encode(['keys' => [$this->key['jwks']]]));
        });

        $verifier->verify($this->token());
        $verifier->verify($this->token());

        $this->assertSame(1, $calls);
    }
}
