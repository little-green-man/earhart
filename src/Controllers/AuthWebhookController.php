<?php

namespace LittleGreenMan\Earhart\Controllers;

use Illuminate\Http\Request;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgApiKeyDeleted;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgCreated;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgDeleted;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgSamlRemoved;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgSamlSetup;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgSamlWentLive;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgScimGroupCreated;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgScimGroupDeleted;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgScimGroupUpdated;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgScimKeyCreated;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgScimKeyRevoked;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgUpdated;
use LittleGreenMan\Earhart\Events\PropelAuth\UserAddedToOrg;
use LittleGreenMan\Earhart\Events\PropelAuth\UserAddedToScimGroup;
use LittleGreenMan\Earhart\Events\PropelAuth\UserCreated;
use LittleGreenMan\Earhart\Events\PropelAuth\UserDeleted;
use LittleGreenMan\Earhart\Events\PropelAuth\UserDeletedPersonalApiKey;
use LittleGreenMan\Earhart\Events\PropelAuth\UserDisabled;
use LittleGreenMan\Earhart\Events\PropelAuth\UserEnabled;
use LittleGreenMan\Earhart\Events\PropelAuth\UserImpersonated;
use LittleGreenMan\Earhart\Events\PropelAuth\UserInvitedToOrg;
use LittleGreenMan\Earhart\Events\PropelAuth\UserLocked;
use LittleGreenMan\Earhart\Events\PropelAuth\UserLoggedOut;
use LittleGreenMan\Earhart\Events\PropelAuth\UserLogin;
use LittleGreenMan\Earhart\Events\PropelAuth\UserRemovedFromOrg;
use LittleGreenMan\Earhart\Events\PropelAuth\UserRemovedFromScimGroup;
use LittleGreenMan\Earhart\Events\PropelAuth\UserRoleChangedWithinOrg;
use LittleGreenMan\Earhart\Events\PropelAuth\UserSendMfaPhoneCode;
use LittleGreenMan\Earhart\Events\PropelAuth\UserUpdated;

class AuthWebhookController
{
    /**
     * Handle the incoming webhook request.
     *
     * Dispatches appropriate events based on the webhook event_type.
     * Supports all PropelAuth webhook events including user, organization,
     * SAML, and SCIM-related webhooks.
     */
    public function __invoke(Request $request)
    {
        $data = $request->json()->all();
        $eventType = $data['event_type'] ?? null;

        match ($eventType) {
            // Organization events
            'org.created' => OrgCreated::dispatch($data),
            'org.deleted' => OrgDeleted::dispatch($data),
            'org.updated' => OrgUpdated::dispatch($data),
            'org.api_key_deleted' => OrgApiKeyDeleted::dispatch($data),
            'org.saml_removed' => OrgSamlRemoved::dispatch($data),
            'org.saml_setup' => OrgSamlSetup::dispatch($data),
            'org.saml_went_live' => OrgSamlWentLive::dispatch($data),
            'org.scim_group_created' => OrgScimGroupCreated::dispatch($data),
            'org.scim_group_deleted' => OrgScimGroupDeleted::dispatch($data),
            'org.scim_group_updated' => OrgScimGroupUpdated::dispatch($data),
            'org.scim_key_created' => OrgScimKeyCreated::dispatch($data),
            'org.scim_key_revoked' => OrgScimKeyRevoked::dispatch($data),
            // User events
            'user.created' => UserCreated::dispatch($data),
            'user.updated' => UserUpdated::dispatch($data),
            'user.deleted' => UserDeleted::dispatch($data),
            'user.enabled' => UserEnabled::dispatch($data),
            'user.disabled' => UserDisabled::dispatch($data),
            'user.locked' => UserLocked::dispatch($data),
            'user.added_to_org' => UserAddedToOrg::dispatch($data),
            'user.removed_from_org' => UserRemovedFromOrg::dispatch($data),
            'user.role_changed_within_org' => UserRoleChangedWithinOrg::dispatch($data),
            'user.added_to_scim_group' => UserAddedToScimGroup::dispatch($data),
            'user.removed_from_scim_group' => UserRemovedFromScimGroup::dispatch($data),
            'user.deleted_personal_api_key' => UserDeletedPersonalApiKey::dispatch($data),
            'user.impersonated' => UserImpersonated::dispatch($data),
            'user.invited_to_org' => UserInvitedToOrg::dispatch($data),
            'user.logged_out' => UserLoggedOut::dispatch($data),
            'user.login' => UserLogin::dispatch($data),
            'user.send_mfa_phone_code' => UserSendMfaPhoneCode::dispatch($data),
            default => null,
        };

        return response()->json(['message' => 'Webhook received', 'event_type' => $eventType]);
    }
}
