<?php

namespace LittleGreenMan\Earhart\Services;

use LittleGreenMan\Earhart\Exceptions\FeatureNotEnabledException;
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\Exceptions\RateLimitException;
use LittleGreenMan\Earhart\Exceptions\StepUpMfaException;
use LittleGreenMan\Earhart\Exceptions\ValidationException;
use LittleGreenMan\Earhart\PropelAuth\MfaSetup;
use LittleGreenMan\Earhart\PropelAuth\StepUpGrantType;

/**
 * Step-up MFA: ask a signed-in user for a fresh MFA code before a sensitive action.
 *
 * Verify a TOTP or SMS code to get a grant, pass the grant to the action, and
 * check it there with verifyGrant(). The action type is your own label (e.g.
 * "DELETE_ACCOUNT") and must match between the two.
 */
class MfaService extends BaseApiService
{
    /**
     * How the user has set up MFA, or null if they haven't.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getUserMfaMethods(string $userId): ?MfaSetup
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/user/{$userId}/mfa", notFound: fn () => InvalidUserException::notFound($userId));

        return isset($response['mfaSetup']['type']) ? MfaSetup::fromArray($response['mfaSetup']) : null;
    }

    /**
     * Check a code from the user's authenticator app and return a step-up grant.
     *
     * @throws StepUpMfaException If the code is wrong or the user has no TOTP set up
     * @throws PropelAuthException On any other API failure
     */
    public function verifyTotp(
        string $userId,
        string $code,
        string $actionType,
        StepUpGrantType $grantType = StepUpGrantType::OneTimeUse,
        int $validForSeconds = 300,
    ): string {
        return $this->stepUp(fn () => $this->makeRequest('POST', '/api/backend/v1/mfa/step-up/verify-totp', [
            'actionType' => $actionType,
            'userId' => $userId,
            'code' => $code,
            'grantType' => $grantType->value,
            'validForSeconds' => $validForSeconds,
        ]), $userId)['stepUpGrant'];
    }

    /**
     * Text a code to one of the user's MFA phone numbers. Returns the challenge ID for verifySmsCode().
     *
     * @param  string  $mfaPhoneId  A key of MfaSetup::$phoneNumbers
     *
     * @throws StepUpMfaException If the user has no SMS MFA set up
     * @throws PropelAuthException On any other API failure
     */
    public function sendSmsCode(
        string $userId,
        string $mfaPhoneId,
        string $actionType,
        StepUpGrantType $grantType = StepUpGrantType::OneTimeUse,
        int $validForSeconds = 300,
    ): string {
        return $this->stepUp(fn () => $this->makeRequest('POST', '/api/backend/v1/mfa/step-up/phone/send', [
            'actionType' => $actionType,
            'userId' => $userId,
            'mfaPhoneId' => $mfaPhoneId,
            'grantType' => $grantType->value,
            'validForSeconds' => $validForSeconds,
        ]), $userId)['challengeId'];
    }

    /**
     * Check the code the user received by SMS and return a step-up grant.
     *
     * @throws StepUpMfaException If the code is wrong
     * @throws PropelAuthException On any other API failure
     */
    public function verifySmsCode(string $userId, string $challengeId, string $code): string
    {
        return $this->stepUp(fn () => $this->makeRequest('POST', '/api/backend/v1/mfa/step-up/phone/verify', [
            'challengeId' => $challengeId,
            'userId' => $userId,
            'code' => $code,
        ]), $userId)['stepUpGrant'];
    }

    /**
     * Whether a step-up grant is valid for this user and action. A one-time grant is used up by this call.
     *
     * @throws PropelAuthException On an API failure other than an unknown grant
     */
    public function verifyGrant(string $userId, string $actionType, string $grant): bool
    {
        try {
            $this->stepUp(fn () => $this->makeRequest('POST', '/api/backend/v1/mfa/step-up/verify-grant', [
                'actionType' => $actionType,
                'userId' => $userId,
                'grant' => $grant,
            ]), $userId);
        } catch (ValidationException $e) {
            if (($e->getErrors()['field_errors']['grant'] ?? null) === 'grant_not_found') {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * Run a step-up call, turning PropelAuth's error_code into the matching exception.
     *
     * @return array<string, mixed>
     */
    protected function stepUp(\Closure $request, string $userId): array
    {
        try {
            return $request();
        } catch (RateLimitException $e) {
            throw $e;
        } catch (PropelAuthException $e) {
            $body = $e instanceof ValidationException ? $e->getErrors() : json_decode($e->getContext()['response_body'] ?? '', true);
            $errorCode = is_array($body) ? ($body['error_code'] ?? null) : null;

            throw match ($errorCode) {
                null, 'invalid_request_fields' => $e,
                'user_not_found' => InvalidUserException::notFound($userId),
                'feature_gated' => new FeatureNotEnabledException(
                    'Step-up MFA is not available on your PropelAuth plan',
                    $e->getStatusCode(),
                    $e->getContext(),
                    $e,
                ),
                'unauthorized' => $e,
                default => new StepUpMfaException(
                    "Step-up MFA failed: {$errorCode}",
                    $errorCode,
                    $e->getStatusCode(),
                    $e->getContext(),
                    $e,
                ),
            };
        }
    }
}
