<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class StaffNotificationChanged implements ShouldBroadcastNow
{
    public function __construct(public readonly string $action, public readonly int $organizationId, public readonly int $userId, public readonly array $notification) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('staff.org.'.$this->organizationId.'.user.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'staff.notification.changed';
    }

    public function broadcastWith(): array
    {
        return ['action' => $this->action, 'organizationId' => $this->organizationId, 'userId' => $this->userId, 'notification' => $this->notification];
    }
}
