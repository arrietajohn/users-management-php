<?php

declare(strict_types=1);

require_once __DIR__ . '/../Ports/Out/GetActiveUserCountPort.php';
require_once __DIR__ . '/../Ports/Out/CelularRepositoryPort.php';
require_once __DIR__ . '/ActivityLogService.php';

final class DashboardService
{
    public function __construct(
        private CelularRepositoryPort $celulares,
        private GetActiveUserCountPort $users,
        private ActivityLogService $activity
    ) {
    }

    /** @return array<string, mixed> */
    public function execute(bool $includeActivity = false): array
    {
        $data = $this->celulares->dashboard();
        $data['activeUsers'] = $this->users->countActive();
        $data['activity'] = $includeActivity ? $this->activity->recent(8) : array();
        return $data;
    }
}
