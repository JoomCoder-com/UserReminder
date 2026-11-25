# Use Cases

These are the situations we had in mind when building User Reminder. They are suggestions, not limits. Adapt them to your site.

## Sign-ups that never activated

- **Situation:** people register, the activation email lands in spam or gets forgotten, and the account sits blocked forever.
- **How it can help:**
  - [Activation reminders](../features/activation-reminders.md) send the activation link again, a few days apart
  - After the last reminder, [delete](../features/account-cleanup.md) the account so the username and email are free again

## Members who activated but never came in

- **Situation:** the account is active, but the person never logged in, so they never saw what your site offers.
- **How it can help:**
  - [Never-logged-in reminders](../features/login-reminders.md) send a link to the site, their username and a password-reset link
  - The [Registration Reminders list](../features/activation-reminders.md#the-list) shows who is waiting and what the next run will do

## A community that went quiet

- **Situation:** members who used to visit have not been back for months.
- **How it can help:**
  - [Inactive-user reminders](../features/inactive-users.md) go out after the number of days you choose, for example 90
  - When the reminders get no reaction, [remove them from a group](../features/account-cleanup.md#remove-inactive-users-from-groups), for example a "Members" group with access to paid content

## Keeping the user table clean

- **Situation:** thousands of accounts that were never used make reports, newsletters and exports misleading.
- **How it can help:**
  - Give unused accounts a few chances, then let User Reminder [delete them](../features/account-cleanup.md)
  - Keep staff and partners out of it with [Opt-out Groups](../features/opt-out.md#opt-out-groups)

## Sites with sending limits

- **Situation:** a shared host allows only a few hundred emails per hour.
- **How it can help:**
  - [Maximum emails per run](../configuration/email-delivery.md) caps each run; the rest go out in the next runs
  - The [dashboard](../features/dashboard.md) shows how many users are due and warns when the backlog grows

## Next

See the [Benefits](benefits.md) for what these situations gain, or [Why Choose It](why-choose-it.md) to check the fit for your site.
