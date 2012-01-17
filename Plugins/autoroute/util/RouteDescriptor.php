<?php
    class RouteDescriptor
    {
        private $_annotations;
        private $_reflectionMethod;

        /**
         * @param array $annotations
         * @param ReflectionMethod $reflectionMethod
         */
        public function __construct($annotations, $reflectionMethod)
        {
            $this->_annotations = $annotations;
            $this->_reflectionMethod = $reflectionMethod;
        }

        public function setAnnotations($annotations)
        {
            $this->_annotations = $annotations;
        }

        public function getAnnotations()
        {
            return $this->_annotations;
        }

        public function setReflectionMethod($reflectionMethod)
        {
            $this->_reflectionMethod = $reflectionMethod;
        }

        public function getReflectionMethod()
        {
            return $this->_reflectionMethod;
        }
    }
?>