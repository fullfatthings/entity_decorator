<?php

/**
 * @file
 *
 * Creates the EntityDecoratorFinder class which implements the EntityDecorator query interface.
 */

class EntityDecoratorFinder {
  protected $query;
  protected $entityType;
  protected $class;

  /**
   * Constructor to instantiate the query.
   */
  public function __construct($class, $bundle, $entityType) {
    $this->entityType = $entityType;
    $this->class = $class;
    $this->query = new EntityFieldQuery();
    $this->query->entityCondition('entity_type', $entityType);

    // User is a "special case" as it has no bundle to query against.
    // Setting a bundle triggers EFQ to add "WHERE 1=0" to the SQL.
    if ($entity_type != 'user') {
      $this->query->entityCondition('bundle', $bundle);
    }
  }


  /**
   * Find entities where the field_name or property has a matching value.
   * @param  string $field_name the field or property to match on
   * @param  array or scalar $value the value to match on
   * @return EntityDecoratorFinder instance
   */
  public function findBy($field_name, $value) {
    if (is_array($value)) {
      $operator = 'IN';
    }
    elseif (is_scalar($value)) {
      $operator = '=';
    }
    else {
      throw new EntityDecoratorUnsupportedArgument('EntityDecoratorFinders can only take scalars and arrays as arguments');
    }

    if (count($value)) {
      // Is the field a property?
      if ($this->isProperty($field_name)) {
        $this->query->propertyCondition($field_name, $value, $operator);
      }
      else {
        $this->query->fieldCondition($field_name, 'value', $value, $operator);
      }
    }

    return $this;
  }

  /**
   * Order results by a field_name or property.
   * @param  string $field_name the field or property to order by
   * @param  array or scalar $asc_or_desc
   * @return EntityDecoratorFinder instance
   */
  public function orderBy($field_name, $asc_or_desc = 'ASC') {

    // Is the field a property?
    if ($this->isProperty($field_name)) {
      $this->query->propertyOrderBy($field_name, $asc_or_desc);
    }
    else {
      $this->query->fieldOrderBy($field_name, 'value', $asc_or_desc);
    }

    return $this;
  }

  /**
   * Find the first entity where the field_name or property has a matching value.
   * @param  string $field_name the field or property to match on
   * @param  array or scalar $value the value to match on
   * @return EntityDecorator subclass instance
   */
  public function findFirstBy($field_name, $value) {
    return $this->findBy($field_name, $value)->executePrivate(TRUE);
  }

  /**
   * Is the field name a property?
   * @param  string  $field_name
   * @return boolean
   */
  protected function isProperty($field_name) {
    $property_info = entity_get_property_info($this->entityType);
    return in_array($field_name, array_keys($property_info['properties']));
  }

  /**
   * Private execute method. We don't expose this as it has different return types based on its parameters.
   * @param  boolean $first_result_only Are we only interested in the first results?
   * @return array of EntityDecorator subclass instances or a single instance
   */
  private function executePrivate($first_result_only = FALSE) {
    $class = $this->class;

    $result = $this->query->execute();

    if (isset($result[$this->entityType])) {

      if ($first_result_only) {
        $entity_id = current(array_keys($result[$this->entityType]));
        $entity = entity_load_single($this->entityType, $entity_id);
        return $class::buildFromEntity($entity);
      }

      $entities = entity_load($this->entityType, array_keys($result[$this->entityType]));

      return array_map(function($entity) use ($class) {
        return $class::buildFromEntity($entity);
      }, $entities);
    }

    // Fall through return empty array or NULL by default.
    if ($first_result_only) {
      return NULL;
    }
    else {
      return array();
    }
  }

  /**
   * Public execute method. Only needs to be called when finding multiple results.
   * @return array of EntityDecorator subclass instances
   */
  public function execute() {
    return $this->executePrivate(FALSE);
  }

  /**
   * Implements the metaprogramming finders.
   */
  public function __call($name, array $args) {
    if (drupal_substr($name, 0, 7) == 'find_by_') {
      return $this->findBy(drupal_substr($name, 8), $args[0]);
    }

    if (drupal_substr($name, 0, 14) == 'find_first_by_') {
      return $this->findFirstBy(drupal_substr($name, 14), $args[0]);
    }

    // Todo make custom exception.
    throw new Exception(get_called_class() . " has not no method called " . $name);
  }
}
