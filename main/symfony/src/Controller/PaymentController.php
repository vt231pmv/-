<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\User;
use App\Repository\CreditRepository;
use App\Repository\PaymentRepository;
use App\Service\PaginationService;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/payments', name: 'api_payments_')]
class PaymentController extends AbstractController
{
    #[Route('', name: 'list', methods: [Request::METHOD_GET])]
    public function list(Request $request, PaymentRepository $repository, PaginationService $paginationService): JsonResponse
    {
        $filters = $request->query->all();

        /** @var User $user */
        $user = $this->getUser();
        if ($user && $user->getRole() === 'client') {
            $filters['clientId'] = $user->getClientId();
        }

        $qb = $repository->getFilteredQueryBuilder($filters);
        $result = $paginationService->paginate($qb, $request);

        return new JsonResponse($result, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'show', methods: [Request::METHOD_GET])]
    public function show(int $id, PaymentRepository $repository): JsonResponse
    {
        $payment = $repository->find($id);
        if (!$payment) {
            return new JsonResponse(['error' => 'Платіж не знайдено'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();
        if ($user && $user->getRole() === 'client') {
            $credit = $payment->getCredit();
            if ($credit->getClient()->getId() !== $user->getClientId()) {
                return new JsonResponse(['error' => 'Доступ заборонено'], Response::HTTP_FORBIDDEN);
            }
        }

        return new JsonResponse(['data' => $payment->toArray()], Response::HTTP_OK);
    }

    #[Route('', name: 'create', methods: [Request::METHOD_POST])]
    public function create(Request $request, CreditRepository $creditRepo, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user && $user->getRole() === 'client') {
            return new JsonResponse(['error' => 'Доступ заборонено. Тільки менеджер або адміністратор може реєструвати платежі'], Response::HTTP_FORBIDDEN);
        }

        $body = json_decode($request->getContent(), true) ?? [];

        if (empty($body['creditId']) || empty($body['amount'])) {
            return new JsonResponse([
                'error' => 'Поля creditId та amount є обов\'язковими'
            ], Response::HTTP_BAD_REQUEST);
        }

        $credit = $creditRepo->find($body['creditId']);
        if (!$credit) {
            return new JsonResponse(['error' => 'Кредит не знайдено'], Response::HTTP_NOT_FOUND);
        }

        $paymentAmount = (string)$body['amount'];

        $payment = new Payment();
        $payment->setCredit($credit)
            ->setAmount($paymentAmount)
            ->setPaymentMethod($body['paymentMethod'] ?? 'bank_transfer')
            ->setStatus($body['status'] ?? 'completed')
            ->setPaymentDate(!empty($body['paymentDate']) ? new \DateTime($body['paymentDate']) : new \DateTime());

        $newRemaining = max(0, (float)$credit->getRemainingAmount() - (float)$paymentAmount);
        $credit->setRemainingAmount((string)$newRemaining);
        if ($newRemaining == 0) {
            $credit->setStatus('paid');
        }

        $em->persist($payment);
        $em->flush();

        return new JsonResponse([
            'data'    => $payment->toArray(),
            'message' => 'Платіж успішно проведено'
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: [Request::METHOD_PUT, Request::METHOD_PATCH])]
    public function update(int $id, Request $request, PaymentRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user && $user->getRole() === 'client') {
            return new JsonResponse(['error' => 'Доступ заборонено. Тільки менеджер або адміністратор може оновлювати платежі'], Response::HTTP_FORBIDDEN);
        }

        $payment = $repository->find($id);
        if (!$payment) {
            return new JsonResponse(['error' => 'Платіж не знайдено'], Response::HTTP_NOT_FOUND);
        }

        $body = json_decode($request->getContent(), true) ?? [];

        if (isset($body['amount']))        $payment->setAmount((string)$body['amount']);
        if (isset($body['paymentMethod'])) $payment->setPaymentMethod($body['paymentMethod']);
        if (isset($body['status']))        $payment->setStatus($body['status']);

        $em->flush();

        return new JsonResponse([
            'data'    => $payment->toArray(),
            'message' => 'Дані платежу успішно оновлено'
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: [Request::METHOD_DELETE])]
    public function delete(int $id, PaymentRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user && $user->getRole() !== 'admin') {
            return new JsonResponse(['error' => 'Доступ заборонено. Тільки адміністратор може видаляти платежі'], Response::HTTP_FORBIDDEN);
        }

        $payment = $repository->find($id);
        if (!$payment) {
            return new JsonResponse(['error' => 'Платіж не знайдено'], Response::HTTP_NOT_FOUND);
        }

        try {
            $em->remove($payment);
            $em->flush();
        } catch (ForeignKeyConstraintViolationException $e) {
            return new JsonResponse(['error' => 'Неможливо видалити платіж через обмеження зовнішнього ключа'], Response::HTTP_CONFLICT);
        }

        return new JsonResponse(['message' => 'Платіж успішно видалено'], Response::HTTP_OK);
    }
}
