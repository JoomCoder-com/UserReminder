# Troubleshooting

## Reminders are not sent automatically

1. **Task missing or disabled**: the dashboard shows **Automatic reminders are not scheduled**. Open **System > Scheduled Tasks** and enable or create a **Send User Reminder emails** task
2. **Plugin disabled**: enable **Task - UserReminder** in **System > Manage > Plugins**
3. **No traffic**: with the lazy scheduler, tasks only run when someone visits the site. Use a server cron job. See [Scheduling](features/scheduling.md)
4. **Nothing enabled**: turn on at least one reminder type on the **Reminders** tab, and check the task's **Run registration reminders** / **Run inactivity reminders** switches
5. **Debug mode**: with **Developer debug mode** on, runs are simulated and nothing is sent

## No emails arrive

1. **Joomla mail**: test it in **System > Global Configuration > Server > Send Test Mail**
2. **Site mail address**: the dashboard warns if the site's From address is missing or invalid
3. **Spam folder**: check it, and set up SPF and DKIM for your domain
4. **Errors**: a run that fails to send reports how many emails failed; details are in the Joomla error log

## Some users are skipped

1. **Not due yet**: the user must wait **Days before next reminder** since registration or the last reminder (unless **Send first reminder immediately** is on)
2. **Cycle finished**: the user already got the **Number of reminders** you set
3. **Opted out**: check **Opt-out Users** and **Opt-out Groups**
4. **Run cap**: **Maximum emails per run** was reached; the rest go out next run

## Users were deleted

1. **Delete users** is on for that reminder type; the dashboard shows an alert while it is on
2. Deletion only happens after the last reminder. Turn it off to keep accounts, or use **Remove from user groups** for inactive users

## Links in the emails are wrong

1. **Site URL**: links use your site's address. If the dashboard warns about a loopback URL, set `$live_site` in `configuration.php` to your real address, for example `https://www.example.com`
2. **Custom paths**: clear the paths on the **Links & URLs** tab to use Joomla's defaults
3. **Community Builder**: turn on **Use Community Builder activation**

## The email looks plain

1. Turn on **Custom HTML container** on the **Email Container (HTML)** tab
2. In **System > Mail Templates > Options**, set **Mail format** to **HTML and plain text**
3. Fill in the HTML body of each template, not only the plain text

## Updates are not found

1. **Download key**: paste it in **System > Update > Update Sites > UserReminder Update**
2. **Subscription**: updates need an active subscription at joomcoder.com
3. **Cache**: in **System > Update > Extensions** click **Clear Cache**, then **Check for Updates**
