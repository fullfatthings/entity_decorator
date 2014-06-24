<?php

namespace EntityDecorator;


abstract class EntityDecorator {
    static $entityType;
    static $bundle;

    public $entity;

    /**
     * Constructor that wraps the entity.
     */
    public function __construct($entity = NULL) {
        if ($entity) {
            $this->entity = $entity;
        }
        else {
            if ($this->getEntityType() == 'node') {
                // Node specific preparation.
                $this->entity = new stdClass;
                $this->entity->type = $this->getBundle();
                node_object_prepare($this->entity);
            }
            else {
                $this->entity = entity_create($this->getEntityType(), array('type' => $this->getBundle()));
            }
        }
    }

    /**
     * Create an instance of the class from a raw entity object.
     * @param  $entity
     * @return Instance of subclass of EntityDecorator
     */
    static public function buildFromEntity($entity) {
        $class = get_called_class();
        $object = new $class($entity);
        return $object;
    }

    /**
     * Find an instance by ID.
     * @param  int $id
     * @return Instance of subclass of EntityDecorator
     */
    static public function find($id) {
        $class = get_called_class();

        if ($class::$entityType == 'node') {
            $entity = $class::findBy('nid', array($id))->execute();
        }
        else {
            $entity = $class::findBy('id', array($id))->execute();
        }

        if (count($entity)) {
            return current($entity);
        }
    }

    static protected function getFinder() {
        $class = get_called_class();
        return new EntityDecoratorFinder($class, $class::$bundle, $class::$entityType);
    }

    /**
     * Find entities where the field_name or property has a matching value.
     * @param  string $field_name the field or property to match on
     * @param  array or scalar $value the value to match on
     * @return EntityDecoratorFinder instance
     */
    static public function findBy($field_name, $value) {
        return self::getFinder()->findBy($field_name, $value);
    }

    /**
     * Find the first entity where the field_name or property has a matching value.
     * @param  string $field_name the field or property to match on
     * @param  array or scalar $value the value to match on
     * @return EntityDecorator subclass instance
     */
    static public function findFirstBy($field_name, $value) {
        return self::getFinder()->findFirstBy($field_name, $value);
    }

    private function getEntityType() {
        $class = get_called_class();
        return $class::$entityType;
    }

    private function getBundle() {
        $class = get_called_class();
        return $class::$bundle;
    }

    public function getWrappedEntity() {
        return entity_metadata_wrapper($this->getEntityType(), $this->entity);
    }

    /**
     * Set a field or property of the extended entity.
     *
     * @param string $field The name of the field or property to set
     * @param $value The value the field or property should have
     */
    public function set($attr_name, $value) {
        $this->getWrappedEntity()->$attr_name->set($value);
    }

    /**
     * Get a field or property of the extended entity.
     *
     * @param string $field The name of the field or property to get
     */
    public function get($attr_name) {
        return $this->getWrappedEntity()->$attr_name->value();
    }

    /**
     * Get a field or property of the extended entity that is an object and return a decorated instance.
     * Works with arrays of objects too (e.g like a field collection).
     * If the field is not an object or an array of objects, it returns the raw value.
     *
     * @param string $field  The name of the field or property to get
     * @param string $class_name The name of the class to decorate it with
     */
    public function getDecorated($attr_name, $class_name) {
        $field = $this->get($attr_name);

        if (is_object($field)) {
            return $class_name::buildFromEntity($field);
        }
        elseif (is_array($field)) {
            return array_map(function($item) use ($class_name) {
                if (is_object($item)) {
                    return $class_name::buildFromEntity($item);
                }
                else {
                    return $item;
                }
            }, $field);
        }
        else {
            return $field;
        }
    }

    /**
     * Persist current state of the entity to the database.
     */
    public function save() {
        return $this->getWrappedEntity()->save();
    }

    /**
     * Delete the current entity from the database.
     */
    public function delete() {
        if ($this->getEntityType() == 'node') {
            node_delete($this->get('nid'));
        }
        else {
            entity_delete($this->getEntityType(), $this->get('id'));
        }
    }

    // Magic methods to act as a decorator (have the same methods and properties as the object we are wrapping).
    // Needs to be a reference so we can set nested properties and array values.
    public function &__get($name) {
        if (isset($this->entity->$name)) {
            return $this->entity->$name;
        }
    }

    public function __set($name, $value) {
        return $this->entity->$name = $value;
    }

    public function __isset($name) {
        return property_exists($this->entity, $name) && !empty($this->entity->$name);
    }

    public function __unset($name) {
        unset($this->entity->$name);
    }

    public function __call($name, array $args) {
        // Implement our default get and set. These should be overridden for special cases.
        if (drupal_substr($name, 0, 4) == 'get_') {
            return $this->get(drupal_substr($name, 4));
        }
        elseif (drupal_substr($name, 0, 4) == 'set_') {
            return $this->set(drupal_substr($name, 4), $args[0]);
        }
        elseif (method_exists($this->entity, $name)) {
            // Implement decorator pattern by proxying methods to the wrapped entity.
            return call_user_func_array(array($this->entity, $name), $args);
        }
        else {
            throw new EntityDecoratorMethodNotFound(get_called_class() . " has no instance method called " . $name);
        }
    }

    // Magic methods to implement our default finders. These should be overridden for special cases.
    public static function __callStatic($name, array $args) {
        if (drupal_substr($name, 0, 8) == 'find_by_') {
            $class = get_called_class();
            return $class::findBy(drupal_substr($name, 8), $args[0]);
        }

        if (drupal_substr($name, 0, 14) == 'find_first_by_') {
            $class = get_called_class();
            return $class::findFirstBy(drupal_substr($name, 14), $args[0]);
        }

        throw new EntityDecoratorMethodNotFound(get_called_class() . " has no static method called " . $name);
    }
}
