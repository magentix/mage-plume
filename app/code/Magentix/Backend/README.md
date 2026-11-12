# Magentix_Backend

Removes log noise and fixes a login redirection issue.

## Features

### Direct redirection after login

After a successful admin login, Magento redirects to the requested URL, which may trigger a second
redirection and an "Invalid Form Key" message. The `Magento\Backend\App\Action\Plugin\Authentication`
preference redirects the user straight to the startup page (dashboard).

### Quiet admin menu

The admin menu builder writes a debug message for every menu item it adds, updates or removes.
`Magento\Backend\Model\Menu` receives a `Psr\Log\NullLogger` (`etc/adminhtml/di.xml`), and the
`Magento\Backend\Model\Menu\Director\Director` preference has no message pattern: the same menu is built
without writing these messages.

### No cache invalidation log

`Magento\Framework\Cache\InvalidateLogger` writes an entry each time a cache type is cleaned or
invalidated. The preference turns it into a no-op.

### Payment Services ACL resource

`etc/acl.xml` declares the `Magento_PaymentServicesDashboard::paymentservices` resource
(Sales > Operations), normally provided by the Payment Services modules. These modules are removed
from the project (`replace` section of `composer.json`), so the resource is declared here to keep
it available.

## License

BSD 2-Clause, see [LICENCE](LICENCE).
