<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Utils\Log;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Uri\Rfc3986\Uri;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Wraps web-auth/webauthn-lib for the passkey (WebAuthn) 2FA method: builds the options a browser
 * needs for navigator.credentials.create()/get(), and validates the responses. All DB persistence
 * lives in TwoFactorController; this class only runs the ceremonies.
 */
class WebauthnController
{
    private const string REG_SESSION = 'webauthn_reg_opts';
    private const string LOGIN_SESSION = 'webauthn_login_opts';
    private const int CHALLENGE_BYTES = 32;

    // COSE algorithm identifiers: ES256 (widely supported) then RS256 (older platform authenticators).
    private const array COSE_ALGORITHMS = [-7, -257];

    /**
     * Build and stash the options for registering a new passkey.
     *
     * @param int      $userId
     * @param string   $userLabel            Shown by the authenticator / OS prompt (the user's email)
     * @param string[] $excludeCredentialIds base64url ids of the user's existing passkeys, so the same authenticator isn't enrolled twice
     *
     * @return string JSON for navigator.credentials.create()
     */
    public static function registrationOptions(int $userId, string $userLabel, array $excludeCredentialIds): string
    {
        $options = new PublicKeyCredentialCreationOptions(
            rp: new PublicKeyCredentialRpEntity(self::rpName(), self::rpId()),
            user: new PublicKeyCredentialUserEntity($userLabel, (string)$userId, $userLabel),
            challenge: random_bytes(self::CHALLENGE_BYTES),
            pubKeyCredParams: array_map(
                static fn(int $alg) => PublicKeyCredentialParameters::create('public-key', $alg),
                self::COSE_ALGORITHMS
            ),
            authenticatorSelection: AuthenticatorSelectionCriteria::create(userVerification: 'preferred', residentKey: 'preferred'),
            attestation: 'none',
            excludeCredentials: self::descriptors($excludeCredentialIds),
            timeout: TWO_FACTOR_CONFIG['webauthn']['timeout'],
        );

        $json = self::serializer()->serialize($options, 'json');
        SessionController::set(self::REG_SESSION, $json);

        return $json;
    }

    /**
     * The relying-party display name shown in the OS/browser passkey prompt.
     *
     * @return string
     */
    private static function rpName(): string
    {
        return (string)APP_NAME;
    }

    /**
     * The relying-party id: the registrable domain a passkey is bound to, taken from APP_URL.
     *
     * @return string
     */
    private static function rpId(): string
    {
        return new Uri((string)APP_URL)->getHost() ?? 'localhost';
    }

    /**
     * Credential descriptors from base64url ids, for the exclude/allow lists.
     *
     * @param string[] $base64UrlIds
     *
     * @return PublicKeyCredentialDescriptor[]
     */
    private static function descriptors(array $base64UrlIds): array
    {
        return array_map(
                static function (string $id): ?PublicKeyCredentialDescriptor {
                    $raw = base64_decode(strtr($id, '-_', '+/'), true);
                    return $raw === false ? null : PublicKeyCredentialDescriptor::create('public-key', $raw);
                },
                $base64UrlIds
            )
                |> array_filter(...)
                |> array_values(...);
    }

    /**
     * The serializer, built once per request.
     *
     * @return SerializerInterface
     */
    private static function serializer(): SerializerInterface
    {
        static $serializer = null;
        return $serializer ??= new WebauthnSerializerFactory(AttestationStatementSupportManager::create())->create();
    }

    /**
     * Validate a navigator.credentials.create() response against the stashed options.
     *
     * @param string $clientResponseJson
     *
     * @return array{credential_id: string, record: string, sign_count: int, transports: string, aaguid: string}|null
     *         Row data for webauthn_credentials, or null on any failure
     */
    public static function verifyRegistration(string $clientResponseJson): ?array
    {
        $optionsJson = SessionController::get(self::REG_SESSION);
        SessionController::remove(self::REG_SESSION);

        if (!is_string($optionsJson)) return null;

        try {
            $options = self::serializer()->deserialize($optionsJson, PublicKeyCredentialCreationOptions::class, 'json');
            $credential = self::serializer()->deserialize($clientResponseJson, PublicKeyCredential::class, 'json');

            if (!$credential->response instanceof AuthenticatorAttestationResponse) return null;

            $record = AuthenticatorAttestationResponseValidator::create(self::ceremonyFactory()->creationCeremony())
                ->check($credential->response, $options, self::rpId());

            return [
                'credential_id' => self::base64Url($record->publicKeyCredentialId),
                'record' => self::serializer()->serialize($record, 'json'),
                'sign_count' => $record->counter,
                'transports' => implode(',', $record->transports),
                'aaguid' => $record->aaguid->toRfc4122(),
            ];
        } catch (Throwable $e) {
            Log::warning('Passkey registration check failed: {error}', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * The ceremony-step factory, built once per request with this site's origin allow-listed.
     *
     * @return CeremonyStepManagerFactory
     */
    private static function ceremonyFactory(): CeremonyStepManagerFactory
    {
        static $factory = null;

        if ($factory === null) {
            $factory = new CeremonyStepManagerFactory();
            $factory->setAllowedOrigins([self::allowedOrigin()]);
        }

        return $factory;
    }

    /**
     * The site's web origin (scheme://host[:port], never a path) as WebAuthn expects it, from APP_URL.
     * A trailing deploy sub-path would make every ceremony fail on a sub-directory install.
     *
     * @return string
     */
    private static function allowedOrigin(): string
    {
        $uri = new Uri((string)APP_URL);
        $origin = ($uri->getScheme() ?? 'https') . '://' . ($uri->getHost() ?? 'localhost');

        return $uri->getPort() !== null ? "$origin:{$uri->getPort()}" : $origin;
    }

    /**
     * URL-safe, unpadded base64: the encoding webauthn-lib uses for ids in its JSON.
     *
     * @param string $bytes
     *
     * @return string
     */
    public static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Build and stash the options for a passkey login challenge.
     *
     * @param string[] $allowCredentialIds base64url ids of the user's registered passkeys
     *
     * @return string JSON for navigator.credentials.get()
     */
    public static function loginOptions(array $allowCredentialIds): string
    {
        $options = PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(self::CHALLENGE_BYTES),
            rpId: self::rpId(),
            allowCredentials: self::descriptors($allowCredentialIds),
            userVerification: 'preferred',
            timeout: TWO_FACTOR_CONFIG['webauthn']['timeout'],
        );

        $json = self::serializer()->serialize($options, 'json');
        SessionController::set(self::LOGIN_SESSION, $json);

        return $json;
    }

    /**
     * The base64url credential id carried by a navigator.credentials.get() response, for the caller to look up.
     *
     * @param string $clientResponseJson
     *
     * @return string|null
     */
    public static function credentialIdFromResponse(string $clientResponseJson): ?string
    {
        try {
            $credential = self::serializer()->deserialize($clientResponseJson, PublicKeyCredential::class, 'json');
            return self::base64Url($credential->rawId);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Validate a navigator.credentials.get() response against the stashed options and the stored record.
     *
     * @param string $clientResponseJson
     * @param string $storedRecordJson The serialized CredentialRecord for the matched passkey
     * @param string $userHandle
     *
     * @return array{record: string, sign_count: int}|null The re-serialized record and its bumped counter, or null on failure
     */
    public static function verifyLogin(string $clientResponseJson, string $storedRecordJson, string $userHandle): ?array
    {
        $optionsJson = SessionController::get(self::LOGIN_SESSION);
        SessionController::remove(self::LOGIN_SESSION);

        if (!is_string($optionsJson)) return null;

        try {
            $options = self::serializer()->deserialize($optionsJson, PublicKeyCredentialRequestOptions::class, 'json');
            $credential = self::serializer()->deserialize($clientResponseJson, PublicKeyCredential::class, 'json');
            $record = self::serializer()->deserialize($storedRecordJson, CredentialRecord::class, 'json');

            if (!$credential->response instanceof AuthenticatorAssertionResponse) return null;

            $updated = AuthenticatorAssertionResponseValidator::create(self::ceremonyFactory()->requestCeremony())
                ->check($record, $credential->response, $options, self::rpId(), $userHandle);

            return ['record' => self::serializer()->serialize($updated, 'json'), 'sign_count' => $updated->counter];
        } catch (Throwable $e) {
            Log::warning('Passkey login check failed: {error}', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
