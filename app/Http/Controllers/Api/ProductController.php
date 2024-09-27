<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use \Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ProductController extends Controller
{

    /**
     * Покупка продукта
     * @param  Product  $product
     * @return JsonResponse
     */
    public function purchase(Product $product): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        //проверка балланса пользователя
        if ($user->balance < $product->price) {
            return response()->json(['error' => 'Insufficient funds'], 403);
        }

        // Создание транзакции
        $transaction = $user->transactions()->create([
            'product_id' => $product->id,
            'type' => 'purchase',
            'code' => Str::uuid()->toString()
        ]);

        // Списание средств с баланса пользователя
        $user->decrementBalance($product->price);

        return response()->json(['message' => 'Product purchased successfully', 'transaction' => $transaction]);
    }


    /**
     * Аренда продукта (ограничение по времени 4,8,12 или 24 часа)
     * @param  Product  $product
     * @param  Request  $request
     * @return JsonResponse
     */
    public function rent(Product $product, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $hours = $request->input('hours');

        //Вешаем проверку, чтобы аренда была только 4, 8, 12 или 24 часа
        if ($hours > 24 || $hours % 4 !== 0) {
            return response()->json(['error' => 'Неверный срок аренды'], );
        }

        $rentEnd = now()->addHours($hours);

        $hoursForRent = $rentEnd->diffInHours(now());

        $priceRent = $hoursForRent * $product->rent_per_hour;



        //Проверяем текущий балланс пользователя, если средств недостаточно, вернем ошибку
        if ($user->balance < $priceRent) {
            return response()->json(['error' => 'На Вашем счету недостаточно средств']);
        }

        $user->transactions()->create([
            'product_id' => $product->id,
            'type' => 'rent',
            'rent_end_at' => $rentEnd
        ]);


        $user->balance -= $priceRent;
        $user->save();

        return response()->json(['message' => 'Product rented successfully', 'rent_end_at' => $rentEnd]);
    }

    /**
     * Продление аренды до 24 часов.
     * @param  Transaction  $transaction
     * @param  Request  $request
     * @return JsonResponse
     */
    public function extendRent(Transaction $transaction, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        // Проверяем, что текущий пользователь — владелец транзакции
        if ($transaction->user_id !== $user->id) {
            return response()->json(['error' => 'У вас нет доступа для выполнения этой операции'], 403);
        }

        $additionalHours = $request->input('hours');

        $hoursAlreadyRented = $transaction->rent_start_at->diffInHours(now());

        // Рассчитываем общую сумму времени аренды после продления
        $totalHoursAfterExtension = $hoursAlreadyRented + $additionalHours;

        // Проверяем, что общее время аренды не превышает 24 часа
        if ($totalHoursAfterExtension > 24) {
            return response()->json(['error' => 'Вы не можете продлить аренду более чем на 24 часа.'], 400);
        }

        $product = $transaction->product;

        if($product){
            //Посчитаем сколько пользователю нужно доплатить
            $priceForRent = $additionalHours * $product->rent_per_hour;

            if($user->balance < $priceForRent){
                return response()->json(['error' => 'На Вашем счету недостаточно средств для продления аренды'], 400);
            }

            $user->balance -= $priceForRent;
            $user->save();

        }else{
            //Ошибка на случай, если товар не найден
            return response()->json(['error' => 'Что-то пошло не так с Вашим товаром. Обратитесь к администратору'], 400);

        }

        // Если проверка пройдена, обновляем время окончания аренды
        $newEnd = $transaction->rent_end_at->copy()->addHours($additionalHours);
        $transaction->update(['rent_end_at' => $newEnd]);



        return response()->json(['message' => 'Аренда продлена', 'new_rent_end_at' => $newEnd]);
    }

    /**
     * Информация о купленном или арендованном товаре
     * @param  Product  $product
     * @return JsonResponse
     */
    public function checkStatus(Product $product): JsonResponse
    {
        //Ищем запись об аренде
        $transaction = auth()->user()->transactions()->where('product_id', $product->id)->first();

        //Если не нащли запись, вернем ошибку
        if (!$transaction) {
            return response()->json(['error' => 'No transaction found for this product'], 404);
        }

        //Создадим уникальный код для товара, если его еще нет
        if(!$transaction->code){
            $transaction->code = Str::uuid()->toString();
            $transaction->save();
        }

        //вернем только нужные данные
        return response()->json([
            'product' => $product,
            'type' => $transaction->type,
            'rent_end_at' => $transaction->rent_end_at,
            'code' => $transaction->code
        ]);
    }

    /**
     * История покупок и аренд продуктов по текущему пользоватею
     * @return JsonResponse
     */
    public function history(): JsonResponse
    {
        $transactions = auth()->user()->transactions()->with('product')->get();

        return response()->json($transactions);
    }

}
