<?php

namespace LittleGreenMan\Earhart\Events\PropelAuth;

use Illuminate\Foundation\Events\Dispatchable;

class UserRemovedFromScimGroup
{
    use Dispatchable;

    public string $user_id;

    public string $scim_group_id;

    public string $org_id;

    /**
     * Create a new event instance.
     */
    public function __construct(array $data)
    {
        $this->user_id = $data['user_id'];
        $this->scim_group_id = $data['scim_group_id'];
        $this->org_id = $data['org_id'];
    }
}
