<?php

namespace LittleGreenMan\Earhart\Events\PropelAuth;

use Illuminate\Foundation\Events\Dispatchable;

class UserImpersonated
{
    use Dispatchable;

    public string $user_id;

    public string $impersonator_user_id;

    /**
     * Create a new event instance.
     */
    public function __construct(array $data)
    {
        $this->user_id = $data['user_id'];
        $this->impersonator_user_id = $data['impersonator_user_id'];
    }
}
