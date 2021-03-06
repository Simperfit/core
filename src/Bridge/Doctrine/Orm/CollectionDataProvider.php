<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ApiPlatform\Core\Bridge\Doctrine\Orm;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultExtensionInterface;
use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * Collection data provider for the Doctrine ORM.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Samuel ROZE <samuel.roze@gmail.com>
 */
class CollectionDataProvider implements CollectionDataProviderInterface
{
    private $managerRegistry;
    private $collectionExtensions;

    /**
     * @param ManagerRegistry                     $managerRegistry
     * @param QueryCollectionExtensionInterface[] $collectionExtensions
     */
    public function __construct(ManagerRegistry $managerRegistry, array $collectionExtensions = [])
    {
        $this->managerRegistry = $managerRegistry;
        $this->collectionExtensions = $collectionExtensions;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection(string $resourceClass, string $operationName = null)
    {
        $manager = $this->managerRegistry->getManagerForClass($resourceClass);
        if (null === $manager) {
            throw new ResourceClassNotSupportedException();
        }

        $repository = $manager->getRepository($resourceClass);
        $queryBuilder = $repository->createQueryBuilder('o');

        foreach ($this->collectionExtensions as $extension) {
            $extension->applyToCollection($queryBuilder, $resourceClass, $operationName);

            if ($extension instanceof QueryResultExtensionInterface) {
                if ($extension->supportsResult($resourceClass, $operationName)) {
                    return $extension->getResult($queryBuilder);
                }
            }
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
