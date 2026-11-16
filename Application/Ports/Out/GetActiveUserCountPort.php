<?php

declare(strict_types=1);

interface GetActiveUserCountPort
{
    public function countActive(): int;
}
