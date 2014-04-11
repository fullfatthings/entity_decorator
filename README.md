entity decorator
================

A Drupal 7 Module to beautifully decorate your entities.

Preamble
--------

Tired of typing code with deep nested arrays like this to work with your fields?

```
 $item_id = $node->{$this->field_name}[LANGUAGE_NONE][0]['value'];
```

Of course you are, so you use the [Entity API](https://drupal.org/project/entity).

Which is an improvment but you may find yourself typing ```entity_metadata_wrapper``` a lot!

```
$wrapper = entity_metadata_wrapper('node', $node);
```

You may get the urge to want to treat your entities as objects and extend them. 

Entity decorator is designed to allow you to create decorator objects for any entity or node type, and presents them with a beautiful, concise interface.

It also creates a new interface to querying for entities and node types which, hopefully, is more concise and intuitive.

A common use case may be you want to create custom entity types with [ECK](https://drupal.org/project/eck) but then create custom methods for those types based on the fields and properties you have created.

Entity decorator should work equally well with custom entities and node types, whichever you prefer to decorate.

Usage
-----

Creating the decorator class is simple. Create a class like this.

```
class MyNodeTypeDecorator extends EntityDecorator {
  static public $entity_type = 'node';
  static public $bundle      = 'my_node_type';
}
```

And that's it. All the entity decorator goodness is immediately available provided by your new class!

Plus, you can add any custom methods to your class that you like.

Now you can write code like this:

```
$node = new MyNodeTypeDecorator();
$node->set_title('The title');
$node->set_field_my_custom_field('My value');
$node->callACustomMethod();
$node->save();
```

Wasn't that concise? Not a deep nested array or entity_metadata_wrapper invocation in site.

**Note**: a bit of metaprogramming magic creates some of those methods. Calling ```set_*``` or ```get_*``` will act as a get or setter to any property OR field. Entity decorator abstracts the distinction between properties and fields away for most purposes.

As this implements the [decorator pattern](http://en.wikipedia.org/wiki/Decorator_pattern) you still have access to all your entity's properties and methods as if your decorator class was the instance it wraps.

Querying
--------

``EntityFieldQuery`` has a somewhat verbose interface, and tricky to remember syntax. While entity decorator's finders don't yet implement the complete functionality of EntityFieldQuery, they should be simpler and easier to use.

Unlike EntityFieldQuery, these finders return EntityDecorators with instantiated entities. We think that's nice.

**Examples**

```
MyNodeTypeDecorator::find(12345); 
```
Returns the node with nid 12345 or null if no such node exists.

```
MyNodeTypeDecorator::find_by_field_my_custom_field('Some value')->execute(); 
```
Returns an array of nodes where the value of 'field_my_custom_field' has the value 'Some value'.

```
MyNodeTypeDecorator::find_by_field_my_custom_field(array('Some value', 'another value'))->execute(); 
```
Returns an array of nodes where the value of 'field_my_custom_field' has the value 'Some value' or 'another value'.


And you can chain them as well...

```
MyNodeTypeDecorator::find_by_field_my_custom_field('Some value')->find_by_title('other value')->execute(); 
```

Returns an array of nodes where 'field_my_custom_field' has the value 'Some value' and the 'title' has the value 'other value'. (Yes the finders also abstract away the property / field distinction).

And sorting...

```
MyNodeTypeDecorator::find_by_field_my_custom_field('Some value')->orderBy(field_another_field, 'ASC'); 
```

Returns an array of nodes where 'field_my_custom_field' has the value 'Some value' and is sorted by the value of field_another_field.

You may also only want one record

```
MyNodeTypeDecorator::find_first_by_field_my_custom_field('Some value');
```

Which will return the first record where 'field_my_custom_field' has the value 'Some value'. **Note** you don't need to call the execute method with find_first_by_* as by definition the query can be executed immediately.












