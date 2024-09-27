<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    public function testPurchaseProductSuccess():void
    {
        // Создаем пользователя с балансом
        $user = User::factory()->create(['balance' => 100]);

        // Логинимся под этим пользователем
        $this->actingAs($user);

        // Создаем продукт
        $product = Product::factory()->create(['price' => 50]);

        // Вызываем метод покупки продукта
        $response = $this->postJson("/api/products/{$product->id}/purchase");

        // Проверяем успешный ответ
        $response->assertStatus(200)
            ->assertJson(['message' => 'Product purchased successfully']);

        // Проверяем, что была создана транзакция
        $this->assertDatabaseHas('transactions', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => 'purchase'
        ]);

        // Проверяем, что баланс пользователя уменьшился
        $this->assertEquals(50, $user->fresh()->balance);
    }

    public function testPurchaseProductInsufficientFunds():void
    {
        $user = User::factory()->create(['balance' => 20]);
        $this->actingAs($user);

        $product = Product::factory()->create(['price' => 50]);

        $response = $this->postJson("/api/products/{$product->id}/purchase");

        $response->assertStatus(403)
            ->assertJson(['error' => 'Insufficient funds']);
    }

    public function testRentProductSuccess():void
    {
        $user = User::factory()->create(['balance' => 100]);
        $this->actingAs($user);

        $product = Product::factory()->create(['rent_per_hour' => 10]);

        $response = $this->postJson("/api/products/{$product->id}/rent", ['hours' => 4]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Product rented successfully']);

        $this->assertDatabaseHas('transactions', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => 'rent',
        ]);

        $this->assertEquals(60, $user->fresh()->balance);  // 40 потрачено на аренду
    }

    public function testRentProductInvalidHours(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $this->actingAs($user);

        $product = Product::factory()->create(['rent_per_hour' => 10]);

        $response = $this->postJson("/api/products/{$product->id}/rent", ['hours' => 3]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Неверный срок аренды']);
    }

    public function testExtendRentSuccess(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $this->actingAs($user);

        $product = Product::factory()->create(['rent_per_hour' => 10]);

        // Создаем транзакцию
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rent_end_at' => now()->addHours(4),
            'type' => 'rent'
        ]);

        $response = $this->postJson("/api/transactions/{$transaction->id}/extend", ['hours' => 4]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Аренда продлена']);

        // Проверяем, что баланс обновился
        $this->assertEquals(60, $user->fresh()->balance);

        // Проверяем, что аренда продлена
        $this->assertEquals(now()->addHours(8)->format('Y-m-d H:i:s'), $transaction->fresh()->rent_end_at->format('Y-m-d H:i:s'));
    }

    public function testExtendRentExceedsLimit(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $this->actingAs($user);

        $product = Product::factory()->create(['rent_per_hour' => 10]);

        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rent_end_at' => now()->addHours(20),
            'type' => 'rent'
        ]);

        $response = $this->postJson("/api/transactions/{$transaction->id}/extend", ['hours' => 6]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Вы не можете продлить аренду более чем на 24 часа.']);
    }

    public function testCheckStatusProductSuccess(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = Product::factory()->create();

        // Создаем транзакцию
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'code' => 'some-unique-code'
        ]);

        $response = $this->getJson("/api/products/{$product->id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'product' => [
                    'id' => $product->id,
                ],
                'type' => 'purchase',
                'code' => 'some-unique-code',
            ]);
    }

    public function testCheckStatusProductNotFound(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = Product::factory()->create();

        $response = $this->getJson("/api/products/{$product->id}/status");

        $response->assertStatus(404)
            ->assertJson(['error' => 'No transaction found for this product']);
    }

}
