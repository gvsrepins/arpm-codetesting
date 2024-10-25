<?php

namespace App\Services;

use App\Jobs\ProcessProductImage;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;

class SpreadsheetService
{
    public function processSpreadsheet($filePath)
    {
        $products_data = $this->importData($filePath);

        foreach ($products_data as $row) {
            if ($this->isValidRow($row)) {
                $product = $this->createProduct($row);
                $this->dispatchProductImageJob($product);
            }
        }
    }

    protected function importData($filePath)
    {
        return app('importer')->import($filePath);
    }

    protected function isValidRow(array $row)
    {
        $validator = Validator::make($row, [
            'product_code' => 'required|unique:products,code',
            'quantity' => 'required|integer|min:1',
        ]);

        return !$validator->fails();
    }

    protected function createProduct(array $validatedData)
    {
        return Product::create($validatedData);
    }

    protected function dispatchProductImageJob(Product $product)
    {
        ProcessProductImage::dispatch($product);
    }
}
