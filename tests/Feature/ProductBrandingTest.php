<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductBrandingTest extends TestCase
{
    public function test_login_page_uses_the_current_product_brand(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<a class="brand" href="/">Acceso<span>Sitios</span></a>', false);
    }
}
