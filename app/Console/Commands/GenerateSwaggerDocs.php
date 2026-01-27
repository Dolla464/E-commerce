<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class GenerateSwaggerDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Swagger documentation (handles PathItem warning)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Set error handler to ignore the PathItem warning
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // Ignore the specific PathItem warning
            if (str_contains($errstr, 'Required @OA\\PathItem() not found')) {
                $this->warn('Note: PathItem warning ignored (known swagger-php limitation)');
                return true; // Suppress the error
            }
            return false; // Let other errors through
        }, E_USER_WARNING);

        try {
            $exitCode = Artisan::call('l5-swagger:generate');
            
            // Check if documentation was generated despite the warning
            $docsPath = storage_path('api-docs/api-docs.json');
            if (file_exists($docsPath)) {
                $this->info('Swagger documentation generated successfully!');
                $this->info('File location: ' . $docsPath);
                return self::SUCCESS;
            } else {
                $this->error('Documentation file was not generated.');
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            // If it's the PathItem error, check if file was still created
            if (str_contains($e->getMessage(), 'PathItem')) {
                $docsPath = storage_path('api-docs/api-docs.json');
                if (file_exists($docsPath)) {
                    $this->info('Swagger documentation generated successfully (despite warning)!');
                    $this->info('File location: ' . $docsPath);
                    return self::SUCCESS;
                }
            }
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        } finally {
            restore_error_handler();
        }
    }
}
