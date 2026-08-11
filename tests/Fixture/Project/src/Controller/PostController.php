<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class PostController
{
    #[Route('/posts', name: 'app_post_index', methods: ['GET'])]
    public function index(): void
    {
    }

    #[Route('/posts/{id}', name: 'app_post_show', requirements: ['id' => '\d+'], methods: ['GET', 'HEAD'])]
    public function show(int $id): void
    {
    }

    #[Route('/posts', name: 'app_post_create', methods: ['POST'])]
    public function create(): void
    {
    }
}
