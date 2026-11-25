# Links & URLs

**Components > User Reminder > Options > Links & URLs**

This tab decides where the links in the reminder emails point: the registration and password-reset pages, and the site address used to build every link.

Leave both fields empty to use Joomla's own pages. Change them only if another component handles registration or password resets.

| Option | Default | Used for |
|---|---|---|
| **Activation URL** | `index.php?option=com_users&task=registration.activate&token=` | The `{ACTIVATE_URL}` tag. The user's activation code is added at the end |
| **Password reset URL (path only)** | `index.php?option=com_users&view=reset` | The `{PASSWORD_RESET_URL}` tag |

Enter a path, not a full address. Paths starting with `index.php?` are turned into your site's friendly URLs; other paths are added to the site address as they are.

With Community Builder, see [Community Builder](../integrations/community-builder.md).

## Where the site address comes from

Every link starts with your site's address. When reminders are sent from a browser (the administrator, or Joomla's lazy scheduler), that is the address in the browser. When they are sent from a server cron job, there is no browser, so User Reminder uses, in this order:

1. **Site URL** (`$live_site`) in `configuration.php`, if set
2. the address of your site the last time someone opened a User Reminder screen in the administrator

So open the User Reminder dashboard once after installing or moving the site. If neither is known, the run sends nothing and writes an error to `administrator/logs/com_userreminder.php`.

A site that runs on `localhost` works, but its links only open on that computer. The run log notes it.
