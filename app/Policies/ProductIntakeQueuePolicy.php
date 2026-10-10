<?php

namespace App\Policies;

use App\Models\Catalog\ProductIntakeQueue;
use App\Models\User\User;

class ProductIntakeQueuePolicy
{
    /**
     * Determine if the user can view the queue item.
     */
    public function view(User $user, ProductIntakeQueue $queueItem): bool
    {
        return $user->currentTeam->id === $queueItem->team_id;
    }

    /**
     * Determine if the user can update the queue item.
     */
    public function update(User $user, ProductIntakeQueue $queueItem): bool
    {
        return $user->currentTeam->id === $queueItem->team_id;
    }

    /**
     * Determine if the user can delete the queue item.
     */
    public function delete(User $user, ProductIntakeQueue $queueItem): bool
    {
        return $user->currentTeam->id === $queueItem->team_id;
    }
}
