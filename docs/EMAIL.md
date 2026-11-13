# Emails

The system emails people when something happens to them:

| When | To |
| --- | --- |
| A class request is rejected (with the reason) or enrolled | The student |
| An excuse letter is accepted or not (with the teacher's note) | The student |
| A submitted photo is rejected (with the reason) | The student |
| A student goes over the absence limit in a class | The student and every guardian with an email |
| An account is made or a password is reset (temporary password) | That person |

A student's email is their sign-in email, or the email on their record when they have no account yet.
A guardian gets absence alerts only when an email is entered for them on the student's record.

If an email cannot be sent, the action still happens; the problem is written to `storage/logs/laravel.log`.

## Turn on real sending

On a development computer emails are only written to the log (`MAIL_MAILER=log`). On the school's
server, set an SMTP account in `.env`, for example a school Google Workspace account with an
**app password**:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=sies-portal@school.edu.ph
MAIL_PASSWORD="the app password"
MAIL_SCHEME=null
MAIL_FROM_ADDRESS=sies-portal@school.edu.ph
MAIL_FROM_NAME="SIES Campus Portal"
```

Then run `php artisan config:cache`. `php artisan sies:check` warns while emails only go to the log.
