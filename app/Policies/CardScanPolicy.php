<?php

namespace App\Policies;

use App\Models\CardScan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CardScanPolicy
{
    public function view(User $user, CardScan $cardScan): Response
    {
        return $this->owns($user, $cardScan);
    }

    public function update(User $user, CardScan $cardScan): Response
    {
        return $this->owns($user, $cardScan);
    }

    public function delete(User $user, CardScan $cardScan): Response
    {
        return $this->owns($user, $cardScan);
    }

    private function owns(User $user, CardScan $cardScan): Response
    {
        return $user->is($cardScan->user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
