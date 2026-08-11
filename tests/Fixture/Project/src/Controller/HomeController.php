<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class HomeController
{
    #[Route('/', name: 'app_home', methods: ['GET', 'HEAD'])]
    public function index(): void
    {
    }
}
