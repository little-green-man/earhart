<?php

namespace LittleGreenMan\Earhart\Events\PropelAuth;

use Illuminate\Foundation\Events\Dispatchable;

class OrgScimGroupCreated
{
    use Dispatchable;

    public string $org_id;

    public string $scim_group_id;

    public string $display_name;

    /**
     * Create a new event instance.
     */
    public function __construct(array $data)
    {
        $this->org_id = $data['org_id'];
        $this->scim_group_id = $data['scim_group_id'];
        $this->display_name = $data['display_name'];
    }
}
