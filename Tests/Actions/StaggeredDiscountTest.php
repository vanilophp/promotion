<?php

declare(strict_types=1);

namespace Vanilo\Promotion\Tests\Actions;

use Nette\Schema\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Vanilo\Promotion\Actions\StaggeredDiscount;
use Vanilo\Promotion\PromotionActionTypes;
use Vanilo\Promotion\Tests\Examples\DummyAdjustableCart;
use Vanilo\Promotion\Tests\Examples\DummyCartItem;
use Vanilo\Promotion\Tests\Examples\SampleAdjustable;
use Vanilo\Promotion\Tests\Examples\SomeBuyableStuff;
use Vanilo\Promotion\Tests\TestCase;

class StaggeredDiscountTest extends TestCase
{
    #[Test] public function it_has_a_name()
    {
        $this->assertNotEmpty(StaggeredDiscount::getName());
    }

    #[Test] public function it_can_be_created_from_the_registry()
    {
        $fixedDiscount = PromotionActionTypes::make(StaggeredDiscount::DEFAULT_ID);

        $this->assertInstanceOf(StaggeredDiscount::class, $fixedDiscount);
    }

    #[Test] public function it_throws_a_validation_exception_if_the_discount_is_missing_from_the_configuration()
    {
        $this->expectException(ValidationException::class);

        $subject = new SampleAdjustable(179);
        $discount = new StaggeredDiscount();

        $discount->apply($subject, []);
    }

    #[Test] public function it_throws_a_validation_exception_if_the_discount_is_not_an_array()
    {
        $this->expectException(ValidationException::class);

        $subject = new SampleAdjustable(179);
        $discount = new StaggeredDiscount();

        $discount->apply($subject, ['discount' => 10]);
    }

    #[Test] public function it_throws_a_validation_exception_if_the_discount_is_an_empty_array()
    {
        $this->expectException(ValidationException::class);

        $subject = new SampleAdjustable(179);
        $discount = new StaggeredDiscount();

        $discount->apply($subject, ['discount' => []]);
    }

    #[Test] public function it_throws_a_validation_exception_if_a_key_is_not_numeric()
    {
        $this->expectException(ValidationException::class);

        $subject = new SampleAdjustable(179);
        $discount = new StaggeredDiscount();

        $config = [
            'discount' => [
                "1" => 50,
                "AA" => 20
            ]
        ];

        $discount->apply($subject, $config);
    }

    #[Test] public function it_throws_a_validation_exception_if_a_key_contains_decimals()
    {
        $this->expectException(ValidationException::class);

        $subject = new SampleAdjustable(179);
        $discount = new StaggeredDiscount();

        $config = [
            'discount' => [
                "10.34" => 50,
            ]
        ];

        $discount->apply($subject, $config);
    }

    #[Test] public function it_throws_a_validation_exception_if_a_configured_percent_is_higher_than_100()
    {
        $this->expectException(ValidationException::class);

        $subject = new SampleAdjustable(179);
        $discount = new StaggeredDiscount();

        $config = [
            'discount' => [
                '5' => 101,
            ]
        ];

        $discount->apply($subject, $config);
    }

    #[Test] public function it_throws_a_validation_exception_if_the_configured_percent_is_negative()
    {
        $this->expectException(ValidationException::class);

        $subject = new SampleAdjustable(179);
        $discount = new StaggeredDiscount();

        $config = [
            'discount' => [
                '5' => -3,
            ]
        ];

        $discount->apply($subject, $config);
    }

    #[Test] public function it_throws_a_validation_exception_if_the_keys_are_not_in_ascending_order()
    {
        $this->expectException(ValidationException::class);

        $subject = new SampleAdjustable(179);
        $discount = new StaggeredDiscount();

        $config = [
            'discount' => [
                '1' => 3,
                '10' => 5,
                '8' => 12,
            ]
        ];

        $discount->apply($subject, $config);
    }

    #[Test] public function it_accepts_various_numeric_keys_representing_whole_numbers()
    {
        $discount = new StaggeredDiscount();

        $subject = new DummyCartItem(preAdjustmentsTotal: 100, quantity: 2);
        $config = [
            'discount' => [
                '5' => 50,
                10 => 70,
                12.00 => 80,
            ]
        ];

        $discount->apply($subject, $config);

        // We just check that no exception was thrown
        $this->assertTrue(true);
    }

    #[Test] public function it_doesnt_apply_any_adjustments_for_quantities_below_the_first_threshold()
    {
        $discount = new StaggeredDiscount();

        $subject = new DummyCartItem(preAdjustmentsTotal: 100, quantity: 2);
        $config = [
            'discount' => [
                '5' => 50,
                '10' => 70,
                '12' => 80,
            ]
        ];

        $adjustments = $discount->apply($subject, $config);
        $this->assertEmpty($adjustments);
    }

    public static function quantityDiscountProvider(): array
    {
        return [
            'Exact threshold 5' => [5, -50],
            'Between 5 and 10' => [9, -50],
            'Exact threshold 10' => [10, -70],
            'Between 10 and 12' => [11, -70],
            'Exact threshold 12' => [12, -80],
            'Above all thresholds' => [15, -80],
        ];
    }

    #[DataProvider('quantityDiscountProvider')] public function test_it_applies_the_right_adjustments_based_on_the_quantity(int $quantity, int $expectedAmount)
    {
        $discount = new StaggeredDiscount();
        $config = [
            'discount' => [
                '5' => 50,
                '10' => 70,
                '12' => 80,
            ]
        ];

        $subject = new DummyCartItem(preAdjustmentsTotal: 100, quantity: $quantity);
        $adjustments = $discount->apply($subject, $config);

        $this->assertCount(1, $adjustments, "Expected one discount adjustment for quantity $quantity");
        $this->assertEquals(
            $expectedAmount,
            $adjustments[0]->getAmount(),
            "Expected discount of -$expectedAmount for quantity $quantity"
        );
    }

    #[Test] public function it_can_be_applied_to_an_entire_cart_and_it_creates_adjustments_for_individual_items(): void
    {
        $discount = new StaggeredDiscount();

        $cart = new DummyAdjustableCart(0);
        $cart->addItem(new SomeBuyableStuff(1, 'Pitaceata', 20));
        $cart->addItem(new SomeBuyableStuff(2, 'Language of the Birds', 30), 3);
        $cart->addItem(new SomeBuyableStuff(3, 'Summer boredom', 100), 15);
        $adjustments = $discount->apply($cart, [
            'discount' => [
                '2' => 10,
                '10' => 50,
            ]
        ]);

        $this->assertCount(2, $adjustments);
        $firstDiscount = $adjustments[0];
        $secondDiscount = $adjustments[1];

        $this->assertEquals(-9, $firstDiscount->getAmount());
        $this->assertTrue($firstDiscount->isCredit());

        $this->assertEquals(-750, $secondDiscount->getAmount());
        $this->assertTrue($secondDiscount->isCredit());
    }

    #[Test] public function the_title_contains_the_configured_min_percentage_if_a_single_line_is_present()
    {
        $config = [
            'discount' => [
                '5' => 50,
            ]
        ];

        $this->assertStringContainsString('50%', (new StaggeredDiscount())->getTitle($config));
    }

    #[Test] public function the_title_contains_the_configured_min_and_max_percentages()
    {
        $config = [
            'discount' => [
                '5' => 50,
                '15' => 60,
                '25' => 80,
            ]
        ];

        $this->assertStringContainsString('50-80%', (new StaggeredDiscount())->getTitle($config));
    }

    #[Test] public function the_title_contains_the_configured_min_and_max_percentages_even_if_they_are_not_ascending()
    {
        $config = [
            'discount' => [
                '5' => 50,
                '15' => 10,
                '25' => 5,
            ]
        ];

        $this->assertStringContainsString('5-50%', (new StaggeredDiscount())->getTitle($config));
    }

    #[Test] public function the_title_warns_about_invalid_configuration()
    {
        $this->assertStringContainsString('Invalid', (new StaggeredDiscount())->getTitle([]));
    }
}
