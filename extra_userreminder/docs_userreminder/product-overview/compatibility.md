# Compatibility

## Requirements

| Requirement | Detail |
|---|---|
| **Joomla** | 4.4, 5.x or 6.x |
| **PHP** | 8.1 or higher |
| **Database** | MySQL 8 or MariaDB 10.4 or newer. PostgreSQL is not supported |
| **Mail** | A working Joomla mail setup (**System > Global Configuration > Server**) |
| **Scheduler** | Joomla Scheduled Tasks, with the lazy scheduler or a server cron job |
| **Site URL** | A public address. Links in the emails are built from it |

Every release is tested on Joomla 4.4, 5 and 6.2, each with MySQL and MariaDB: a new install, an update from 6.0 and an update from 5.2.3, with real emails checked in a mail catcher.

## Joomla 4 differences

On Joomla 4 the [email container](../configuration/email-container.md) (logo, header, footer) is not applied, because Joomla 4 has no way to pass it to mail templates. The template's HTML body is sent as it is. Everything else works the same.

## Other extensions

- **Community Builder:** activation reminders can carry the CB activation link. See [Community Builder](../integrations/community-builder.md).
- **Mail plugins:** User Reminder sends through Joomla's mailer, so SMTP and mail-transport plugins apply to it.
- **Other extensions can react to reminders:** plugins in the `userreminder` group receive `onUserReminderBeforeSend` (and can stop a send) and `onUserReminderAfterSend`.

## Updates

Once your download key is entered under **System > Update > Update Sites**, updates arrive under **System > Update > Extensions**. You can also install a newer package over the old one. See [Installation](../installation.md).

## Licence

GNU General Public License v2 or later.
