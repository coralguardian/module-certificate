<?php

namespace D4rk0snet\Certificate\Model;

use D4rk0snet\Adoption\Enums\AdoptedProduct;
use D4rk0snet\Adoption\Enums\Seeder;
use D4rk0snet\Coralguardian\Enums\Language;

class CertificateModel
{
    private Seeder $seeder;
    private string $adopteeName;
    private AdoptedProduct $adoptedProduct;
    private \DateTime $date;
    private Language $language;
    private string $productPicture;
    private string $saveFolder;

    public function __construct(
        AdoptedProduct $adoptedProduct,
        string $adopteeName,
        Seeder $seeder,
        \DateTime $date,
        Language $language,
        string $productPicture,
        string $saveFolder
    ) {
        $this->adoptedProduct = $adoptedProduct;
        $this->adopteeName = $adopteeName;
        $this->date = $date;
        $this->language = $language;
        $this->seeder = $seeder;
        $this->productPicture = $productPicture;
        $this->saveFolder = $saveFolder;
    }

    public function toArray() : array
    {
        return [
            'adopteeName' => $this->adopteeName,
            'seeder' => $this->seeder->getName(),
            'seederPicture' => $this->getSeeder()->getPicture(),
            'productPicture' => $this->getProductPicture(),
            'date' => $this->date->format("d/m/Y")
        ];
    }

    public function getAdoptedProduct(): AdoptedProduct
    {
        return $this->adoptedProduct;
    }

    public function getLanguage(): Language
    {
        return $this->language;
    }

    public function getSeeder(): Seeder
    {
        return $this->seeder;
    }

    public function getAdopteeName(): string
    {
        return $this->adopteeName;
    }

    /**
     * @param string $productPicture
     */
    public function setProductPicture(string $productPicture): void
    {
        $this->productPicture = $productPicture;
    }

    public function getProductPicture(): string
    {
        return $this->productPicture;
    }

    public function getSaveFolder(): string
    {
        return $this->saveFolder;
    }
}
