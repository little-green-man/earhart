# PropelAuth response fixtures

Example responses from PropelAuth's Postman collection
(https://docs.propelauth.com/files/PropelAuth.postman_collection.json), used to check that
Earhart parses real response shapes.

- `fetch_user_with_orgs.json` is the `user` object from the collection's "Validate API Key" example. It is the only example with populated `org_id_to_org_info`.
- `access_token_claims.json` is the decoded payload of the collection's "Create Access Token" example.

The collection's example data dates from April 2024, so prefer PropelAuth's current docs and the official Node SDK (https://github.com/PropelAuth/node-apis) where they differ. See `docs/PROPELAUTH_API_AUDIT.md`.
