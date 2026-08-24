<?php

declare(strict_types=1);

namespace App\Domain;

final class Product
{
    private readonly string $id;
    private readonly string $name;
    private readonly Money $price;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(string $id, string $name, Money $price)
    {
        if ('' === $id) {
            throw new \InvalidArgumentException('Product id cannot be empty');
        }
        if ('' === $name) {
            throw new \InvalidArgumentException('Product name cannot be empty');
        }
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): Money
    {
        return $this->price;
    }
}
