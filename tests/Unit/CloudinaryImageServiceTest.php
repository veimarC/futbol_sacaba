<?php

namespace Tests\Unit;

use App\Services\CloudinaryImageService;
use InvalidArgumentException;
use Tests\TestCase;

class CloudinaryImageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cloudinary.cloud_name' => 'nube-prueba',
            'services.cloudinary.api_key' => '123456',
            'services.cloudinary.api_secret' => 'secreto-solo-para-pruebas',
            'services.cloudinary.secure' => true,
        ]);
    }

    public function test_it_generates_a_secure_optimized_image_url(): void
    {
        $url = app(CloudinaryImageService::class)
            ->urlOptimizada('futbol-sacaba/jugadores/carlos', 400, 400);

        $this->assertStringStartsWith('https://res.cloudinary.com/nube-prueba/image/upload/', $url);
        $this->assertStringContainsString('c_fill', $url);
        $this->assertStringContainsString('f_auto', $url);
        $this->assertStringContainsString('q_auto', $url);
        $this->assertStringContainsString('h_400', $url);
        $this->assertStringContainsString('w_400', $url);
    }

    public function test_it_rejects_invalid_dimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(CloudinaryImageService::class)->urlOptimizada('imagen', 0, 400);
    }
}
