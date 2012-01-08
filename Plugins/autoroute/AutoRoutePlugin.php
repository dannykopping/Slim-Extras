<?php

    class AutoRoutePlugin extends Slim_Plugin_Base
    {
        private $classes;

        public function __construct(Slim $slimInstance, $args=null)
        {
            parent::__construct($slimInstance, $args);
            spl_autoload_register(array('AutoRoutePlugin', 'autoload'));

            $this->classes = $args;
            $this->analyzeClassesForAutoRoutes($this->classes);
        }

        public static function autoload($class)
        {
            // check same directory
            $file = realpath(dirname(__FILE__) . "/" . $class . ".php");

            $searchDirectories = array(dirname(__FILE__) . "/phpdbl/lib/");

            // if none found, check other directories
            if (!$file)
            {
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

            foreach ($classes as $class)
            {
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
            }

            $this->slimInstance->applyHook("slim.plugin.autoroute.ready", $routes);
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

            $route = new Route();
            $route->setUri($uri);
            $route->setMethods($httpMethods);
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

    }

?>