# Netgen Remote Media & Ibexa CMS integration installation instructions

## Use Composer to install the integration

Run the following command to install Netgen Remote Media & Ibexa CMS integration:

```
composer require netgen/remote-media-ibexa
```

## Activate the bundle

Activate the bundle in your kernel class. Make sure it is activated after Netgen Remote Media bundle:

```
...
...

$bundles[] = new Netgen\Bundle\RemoteMediaIbexaBundle\NetgenRemoteMediaIbexaBundle();

return $bundles;
```

## Configure Netgen Remote Media

This bundle will automatically install [Netgen Remote Media bundle](https://github.com/netgen/NetgenRemoteMediaBundle/tree/3.0) so you have to follow it's [install instructions](https://github.com/netgen/NetgenRemoteMediaBundle/blob/3.0/docs/INSTALL.md) in order to properly configure it, add required routing etc.

## Database

The bundle ships a Doctrine migration which creates its database tables. It requires MySQL or MariaDB and `doctrine/doctrine-migrations-bundle`:

```
composer require doctrine/doctrine-migrations-bundle
php bin/console doctrine:migrations:migrate --configuration=vendor/netgen/remote-media-ibexa/migrations/doctrine.yaml
```

Run the migrations of `netgen/remote-media-bundle` first: the table of this bundle references `ngrm_remote_resource_location`.
