<?php

declare(strict_types=1);

namespace App;

final class RouteCalls
{
    public function show(): string
    {
        return $this->redirectToRoute('app_post_show');
    }

    public function home(): string
    {
        return $this->generateUrl('app_home');
    }

    public function missing(): string
    {
        return $this->generateUrl('app_not_defined');
    }
}
