# PropelAuth response fixtures

Example responses from PropelAuth's Postman collection
(https://docs.propelauth.com/files/PropelAuth.postman_collection.json), used to check that
Earhart parses real response shapes.

- `fetch_user_with_orgs.json` is the `user` object from the collection's "Validate API Key" example. It is the only example with populated `org_id_to_org_info`.
- `validate_api_key.json`, `fetch_api_key.json`, `active_api_keys.json` and `create_api_key.json` are the collection's API key examples.
- `top_inviter_report.json` and `chart_metrics.json` are the cURL examples from PropelAuth's insights docs (https://docs.propelauth.com/reference/api/insights), which the Postman collection doesn't cover.
- `access_token_claims.json` is the decoded payload of the collection's "Create Access Token" example.

The collection's example data dates from April 2024, so prefer PropelAuth's current docs and the official Node SDK (https://github.com/PropelAuth/node-apis) where they differ. See `docs/PROPELAUTH_API_AUDIT.md`.
