<?php

namespace LittleGreenMan\Earhart\Events\PropelAuth;

use Illuminate\Foundation\Events\Dispatchable;

class UserRoleChangedWithinOrg
{
    use Dispatchable;

    public string $new_role;

    public string $org_id;

    public string $user_id;

    /**
     * Create a new event instance.
     */
    public function __construct(
        array $data
    ) {
        $this->user_id = $data['user_id'];
        $this->org_id = $data['org_id'];
        $this->new_role = $data['new_role'];
    }
}
