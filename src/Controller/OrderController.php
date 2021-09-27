<?php

namespace App\Controller;

use App\Classe\Cart;
use App\Entity\Order;
use App\Entity\OrderDetails;
use App\Form\OrderType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrderController extends AbstractController
{

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/order", name="order")
     */
    public function index(Cart $cart): Response
    {
        if (!$this->getUser()->getAddresses()->getValues()) {
            return $this->redirectToRoute('account_address_add');
        }

        $form = $this->createForm(OrderType::class, null, [
            'user' => $this->getUser()
        ]);

//        $form->handleRequest($request);
//        if ($form->isSubmitted() && $form->isValid()) {
//            dd($form->getData());
//        }

        return $this->render('order/index.html.twig', [
            'form' => $form->createView(),
            'cart' => $cart->getCart()
        ]);
    }

    /**
     * @Route("/order/summary", name="order_summary", methods={"POST"})
     */
    public function summary(Cart $cart, Request $request): Response
    {
        $form = $this->createForm(OrderType::class, null, [
            'user' => $this->getUser()
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $date = new \DateTime();
            $carriers = $form->get('carriers')->getData();
            $delivery = $form->get('addresses')->getData();
            $delivery_content = $delivery->getFirstname() . ' ' . $delivery->getLastname();
            if ($delivery->getCompany()) {
                $delivery_content .= '<br/>' . $delivery->getCompany();
            }
            $delivery_content .= '<br/>' . $delivery->getAddress();
            $delivery_content .= '<br/>' . $delivery->getPostal() . ' ' . $delivery->getCity();
            $delivery_content .= '<br/>' . $delivery->getCountry();
            $delivery_content .= '<br/>' . $delivery->getPhone();

            $order = new Order();
            $order->setReference($date->format('dmY') . '-' . uniqid());
            $order->setUser($this->getUser());
            $order->setCraetedAt($date);
            $order->setCarriername($carriers->getName());
            $order->setCarrierprice($carriers->getPrice());
            $order->setDelivery($delivery);
            $order->setIsPaid(false);

            $this->entityManager->persist($order);

            foreach ($cart->getCart() as $item) {
                $orderDetails = new OrderDetails();
                $orderDetails->setMyorder($order);
                $orderDetails->setProduct($item['product']->getName());
                $orderDetails->setQuantity($item['quantity']);
                $orderDetails->setPrice($item['product']->getPrice());
                $orderDetails->setTotal($item['product']->getPrice() * $item['quantity']);
                $this->entityManager->persist($orderDetails);
            }

            $this->entityManager->flush();

            return $this->render('order/add.html.twig', [
                'cart' => $cart->getCart(),
                'carrier' => $carriers,
                'delivery' => $delivery_content,
                'reference' => $order->getReference()
            ]);

        }

        return $this->redirectToRoute('cart');
    }


}
