<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Notification;

final class NotificationController extends Controller
{
    /** Called when the bell dropdown opens. */
    public function read(Request $request): Response
    {
        Notification::markAllRead(auth());

        return $this->ok();
    }
}
