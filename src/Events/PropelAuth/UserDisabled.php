<?php

namespace LittleGreenMan\Earhart\Events\PropelAuth;

use Illuminate\Foundation\Events\Dispatchable;

class UserDisabled
{
    use Dispatchable;


    public string $user_id;


    /**
     * Create a new event instance.
     */
    public function __construct(
        array $data
    )
    {
        $this->user_id = $data['user_id'];
    }
}
