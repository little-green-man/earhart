<?php

namespace LittleGreenMan\Earhart\Events\PropelAuth;

use Illuminate\Foundation\Events\Dispatchable;

class UserRemovedFromOrg
{
    use Dispatchable;

    public string $removed_user_id;

    public string $org_id;

    /**
     * Create a new event instance.
     */
    public function __construct(
        array $data
    ) {
        $this->removed_user_id = $data['removed_user_id'];
        $this->org_id = $data['org_id'];
    }
}
