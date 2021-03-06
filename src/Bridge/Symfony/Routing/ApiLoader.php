<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ApiPlatform\Core\Bridge\Symfony\Routing;

use ApiPlatform\Core\Exception\InvalidResourceException;
use ApiPlatform\Core\Exception\RuntimeException;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Core\Naming\ResourcePathNamingStrategyInterface;
use Doctrine\Common\Inflector\Inflector;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Loader\XmlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Loads Resources.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class ApiLoader extends Loader
{
    const ROUTE_NAME_PREFIX = 'api_';
    const DEFAULT_ACTION_PATTERN = 'api_platform.action.';

    private $fileLoader;
    private $resourceNameCollectionFactory;
    private $resourceMetadataFactory;
    private $resourcePathGenerator;
    private $container;
    private $formats;

    public function __construct(KernelInterface $kernel, ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory, ResourceMetadataFactoryInterface $resourceMetadataFactory, ResourcePathNamingStrategyInterface $resourcePathGenerator, ContainerInterface $container, array $formats)
    {
        $this->fileLoader = new XmlFileLoader(new FileLocator($kernel->locateResource('@ApiPlatformBundle/Resources/config/routing')));
        $this->resourceNameCollectionFactory = $resourceNameCollectionFactory;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->resourcePathGenerator = $resourcePathGenerator;
        $this->container = $container;
        $this->formats = $formats;
    }

    /**
     * {@inheritdoc}
     */
    public function load($data, $type = null)
    {
        $routeCollection = new RouteCollection();

        $this->loadExternalFiles($routeCollection);

        if ($this->container->getParameter('api_platform.enable_swagger')) {
            $routeCollection->addCollection($this->fileLoader->load('swagger.xml'));
        }
        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
            $resourceShortName = $resourceMetadata->getShortName();

            if (null === $resourceShortName) {
                throw new InvalidResourceException(sprintf('Resource %s has no short name defined.', $resourceClass));
            }

            foreach ($resourceMetadata->getCollectionOperations() as $operationName => $operation) {
                $this->addRoute($routeCollection, $resourceClass, $operationName, $operation, $resourceShortName, true);
            }

            foreach ($resourceMetadata->getItemOperations() as $operationName => $operation) {
                $this->addRoute($routeCollection, $resourceClass, $operationName, $operation, $resourceShortName, false);
            }
        }

        return $routeCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return 'api_platform' === $type;
    }

    /**
     * Load external files.
     *
     * @param RouteCollection $routeCollection
     */
    private function loadExternalFiles(RouteCollection $routeCollection)
    {
        $routeCollection->addCollection($this->fileLoader->load('api.xml'));

        if (isset($this->formats['jsonld'])) {
            $routeCollection->addCollection($this->fileLoader->load('jsonld.xml'));
            $routeCollection->addCollection($this->fileLoader->load('hydra.xml'));
        }
    }

    /**
     * Creates and adds a route for the given operation to the route collection.
     *
     * @param RouteCollection $routeCollection
     * @param string          $resourceClass
     * @param string          $operationName
     * @param array           $operation
     * @param string          $resourceShortName
     * @param bool            $collection
     *
     * @throws RuntimeException
     */
    private function addRoute(RouteCollection $routeCollection, string $resourceClass, string $operationName, array $operation, string $resourceShortName, bool $collection)
    {
        if (isset($operation['route_name'])) {
            return;
        }

        if (!isset($operation['method'])) {
            throw new RuntimeException('Either a "route_name" or a "method" operation attribute must exist.');
        }

        $controller = $operation['controller'] ?? null;
        $collectionType = $collection ? 'collection' : 'item';
        $actionName = sprintf('%s_%s', strtolower($operation['method']), $collectionType);

        if (null === $controller) {
            $controller = self::DEFAULT_ACTION_PATTERN.$actionName;

            if (!$this->container->has($controller)) {
                throw new RuntimeException(sprintf('There is no builtin action for the %s %s operation. You need to define the controller yourself.', $collectionType, $operation['method']));
            }
        }

        if ($operationName !== strtolower($operation['method'])) {
            $actionName = sprintf('%s_%s', $operationName, $collection ? 'collection' : 'item');
        }

        $path = $operation['path'] ?? null;

        if (null === $path) {
            $path = '/'.$this->resourcePathGenerator->generateResourceBasePath($resourceShortName);

            if (!$collection) {
                $path .= '/{id}';
            }

            $path .= '.{_format}';
        }

        $resourceRouteName = Inflector::pluralize(Inflector::tableize($resourceShortName));
        $routeName = sprintf('%s%s_%s', self::ROUTE_NAME_PREFIX, $resourceRouteName, $actionName);

        $route = new Route(
            $path,
            [
                '_controller' => $controller,
                '_format' => null,
                '_api_resource_class' => $resourceClass,
                sprintf('_api_%s_operation_name', $collection ? 'collection' : 'item') => $operationName,
            ],
            [],
            [],
            '',
            [],
            [$operation['method']]
        );

        $routeCollection->add($routeName, $route);
    }
}
