<?php
    class RouteDescriptor
    {
        private $_annotations;
        private $_reflectionMethod;

        public function __construct(array $annotations, $reflectionMethod)
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