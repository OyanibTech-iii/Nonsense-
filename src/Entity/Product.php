<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $isAvailable = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    /**
     * @var Collection<int, Stock>
     */
    #[ORM\ManyToMany(targetEntity: Stock::class, mappedBy: 'products')]
    private Collection $stocks;

    public function __construct()
    {
        $this->stocks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function isAvailable(): ?bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user && $this->owner && $this->owner->getId() === $user->getId();
    }

    /**
     * Return a web-accessible path for the product image or an absolute URL.
     * - If the stored image value is an absolute URL (http/https) it is returned as-is.
     * - If it's a filename, the method returns the relative path inside public/ (uploads/products/).
     */
    public function getImagePath(): ?string
    {
        if (null === $this->image || '' === $this->image) {
            return null;
        }

        // If the image value is already an absolute URL, return it unchanged
        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        // Otherwise assume it's a filename stored in public/uploads/images/
        return 'uploads/images/' . $this->image;
    }

    /**
     * @return Collection<int, Stock>
     */
    public function getStocks(): Collection
    {
        return $this->stocks;
    }

    public function addStock(Stock $stock): static
    {
        if (!$this->stocks->contains($stock)) {
            $this->stocks->add($stock);
            $stock->addProduct($this);
        }
        return $this;
    }

    public function removeStock(Stock $stock): static
    {
        if ($this->stocks->removeElement($stock)) {
            $stock->removeProduct($this);
        }
        return $this;
    }

    /**
     * Total quantity across all linked stock records.
     */
    public function getCurrentStockQuantity(): int
    {
        $total = 0;
        foreach ($this->stocks as $stock) {
            $total += $stock->getQuantity() ?? 0;
        }
        return $total;
    }

    /**
     * Simplified stock status derived from total quantity.
     */
    public function getStockStatus(): string
    {
        $qty = $this->getCurrentStockQuantity();
        if ($qty <= 0) {
            return 'Out of stock';
        }

        if ($qty <= 5) {
            return 'Low stock';
        }

        return 'In stock';
    }
}