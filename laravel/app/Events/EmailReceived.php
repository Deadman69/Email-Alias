<?php

namespace App\Events;

use App\Models\InboundEmail;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmailReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly InboundEmail $inboundEmail) {}

    /**
     * Broadcast on the alias-specific private channel.
     *
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("alias.{$this->inboundEmail->alias_id}"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id'           => $this->inboundEmail->id,
            'from_address' => $this->inboundEmail->from_address,
            'from_name'    => $this->inboundEmail->from_name,
            'subject'      => $this->inboundEmail->subject,
            'received_at'  => $this->inboundEmail->created_at->toIso8601String(),
            'alias_id'     => $this->inboundEmail->alias_id,
        ];
    }
}
