<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Proxy\Forwarder;
use Core\Http\Request;
use Core\Http\Response;

class ProxyController
{
    public function __construct(private ?Forwarder $forwarder = null)
    {
    }

    public function auth(Request $request): Response
    {
        return $this->forwarder()->forward('auth', $request, false, true);
    }

    public function tasks(Request $request): Response
    {
        return $this->forwarder()->forward('tasks', $request, true);
    }

    public function notifications(Request $request): Response
    {
        return $this->forwarder()->forward('notifications', $request, true);
    }

    private function forwarder(): Forwarder
    {
        return $this->forwarder ??= Forwarder::fromEnv();
    }
}
