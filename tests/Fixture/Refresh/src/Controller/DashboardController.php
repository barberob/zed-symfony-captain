<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class DashboardController
{
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET', 'HEAD'])]
    public function index(): void
    {
    }
}
