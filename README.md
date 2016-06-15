# PHP Boot

Boot is a **minimalistic** framework aimed to develop lightweight PHP applications.
Well suited use cases for Boot are **micro services** and other applications for which micro frameworks are 
often a natural fit such as **console applications** or background processes. 


For usage examples check out the examples folder.

## Why Boot? 

The motivation for writing this framework arises from the need for a micro framework without having to sacrifice good design practices. 
My goal was to develop a framework that is fast, simple, free of as much verbosity as possible, but without sacrificing the features, clearity
and design practices that makes symfony one of the most popular PHP frameworks today.

The framework is a minimal wrapper of the well known service container, router and http components. 
This framework will sacrifice a few milliseconds per request for implementing core components that feature the full stack framework.



Boot provides a builder to bootstrap a PHP application. A special web builder is provided to bootstrap micro services. 

*Boot aims to be particularly useful for the following type of projects:*
* Micro-services or other light weight web applications
* Console applications / background processes
* Customized frameworks


Boot is highly extensible which makes it easy to fit the framework to your needs.


## Installation

### Composer

Add this to your composer.json:
```
{
    "require": {
        "leadtech/boot": "^1.0"
    }
}
```

## Examples

Examples: 

- Example 1:  Boot Console Application
- Example 2:  Boot Micro Service

*Note: For the complete example go to the examples folder*

### Example 1: Boot Console Application


#### Bootstrapping the application

```php
// Autoload packages
require_once __DIR__ . '/vendor/autoload.php';

// Build application
$rootDir = realpath(__DIR__ . '/..');
$app = (new \Boot\Builder($rootDir))
    ->appName('SimpleConsoleApplication')
    ->caching('cache', true)
    ->environment('prod')
    ->path('resources/config')
    ->path('src/MyPackage/resources/config')
    ->parameter('project_dir', $rootDir)
    ->parameter('some_other_variable', 123)
    ->beforeOptimization(new \Boot\Console\CompilerPass\CommandCompilerPass())
    ->build()
;

/** @var Symfony\Component\Console\Application $console */
$console = $app->get('console');
$console->run();
```


#### Configure service container
```
<!--
CONSOLE SERVICE
-->

<service id="console" class="Symfony\Component\Console\Application">
    <argument type="service" id="logger" />
    <call method="setName">
        <argument>%APP_NAME%</argument>
    </call>
    <call method="setVersion">
        <argument>%APP_VERSION%</argument>
    </call>
</service>

<!--
CONSOLE COMMANDS
-->

<service id="command.hello_world" class="HelloWorld\Command\HelloWorldCommand">
    <argument type="string">hello:world</argument>
    <argument type="service" id="logger" />
    <tag name="console_command" />
</service>
```


### Example 2: Boot Micro Service


#### Bootstrapping the application

```php
// Build application
$rootDir = realpath(__DIR__ . '/..');

$app = (new \Boot\Http\WebBuilder($rootDir))

    // Set application name
    ->appName('SimpleMicroService')
    
    // Optimize performance by caching compiled versions of componenents like the service container
    ->caching('cache', true)
    
    // Sets the environment
    ->environment(Boot::DEVELOPMENT)
    
    // Sets resources path(s) 
    ->path('resources/config')
    
    // Sets a parameter made available to the service container
    ->parameter('project_dir', $rootDir)
    
    // Sets default values for route parameters
    ->pathDefaults(['countryCode' => 'NL'])

    // Sets default constraints to route parameters
    ->defaultPathRequirements(['countryCode' => 'US|EN|FR|NL'])

    // Get employees
    ->get('employees/{countryCode}', EmployeeService::class, 'all', new RouteOptions(
        'all-employees'
    ))

    // Create employee
    ->post('employees/{countryCode}', EmployeeService::class, 'create', new RouteOptions(
        'create-employee'
    ))

    // Update employee
    ->put('employees/{countryCode}', EmployeeService::class, 'update', new RouteOptions(
        'update-employee'
    ))

    // Delete employee
    ->delete('employees/{countryCode}', EmployeeService::class, 'create', new RouteOptions(
        'delete-employee'
    ))

    ->build()
;

// Handle HTTP request
$app->get('http')->handle(Request::createFromGlobals());
```

#### Implementing the micro service

Services in boot are very similar to controllers. I chose to use a different terminology for Boot since controllerw are typical to MVC frameworks. 
The term 'controller' usually implies an architecture in which a controller is one amongst many.
For the scope of this framework I feel like it is more appropriate to talk about Services rather than Controllers.

```php
class EmployeeService extends AbstractService
{
    /** @var  object */
    protected $someDependency;

    /**
     * Create the services
     *
     * This demo demonstraties how to override the createService method 
     * to obtain the service container and do a dependency lookup on bootstrap. 
     *
     * @throws ServiceNotFoundException
     *
     * @param  ContainerInterface $serviceContainer
     *
     * @return ServiceInterface
     */
    public static function createService(ContainerInterface $serviceContainer)
    {
        /** @var self $service */
        $service = parent::createService($serviceContainer);
        $service->setSomeDependency($serviceContainer->get('some.dependency'));

        return $service;
    }

    /**
     * Returns all employees
     *
     * @param Request $request     A request object
     *
     * @return array               Arrays or instances of JsonSerializable are automatically encoded as json
     */
    public function all(Request $request)
    {
        // This service method returns a raw array
        return [
            ['id' => 1, 'firstName' => 'Jan', 'lastName' => 'Bakker', 'age' => 30],
            ['id' => 2, 'firstName' => 'Ben', 'lastName' => 'Gootmaker', 'age' => 32],
            ['id' => 3, 'firstName' => 'Nico', 'lastName' => 'Fransen', 'age' => 24],
            ['id' => 4, 'firstName' => 'Jacob', 'lastName' => 'Roos', 'age' => 27],
        ];
    }

    /**
     * Update an employee
     * 
     * @param Request $request     A request object
     *
     * @return string              A textual response is outputted as is
     */
    public function update(Request $request)
    {
        return __METHOD__;
    }

    /**
     * This method will delete an employee and send a 201 Accepted on success.
     *
     * @param Request $request    A request object
     * @return Response           A regular symfony response object
     */
    public function delete(Request $request)
    {
        return Response::create('ACCEPTED', 201);
    }

    /**
     * This method will add an employee and send a 201 Accepted on success.
     *
     * @param Request $request    A request object
     * @return Response           A regular symfony response object
     */
    public function create(Request $request)
    {
        return Response::create('ACCEPTED', 201);
    }

    /**
     * @return object
     */
    public function getSomeDependency()
    {
        return $this->someDependency;
    }

    /**
     * @param object $someDependency
     */
    public function setSomeDependency($someDependency)
    {
        $this->someDependency = $someDependency;
    }
}
```