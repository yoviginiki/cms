<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentProgressEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $siteId,
        public string $deploymentId,
        public string $status,
        public string $message,
        public array $progress = [],
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("sites.{$this->siteId}.deployments")];
    }

    public function broadcastAs(): string
    {
        return 'deployment.progress';
    }
}
