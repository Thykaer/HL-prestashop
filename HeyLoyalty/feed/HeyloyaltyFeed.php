<?php

include(__DIR__ . '/../HeyLoyalty.php');

class HeyloyaltyFeed extends HeyLoyalty
{
    public function generateProductFeed($lang, $currency)
    {
        $langId = Language::getIdByIso($lang);
        $currencyId = Currency::getIdByIsoCode($currency);
        $productModel = new Product();
        $products = $productModel->getProducts($langId, 0, 0, 'id_product', 'DESC', false, true);
        $productData = [];
        $link = new Link();
        foreach ($products as $product) {
            $prod = new Product($product['id_product']);
            $productData[] = [
                'ProductId' => $product['reference'],
                'ProductName' => $product['name'],
                'Description' => $product['description_short'],
                'Price' => $this->getPrice($prod, $currencyId),
                'OriginalPrice' => $this->getOriginalPrice($prod, $currencyId),
                'Discount' => $this->getDiscount($prod, $currencyId),
                'ProductLink' => $prod->getLink(),
                'ProductImageUrl' => $this->getProductImage($prod, $product, $link),
                'CategoryId' => $product['id_category_default'],
                'CategoryName' => $this->getCategoryName($product['id_category_default'], $langId),
                'Brand' => $this->getBrand($product['id_manufacturer'], $langId)
            ];
        }
        return json_encode($productData);
    }

    protected function getOriginalPrice($prod, $currencyId)
    {
        $originalPrice = $prod->getPrice(true, null, 2, null, false, false);
        $originalPrice = Tools::displayPrice($originalPrice, $currencyId);
        return preg_replace('/[^0-9,.]/', '', $originalPrice);
    }

    protected function getDiscount($prod, $currencyId)
    {
        $discount = $prod->getPrice(true, null, 2, null, true);
        $discount = Tools::displayPrice($discount, $currencyId);
        return preg_replace('/[^0-9,.]/', '', $discount);
    }

    protected function getPrice($prod, $currencyId)
    {
        $price = $prod->getPrice();
        $price = Tools::displayPrice($price, $currencyId);
        return preg_replace('/[^0-9,.]/', '', $price);
    }

    protected function getProductImage($prod, $product, $link)
    {
        $cover = $prod->getCover($product['id_product']);
        return 'http://' . $link->getImageLink($product['link_rewrite'], $cover['id_image']);
    }

    protected function getCategoryName($categoryId, $langId)
    {
        $category = new Category($categoryId);
        return $category->name[$langId];
    }

    protected function getBrand($manafacturerId, $langId)
    {
        $manufacturer = new Manufacturer($manafacturerId, $langId);
        return $manufacturer->name;
    }
}
