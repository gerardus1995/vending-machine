<?php

declare(strict_types=1);

namespace App\Domain;

class InMemoryProductRepository implements ProductRepositoryInterface
{
    /**
     * @var array<string, Product>
     */
    private array $products;

    /**
     * @param Product[] $products list of products to initialize the repository with
     */
    public function __construct(array $products = [])
    {
        foreach ($products as $product) {
            $this->products[$product->id()] = $product;
        }
    }

    public static function createDefault(): self
    {
        $water = new Product('water', 'Water', Money::fromString('0.65'));
        $juice = new Product('juice', 'Juice', Money::fromString('1.00'));
        $soda = new Product('soda', 'Soda', Money::fromString('1.50'));

        return new self([$water, $juice, $soda]);
    }

    public function findById(string $id): ?Product
    {
        return $this->products[$id] ?? null;
    }

    /**
     * @return Product[]
     */
    public function findAll(): array
    {
        return array_values($this->products);
    }
}
