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
# app/config/packages/glavweb_data_schema.yaml

# Add hydrators to Doctrine
doctrine:
    orm:
        hydrators:
            DatagridHydrator: Glavweb\DataSchemaBundle\Hydrator\Doctrine\DatagridHydrator

glavweb_data_schema:
    default_hydrator_mode: DatagridHydrator
    data_schema:
        dir: "%kernel.project_dir%/data_schemas"

    scope:
        dir: "%kernel.project_dir%/scopes"
            
```

Basic Usage
===========

Define data schema:

```
# app/data_schemas/article.schema.yaml

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
# app/scopes/article/short.yaml

scope:
    name: 
```
