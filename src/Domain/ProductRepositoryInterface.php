<?php

declare(strict_types=1);

namespace App\Domain;

interface ProductRepositoryInterface
{
    public function findById(string $id): ?Product;

    /**
     * @return Product[]
     */
    public function findAll(): array;
}