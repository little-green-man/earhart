<?php

namespace LittleGreenMan\Earhart\Controllers;


use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgCreated;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgDeleted;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgUpdated;
use LittleGreenMan\Earhart\Events\PropelAuth\UserAddedToOrg;
use LittleGreenMan\Earhart\Events\PropelAuth\UserCreated;
use LittleGreenMan\Earhart\Events\PropelAuth\UserDeleted;
use LittleGreenMan\Earhart\Events\PropelAuth\UserDisabled;
use LittleGreenMan\Earhart\Events\PropelAuth\UserEnabled;
use LittleGreenMan\Earhart\Events\PropelAuth\UserLocked;
use LittleGreenMan\Earhart\Events\PropelAuth\UserRemovedFromOrg;
use LittleGreenMan\Earhart\Events\PropelAuth\UserRoleChangedWithinOrg;
use LittleGreenMan\Earhart\Events\PropelAuth\UserUpdated;
use LittleGreenMan\Earhart\Middleware\VerifySvixWebhook;
use Svix\Exception\WebhookVerificationException;
use Svix\Webhook;

class AuthWebhookController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $data = $request->json()->all();

        match ($data['type']) {
            'org.created' => OrgCreated::dispatch($data),
            'org.deleted' => OrgDeleted::dispatch($data),
            'org.updated' => OrgUpdated::dispatch($data),
            'user.added_to_org' => UserAddedToOrg::dispatch($data),
            'user.created' => UserCreated::dispatch($data),
            'user.deleted' => UserDeleted::dispatch($data),
            'user.disabled' => UserDisabled::dispatch($data),
            'user.enabled' => UserEnabled::dispatch($data),
            'user.locked' => UserLocked::dispatch($data),
            'user.removed_from_org' => UserRemovedFromOrg::dispatch($data),
            'user.updated' => UserUpdated::dispatch($data),
            'user.role_changed_within_org' => UserRoleChangedWithinOrg::dispatch($data),

            // Webhooks we don't handle yet
            // Feel free to raise a PR to add them!
            'org.api_key_deleted', 'org.saml_removed', 'org.saml_setup', 'org.saml_went_live', 'org.scim_group_created', 'org.scim_group_deleted', 'org.scim_group_updated', 'org.scim_key_created', 'org.scim_key_revoked', 'user.added_to_scim_group', 'user.deleted_personal_api_key', 'user.impersonated', 'user.invited_to_org', 'user.logged_out', 'user.login', 'user.removed_from_scim_group', 'user.send_mfa_phone_code' => null,
        };
        return response()->json(['message' => 'Webhook received', 'event_type' => $data['type']]);
    }
}
