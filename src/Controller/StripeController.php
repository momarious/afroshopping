<?php

namespace App\Controller;

use App\Classe\Cart;
use App\Entity\Order;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StripeController extends AbstractController
{
    /**
     * @Route("/order/create-session/{reference}", name="create_checkout_session")
     */
    public function index(EntityManagerInterface $entityManager, Cart $cart, $reference): Response
    {

        $order = $entityManager->getRepository(Order::class)->findOneByReference($reference);
        if (!$order) {
            new JsonResponse(['error' => 'order']);
        }

        $YOUR_DOMAIN = 'http://127.0.0.1:8000';
        Stripe::setApiKey('sk_test_51JYW6fIyWISoWdIjJn200q6rjkdol5tpGAhKpgk2Q6y1HfGthoNueWf2MNAHvS901z4HBV3d5VMSGVYZg799yROi00nlL45hZq');
        $line_items = [];

        foreach ($order->getOrderDetails()->getValues() as $item) {
            $product = $entityManager->getRepository(Product::class)->findOneByName($item->getProduct());
            $line_items[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $item->getPrice(),
                    'product_data' => [
                        'name' => $item->getProduct(),
                        'images' => [$YOUR_DOMAIN . '/uploads/' . $product->getIllustration()]
                    ]
                ],
                'quantity' => $item->getQuantity(),
            ];
        }

        $line_items[] = [
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => "" .$order->getCarrierPrice() * 100,
                'product_data' => [
                    'name' => $order->getCarrierName(),
                    'images' => [$YOUR_DOMAIN]
                ]
            ],
            'quantity' => 1,
        ];

        $checkout_session = Session::create([
            'customer_email' => $this->getUser()->getYes(),
//            'shipping_address_collection' => [
//                'allowed_countries' => ['SN'],
//            ],
            'payment_method_types' => ['card'],
            'line_items' => [$line_items],
            'mode' => 'payment',
            'success_url' => $YOUR_DOMAIN . '/order/success/{CHECKOUT_SESSION_ID}',
            'cancel_url' => $YOUR_DOMAIN . '/order/cancel/{CHECKOUT_SESSION_ID}',
        ]);

        $order->setStripeSessionId($checkout_session->id);
        $entityManager->flush();
        return new JsonResponse(['id' => $checkout_session->id]);
    }
}
