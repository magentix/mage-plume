# Mage Plume

Lightweight Magento Open Source setup, for small servers and shared hosting.

- Unused modules removed (`replace` section of `composer.json`): smaller install, less memory, faster builds.
- No Elasticsearch or OpenSearch required: catalog search runs on MySQL, with typo tolerance.
- Security patches and hardening included.

## Installation

### 1. Adobe access keys

Magento packages are downloaded from `repo.magento.com`, which requires free access keys:

1. Create an account on [commercemarketplace.adobe.com](https://commercemarketplace.adobe.com/).
2. Go to *My Profile > Access Keys* and create a key pair.

When Composer asks for credentials, the username is the **public key** and the password is the **private key**.

### 2. Install

Copy the Mage Plume files into an empty directory, then:

```bash
composer install
bin/magento setup:install --base-url=http://mageplume.local/ --db-host=localhost --db-name=magento --db-user=magento --db-password=secret --use-rewrites=1
bin/magento config:set --lock-config catalog/search/engine lmysql
bin/magento admin:user:create
bin/magento indexer:reindex
bin/magento cache:flush
```

## License

See [NOTICE](NOTICE): each component keeps its own license.
