# PropelAuth API audit

**Date:** 6 October 2026
**Status:** All 3.0.0 fixes in section 5 are implemented on `release/3.0.0` (2.5 as the optional filters only, keeping `GET`). The section 4 endpoints marked 3.1 are implemented on `release/3.1.0`; end-user API keys and insights remain.
**Scope:** `little-green-man/earhart` on branch `release/3.0.0` (commit `92651b0`), compared with the PropelAuth backend API reference.
**Method:** Each method in `UserService` and `OrganisationService` was traced through `BaseApiService`'s camelCase-to-snake_case key conversion and compared with the **cURL** examples in PropelAuth's docs. The cURL tabs were used because they show the request and response shape on the wire; the JavaScript and Python tabs use SDK names, which sometimes differ (for example `enableAutoJoiningByDomain` in JavaScript is `autojoin_by_domain` on the wire).

Every finding was then cross-checked against two further sources: PropelAuth's Postman collection and their official SDKs (section 0). Nothing was tested against a live PropelAuth environment. Findings marked **confirmed** are backed by the docs and at least one SDK.

## Summary

- Most existing calls send the correct snake_case fields. The automatic key conversion works for the common cases.
- **Fifteen defects** were found: wrong endpoints, methods, field names or response keys, plus two issues in the library's own logic. Four of them together mean the authentication and organisation middleware probably reject every request.
- **Many optional parameters** are missing from existing methods.
- **Several endpoint groups** aren't covered at all.

Recommendation: fix the defects (section 2) and add the missing parameters (section 3) in 3.0.0, which is unreleased and already a major version. Add the missing endpoints (section 4) in 3.1.

## References

| Area | URL |
| --- | --- |
| Getting started | https://docs.propelauth.com/reference/api/getting-started |
| User APIs | https://docs.propelauth.com/reference/api/user |
| Organisation APIs | https://docs.propelauth.com/reference/api/org |
| Enterprise SSO APIs | https://docs.propelauth.com/reference/api/enterprise-sso |
| End-user API key APIs | https://docs.propelauth.com/reference/api/apikey |
| Social login APIs | https://docs.propelauth.com/reference/api/social-login |
| Step-up MFA APIs | https://docs.propelauth.com/reference/api/mfa |
| Insights APIs | https://docs.propelauth.com/reference/api/insights |
| OAuth2 APIs | https://docs.propelauth.com/reference/api/oauth2 |
| MCP APIs | https://docs.propelauth.com/reference/api/mcp |
| Postman collection | https://docs.propelauth.com/files/PropelAuth.postman_collection.json |
| Official Node SDK (backend API client) | https://github.com/PropelAuth/node-apis |
| Official Python SDK | https://github.com/PropelAuth/propelauth-py |

---

## 0. Verification against the Postman collection and official SDKs

### Sources

| Source | Version checked | How current | Notes |
| --- | --- | --- | --- |
| Docs (cURL tabs) | 6 October 2026 | Current | Some errors (see below) |
| Postman collection | as downloaded 6 October 2026 | Stale: example data dates from April 2024 | Has example responses for most user and org endpoints. Doesn't cover SCIM, OIDC, employee, insights, `clear_password`, `invite_user_by_id`, `isolate_org` or signup parameters. |
| `PropelAuth/node-apis` | `3db3448`, 20 August 2026 | Current, most complete | Every endpoint in the docs except `isolate_org`, plus several undocumented ones |
| `PropelAuth/propelauth-py` | `20487b0`, 21 April 2026 | Current | Used to break ties and for token validation |

`node-apis` is the closest thing to a feature-complete official reference, and is what this audit treats as authoritative where sources disagree. Both SDKs send requests to `https://propelauth-api.com` with an `X-Propelauth-url` header naming the Auth URL, rather than calling the Auth URL directly. The docs say the Auth URL works, so Earhart's approach isn't a defect.

### Where the sources disagree

| Point | Docs (cURL) | Postman | Node SDK | Python SDK | Conclusion |
| --- | --- | --- | --- | --- | --- |
| Query orgs method | `POST`, JSON body | `GET`, query string | `POST`, JSON body | `GET`, query string | The server evidently accepts both. **2.5 is not a defect** (see below). |
| Create org: domain flags | `autojoin_by_domain`, `restrict_to_domain` | `enable_auto_joining_by_domain`, `members_must_have_matching_domain` | `enable_auto_joining_by_domain`, `members_must_have_matching_domain` | `enable_auto_joining_by_domain` | **The docs are wrong for create.** Use `enable_auto_joining_by_domain` and `members_must_have_matching_domain`. |
| Update org: domain flags | `autojoin_by_domain`, `restrict_to_domain` | `enable_auto_joining_by_domain`, `members_must_have_matching_domain` | `autojoin_by_domain`, `restrict_to_domain` | `autojoin_by_domain`, `restrict_to_domain` | **Postman is stale.** Use `autojoin_by_domain` and `restrict_to_domain`. |
| Migrate user: password flag | `update_password_required` | `update_password_required` | `update_password_required` (SDK name `askUserToUpdatePasswordOnLogin`) | — | `update_password_required` |
| `isolate_org` | documented | absent | absent | absent | Docs only. **Unverified**; keep Earhart's method, but flag it as such. |

### Verdict on each finding

| Finding | Verdict | Evidence |
| --- | --- | --- |
| 2.1 Token validation | **Confirmed** | No source has `/user/me`. Both SDKs fetch `/api/v1/token_verification_metadata` and verify an RS256 JWT locally. Python checks `exp`, `iat` and `iss` (not `aud`) with 60 s leeway. The Postman access-token example decodes to claims `sub`, `iat`, `exp`, `iss`, `user_id`, `email`, `first_name`, `last_name`, `username`, `properties` and `org_id_to_org_member_info`; Python also reads `legacy_user_id`, `impersonator_user_id`, `login_method` and `org_member_info` (active org). |
| 2.2 `org_id_to_org_info` | **Confirmed** | Postman fetch-by-ID example returns `org_id_to_org_info` |
| 2.3 `include_orgs` | **Confirmed** | Postman requests `?include_orgs=true`; Node defaults it to `false` |
| 2.4 Middleware | **Confirmed** | Follows from 2.1–2.3 |
| 2.5 Query orgs method | **Withdrawn** | Python SDK and Postman use `GET`; Node and the docs use `POST`. Earhart's `GET` matches a maintained SDK. Optional: switch to `POST` to match the newer SDK. |
| 2.6 Role mappings key | **Confirmed** | Postman, Node and Python all read `custom_role_mappings` |
| 2.7 Role mapping field | **Confirmed** | Postman, Node and Python all send `custom_role_mapping_name` |
| 2.8 SAML IdP body | **Confirmed** | Postman and Node send `idp_entity_id`, `idp_sso_url`, `idp_certificate`, `provider`, `org_id` |
| 2.9 SAML SP metadata | **Confirmed** | Postman example returns `entity_id`, `acs_url`, `logout_url` |
| 2.10 Create org fields | **Confirmed, with corrected names** | See the disagreement table |
| 2.11 `OrganisationData` | **Confirmed** | Postman fetch-org example includes `max_users` and `domain` |
| 2.12 `role` required | **Confirmed** | Postman bodies include it; Node types it as `role: string` (not optional) |
| 2.13 Key conversion | **Confirmed** | Postman shows user-defined keys inside `org_metadata` and `properties` |
| 2.14 Pending invites | **Confirmed** | Postman example returns `total_invites`, `current_page`, `page_size` |

### What the extra sources added

- **2.15:** a 426 status when B2B is disabled (new defect, below).
- **2.16:** response fields not covered by the DTOs (new defect, below).
- **Empty and plain-text success bodies.** `resend_email_confirmation` and `logout_all_sessions` return 204 with no body, and `invite_user` returns 200 with a plain-text body ("Invitation email sent…"). Earhart treats any non-JSON body as `[]`, so these already work; worth a regression test.
- **404 handling differs from the SDKs.** Node returns `false` on a 404 from `disableUser`, `enableUser`, `deleteUser` and the like, rather than throwing. Earhart 3.0 deliberately throws instead (proposal 2 of the 3.0 changes), so this is a design difference, not a defect.
- **Undocumented endpoints.** These are used by the Node SDK but absent from the docs (added to section 4):
  - batch fetch: `POST /api/backend/v1/user/user_ids`, `/user/emails` and `/user/usernames`, each with an optional `?include_orgs=true`
  - `PUT /api/backend/v1/user/{user_id}/can_create_orgs/enable` and `…/disable`
- **End-user API key validation.** It sends `api_key_token`, not `api_key`. This matters when that service is built.

---

## 1. Background: how keys are converted

`BaseApiService::makeRequest()` converts outgoing keys from camelCase to snake_case and incoming keys from snake_case to camelCase, recursively. The keys inside `properties`, `metadata` and `orgs` are left as they are, because they hold user-defined data ([BaseApiService.php:53](../src/Services/BaseApiService.php:53), [BaseApiService.php:86](../src/Services/BaseApiService.php:86)).

So a PHP payload key `firstName` is sent as `first_name`, and a response key `org_id_to_org_info` arrives as `orgIdToOrgInfo`. The findings below take this into account.

---

## 2. Defects

### 2.1 Token validation calls an endpoint no PropelAuth source uses (confirmed; critical)

- **Where:** `UserService::validateToken()`, [UserService.php:36](../src/Services/UserService.php:36), used by `VerifyPropelAuthUser`.
- **Library:** `GET /api/backend/v1/user/me?token=…`
- **API:** This endpoint isn't in the docs, the Postman collection or either official SDK. PropelAuth's backend libraries verify access tokens locally, with no request per token. They fetch the verification key once from `GET /api/v1/token_verification_metadata` (API-key auth; response field `verifier_key_pem`), then verify the access token as an RS256 JWT whose issuer is the Auth URL.
- **Effect:** `VerifyPropelAuthUser` very likely returns 401 for every token.
- **Proposed fix:**
  - Add a `TokenVerifier` that fetches and caches `verifier_key_pem`, then verifies the JWT's signature, expiry and issuer, using `firebase/php-jwt` (a new dependency).
  - Build the user from the token's claims (`user_id`, `email`, `org_id_to_org_member_info`, …), with an option to fetch the full `UserData` with `getUser()`.
  - Add a `token_verification.verifier_key` config override for environments that can't make the metadata request.
  - Keep `validateToken()` as the public method, but give it the new implementation.

### 2.2 Organisation memberships are read from a key the API doesn't send (confirmed; critical)

- **Where:** `UserData::fromArray()`, [UserData.php:56](../src/PropelAuth/UserData.php:56).
- **Library:** reads `$data['orgs']`.
- **API:** memberships are returned under `org_id_to_org_info`, keyed by org ID. After conversion this is `orgIdToOrgInfo`. Each entry has `org_id`, `org_name`, `org_metadata`, `user_role`, `user_permissions`, `url_safe_org_name`, `inherited_user_roles_plus_current_role`, `org_role_structure` and `additional_roles`.

  ```json
  "org_id_to_org_info": {
      "1189c444-…": {
          "org_id": "1189c444-…",
          "org_name": "Acme Inc",
          "user_role": "Admin",
          "user_permissions": ["CanViewBilling"],
          "additional_roles": ["Member"]
      }
  }
  ```

- **Effect:** `UserData::$orgs` is always empty for real API responses.
- **Proposed fix:**
  - Map `orgIdToOrgInfo` into `UserData::$orgs`, keyed by org ID.
  - Add a typed `OrgMemberInfo` DTO (`orgId`, `orgName`, `userRole`, `userPermissions`, `additionalRoles`, `inheritedRoles`, `orgMetadata`, `urlSafeOrgName`).
  - Add helpers: `UserData::org(string $orgId): ?OrgMemberInfo`, `isMemberOf()`, `hasRole()`, `hasPermission()`.
  - Add `orgIdToOrgInfo` to the list of keys left unconverted, and convert each entry by hand, keeping `org_metadata` unconverted (see 2.13).

### 2.3 Fetch user by ID and query users don't ask for memberships (confirmed; critical)

- **Where:** `fetchUserFromAPI()`, [UserService.php:440](../src/Services/UserService.php:440), and `queryUsers()`, [UserService.php:82](../src/Services/UserService.php:82).
- **Library:** no `include_orgs` parameter.
- **API:** `GET /api/backend/v1/user/{user_id}?include_orgs=true`. Without it, `org_id_to_org_info` isn't returned (the cURL example for fetch by ID doesn't contain it).
- **Effect:** even after 2.2 is fixed, `getUser()` returns no memberships.
- **Proposed fix:** add `bool $includeOrgs = true` to `getUser()` and `queryUsers()`, and send it as `include_orgs`. Include it in the cache key so cached entries with and without memberships stay separate.

### 2.4 Organisation middleware matches the wrong key (confirmed; critical)

- **Where:** `VerifyPropelAuthOrg::userBelongsToOrg()`, [VerifyPropelAuthOrg.php:71](../src/Middleware/VerifyPropelAuthOrg.php:71). `VerifyPropelAuthPermission` ([VerifyPropelAuthPermission.php:84](../src/Middleware/VerifyPropelAuthPermission.php:84)) accepts `id` or `orgId`, but depends on 2.1–2.3.
- **Library:** `VerifyPropelAuthOrg` only checks `$org['id']`.
- **API:** entries carry `org_id` (`orgId` after conversion), and the map is keyed by org ID.
- **Effect:** combined with 2.1–2.3, every organisation and permission check fails.
- **Proposed fix:** use the `UserData` helpers from 2.2 in both middleware. Also check `inherited_user_roles_plus_current_role` and `user_permissions` rather than the hard-coded owner/admin/member order, so custom role structures work.

### 2.5 Querying organisations uses the wrong HTTP method (withdrawn; see section 0)

> **Withdrawn.** The Python SDK and the Postman collection both use `GET` with query parameters, as Earhart does, while the Node SDK and the docs use `POST`. The server evidently accepts both. Switching to `POST` and adding the filters below is optional.


- **Where:** `OrganisationService::queryOrganisations()`, [OrganisationService.php:47](../src/Services/OrganisationService.php:47). Also used by `Earhart::getOrganisations()`.
- **Library:** `GET /api/backend/v1/org/query?order_by=…&page_number=…&page_size=…`
- **API:**

  ```bash
  curl -X "POST" -d '{"page_size": 10, "page_number": 0, "order_by": "CREATED_AT_ASC", "name": "acme", "legacy_org_id": "1234", "domain": "example.com"}' \
      "<AUTH_URL>/api/backend/v1/org/query"
  ```

- **Effect:** none, given the Python SDK uses `GET`.
- **Proposed fix:** add the optional `name`, `legacyOrgId` and `domain` filters. Optionally switch to `POST` to match the current Node SDK.

### 2.6 Role mappings are read from the wrong key (confirmed; medium)

- **Where:** `getRoleMappings()`, [OrganisationService.php:231](../src/Services/OrganisationService.php:231).
- **Library:** reads `roleMappings`.
- **API:** `{"custom_role_mappings": [{"custom_role_mapping_name": "Free Plan", "num_orgs_subscribed": 2}]}`
- **Effect:** always returns `[]`.
- **Proposed fix:** read `customRoleMappings`, and return a list of `{customRoleMappingName, numOrgsSubscribed}` (or a small DTO).

### 2.7 Subscribing to a role mapping sends the wrong field (confirmed; medium)

- **Where:** `subscribeOrgToRoleMapping()`, [OrganisationService.php:243](../src/Services/OrganisationService.php:243).
- **Library:** sends `custom_role_mapping_id`.
- **API:** `PUT /api/backend/v1/org/{org_id}` with `{"custom_role_mapping_name": "Paid Plan"}`.
- **Effect:** the subscription doesn't change, or the request is rejected.
- **Proposed fix:** send `customRoleMappingName`, and rename the parameter from `$mappingId` to `$mappingName` (breaking for anyone calling it with a named argument).

### 2.8 Setting SAML IdP metadata sends the wrong body (confirmed; medium)

- **Where:** `setSAMLIdPMetadata()`, [OrganisationService.php:344](../src/Services/OrganisationService.php:344).
- **Library:** `{"org_id": …, "idp_metadata": "<xml…>"}`
- **API:**

  ```json
  {
      "org_id": "1189c444-…",
      "idp_entity_id": "https://sts.windows.net/SOME-UUID/",
      "idp_sso_url": "https://login.microsoftonline.com/SOME-UUID/saml2",
      "idp_certificate": "-----BEGIN CERTIFICATE-----…",
      "provider": "Azure"
  }
  ```

- **Effect:** the call fails or has no effect.
- **Proposed fix:** change the signature to `setSAMLIdPMetadata(string $orgId, string $idpEntityId, string $idpSsoUrl, string $idpCertificate, string $provider)` (breaking).

### 2.9 Fetching SAML SP metadata reads the wrong key (confirmed; medium)

- **Where:** `fetchSAMLMetadata()`, [OrganisationService.php:335](../src/Services/OrganisationService.php:335).
- **Library:** returns `$response['metadata'] ?? ''`.
- **API:** `{"entity_id": "…/metadata", "acs_url": "…/acs", "logout_url": "…/logout"}`
- **Effect:** always returns `''`.
- **Proposed fix:** return a `SamlSpMetadata` DTO (`entityId`, `acsUrl`, `logoutUrl`). This changes the return type (breaking).

### 2.10 Creating an organisation sends fields the API doesn't accept (confirmed; medium)

- **Where:** `createOrganisation()`, [OrganisationService.php:93](../src/Services/OrganisationService.php:93).
- **Library:** `name`, `url_safe_org_slug`, `metadata`.
- **API:** confirmed by Postman, Node and Python. The docs' cURL example uses `autojoin_by_domain` / `restrict_to_domain` here, which are the *update* field names; that example is wrong.

  ```json
  {"name": "Acme Inc", "domain": "acme.com", "enable_auto_joining_by_domain": true, "members_must_have_matching_domain": true,
   "max_users": 100, "legacy_org_id": "1234", "custom_role_mapping_name": "Business Plan"}
  ```

  The response is `{"org_id": …, "name": …}`.
- **Effect:** the slug and metadata are ignored or rejected, and the supported fields can't be set.
- **Proposed fix:** change the signature to `createOrganisation(string $name, ?string $domain = null, ?bool $enableAutoJoiningByDomain = null, ?bool $membersMustHaveMatchingDomain = null, ?int $maxUsers = null, ?string $legacyOrgId = null, ?string $customRoleMappingName = null)` (breaking). Callers who need metadata follow up with `updateOrganisation()`.

### 2.11 `OrganisationData` doesn't map several fields (confirmed; medium)

- **Where:** [OrganisationData.php:26](../src/PropelAuth/OrganisationData.php:26), [OrganisationData.php:47](../src/PropelAuth/OrganisationData.php:47).
- **Library:** `maxOrgMembers` reads `$data['maxOrgMembers']`, a key the API never sends.
- **API (fetch org):** `org_id`, `name`, `url_safe_org_slug`, `can_setup_saml`, `is_saml_configured`, `is_saml_in_test_mode`, `domain`, `extra_domains`, `domain_autojoin`, `domain_restrict`, `max_users`, `custom_role_mapping_name`, `legacy_org_id`, `isolated`, `metadata`, `password_rotation_enabled`, `password_rotation_history_size`, `password_rotation_period`. Organisation query results also include `created_at`.
- **Effect:** `maxOrgMembers` is always null. `domain`, `legacyOrgId`, `isolated` and the password-rotation fields can't be read.
- **Proposed fix:** rename `maxOrgMembers` to `maxUsers`, read from `maxUsers` (breaking), and add `domain`, `legacyOrgId`, `isolated`, `passwordRotationEnabled`, `passwordRotationHistorySize` and `passwordRotationPeriod`.

### 2.12 `role` is optional where the API requires it (confirmed; low)

- **Where:** `addUserToOrganisation()`, [OrganisationService.php:151](../src/Services/OrganisationService.php:151), and `inviteUserToOrganisation()`, [OrganisationService.php:173](../src/Services/OrganisationService.php:173).
- **Library:** `?string $role = null`, which is dropped from the payload when null.
- **API:** `role` is required on `org/add_user` and `invite_user`.
- **Effect:** calls without a role are rejected with a `ValidationException`.
- **Proposed fix:** make `string $role` required, and add `array $additionalRoles = []` (see section 3). This is breaking for callers who omit the role, but those calls fail today anyway.

### 2.13 User-defined keys in responses get renamed (confirmed; low)

- **Where:** `BaseApiService::toCamelCase()`, [BaseApiService.php:86](../src/Services/BaseApiService.php:86).
- **Library:** only `properties`, `metadata` and `orgs` are left unconverted.
- **API:** two more fields hold user-defined keys:
  - `user_signup_query_parameters`, returned by `getUserSignupParams()` and sent with magic links
  - `org_metadata`, inside each `org_id_to_org_info` entry
- **Effect:** `{"query_param_example": "x"}` comes back as `{"queryParamExample": "x"}`, and org metadata keys are renamed.
- **Proposed fix:** add `user_signup_query_parameters`, `org_metadata` and `org_id_to_org_info` (whose keys are org IDs) to the list of keys left unconverted, in both directions.

### 2.14 Pending invites can't be paged, and `allPages()` can loop forever (confirmed; medium)

- **Where:** `getPendingInvites()`, [OrganisationService.php:255](../src/Services/OrganisationService.php:255). The next-page function is on line 266.
- **Library:** sends no `page_size` or `page_number`. The next-page function ignores the page number it's given, `fn (int $nextPage) => $this->getPendingInvites($orgId)`, so each "next page" re-fetches the same page. `total_invites` isn't read.
- **API:** `GET /api/backend/v1/pending_org_invites?org_id=…&page_size=10&page_number=0`, returning `invites`, `total_invites`, `page_size`, `current_page` and `has_more_results`.
- **Effect:** if `has_more_results` is true, `allPages()` never returns. `totalItems` only counts the current page.
- **Proposed fix:** add `int $pageSize = 10, int $pageNumber = 0`, pass `$nextPage` through, and have `PaginatedResult::from()` read `totalInvites`. Optionally add a `PendingInvite` DTO (`inviteeEmail`, `orgId`, `orgName`, `roleInOrg`, `additionalRolesInOrg`, `createdAt`, `expiresAt`, `inviterEmail`, `inviterUserId`).

### 2.15 "B2B not enabled" is reported as a generic error (confirmed; low)

- **Where:** all organisation calls, via `PropelAuthException::forStatus()`.
- **Library:** a 426 becomes a plain `PropelAuthException` with status 426.
- **API:** PropelAuth returns 426 on organisation endpoints when B2B support isn't enabled for the project. The Node SDK reports this as "Cannot use organizations unless B2B support is enabled. Enable it in your PropelAuth dashboard."
- **Effect:** a configuration problem looks like an unexplained API error.
- **Proposed fix:** map 426 to a `FeatureNotEnabledException` (a subclass of `PropelAuthException`) with that explanation.

### 2.16 Response fields that aren't mapped (confirmed; low)

- **Where:** `UserData`, and `getOrganisationUsers()`.
- **API (Postman examples):**
  - User responses include a top-level `metadata` object (separate from `properties`) and, when set, `legacy_user_id`.
  - Each user returned by `GET /user/org/{org_id}` includes `role_in_org`.
- **Effect:** these values are dropped.
- **Proposed fix:**
  - Add `?array $metadata` and `?string $legacyUserId` to `UserData`.
  - Expose `role_in_org`, either as `UserData::$roleInOrg` (null outside that call) or by returning `OrgMember` items from `getOrganisationUsers()`.

---

## 3. Missing parameters on existing methods

All names are as sent on the wire (cURL). Adding them is non-breaking if they go at the end of each signature as optional parameters, apart from where noted.

| Method | Missing |
| --- | --- |
| `createUser` | `email_confirmed`, `ignore_domain_restrictions`, `ask_user_to_update_password_on_login` |
| `getUser` | `include_orgs` (see 2.3) |
| `getUserByEmail`, `getUserByUsername` | `isolated_org_id` |
| `queryUsers` | `legacy_user_id`, `include_orgs`, `isolated_org_id` |
| `createMagicLink` | `expire_after_first_use`, `requires_interstitial`, `user_signup_query_parameters` (left unconverted, see 2.13) |
| `migrateUserFromExternal` | `update_password_required`, `enabled`, `picture_url` |
| `getOrganisationUsers` | `role` filter |
| `queryOrganisations` | `name`, `legacy_org_id`, `domain` (see 2.5); `order_by` accepts `CREATED_AT_ASC`, `CREATED_AT_DESC`, `NAME` |
| `updateOrganisation` | `domain`, `extra_domains`, `autojoin_by_domain`, `restrict_to_domain`, `max_users`, `can_setup_saml`, `legacy_org_id`, `sso_trust_level`, `require_2fa_by`, `password_rotation_enabled`, `password_rotation_history_size`, `password_rotation_period` |
| `addUserToOrganisation`, `inviteUserToOrganisation`, `changeUserRole` | `additional_roles` |
| `createSAMLConnectionLink` | `expires_in_seconds` |
| `getPendingInvites` | `page_size`, `page_number` (see 2.14) |
| `UserData` (response) | `legacy_user_id` |

**Note on `require_2fa_by`:** the converter splits only on a lowercase letter or digit followed by a capital, so `require2faBy` would be sent as `require2fa_by`. When this field is added, pass it with an explicit key, or add the PHP-to-API name mapping.

**Note on `updateOrganisation`:** with this many optional fields, take named arguments, or an `array $attributes` validated against the allowed field names.

---

## 4. Endpoints not covered

| Area | Endpoint | Notes | Suggested release |
| --- | --- | --- | --- |
| Users | `POST /api/backend/v1/user/user_ids`, `/user/emails`, `/user/usernames` (body `{"user_ids": [...]}` etc., optional `?include_orgs=true`) | Batch fetch. **Undocumented**; used by the Node SDK. Returns a list of users. | 3.1 |
| Users | `PUT /api/backend/v1/user/{user_id}/can_create_orgs/enable` and `/disable` | **Undocumented**; used by the Node SDK | 3.1 |
| Organisations | `POST /api/backend/v1/invite_user_by_id` | `user_id`, `org_id`, `role`, `additional_roles` | 3.1 |
| Enterprise SSO | `POST /api/backend/v1/oidc_idp_metadata` | `org_id`, `client_id`, `client_secret`, `uses_pkce`, `idp_type`, plus optional `okta_sso_domain`, `entra_tenant_id`, `auth_url`, `token_url`, `userinfo_url` | 3.1 |
| Enterprise SSO | `GET /api/backend/v1/scim/{org_id}/groups` | `user_id`, `page_size`, `page_number`; returns `groups[]` with `group_id`, `display_name`, `external_id_from_idp`. Answers whether a user is provisioned through SCIM. | 3.1 |
| Enterprise SSO | `GET /api/backend/v1/scim/{org_id}/groups/{group_id}` | `members_page_size`, `members_page_number` | 3.1 |
| Users | `GET /api/backend/v1/employee/{employee_id}` | Returns `email`. Useful for showing who impersonated a user (`UserImpersonated` webhook). | 3.1 |
| Social login | `GET /api/backend/v1/user/{user_id}/oauth_token` | The user's tokens per OAuth provider | 3.1 |
| Social login | `GET /api/backend/v1/user/{user_id}/{provider}/fresh_token` | | 3.1 |
| Step-up MFA | `GET /api/backend/v1/user/{user_id}/mfa`; `POST …/mfa/step-up/verify-totp`, `…/phone/send`, `…/phone/verify`, `…/verify-grant` | A small `MfaService` | 3.1 |
| End-user API keys | 12 endpoints under `/api/backend/v1/end_user_api_keys` (create, validate, validate personal/org/imported, fetch, list active/archived, usage, import, update via `PATCH`, delete) | Needs a new `ApiKeyService` and `PATCH` support in `sendRequest()`. Already listed as not implemented in `docs/USING_PROPEL_API.md`. | 3.1 or 3.2 |
| Insights | `user_report/*`, `org_report/*`, `chart_metrics/{metric}` | Lower priority | Later |
| OAuth2 / MCP | `{AUTH_URL}/propelauth/oauth/*`, `{AUTH_URL}/oauth/2.1/*` | Browser and OAuth-client flows. Login is already handled by Socialite; out of scope for a backend client. | Not planned |

---

## 5. Proposed plan

### 3.0.0 (before tagging)

1. **Token validation (2.1):** local JWT verification with a cached verifier key; new `firebase/php-jwt` dependency.
2. **Memberships (2.2–2.4):** map `org_id_to_org_info`, add `OrgMemberInfo`, request `include_orgs`, and rebuild both org middleware on the new helpers.
3. **Endpoint and shape fixes (2.6–2.12, 2.14–2.16):** as described in each finding. 2.5 is optional.
4. **Key conversion (2.13):** extend the list of keys left unconverted.
5. **Missing parameters (section 3).**
6. **Fake:** update `EarhartFake` to match the corrected shapes. That means `orgIdToOrgInfo` entries, `OrgMemberInfo`, required roles, SAML DTOs, real token validation (`issueToken()` signs a JWT with a test key), and the new parameters.
7. **Tests:** each fix gets a test asserting the exact request (method, URL, query string or JSON body) against the Node SDK, and parsing the Postman example response. Add a contract test that loads the Postman collection, sends each covered method through `Http::fake()`, and checks the method, path and field names. Fields where the collection is known to be stale (section 0) are listed as exceptions.
8. **Docs:** update `UPGRADE-3.0.md` with the breaking signature changes:
   - `createOrganisation`
   - `setSAMLIdPMetadata`
   - `fetchSAMLMetadata`
   - `subscribeOrgToRoleMapping`
   - `OrganisationData::$maxOrgMembers` → `$maxUsers`
   - required `role`
   - `UserData::$orgs` shape

   Then update `CHANGELOG.md` and `docs/USING_PROPEL_API.md` to match.

### 3.1.0

- The section 4 endpoints marked 3.1: invite by user ID, OIDC IdP metadata, SCIM groups, employee lookup, social login tokens, step-up MFA.
- End-user API keys, if time allows; otherwise 3.2.

---

## 6. Breaking changes these fixes would add to 3.0.0

| Change | Who is affected |
| --- | --- |
| `UserData::$orgs` becomes a map of `OrgMemberInfo` keyed by org ID | Code reading `$user->orgs` directly. It is empty today with real API data, so in practice little breaks. |
| `createOrganisation()` parameters | Callers passing a slug or metadata |
| `setSAMLIdPMetadata()` parameters | All callers (the current call doesn't work) |
| `fetchSAMLMetadata()` returns a DTO instead of a string | All callers (currently always `''`) |
| `subscribeOrgToRoleMapping()` `$mappingId` → `$mappingName` | Callers using the named argument |
| `OrganisationData::$maxOrgMembers` → `$maxUsers` | Code reading the property (currently always null) |
| `role` required on add and invite | Callers omitting it (currently rejected by the API) |
| `validateToken()` verifies locally | No signature change. Needs the API key to be able to read the verification metadata, or the new verifier-key config. |
