<?php

namespace Tests\Feature\Delivery;

use App\Services\Delivery\DeliveryUnavailableException;
use App\Services\Delivery\DistanceResult;
use App\Services\Delivery\FeeCalculator;
use App\Services\Delivery\Haversine;
use App\Services\Delivery\RouteDistance;
use App\Services\Delivery\StraightLineDistance;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Defaults: base 1,500 + 700/km, min 2,000, max 10 km, commission 15%. */
class FeeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function calculatorFor(float $km): FeeCalculator
    {
        $this->app->instance(RouteDistance::class, new class($km) implements RouteDistance
        {
            public function __construct(private float $km) {}

            public function between(float $a, float $b, float $c, float $d): DistanceResult
            {
                return new DistanceResult($this->km, 'straight_line');
            }
        });

        return $this->app->make(FeeCalculator::class);
    }

    public static function fees(): array
    {
        return [
            // km => [fee, commission, earnings]
            '2 km exact' => [2.0, 2900, 435, 2465],
            '3 km exact, no float bump' => [3.0, 3600, 540, 3060],
            '4.3 km rounds up to next 100' => [4.3, 4600, 690, 3910],
            'short hop hits minimum fee' => [0.5, 2000, 300, 1700],
            'zero distance still minimum' => [0.0, 2000, 300, 1700],
            'exactly the max distance' => [10.0, 8500, 1275, 7225],
        ];
    }

    #[DataProvider('fees')]
    public function test_fee_commission_and_earnings(float $km, int $fee, int $commission, int $earnings): void
    {
        $quote = $this->calculatorFor($km)->quote(0, 0, 0, 0);

        $this->assertSame($fee, $quote->fee);
        $this->assertSame($commission, $quote->commission);
        $this->assertSame($earnings, $quote->driverEarnings);
        $this->assertSame($quote->fee, $quote->commission + $quote->driverEarnings);
    }

    public function test_beyond_max_distance_is_refused_unless_explicitly_allowed(): void
    {
        $calculator = $this->calculatorFor(10.5);

        try {
            $calculator->quote(0, 0, 0, 0);
            $this->fail('Expected DeliveryUnavailableException');
        } catch (DeliveryUnavailableException $e) {
            $this->assertSame('too_far', $e->reason);
        }

        $this->assertSame(8900, $calculator->quote(0, 0, 0, 0, enforceMaxDistance: false)->fee);
    }

    public function test_admin_overrides_in_settings_table_take_effect(): void
    {
        Settings::set('delivery_base_fee', 2000);
        Settings::set('delivery_per_km_fee', 1000);
        Settings::set('delivery_commission_pct', 20);

        $quote = $this->calculatorFor(2.0)->quote(0, 0, 0, 0);

        $this->assertSame(4000, $quote->fee);
        $this->assertSame(800, $quote->commission);
        $this->assertSame(3200, $quote->driverEarnings);
    }

    public function test_straight_line_distance_applies_the_road_multiplier(): void
    {
        $straight = Haversine::km(-6.8190, 39.2760, -6.7900, 39.2800);
        $priced = (new StraightLineDistance)->between(-6.8190, 39.2760, -6.7900, 39.2800);

        $this->assertEqualsWithDelta(3.25, $straight, 0.05);
        $this->assertEqualsWithDelta($straight * 1.3, $priced->km, 0.01);
        $this->assertSame('straight_line', $priced->source);
    }
}
