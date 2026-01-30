<?php

namespace LittleGreenMan\Earhart\Events\PropelAuth;

use Illuminate\Foundation\Events\Dispatchable;

class OrgScimKeyRevoked
{
    use Dispatchable;

    public string $org_id;

    public string $scim_key_id;

    /**
     * Create a new event instance.
     */
    public function __construct(array $data)
    {
        $this->org_id = $data['org_id'];
        $this->scim_key_id = $data['scim_key_id'];
    }
}
