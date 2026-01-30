<?php

namespace LittleGreenMan\Earhart\Events\PropelAuth;

use Illuminate\Foundation\Events\Dispatchable;

class UserLogin
{
    use Dispatchable;

    public string $user_id;

    public ?string $active_org_id;

    /**
     * Create a new event instance.
     */
    public function __construct(array $data)
    {
        $this->user_id = $data['user_id'];
        $this->active_org_id = $data['active_org_id'] ?? null;
    }
}
