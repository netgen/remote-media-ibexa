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

## Import database tables

This bundle stores used resources in a separate table in the database. Use the following command to update your database:

```
php bin/console doctrine:schema:update
```

**Note:** Use the command with `--dump-sql` first to check that it will do only modifications related to this bundle, and then use it with parameter `--force` to do the actual changes.
