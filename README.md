Installation
============

### Get the bundle using composer

Add GlavwebDataSchemaBundle by running this command from the terminal at the root of
your Symfony project:

```bash
php composer.phar require glavweb/data-schema-bundle
```

### Enable the bundle

To start using the bundle, register the bundle in your application's kernel class:

```php
// app/AppKernel.php
public function registerBundles()
{
    $bundles = array(
        // ...
        new Glavweb\DataSchemaBundle\GlavwebDataSchemaBundle(),
        // ...
    );
}
```

### Configure the bundle

This bundle was designed to just work out of the box. The only thing you have to configure in order to get this bundle up and running is a mapping.

```yaml
# app/config/config.yml

# Add hydrators to Doctrine
doctrine:
    orm:
        hydrators:
            DatagridHydrator: Glavweb\DataSchemaBundle\Hydrator\Doctrine\DatagridHydrator

glavweb_data_schema:
    default_hydrator_mode: DatagridHydrator
    data_schema:
        dir: "%kernel.root_dir%/config/data_schema"

    scope:
        dir: "%kernel.root_dir%/config/scopes"
            
```

Basic Usage
===========

Define data schema:

```
# app/config/data_schema/article.schema.yml

schema:
    class: AppBundle\Entity\Article
    properties:
        id:
        name:
        slug:
        body:
```

Define scope:

```
# app/config/scopes/article/short.yml

scope:
    name: 
```
