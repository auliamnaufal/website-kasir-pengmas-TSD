<?php
namespace Database\Factories;
use App\Models\Payment;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition()
    {
        return [
            'order_id' => Order::factory(),
            'payment_date' => $this->faker->dateTimeThisMonth(),
            'payment_amount' => $this->faker->randomFloat(2, 20, 500),
            'payment_method' => $this->faker->randomElement(['cash', 'qris']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

