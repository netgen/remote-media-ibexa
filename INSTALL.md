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

This bundle ships no migrations. Its table `ngrm_remote_media_content_link` is created by the Doctrine migrations of `netgen/remote-media-bundle` (3.0.2 or newer), which also create the tables of Netgen Remote Media itself. It requires MySQL or MariaDB.

Run the migrations of `netgen/remote-media-bundle`:

```
php bin/console doctrine:migrations:migrate --configuration=vendor/netgen/remote-media-bundle/migrations/doctrine.yaml
```

Run this command after every update of the bundles, for example as part of your deployment, so new migrations are applied. See the [Netgen Remote Media install instructions](https://github.com/netgen/NetgenRemoteMediaBundle/blob/3.0/docs/INSTALL.md#database) for running it automatically from the Composer scripts.
