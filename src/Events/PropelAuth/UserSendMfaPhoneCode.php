<?php

namespace LittleGreenMan\Earhart\Events\PropelAuth;

use Illuminate\Foundation\Events\Dispatchable;

class UserSendMfaPhoneCode
{
    use Dispatchable;

    public string $user_id;

    public string $phone_number;

    /**
     * Create a new event instance.
     */
    public function __construct(array $data)
    {
        $this->user_id = $data['user_id'];
        $this->phone_number = $data['phone_number'];
    }
}
