<?php

namespace Gloudemans\Tests\Shoppingcart;

use Mockery;
use PHPUnit\Framework\Assert;
use Gloudemans\Shoppingcart\Cart;
use Orchestra\Testbench\TestCase;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Collection;
use Gloudemans\Shoppingcart\CartItem;
use Illuminate\Support\Facades\Event;
use Illuminate\Session\SessionManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Gloudemans\Shoppingcart\ShoppingcartServiceProvider;
use Gloudemans\Tests\Shoppingcart\Fixtures\ProductModel;
use Gloudemans\Tests\Shoppingcart\Fixtures\BuyableProduct;

class CartTest extends TestCase
{
    use CartAssertions;

    /**
     * Set the package service provider.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [ShoppingcartServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cart.database.connection', 'testing');

        $app['config']->set('session.driver', 'array');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp()
    {
        parent::setUp();

        $this->app->afterResolving('migrator', function ($migrator) {
            $migrator->path(realpath(__DIR__.'/../database/migrations'));
        });
    }

    /** @test */
    public function it_has_a_default_instance()
    {
        $cart = $this->getCart();

        $this->assertEquals(Cart::DEFAULT_INSTANCE, $cart->currentInstance());
    }

    /** @test */
    public function it_can_have_multiple_instances()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item'));

        $cart->instance('wishlist')->add(new BuyableProduct(2, 'Second item'));

        $this->assertItemsInCart(1, $cart->instance(Cart::DEFAULT_INSTANCE));
        $this->assertItemsInCart(1, $cart->instance('wishlist'));
    }

    /** @test */
    public function it_can_add_an_item()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $this->assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_will_return_the_cartitem_of_the_added_item()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct);

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertEquals('3530c9670bae9d337f1806295206091b', $cartItem->rowId);

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_multiple_buyable_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add([new BuyableProduct(1), new BuyableProduct(2)]);

        $this->assertEquals(2, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_will_return_an_array_of_cartitems_when_you_add_multiple_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItems = $cart->add([new BuyableProduct(1), new BuyableProduct(2)]);

        $this->assertTrue(is_array($cartItems));
        $this->assertCount(2, $cartItems);
        $this->assertContainsOnlyInstancesOf(CartItem::class, $cartItems);

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_an_item_from_attributes()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 10.00);

        $this->assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_an_item_from_an_array()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(['id' => 1, 'name' => 'Test item', 'qty' => 1, 'price' => 10.00]);

        $this->assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_multiple_array_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add([
            ['id' => 1, 'name' => 'Test item 1', 'qty' => 1, 'price' => 10.00],
            ['id' => 2, 'name' => 'Test item 2', 'qty' => 1, 'price' => 10.00]
        ]);

        $this->assertEquals(2, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_an_item_with_options()
    {
        Event::fake();

        $cart = $this->getCart();

        $options = ['size' => 'XL', 'color' => 'red'];

        $cart->add(new BuyableProduct, 1, $options);

        $cartItem = $cart->get('6dbc69ac09fcab6dc16fa278bd8ffb29');

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertEquals('XL', $cartItem->options->size);
        $this->assertEquals('red', $cartItem->options->color);

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_an_item_with_extras()
    {
        $this->expectsEvents('cart.added');

        $cart = $this->getCart();

        $extras = ['gift' => true];

        $cart->add(new BuyableProduct, 1, [], $extras);

        $cartItem = $cart->get('3530c9670bae9d337f1806295206091b');

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertEquals(true, $cartItem->extras->gift);
    }

    /** @test */
    public function it_can_add_an_item_with_options_and_extras()
    {
        $this->expectsEvents('cart.added');

        $cart = $this->getCart();

        $options = ['size' => 'XL', 'color' => 'red'];

        $extras = ['gift' => true];

        $cart->add(new BuyableProduct, 1, $options, $extras);

        $cartItem = $cart->get('6dbc69ac09fcab6dc16fa278bd8ffb29');

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertEquals('XL', $cartItem->options->size);
        $this->assertEquals('red', $cartItem->options->color);
        $this->assertEquals(true, $cartItem->extras->gift);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Please supply a valid identifier.
     */
    public function it_will_validate_the_identifier()
    {
        $cart = $this->getCart();

        $cart->add(null, 'Some title', 1, 10.00);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Please supply a valid name.
     */
    public function it_will_validate_the_name()
    {
        $cart = $this->getCart();

        $cart->add(1, null, 1, 10.00);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Please supply a valid quantity.
     */
    public function it_will_validate_the_quantity()
    {
        $cart = $this->getCart();

        $cart->add(1, 'Some title', 'invalid', 10.00);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Please supply a valid price.
     */
    public function it_will_validate_the_price()
    {
        $cart = $this->getCart();

        $cart->add(1, 'Some title', 1, 'invalid');
    }

    /** @test */
    public function it_will_update_the_cart_if_the_item_already_exists_in_the_cart()
    {
        $cart = $this->getCart();

        $item = new BuyableProduct;

        $cart->add($item);
        $cart->add($item);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    /** @test */
    public function it_will_keep_updating_the_quantity_when_an_item_is_added_multiple_times()
    {
        $cart = $this->getCart();

        $item = new BuyableProduct;

        $cart->add($item);
        $cart->add($item);
        $cart->add($item);

        $this->assertItemsInCart(3, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    /** @test */
    public function it_can_update_the_quantity_of_an_existing_item_in_the_cart()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('3530c9670bae9d337f1806295206091b', 2);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);

        Event::assertDispatched('cart.updated');
    }

    /** @test */
    public function it_can_update_an_existing_item_in_the_cart_from_a_buyable()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('3530c9670bae9d337f1806295206091b', new BuyableProduct(1, 'Different description'));

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('Different description', $cart->get('3530c9670bae9d337f1806295206091b')->name);

        Event::assertDispatched('cart.updated');
    }

    /** @test */
    public function it_can_update_an_existing_item_in_the_cart_from_an_array()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('3530c9670bae9d337f1806295206091b', ['name' => 'Different description']);

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('Different description', $cart->get('3530c9670bae9d337f1806295206091b')->name);

        Event::assertDispatched('cart.updated');
    }

    /**
     * @test
     * @expectedException \Gloudemans\Shoppingcart\Exceptions\InvalidRowIDException
     */
    public function it_will_throw_an_exception_if_a_rowid_was_not_found()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('none-existing-rowid', new BuyableProduct(1, 'Different description'));
    }

    /** @test */
    public function it_will_regenerate_the_rowid_if_the_options_changed()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct, 1, ['color' => 'red']);

        $cart->update('0f2ae8d78274cefebda50879cd65e896', ['options' => ['color' => 'blue']]);

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('f080a49705d0bb6e76bf35d7160e6859', $cart->content()->first()->rowId);
        $this->assertEquals('blue', $cart->get('f080a49705d0bb6e76bf35d7160e6859')->options->color);
    }

    /** @test */
    public function it_will_add_the_item_to_an_existing_row_if_the_options_changed_to_an_existing_rowid()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct, 1, ['color' => 'red']);
        $cart->add(new BuyableProduct, 1, ['color' => 'blue']);

        $cart->update('f080a49705d0bb6e76bf35d7160e6859', ['options' => ['color' => 'red']]);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    /** @test */
    public function it_can_remove_an_item_from_the_cart()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->remove('3530c9670bae9d337f1806295206091b');

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    /** @test */
    public function it_will_remove_the_item_if_its_quantity_was_set_to_zero()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('3530c9670bae9d337f1806295206091b', 0);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    /** @test */
    public function it_will_remove_the_item_if_its_quantity_was_set_negative()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('3530c9670bae9d337f1806295206091b', -1);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    /** @test */
    public function it_can_get_an_item_from_the_cart_by_its_rowid()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cartItem = $cart->get('3530c9670bae9d337f1806295206091b');

        $this->assertInstanceOf(CartItem::class, $cartItem);
    }

    /** @test */
    public function it_can_get_the_content_of_the_cart()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1));
        $cart->add(new BuyableProduct(2));

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertCount(2, $content);
    }

    /** @test */
    public function it_will_return_an_empty_collection_if_the_cart_is_empty()
    {
        $cart = $this->getCart();

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertCount(0, $content);
    }

    /** @test */
    public function it_will_include_the_tax_and_subtotal_when_converted_to_an_array()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1));
        $cart->add(new BuyableProduct(2));

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertEquals([
            '3530c9670bae9d337f1806295206091b' => [
                'rowId' => '3530c9670bae9d337f1806295206091b',
                'id' => 1,
                'name' => 'Item name',
                'qty' => 1,
                'price' => 10.00,
                'tax' => 2.10,
                'subtotal' => 10.0,
                'isSaved' => false,
                'options' => [],
                'extras' => [],
                'discount' => 0,
                'discountTotal' => 0,
                'priceDiscount' => 10.00,
                'discountRate' => [
                    'value' => 0,
                    'type' => 'currency',
                    'description' => '',
                    'symbol' => '-'
                ]
            ],
            'b075a4fdc839e28270cf955644f691c5' => [
                'rowId' => 'b075a4fdc839e28270cf955644f691c5',
                'id' => 2,
                'name' => 'Item name',
                'qty' => 1,
                'price' => 10.00,
                'tax' => 2.10,
                'subtotal' => 10.0,
                'isSaved' => false,
                'options' => [],
                'extras' => [],
                'discount' => 0,
                'discountTotal' => 0,
                'priceDiscount' => 10.00,
                'discountRate' => [
                    'value' => 0,
                    'type' => 'currency',
                    'description' => '',
                    'symbol' => '-'
                ]
            ]
        ], $content->toArray());
    }

    /** @test */
    public function it_can_destroy_a_cart()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $this->assertItemsInCart(1, $cart);

        $cart->destroy();

        $this->assertItemsInCart(0, $cart);
    }

    /** @test */
    public function it_can_get_the_total_price_of_the_cart_content()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item', 10.00));
        $cart->add(new BuyableProduct(2, 'Second item', 25.00), 2);

        $this->assertItemsInCart(3, $cart);
        $this->assertEquals(60.00, $cart->subtotal());
    }

    /** @test */
    public function it_can_return_a_formatted_total()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item', 1000.00));
        $cart->add(new BuyableProduct(2, 'Second item', 2500.00), 2);

        $this->assertItemsInCart(3, $cart);
        $this->assertEquals('6.000,00', $cart->subtotal(2, ',', '.'));
    }

    /** @test */
    public function it_can_search_the_cart_for_a_specific_item()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some item'));
        $cart->add(new BuyableProduct(2, 'Another item'));

        $cartItem = $cart->search(function ($cartItem, $rowId) {
            return $cartItem->name == 'Some item';
        });

        $this->assertInstanceOf(Collection::class, $cartItem);
        $this->assertCount(1, $cartItem);
        $this->assertInstanceOf(CartItem::class, $cartItem->first());
        $this->assertEquals(1, $cartItem->first()->id);
    }

    /** @test */
    public function it_can_search_the_cart_for_multiple_items()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some item'));
        $cart->add(new BuyableProduct(2, 'Some item'));
        $cart->add(new BuyableProduct(3, 'Another item'));

        $cartItem = $cart->search(function ($cartItem, $rowId) {
            return $cartItem->name == 'Some item';
        });

        $this->assertInstanceOf(Collection::class, $cartItem);
    }

    /** @test */
    public function it_can_search_the_cart_for_a_specific_item_with_options()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some item'), 1, ['color' => 'red']);
        $cart->add(new BuyableProduct(2, 'Another item'), 1, ['color' => 'blue']);

        $cartItem = $cart->search(function ($cartItem, $rowId) {
            return $cartItem->options->color == 'red';
        });

        $this->assertInstanceOf(Collection::class, $cartItem);
        $this->assertCount(1, $cartItem);
        $this->assertInstanceOf(CartItem::class, $cartItem->first());
        $this->assertEquals(1, $cartItem->first()->id);
    }

    /** @test */
    public function it_will_associate_the_cart_item_with_a_model_when_you_add_a_buyable()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cartItem = $cart->get('3530c9670bae9d337f1806295206091b');

        $this->assertContains(BuyableProduct::class, Assert::readAttribute($cartItem, 'associatedModel'));
    }

    /** @test */
    public function it_can_associate_the_cart_item_with_a_model()
    {
        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 10.00);

        $cart->associate('3530c9670bae9d337f1806295206091b', new ProductModel);

        $cartItem = $cart->get('3530c9670bae9d337f1806295206091b');

        $this->assertEquals(ProductModel::class, Assert::readAttribute($cartItem, 'associatedModel'));
    }

    /**
     * @test
     * @expectedException \Gloudemans\Shoppingcart\Exceptions\UnknownModelException
     * @expectedExceptionMessage The supplied model SomeModel does not exist.
     */
    public function it_will_throw_an_exception_when_a_non_existing_model_is_being_associated()
    {
        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 10.00);

        $cart->associate('3530c9670bae9d337f1806295206091b', 'SomeModel');
    }

    /** @test */
    public function it_can_get_the_associated_model_of_a_cart_item()
    {
        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 10.00);

        $cart->associate('3530c9670bae9d337f1806295206091b', new ProductModel);

        $cartItem = $cart->get('3530c9670bae9d337f1806295206091b');

        $this->assertInstanceOf(ProductModel::class, $cartItem->model);
        $this->assertEquals('Some value', $cartItem->model->someValue);
    }

    /** @test */
    public function it_can_calculate_the_subtotal_of_a_cart_item()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 9.99), 3);

        $cartItem = $cart->get('3530c9670bae9d337f1806295206091b');

        $this->assertEquals(29.97, $cartItem->subtotal);
    }

    /** @test */
    public function it_can_return_a_formatted_subtotal()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 500), 3);

        $cartItem = $cart->get('3530c9670bae9d337f1806295206091b');

        $this->assertEquals('1.500,00', $cartItem->subtotal(2, ',', '.'));
    }

    /** @test */
    public function it_can_calculate_discount_based_on_the_specified_discount()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);

        $cart->setDiscount('3530c9670bae9d337f1806295206091b', 10, 'percentage', 'Same Discount');

        $cartItem = $cart->get('3530c9670bae9d337f1806295206091b');

        $this->assertEquals(1.00, $cartItem->discount);
    }

    /** @test */
    public function it_can_calculate_tax_based_on_the_default_tax_rate_in_the_config()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);

        $cartItem = $cart->get('3530c9670bae9d337f1806295206091b');

        $this->assertEquals(2.10, $cartItem->tax);
    }

    /** @test */
    public function it_can_calculate_tax_based_on_the_specified_tax()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);

        $cart->setTax('3530c9670bae9d337f1806295206091b', 19);

        $cartItem = $cart->get('3530c9670bae9d337f1806295206091b');

        $this->assertEquals(1.90, $cartItem->tax);
    }

    /** @test */
    public function it_can_calculate_tax_based_on_discounted_price()
    {
        $this->app['config']->set('cart.calculate_taxes_on_discounted_price', true); //Set the calculate on discounted price

        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);

        $cart->setDiscount('3530c9670bae9d337f1806295206091b', 10, 'percentage', 'Same Discount');

        $cart->setTax('3530c9670bae9d337f1806295206091b', 10);

        $cartItem = $cart->get('3530c9670bae9d337f1806295206091b');

        $this->assertEquals(0.90000000000000002, $cartItem->tax);
    }

    /** @test */
    public function it_can_return_the_calculated_tax_formatted()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10000.00), 1);

        $cartItem = $cart->get('3530c9670bae9d337f1806295206091b');

        $this->assertEquals('2.100,00', $cartItem->tax(2, ',', '.'));
    }

    /** @test */
    public function it_can_calculate_the_total_tax_for_all_cart_items()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 20.00), 2);

        $this->assertEquals(10.50, $cart->tax);
    }

    /** @test */
    public function it_can_return_formatted_total_tax()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('1.050,00', $cart->tax(2, ',', '.'));
    }

    /** @test */
    public function it_can_return_the_subtotal()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 20.00), 2);

        $this->assertEquals(50.00, $cart->subtotal);
    }

    /** @test */
    public function it_can_return_formatted_subtotal()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('5000,00', $cart->subtotal(2, ',', ''));
    }

    /** @test */
    public function it_can_return_cart_formated_numbers_by_config_values()
    {
        $this->setConfigFormat(2, ',', '');

        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('5000,00', $cart->subtotal());
        $this->assertEquals('1050,00', $cart->tax());
        $this->assertEquals('6050,00', $cart->total());
    }

    /** @test */
    public function it_can_return_cart_float_numbers()
    {
        $this->setConfigFormat(2, ',', '');

        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals(5000.00, $cart->subtotal);
        $this->assertEquals(1050.00, $cart->tax);
        $this->assertEquals(6050.00, $cart->total);
    }

    /** @test */
    public function it_can_return_cartItem_formated_numbers_by_config_values()
    {
        $this->setConfigFormat(2, ',', '');

        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 2000.00), 2);

        $cart->setDiscount('3530c9670bae9d337f1806295206091b', 10, 'percentage', 'Same Discount');

        $cartItem = $cart->get('3530c9670bae9d337f1806295206091b');

        $this->assertEquals('2000,00', $cartItem->price());
        $this->assertEquals('1800,00', $cartItem->priceDiscount());
        $this->assertEquals('2220,00', $cartItem->priceTax());
        $this->assertEquals('4000,00', $cartItem->subtotal());
        $this->assertEquals('4440,00', $cartItem->total());
        $this->assertEquals('200,00', $cartItem->discount());
        $this->assertEquals('400,00', $cartItem->discountTotal());
        $this->assertEquals('420,00', $cartItem->tax());
        $this->assertEquals('840,00', $cartItem->taxTotal());
    }

    /** @test */
    public function it_can_store_the_cart_in_a_database()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->store($identifier = 123);

        $serialized = serialize($cart->content());

        $this->assertDatabaseHas('shoppingcart', ['identifier' => $identifier, 'instance' => 'default', 'content' => $serialized]);

        Event::assertDispatched('cart.stored');
    }

    /** @test */
    public function it_can_update_the_cart_in_database()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->store($identifier = 123);

        $serialized = serialize($cart->content());

        $this->assertDatabaseHas('shoppingcart', ['identifier' => $identifier, 'instance' => 'default', 'content' => $serialized]);

        Event::assertDispatched('cart.stored');
    }

    /** @test */
    public function it_can_restore_a_cart_from_the_database()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->store($identifier = 123);

        $cart->destroy();

        $this->assertItemsInCart(0, $cart);

        $cart->restore($identifier);

        $this->assertItemsInCart(1, $cart);

        Event::assertDispatched('cart.restored');
    }

    /** @test */
    public function it_will_just_keep_the_current_instance_if_no_cart_with_the_given_identifier_was_stored()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        $cart = $this->getCart();

        $cart->restore($identifier = 123);

        $this->assertItemsInCart(0, $cart);
    }

    /** @test */
    public function it_can_calculate_all_values()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);

        $cartItem = $cart->get('3530c9670bae9d337f1806295206091b');

        $cart->setTax('3530c9670bae9d337f1806295206091b', 19);
        $cart->setDiscount('3530c9670bae9d337f1806295206091b', 10, 'percentage', 'Same Discount');

        $this->assertEquals(10.00, $cartItem->price(2));
        $this->assertEquals(9.00, $cartItem->priceDiscount(2));
        $this->assertEquals(10.90, $cartItem->priceTax(2));
        $this->assertEquals(20.00, $cartItem->subtotal(2));
        $this->assertEquals(21.80, $cartItem->total(2));
        $this->assertEquals(1.00, $cartItem->discount(2));
        $this->assertEquals(2.00, $cartItem->discountTotal(2));
        $this->assertEquals(1.90, $cartItem->tax(2));
        $this->assertEquals(3.80, $cartItem->taxTotal(2));

        $this->assertEquals(20.00, $cart->subtotal(2));
        $this->assertEquals(21.80, $cart->total(2));
        $this->assertEquals(3.80, $cart->tax(2));
    }

    /** @test */
    public function it_will_destroy_the_cart_when_the_user_logs_out_and_the_config_setting_was_set_to_true()
    {
        $this->app['config']->set('cart.destroy_on_logout', true);

        $this->app->instance(SessionManager::class, Mockery::mock(SessionManager::class, function ($mock) {
            $mock->shouldReceive('forget')->once()->with('cart');
        }));

        $user = Mockery::mock(Authenticatable::class);

        event(new Logout(config('auth.guards'), $user));
    }

    /**
     * Get an instance of the cart.
     *
     * @return \Gloudemans\Shoppingcart\Cart
     */
    private function getCart()
    {
        $session = $this->app->make('session');
        $events = $this->app->make('events');

        return new Cart($session, $events);
    }

    /**
     * Set the config number format.
     *
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     */
    private function setConfigFormat($decimals, $decimalPoint, $thousandSeperator)
    {
        $this->app['config']->set('cart.format.decimals', $decimals);
        $this->app['config']->set('cart.format.decimal_point', $decimalPoint);
        $this->app['config']->set('cart.format.thousand_seperator', $thousandSeperator);
    }
}
