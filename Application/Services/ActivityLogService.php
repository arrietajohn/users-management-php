<?php

declare(strict_types=1);

require_once __DIR__ . '/../Ports/Out/ActivityLogPort.php';

final class ActivityLogService
{
    public function __construct(private ActivityLogPort $port)
    {
    }

    /** @param array<string, scalar|null> $details */
    public function record(?string $actorUserId, string $action, string $subjectType, ?string $subjectId, array $details = array()): void
    {
        $this->port->record($actorUserId, $action, $subjectType, $subjectId, $details);
    }

    /** @return array<int, array{action: string, subject_type: string, subject_id: ?string, actor_name: ?string, created_at: string}> */
    public function recent(int $limit = 10): array
    {
        return $this->port->recent(min(20, max(1, $limit)));
    }
}
