<?php

namespace Boot\Http;

use Boot\Boot;
use Boot\Builder;
use Boot\Http\Router\RouteOptions;
use Boot\Utils\StringUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class WebBuilder.
 */
class WebBuilder extends Builder
{
    const HTTP_GET = 'GET';
    const HTTP_POST = 'POST';
    const HTTP_PUT = 'PUT';
    const HTTP_DELETE = 'DELETE';
    const HTTP_PATCH = 'PATCH';

    /** @var array  */
    private $defaultRouteParams = array();

    /** @var array  */
    private $defaultRouteRequirements = array();

    /** @var  RouteCollection */
    private $routeCollection;

    /** @var string  */
    private $httpServiceIdentifier = 'http';

    /**
     * @param $projectDir
     */
    public function __construct($projectDir)
    {
        parent::__construct($projectDir);
        $this->routeCollection = new RouteCollection();
    }

    /**
     * @return Application|ContainerInterface
     */
    public function build()
    {
        $isDebug = $this->environment !== Boot::PRODUCTION;

        // Set defaults
        $this->routeCollection->addDefaults($this->defaultRouteParams);
        $this->routeCollection->addRequirements($this->defaultRouteRequirements);

        $this->initializer(new HttpServiceInitializer(
            $this->getHttpServiceIdentifier(),
            $isDebug
        ));

        // Create web application. Decorates the container and adds the 'run' method.
        return new Application(
            parent::build(),
            $this->getHttpServiceIdentifier()
        );
    }

    /**
     * @param string $baseUrl
     *
     * @return $this
     */
    public function baseUrl($baseUrl)
    {
        // The base url needs a prepended slash, for simplicity allow both /foo or foo as valid input
        $this->routeCollection->addPrefix('/'.ltrim($baseUrl, '/'));

        return $this;
    }

    /**
     * @param string       $path         e.g. /employees/{employeeId}
     * @param string       $service      e.g. App\Service\EmployeeService
     * @param string       $method       e.g. findOne
     * @param RouteOptions $routeOptions
     *
     * @return WebBuilder
     */
    public function get($path, $service, $method, RouteOptions $routeOptions)
    {
        $this->addService(
            $service,
            $method,
            $this->createMethod(self::HTTP_GET, $path, $routeOptions),
            $routeOptions
        );

        return $this;
    }

    /**
     * @param string       $path         e.g. /employees/{employeeId}
     * @param string       $service      e.g. App\Service\EmployeeService
     * @param string       $method       e.g. findOne
     * @param RouteOptions $routeOptions
     *
     * @return WebBuilder
     */
    public function post($path, $service, $method, RouteOptions $routeOptions)
    {
        $this->addService(
            $service,
            $method,
            $this->createMethod(self::HTTP_POST, $path, $routeOptions),
            $routeOptions
        );

        return $this;
    }

    /**
     * @param string       $path         e.g. /employees/{employeeId}
     * @param string       $service      e.g. App\Service\EmployeeService
     * @param string       $method       e.g. findOne
     * @param RouteOptions $routeOptions
     *
     * @return WebBuilder
     */
    public function put($path, $service, $method, RouteOptions $routeOptions)
    {
        $this->addService(
            $service,
            $method,
            $this->createMethod(self::HTTP_PUT, $path, $routeOptions),
            $routeOptions
        );

        return $this;
    }

    /**
     * @param string       $path         e.g. /employees/{employeeId}
     * @param string       $service      e.g. App\Service\EmployeeService
     * @param string       $method       e.g. findOne
     * @param RouteOptions $routeOptions
     *
     * @return WebBuilder
     */
    public function delete($path, $service, $method, RouteOptions $routeOptions)
    {
        $this->addService(
            $service,
            $method,
            $this->createMethod(self::HTTP_DELETE, $path, $routeOptions),
            $routeOptions
        );

        return $this;
    }

    /**
     * @param string       $path         e.g. /employees/{employeeId}
     * @param string       $service      e.g. App\Service\EmployeeService
     * @param string       $method       e.g. findOne
     * @param RouteOptions $routeOptions
     *
     * @return WebBuilder
     */
    public function patch($path, $service, $method, RouteOptions $routeOptions)
    {
        $this->addService(
            $service,
            $method,
            $this->createMethod(self::HTTP_PATCH, $path, $routeOptions),
            $routeOptions
        );

        return $this;
    }

    /**
     * Sets global route defaults.
     *
     * @param array $defaults
     *
     * @return WebBuilder
     */
    public function defaultRouteParams(array $defaults)
    {
        $this->defaultRouteParams = array_merge($this->defaultRouteParams, $defaults);

        return $this;
    }

    /**
     * Sets global route requirements.
     *
     * @param array $requirements
     *
     * @return WebBuilder
     */
    public function defaultRouteRequirements(array $requirements)
    {
        $this->defaultRouteRequirements = array_merge($this->defaultRouteRequirements, $requirements);

        return $this;
    }

    /**
     * @param string       $method       e.g.  GET, POST, PUT, DELETE or PATCH
     * @param string       $path
     * @param RouteOptions $routeOptions
     *
     * @return HttpMethod
     */
    private function createMethod($method, $path, RouteOptions $routeOptions)
    {
        // Sanitize path
        $path = '/'.ltrim($path, '/');

        // Validate the provided path
        $this->validateRoutePath($path);

        /** @var HttpMethod $route */
        $route = new HttpMethod($method, $routeOptions->getRouteName(), $path);
        $route = $route
            ->setDefaults($routeOptions->getDefaults())
            ->setRequirements($routeOptions->getRequirements())
        ;

        // Set expression when available
        if ($routeOptions->getExpression()) {
            $route->setExpr($routeOptions->getExpression());
        }

        return $route;
    }

    /**
     * @param string $path
     *
     * @throws \LogicException
     */
    private function validateRoutePath($path)
    {
        // The underscore as a prefix is reserved for the framework to store route specific metadata.
        // I chose to reserve the underscore for the framework to make it easy to extend route specific metadata.
        foreach (StringUtils::extractStringsEnclosedWith($path, '{', '}') as $routeParam) {
            if (StringUtils::startWith($routeParam, '_')) {
                throw new \LogicException(
                    "Illegal route parameter '{$routeParam}'! The underscore prefix is reserved for the " .
                    "framework to store route specific metadata."
                );
            }
        }
    }

    /**
     * @param string       $serviceName
     * @param string       $methodName
     * @param HttpMethod   $method
     * @param RouteOptions $routeOptions
     */
    private function addService($serviceName, $methodName, HttpMethod $method, RouteOptions $routeOptions)
    {
        // Get the remote access policy
        $accessPolicy = $routeOptions->getRemoteAccessPolicy();

        // Create symfony route
        $route = $method->createRoute()->addDefaults([
            '_serviceClass' => $serviceName,
            '_serviceMethod' => $methodName,
            '_publicIpRangesDenied' => $accessPolicy->isPublicIpRangesDenied(),
            '_privateIpRangesDenied' => $accessPolicy->isPrivateIpRangesDenied(),
            '_reservedIpRangesDenied' => $accessPolicy->isReservedIpRangedDenied(),
            '_whitelistHosts' => $accessPolicy->getWhitelistHosts(),
            '_blacklistHosts' => $accessPolicy->getBlacklistHosts(),
            '_whitelistIps' => $accessPolicy->getWhitelistIps(),
            '_blacklistIps' => $accessPolicy->getBlacklistIps(),
        ]);

        // Add to route collection
        $this->routeCollection->add($method->getName(), $route);
    }

    /**
     * @return RouteCollection
     */
    public function getRouteCollection()
    {
        return $this->routeCollection;
    }

    /**
     * The service ID used to lookup the HTTP service in the DI container.
     * The default value is 'http'.
     *
     * @param string $serviceIdentifier
     *
     * @return $this
     */
    public function httpServiceIdentifier($serviceIdentifier)
    {
        $this->httpServiceIdentifier = $serviceIdentifier;

        return $this;
    }

    /**
     * @return string
     */
    public function getHttpServiceIdentifier()
    {
        return $this->httpServiceIdentifier;
    }

    /**
     * @return array
     */
    public function getDefaultRouteParams()
    {
        return $this->defaultRouteParams;
    }

    /**
     * @return array
     */
    public function getDefaultRouteRequirements()
    {
        return $this->defaultRouteRequirements;
    }
}
