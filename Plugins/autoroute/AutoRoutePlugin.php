<?php

    require_once dirname(__FILE__)."/Route.php";

    class AutoRoutePlugin extends Slim_Plugin_Base
    {
        private $classes;

        private $routes;

        protected static $authorizationCallback;

        public function __construct(Slim $slimInstance, $args=null)
        {
            parent::__construct($slimInstance, $args);
            spl_autoload_register(array('AutoRoutePlugin', 'autoload'));

            $this->classes = $args;
            $this->analyzeClassesForAutoRoutes($this->classes);

            $this->slimInstance->hook("slim.before.dispatch", array($this, "checkAuthorizationForRoute"));
        }

        public static function autoload($class)
        {
            // check same directory
            $file = realpath(dirname(__FILE__) . "/" . $class . ".php");

            // if none found, check other directories
            if (!$file)
            {
                $searchDirectories = array(dirname(__FILE__) . "/phpdbl/lib/");

                foreach ($searchDirectories as $dir)
                    $file = realpath($dir . "/" . $class . ".php");
            }

            // if found, require_once the sucker!
            if ($file)
                require_once $file;
        }

        private function analyzeClassesForAutoRoutes($classes)
        {
            if (empty($classes))
                return;

            if (!is_array($classes))
                $classes = array($classes);

            $classRoutes = array();

            foreach ($classes as $class)
            {
                $className = is_object($class) ? get_class($class) : $class;
                $classRoutes[$className] = array();

                $dbp = new DocBlockParser();
                $dbp->setAllowInherited(false);
                $dbp->setMethodFilter(ReflectionMethod::IS_PUBLIC);
                $dbp->analyze($class);

                $methods = $dbp->getMethods();

                if (empty($methods))
                    continue;

                $routes = array();

                foreach ($methods as $method)
                {
                    // @ignore annotations force the AutoRouter to ignore that method
                    if ($method->hasAnnotation("ignore"))
                        continue;

                    if (!is_object($class) && !$method->getReflectionObject()->isStatic())
                    {
                        die($method->name . " is not statically accessible. Try passing " .
                            "a class instance of " . $class . " to the AutoRoute plugin " .
                            "instead of the class name.");
                    }

                    $routes[] = $this->createRoute($method, $class);
                }

                foreach ($routes as $route)
                {
                    if(empty($route))
                        continue;

                    $slimRoute = $this->getSlimInstance()->map($route->getUri(), $route->getCallback());
                    foreach ($route->getMethods() as $method)
                        $slimRoute->via($method);
                }

                $classRoutes[$className][] = $routes;
            }

            $this->routes = $classRoutes;
            $this->slimInstance->applyHook("slim.plugin.autoroute.ready", $classRoutes);
        }

        /**
         * @param MethodElement $method
         * @param string|object $class
         *
         * @return Route
         */
        private function createRoute(MethodElement $method, $class)
        {
            $uri = $this->getRouteAnnotation($method);
            if(!$uri)
                return null;

            $httpMethods = $this->getRouteMethods($method);
            $authorizedUsers = $this->getAuthorizedUsers($method);

            $route = new Route();
            $route->setUri($uri);
            $route->setMethods($httpMethods);
            $route->setAuthorizedUsers($authorizedUsers);
            $route->setCallback(array($class, $method->name));

            return $route;
        }

        private function getRouteAnnotation(MethodElement $method)
        {
            $routeAnnotation = $method->getAnnotation("route");

            if (!empty($routeAnnotation) && (empty($routeAnnotation->values) || empty($routeAnnotation->values[0])))
            {
                throw new Exception("The method [" . $method->getClass()->name . "::" . $method->name . "] requires " .
                    "a value for the @route annotation. Example:\n" .
                    "/**\n" .
                    "* @route	/users/get\n" .
                    "*/");
            }

            if(!empty($routeAnnotation))
                return $routeAnnotation->values[0];

            return null;
        }

        private function getRouteMethods($method)
        {
            $routeMethodsAnnotation = $method->getAnnotation("routeMethods");

            if (!$routeMethodsAnnotation)
            {
                throw new Exception("No @routeMethods annotation could be found in [" . $method->getClass()->name . "::" . $method->name . "]. " .
                    "This annotation is required for routing. " .
                    "Add a @ignore annotation to exclude this method from auto-routing");
            }

            if (empty($routeMethodsAnnotation->values) || empty($routeMethodsAnnotation->values[0]))
            {
                throw new Exception("The method [" . $method->getClass()->name . "::" . $method->name . "] requires " .
                    "a value for the @routeMethods annotation. Example:\n" .
                    "/**\n" .
                    "* @routeMethods	GET,POST\n" .
                    "*/");
            }

            return explode(",", $routeMethodsAnnotation->values[0]);
        }

        private function getAuthorizedUsers($method)
        {
            $authorizeAnnotation = $method->getAnnotation("authorize");

            // check for spelling errors
            if(empty($authorizeAnnotation))
                $authorizeAnnotation = $method->getAnnotation("authorise");

            if(empty($authorizeAnnotation))
                return null;

            if (empty($authorizeAnnotation->values) || empty($authorizeAnnotation->values[0]))
            {
                throw new Exception("The method [" . $method->getClass()->name . "::" . $method->name . "] requires " .
                    "a value for the @authorize annotation. Example:\n" .
                    "/**\n" .
                    "* @authorize	user,admin\n" .
                    "*/");
            }

            return explode(",", $authorizeAnnotation->values[0]);
        }

        public function checkAuthorizationForRoute($route)
        {
            $callable = $route->getCallable();
            $authCallback = self::getAuthorizationCallback();

            if(empty($authCallback))
                return;

            foreach($this->routes as $classes)
            {
                foreach($classes as $classMethodRoutes)
                {
                    foreach($classMethodRoutes as $route)
                    {
                        // check that the auto-route's callable is the same as the pending route's callable
                        if($route->getCallback() === $callable)
                        {
                            $authorizedUsers = $route->getAuthorizedUsers();
                            $authorized = call_user_func_array(self::getAuthorizationCallback(), array($authorizedUsers));

                            if(!$authorized)
                            {
                                $this->slimInstance->halt(401, "You are not authorized to execute this function");
                            }
                        }
                    }
                }
            }
        }

        public static function authorizationCallback($callable)
        {
            self::$authorizationCallback = $callable;
        }

        public static function getAuthorizationCallback()
        {
            return self::$authorizationCallback;
        }
    }

?>