<?php

declare(strict_types=1);

interface ActivityLogPort
{
    /** @param array<string, scalar|null> $details */
    public function record(?string $actorUserId, string $action, string $subjectType, ?string $subjectId, array $details = array()): void;

    /** @return array<int, array{action: string, subject_type: string, subject_id: ?string, actor_name: ?string, created_at: string}> */
    public function recent(int $limit = 10): array;
}
