<?php

namespace LittleGreenMan\Earhart\Events\PropelAuth;

use Illuminate\Foundation\Events\Dispatchable;

class UserAddedToOrg
{
    use Dispatchable;

    public string $user_id;

    public string $org_id;

    public string $role;

    /**
     * Create a new event instance.
     */
    public function __construct(
        array $data
    ) {
        $this->user_id = $data['user_id'];
        $this->org_id = $data['org_id'];
        $this->role = $data['role'];
    }
}
