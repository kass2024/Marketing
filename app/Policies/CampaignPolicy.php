<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Campaign;

class CampaignPolicy
{
    public function view(User $user, Campaign $campaign)
    {
        return $user->client->id === $campaign->client_id;
    }

    public function update(User $user, Campaign $campaign)
    {
        return $user->client->id === $campaign->client_id;
    }

    public function delete(User $user, Campaign $campaign)
    {
        return $user->client->id === $campaign->client_id;
    }
}