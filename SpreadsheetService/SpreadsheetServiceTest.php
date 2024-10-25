<?php

namespace Tests\Unit\Services;

use App\Jobs\ProcessProductImage;
use App\Models\Product;
use App\Services\SpreadsheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SpreadsheetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $spreadsheetService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spreadsheetService = new SpreadsheetService();
    }

    /**
     * Test that importData method returns correct data.
     */
    public function test_import_data()
    {
        $filePath = 'path/to/fake/spreadsheet.xlsx';

        // Mocking the importer to return sample data
        $importerMock = Mockery::mock('importer');
        $importerMock->shouldReceive('import')
            ->with($filePath)
            ->andReturn([
                ['product_code' => 'P001', 'quantity' => 5],
                ['product_code' => 'P002', 'quantity' => 10],
            ]);

        app()->instance('importer', $importerMock);

        $data = $this->spreadsheetService->importData($filePath);

        $this->assertCount(2, $data);
        $this->assertEquals('P001', $data[0]['product_code']);
        $this->assertEquals(5, $data[0]['quantity']);
    }

    /**
     * Test the isValidRow method with valid and invalid data.
     */
    public function test_is_valid_row()
    {
        // Valid row
        $validRow = ['product_code' => 'P003', 'quantity' => 5];
        $this->assertTrue($this->spreadsheetService->isValidRow($validRow));

        // Duplicate product code (assuming 'P003' exists in the database)
        Product::create(['code' => 'P003', 'quantity' => 5]);
        $invalidRow = ['product_code' => 'P003', 'quantity' => 5];
        $this->assertFalse($this->spreadsheetService->isValidRow($invalidRow));

        // Invalid quantity
        $invalidRow = ['product_code' => 'P004', 'quantity' => 0];
        $this->assertFalse($this->spreadsheetService->isValidRow($invalidRow));
    }

    /**
     * Test that createProduct creates a new product in the database.
     */
    public function test_create_product()
    {
        $validatedData = ['code' => 'P005', 'quantity' => 10];

        $product = $this->spreadsheetService->createProduct($validatedData);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertDatabaseHas('products', ['code' => 'P005', 'quantity' => 10]);
    }

    /**
     * Test that dispatchProductImageJob dispatches the job.
     */
    public function test_dispatch_product_image_job()
    {
        Queue::fake();

        $product = Product::create(['code' => 'P006', 'quantity' => 7]);
        $this->spreadsheetService->dispatchProductImageJob($product);

        Queue::assertPushed(ProcessProductImage::class, fn($job) => $job->product->is($product));
    }

    /**
     * Test the full processSpreadsheet method to confirm integration.
     */
    public function test_process_spreadsheet_integration()
    {
        Queue::fake();
        $filePath = 'path/to/fake/spreadsheet.xlsx';

        // Mocking importer to return data
        $importerMock = Mockery::mock('importer');
        $importerMock->shouldReceive('import')
            ->with($filePath)
            ->andReturn([
                ['product_code' => 'P007', 'quantity' => 5],
                ['product_code' => 'P008', 'quantity' => 0], // Invalid quantity
            ]);

        app()->instance('importer', $importerMock);

        $this->spreadsheetService->processSpreadsheet($filePath);

        // Assert only valid product is in database and job is dispatched
        $this->assertDatabaseHas('products', ['code' => 'P007', 'quantity' => 5]);
        $this->assertDatabaseMissing('products', ['code' => 'P008', 'quantity' => 0]);
        Queue::assertPushed(ProcessProductImage::class, fn($job) => $job->product->code === 'P007');
    }
}
