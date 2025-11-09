<?php

namespace LittleGreenMan\Earhart\Events\PropelAuth;

use Illuminate\Foundation\Events\Dispatchable;

class OrgCreated
{
    use Dispatchable;

    public string $name;

    public string $org_id;

    /**
     * Create a new event instance.
     */
    public function __construct(
        array $data
    ) {
        $this->name = $data['name'];
        $this->org_id = $data['org_id'];
    }
}
