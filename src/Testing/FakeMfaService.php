<?php

namespace LittleGreenMan\Earhart\Testing;

use Illuminate\Support\Str;
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\Exceptions\StepUpMfaException;
use LittleGreenMan\Earhart\PropelAuth\MfaSetup;
use LittleGreenMan\Earhart\PropelAuth\StepUpGrantType;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\MfaService;

/**
 * In-memory MfaService used by Earhart::fake(). Codes equal to FakeState::$validMfaCode pass.
 */
class FakeMfaService extends MfaService
{
    use RecordsCalls;

    public function __construct(FakeState $state)
    {
        parent::__construct('fake-api-key', 'https://auth.example.test', new CacheService(false));
        $this->state = $state;
    }

    public function getUserMfaMethods(string $userId): ?MfaSetup
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($userId) {
            $this->requireUser($userId);

            $setup = $this->state->mfa[$userId] ?? null;

            return $setup === null ? null : new MfaSetup($setup['type'], $setup['phoneNumbers']);
        });
    }

    public function verifyTotp(
        string $userId,
        string $code,
        string $actionType,
        StepUpGrantType $grantType = StepUpGrantType::OneTimeUse,
        int $validForSeconds = 300,
    ): string {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($userId, $code, $actionType, $grantType, $validForSeconds) {
            $this->requireMfa($userId, 'Totp');
            $this->checkCode($code);

            return $this->grant($userId, $actionType, $grantType, $validForSeconds);
        });
    }

    public function sendSmsCode(
        string $userId,
        string $mfaPhoneId,
        string $actionType,
        StepUpGrantType $grantType = StepUpGrantType::OneTimeUse,
        int $validForSeconds = 300,
    ): string {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($userId, $mfaPhoneId, $actionType, $grantType, $validForSeconds) {
            $setup = $this->requireMfa($userId, 'Phone');

            if (! isset($setup['phoneNumbers'][$mfaPhoneId])) {
                throw PropelAuthException::forStatus(400, 'PropelAuth API error: 400 on sendSmsCode (fake)', null, ['error_code' => 'invalid_request_fields']);
            }

            $challengeId = (string) Str::uuid();
            $this->state->smsChallenges[$challengeId] = [
                'userId' => $userId,
                'actionType' => $actionType,
                'grantType' => $grantType->value,
                'validForSeconds' => $validForSeconds,
            ];

            return $challengeId;
        });
    }

    public function verifySmsCode(string $userId, string $challengeId, string $code): string
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($userId, $challengeId, $code) {
            $challenge = $this->state->smsChallenges[$challengeId] ?? null;

            if ($challenge === null || $challenge['userId'] !== $userId) {
                throw PropelAuthException::forStatus(400, 'PropelAuth API error: 400 on verifySmsCode (fake)', null, ['error_code' => 'invalid_request_fields']);
            }

            $this->checkCode($code);
            unset($this->state->smsChallenges[$challengeId]);

            return $this->grant($userId, $challenge['actionType'], StepUpGrantType::from($challenge['grantType']), $challenge['validForSeconds']);
        });
    }

    public function verifyGrant(string $userId, string $actionType, string $grant): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($userId, $actionType, $grant) {
            $stored = $this->state->grants[$grant] ?? null;

            if ($stored === null
                || $stored['userId'] !== $userId
                || $stored['actionType'] !== $actionType
                || $stored['expiresAt'] < now()->getTimestamp()) {
                return false;
            }

            if ($stored['oneTimeUse']) {
                unset($this->state->grants[$grant]);
            }

            return true;
        });
    }

    protected function requireUser(string $userId): void
    {
        if (! isset($this->state->users[$userId])) {
            throw InvalidUserException::notFound($userId);
        }
    }

    /**
     * @return array{type: string, phoneNumbers: array<string, string>}
     */
    protected function requireMfa(string $userId, string $type): array
    {
        $this->requireUser($userId);
        $setup = $this->state->mfa[$userId] ?? null;

        if ($setup === null || $setup['type'] !== $type) {
            throw new StepUpMfaException('Step-up MFA failed: mfa_not_enabled', StepUpMfaException::MFA_NOT_ENABLED);
        }

        return $setup;
    }

    protected function checkCode(string $code): void
    {
        if ($code !== $this->state->validMfaCode) {
            throw new StepUpMfaException('Step-up MFA failed: incorrect_mfa_code', StepUpMfaException::INCORRECT_CODE);
        }
    }

    protected function grant(string $userId, string $actionType, StepUpGrantType $grantType, int $validForSeconds): string
    {
        $grant = Str::random(64);
        $this->state->grants[$grant] = [
            'userId' => $userId,
            'actionType' => $actionType,
            'oneTimeUse' => $grantType === StepUpGrantType::OneTimeUse,
            'expiresAt' => now()->getTimestamp() + $validForSeconds,
        ];

        return $grant;
    }
}
