<?php

namespace LittleGreenMan\Earhart;

use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\OrganisationsData;
use LittleGreenMan\Earhart\PropelAuth\UserData;
use LittleGreenMan\Earhart\PropelAuth\UsersData;

class Earhart
{
    public function __construct(
        protected string $clientId,
        protected string $clientSecret,
        protected string $callbackUrl,
        protected string $authUrl,
        protected string $svixSecret,
        protected string $apiKey
    ) {
    }

    public function getOrganisations()
    {
        return OrganisationsData::from(
            Http::withToken($this->apiKey)
                ->get($this->authUrl . '/api/backend/v1/org/query', [
                    'pageSize' => 1000
                ])->json()
        );
    }

    public function getOrganisation(string $id)
    {
        return OrganisationData::from(
            Http::withToken($this->apiKey)->get($this->authUrl . '/api/backend/v1/org/' . $id)->json()
        );
    }

    public function getUsersInOrganisation(string $organisationId)
    {
        return UsersData::from(
            Http::withToken($this->apiKey)
                ->get($this->authUrl . '/api/backend/v1/user/org/' . $organisationId, [
                    'pageSize' => 1000,
                    'includeOrgs' => false,
                ])->json()
        );
    }

    public function getUser(string $userId)
    {
        return UserData::from(
            Http::withToken($this->apiKey)
                ->get($this->authUrl . '/api/backend/v1/user/' . $userId)->json()
        );
    }
}
