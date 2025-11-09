<?php

namespace LittleGreenMan\Earhart\Events\PropelAuth;

use Illuminate\Foundation\Events\Dispatchable;

class OrgUpdated
{
    use Dispatchable;

    public string $org_id;

    /**
     * Create a new event instance.
     */
    public function __construct(
        array $data
    ) {
        $this->org_id = $data['org_id'];
    }
}
