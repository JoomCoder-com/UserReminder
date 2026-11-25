# Community Builder

If people register through [Community Builder](https://www.joomlapolis.com/) (CB), their activation code is stored by CB, not by Joomla. User Reminder can put CB's activation link in the [activation reminder](../features/activation-reminders.md).

## Set it up

1. Go to **Components > User Reminder > Options > Reminders**
2. Under **Incomplete Registrations**, set **Use Community Builder activation** to **Yes**
3. On the **Links & URLs** tab, set **Activation URL** to CB's activation path, for example:

```text
index.php?option=com_comprofiler&view=confirm&confirmcode=
```

Check the exact path in a CB activation email from your site: everything before the code goes into the field.

The `{ACTIVATE_URL}` tag then becomes your site address, that path and the user's CB activation code.

## Check it

1. Register a test account through CB and don't activate it
2. Turn on **Send first reminder immediately** for the test, or wait the days you set
3. Click **Send Reminders** on **Registration Reminders**
4. Open the link in the email: the account should activate

## Without CB activation

If **Use Community Builder activation** is off, activation reminders use Joomla's own activation link, which does not work for accounts CB is waiting to confirm.
