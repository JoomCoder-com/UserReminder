# Troubleshooting

Two logs help with most problems:

- the task log in **System > Scheduled Tasks** (one line per run: processed, sent, deleted, skipped, mail errors)
- `administrator/logs/com_userreminder.php`: why an email failed, or why a run sent nothing

## Reminders are not sent automatically

1. **Task missing or disabled**: the dashboard shows **Automatic reminders are not scheduled**. In **System > Scheduled Tasks**, enable the **User Reminder emails** task or create one of type **Send User Reminder emails**
2. **Plugin disabled**: enable **Task - UserReminder** in **System > Manage > Plugins**
3. **No visitors**: with Joomla's lazy scheduler, tasks only start when someone visits the site. Use a server cron job. See [Scheduling](features/scheduling.md)
4. **Reminder types off**: switch them on in **Options > Reminders**, and check the task's **Run registration reminders** and **Run inactivity reminders**
5. **Debug mode**: with **Developer debug mode** on, runs are simulated. See [Testing](features/testing.md)

## A cron run sends nothing, and the log says "no usable site URL"

The run came from the command line and User Reminder could not tell your site's address. Open the User Reminder dashboard in the administrator once (it remembers the address), or set `$live_site` in `configuration.php`. See [Where the site address comes from](configuration/links.md#where-the-site-address-comes-from).

## No emails arrive

1. **Joomla mail**: test it with **System > Global Configuration > Server > Send Test Mail**
2. **Site mail address**: the dashboard warns if the From address is missing or invalid
3. **Errors**: the message after **Send Reminders** and the task log count failed emails; the reason is in `administrator/logs/com_userreminder.php`
4. **Spam folder**: check it, and set up SPF and DKIM for your domain
5. **See what was sent**: turn on the [admin copy (BCC)](configuration/email-delivery.md#admin-copy-bcc)

## A user is not emailed

Check the **Action to Perform** column on **Registration Reminders** or **Active User Reminders** and hover over it for the reason.

1. **Waiting**: **Days before next reminder** have not passed since registration or the last reminder
2. **All reminders sent**: the user had the **Number of reminders**
3. **Not in the list at all**: the user opted out, is in an [opt-out group](features/opt-out.md#opt-out-groups), was blocked by an administrator, or does not match the reminder type
4. **Run cap**: **Maximum emails per run** was reached; the rest go out next run

## Users were deleted

**Delete users** is on for that reminder type; the dashboard shows an alert while it is on. Deletion only happens after the last reminder. Turn it off, or for inactive users use **Remove from user groups** instead. Deleted accounts can only come back from a backup.

## Links in the emails are wrong

1. **Wrong address**: see [Where the site address comes from](configuration/links.md#where-the-site-address-comes-from). After moving a site, open the dashboard once so it remembers the new address
2. **localhost links**: the site was set up on a local address. Set `$live_site` to the public address
3. **Custom paths**: clear the fields on **Options > Links & URLs** to use Joomla's own pages
4. **Community Builder**: see [Community Builder](integrations/community-builder.md)

## The email looks plain

1. Turn on **Custom HTML container** in **Options > Email Container (HTML)**
2. In **System > Mail Templates > Options**, set **Mail format** to **HTML and plain text** and **HTML layout** to **userreminder**
3. Fill in the HTML body of each template, not only the plain text
4. On Joomla 4 the container is not available. See [Compatibility](product-overview/compatibility.md#joomla-4-differences)

## Updates are not found

1. **Download key**: paste it in **System > Update > Update Sites > UserReminder Update**
2. **Subscription**: updates need an active subscription at joomcoder.com
3. **Cache**: in **System > Update > Extensions** click **Clear Cache**, then **Check for Updates**

## More help

For questions about how User Reminder works, see the [FAQ](faq.md). Still stuck? Email support@joomcoder.com with the error from the log.
