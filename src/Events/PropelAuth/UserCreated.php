<?php

namespace LittleGreenMan\Earhart\Events\PropelAuth;

use Illuminate\Foundation\Events\Dispatchable;

class UserCreated
{
    use Dispatchable;

    public string $email;

    public bool $email_confirmed;

    public string $event_type;

    public ?string $first_name;

    public ?string $last_name;

    public ?string $picture_url;

    public string $user_id;

    public ?string $username;

    /**
     * Create a new event instance.
     */
    public function __construct(array $data)
    {
        $this->email = $data['email'];
        $this->email_confirmed = $data['email_confirmed'];
        $this->event_type = $data['event_type'];
        $this->first_name = $data['first_name'] ?? null;
        $this->last_name = $data['last_name'] ?? null;
        $this->picture_url = $data['picture_url'] ?? null;
        $this->user_id = $data['user_id'];
        $this->username = $data['username'] ?? null;
    }
}
