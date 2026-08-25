<?php

declare(strict_types=1);

namespace App\Domain\Catalogue;

use App\Domain\Entity\Product;
use App\Domain\Exception\ProductNotFoundException;

/**
 * The machine's fixed product catalogue: which products exist and at what price.
 *
 * Domain invariants:
 * - the catalogue is never empty (a machine without products is meaningless);
 * - every map key matches its own product id, so lookups cannot drift;
 * - products are immutable once published: adding one is a code change.
 */
final class ProductCatalogue
{
    /** @var array<string, Product> keyed by product id */
    private array $products;

    /**
     * @param array<string, Product> $products id => product
     *
     * @throws \InvalidArgumentException when the catalogue is empty or a key does not match its product id
     */
    public function __construct(array $products)
    {
        if ([] === $products) {
            throw new \InvalidArgumentException('Product catalogue cannot be empty');
        }

        foreach ($products as $productId => $product) {
            if ((string) $productId !== $product->id()) {
                throw new \InvalidArgumentException(sprintf(
                    'Catalogue key "%s" does not match product id "%s"',
                    (string) $productId,
                    $product->id()
                ));
            }
        }

        $this->products = $products;
    }

    /**
     * @throws ProductNotFoundException when no product exists for the given id
     */
    public function get(string $productId): Product
    {
        return $this->products[$productId]
            ?? throw new ProductNotFoundException(sprintf('Product "%s" does not exist', $productId));
    }

    public function has(string $productId): bool
    {
        return isset($this->products[$productId]);
    }

    /**
     * @return list<string>
     */
    public function knownIds(): array
    {
        return array_keys($this->products);
    }
}
