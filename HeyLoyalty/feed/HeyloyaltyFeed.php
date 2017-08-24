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
            $combinations = $this->getCombinations($product, $langId, $this->context->shop->id);
            if (!empty($combinations)) {
                foreach ($combinations as $combination) {
                    $name = $product['name'] . ' - ' . $combination['name'];
                    $additionalCost = $combination['price'];
                    if (isset($combination['images'])) {
                        $productImage = 'http://' . $this->getCover($product['id_product'], $combination['id_product_attribute'], $link, $product['link_rewrite']);
                    } else {
                        $productImage = $this->getProductImage($prod, $product, $link);
                    }
                    $images = $combination['images'];
                    $productData[] = [
                        'id' => $combination['id'],
                        'ProductId' => $combination['reference'],
                        'ProductName' => $product['name'] . ' - ' . $combination['name'],
                        'Description' => $product['description_short'],
                        'Price' => $this->getPrice($prod, $currencyId) + $combination['price'],
                        'OriginalPrice' => $this->getOriginalPrice($prod, $currencyId) + $combination['price'],
                        'Discount' => (($this->getOriginalPrice($prod, $currencyId) + $combination['price']) - ($this->getPrice($prod, $currencyId) + $combination['price'])),
                        'ProductLink' => 'http:' . $combination['link'],
                        'ProductImageUrl' => $productImage,
                        'CategoryId' => $product['id_category_default'],
                        'CategoryName' => $this->getCategoryName($product['id_category_default'], $langId),
                        'Brand' => $this->getBrand($product['id_manufacturer'], $langId)
                    ];
                }
                continue;
            }
            $productData[] = [
                'id' => $product['id_product'],
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

    public function getCombinations($product, $id_lang, $id_shop)
    {
        $product_attributes = Db::getInstance()->executeS(
            'SELECT pa.id_product_attribute
            FROM '._DB_PREFIX_.'product_attribute pa
            INNER JOIN '._DB_PREFIX_.'product_attribute_shop pas ON (pas.id_product_attribute = pa.id_product_attribute AND pas.id_shop = ' . $id_shop . ')
            WHERE pa.id_product = '.(int) $product['id_product'] . ' AND pas.active > 0'
        );

        $combinations = array();
        foreach ($product_attributes as $key => $product_attribute){
            $combination = new Combination($product_attribute['id_product_attribute']);
            $name = $combination->getAttributesName($id_lang);
            $link = new Link();
            $link = $link->getProductLink(
                $product['id_product'],
                $product['link_rewrite'],
                $product['id_category_default'],
                $product['ean13'],
                (int) $id_lang,
                (int) $id_shop,
                (int) $product_attribute['id_product_attribute'],
                true,
                true
            );
            $combinations[$key]['id'] = (int) $product_attribute['id_product_attribute'];
            $combinations[$key]['id_product_attribute'] = $product_attribute['id_product_attribute'];
            $combinations[$key]['name'] = $name[0]['name'];
            $combinations[$key]['price'] = (int) $combination->unit_price_impact;
            $combinations[$key]['images'] = $combination->getWsImages();
            $combinations[$key]['link'] = $link;
            $combinations[$key]['default_on'] = $combination->default_on;
            $combinations[$key]['reference'] = $combination->reference;
            $combinations[$key]['ean13'] = $combination->ean13;
        }
        return $combinations;
    }

    protected function getCover($id, $id_product_attribute, $link, $linkRewrite)
    {
        $feedMainImage = Db::getInstance()->getValue(
            "
            SELECT id_cover FROM " . _DB_PREFIX_ . "product_attribute_combination
            WHERE id_product_attribute = '" . $id_product_attribute . "'
            "
        );
        if (empty($feedMainImage) || $feedMainImage == 0) {
            $sql = "SELECT i.id_image FROM ps_image as i WHERE i.id_product = '{$id}' AND i.position = (
                    SELECT min(i2.position) as position 
                    FROM ps_product_attribute_image as pai2
                    LEFT JOIN ps_image as i2 ON (pai2.id_image = i2.id_image)
                    WHERE pai2.id_product_attribute = '{$id_product_attribute}'
                ) 
                ORDER BY i.id_product
            ";
            $feedMainImage = Db::getInstance()->getValue($sql);
        }
        return $link->getImageLink($linkRewrite, $feedMainImage, 'thickbox_default');
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
