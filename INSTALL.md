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
