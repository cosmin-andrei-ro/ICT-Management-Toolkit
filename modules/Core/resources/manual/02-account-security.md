---
title: Your account security
audience: everyone
---

Click your name at the top right to open **Account security**.

## Two-factor authentication

With two-factor authentication, signing in takes your password **and** a 6-digit code from an app on your phone. Someone who steals or guesses your password still cannot sign in.

**It is required for IT staff**: everyone with a role other than *Requester* (agents, team leads, CAB members, administrators), in any organization, Active Directory accounts included. Until you set it up, the application only shows the page where you do. For everyone else it is optional and recommended.

### Setting it up

1. Install an authenticator app on your phone: any app that supports standard 6-digit codes.
2. On **Account security**, enter your current password and click **Set up two-factor authentication**. If you sign in through your company's single sign-on or directory, there is no password to enter, but you must have signed in within the last 15 minutes.
3. In the app, add an account and scan the QR code. If you cannot scan it, type the key shown under it.
4. Enter the 6-digit code the app shows and click **Turn on**.
5. **Save the ten recovery codes** shown next, for example in a password manager. **Download codes** saves them as a text file. They are shown only once.

From then on, after your password, the sign-in asks for the code from your app. Each code works once.

### If you lose your phone

On the code page, enter one of your **recovery codes** instead of the app code. Each recovery code works once. Then, on **Account security**, enter another recovery code and click **Turn off** (IT staff: **Move to a new phone**), and set it up again on your new phone. IT staff are taken straight to the setup.

Without your phone and without recovery codes, ask an administrator to **reset** your two-factor authentication. They check who is asking first. You set it up again at your next sign-in. If your account belongs to several organizations, the operator of the installation does this instead.

**Make new recovery codes** replaces all of them, for example when you have used several.

## Security keys and passkeys

Once the authenticator app is set up, you can add a **security key** (a YubiKey or similar, plugged in or held against the phone) or a **passkey** (Windows Hello, Touch ID, Face ID, or your phone). At sign-in, after your password, click **Use your security key or passkey** and touch the key or unlock your device: no code to type.

A key only works on this site: a fake page imitating it cannot use it, so keys also protect you from phishing. **Passkeys** let you sign in without your password: on the sign-in page, click **Sign in with a passkey** and unlock your device.

To add one, on **Account security** give it a name (for example *YubiKey on my keyring*), enter a code from your authenticator app and click **Add a key**, then follow your browser. Adding two keys - one you carry and one kept safely - means losing one is not a problem. **Remove** takes a key off your account: enter an app code first.

Your authenticator app and recovery codes stay as a fallback. Turning two-factor authentication off, or a reset by an administrator, removes your keys too.

## Password

Local accounts change their password here: enter the current one and the new one twice. Passwords have at least 15 characters, or 12 if your account uses two-factor authentication, and your organization may also ask for upper and lower case letters, a digit and a symbol; a sentence of several words is easy to remember and hard to guess. Passwords that appeared in known data breaches are refused, even if they look strong: attackers try those first. Changing it signs you out everywhere else, for example on a computer where you forgot to sign out.

Active Directory accounts change their password in Windows.

## Locked account

After 10 failed sign-ins in a row (wrong password or wrong code, including codes and current passwords typed on this page), your account is locked and you receive an e-mail. While it is locked, even the right password is refused.

- To unlock it yourself, use **Forgot your password?** on the sign-in page and choose a new password.
- Or ask an administrator to unlock it.

If you did not cause the failures yourself, someone may be trying to guess your password: choose a new one and tell the IT department. Active Directory accounts follow the lockout rules of Windows instead.

## Invitations from other organizations

If another organization using this toolkit invites you, you receive an email. Sign in and open **Invitations** (the link in the email, or at the bottom of this page while one is waiting): you see who invited you and with which roles. **Join** adds that organization to your account; **Decline** discards the invitation. Nothing changes until you accept, and an invitation expires after 14 days. Accept only invitations you expected.

## API tokens

If you work in IT, **API tokens** (link at the bottom of this page) lets you create a token for a script or another system that uses the toolkit's API as you:

1. Give it a name that says where it is used.
2. Choose only the abilities it needs, and how many days it stays valid.
3. Enter a code from your authenticator app.

Copy the token at once: it is shown only that time. Keep it in a password manager or in the secret store of the system that uses it. Revoke it as soon as it is no longer needed, or if it may have leaked. Resetting your password with **Forgot your password?** also revokes all your tokens.

