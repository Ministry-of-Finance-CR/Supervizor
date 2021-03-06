<?php

/*
 * Copyright (C) 2016 Adam Schubert <adam.schubert@sg1-game.net>.
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301  USA
 */

namespace Supervizor\Invoice;

use Supervizor\Budget\BudgetGroup;
use Kdyby\Doctrine\EntityManager;
use Supervizor\Supplies\Supplier;

class InvoiceRepository
{

    /** @var \Kdyby\Doctrine\EntityRepository */
    private $invoiceRepository;

    /** @var \Kdyby\Doctrine\EntityRepository */
    private $budgetGroupRepository;

    /** @var \Kdyby\Doctrine\EntityRepository */
    private $invoiceItemRepository;

    /**
     * InvoiceRepository constructor.
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->invoiceRepository = $entityManager->getRepository(Invoice::class);
        $this->budgetGroupRepository = $entityManager->getRepository(BudgetGroup::class);
        $this->invoiceItemRepository = $entityManager->getRepository(InvoiceItem::class);
    }

    /**
     * @param $budgetItem
     * @param $invoice
     * @return mixed|null|object
     */
    public function findItemByInvoiceAndBudgetItem($budgetItem, $invoice)
    {
        return $this->invoiceItemRepository->findOneBy(['budgetItem' => $budgetItem, 'invoice' => $invoice]);
    }

    /**
     * @param $identifier
     * @return mixed|null|object
     */
    public function findByIdentifier($identifier)
    {
        return $this->invoiceRepository->findOneBy(['identifier' => $identifier]);
    }

    /**
     * @return mixed|null|object
     */
    public function getLastUpdated()
    {
        return $this->invoiceRepository->findOneBy([], ['updated' => 'DESC']);
    }

    /**
     * @param Supplier $supplier
     * @param BudgetGroup $budgetGroup
     * @return Invoice[]
     */
    public function getBySupplierAndGroup(
        Supplier $supplier,
        BudgetGroup $budgetGroup,
        array $budgetItems = [],
        $dateFrom = null,
        $dateTo = null
    )
    {
        $qb = $this->invoiceRepository->createQueryBuilder('i')
            ->select('i')
            ->join('i.invoiceItems', 'ii')
            ->join('ii.budgetItem', 'bi')
            ->where('i.supplier = :supplier')
            ->andWhere('bi.budgetGroup = :budgetGroup')
            ->groupBy('i.id')
            ->setParameters(['supplier' => $supplier, 'budgetGroup' => $budgetGroup]);


        if (!empty($budgetItems)) {
            $qb->andWhere('bi.identifier IN (:budget_items)')
                ->setParameter('budget_items', $budgetItems);
        }

        if ($dateFrom) {
            $qb->andWhere('i.issued >= :issued_from')
                ->setParameter('issued_from', new \DateTime('@' . (int)$dateFrom));
        }

        if ($dateTo) {
            $qb->andWhere('i.issued <= :issued_to')
                ->setParameter('issued_to', new \DateTime('@' . (int)$dateTo));
        }

        return $qb->getQuery()->getResult();
    }



    /**
     * @param $query
     * @param null $limit
     * @param null $offset
     * @return Invoice[]
     */
    public function search($query, $limit = null, $offset = null)
    {
        $qb = $this->createSearchQuery($query);
        if ($limit) {
            $qb->setMaxResults($limit);
        }
        if ($offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }



    /**
     * @param $query
     * @return int
     */
    public function searchResultTotalCount($query)
    {
        $qb = $this->createSearchQuery($query);
        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($qb);
        return $paginator->count();
    }



    /**
     * @param $query
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function createSearchQuery($query)
    {
        $qb = $this->invoiceRepository->createQueryBuilder('i')
            ->select('i')
            ->join('i.invoiceItems', 'ii')
            ->join('i.supplier', 'isu')
            ->join('ii.budgetItem', 'bi')
            ->where('isu.name LIKE :query')
            ->orWhere('i.identifier LIKE :query')
            ->orWhere('bi.name LIKE :query')
            ->groupBy('i.id')
            ->setParameters(['query' => '%' . $query . '%']);
        return $qb;
    }

}
