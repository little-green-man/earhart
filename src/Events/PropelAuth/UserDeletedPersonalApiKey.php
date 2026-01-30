<?php

namespace LittleGreenMan\Earhart\Events\PropelAuth;

use Illuminate\Foundation\Events\Dispatchable;

class UserDeletedPersonalApiKey
{
    use Dispatchable;

    public string $user_id;

    public string $api_key_id;

    /**
     * Create a new event instance.
     */
    public function __construct(array $data)
    {
        $this->user_id = $data['user_id'];
        $this->api_key_id = $data['api_key_id'];
    }
}
