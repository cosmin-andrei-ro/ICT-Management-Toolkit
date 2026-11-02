---
title: Your account security
audience: everyone
---

Click your name at the top right to open **Account security**.

## Two-factor authentication

With two-factor authentication, signing in takes your password **and** a 6-digit code from an app on your phone. Someone who steals or guesses your password still cannot sign in.

**It is required for IT staff**: everyone with a role other than *Requester* (agents, team leads, CAB members, administrators), in any organization, Active Directory accounts included. Until you set it up, the application only shows the page where you do. For everyone else it is optional and recommended.

### Setting it up

1. Install an authenticator app on your phone: Microsoft Authenticator, Google Authenticator or any app that supports standard 6-digit codes.
2. On **Account security**, click **Set up two-factor authentication**.
3. In the app, add an account and scan the QR code. If you cannot scan it, type the key shown under it.
4. Enter the 6-digit code the app shows and click **Turn on**.
5. **Save the ten recovery codes** shown next, for example in a password manager. **Download codes** saves them as a text file. They are shown only once.

From then on, after your password, the sign-in asks for the code from your app. Each code works once.

### If you lose your phone

On the code page, enter one of your **recovery codes** instead of the app code. Each recovery code works once. Then, on **Account security**, enter another recovery code and click **Turn off** (IT staff: **Move to a new phone**), and set it up again on your new phone. IT staff are taken straight to the setup.

Without your phone and without recovery codes, ask an administrator to **reset** your two-factor authentication. They check who is asking first. You set it up again at your next sign-in.

**Make new recovery codes** replaces all of them, for example when you have used several.

## Security keys and passkeys

Once the authenticator app is set up, you can add a **security key** (a YubiKey or similar, plugged in or held against the phone) or a **passkey** (Windows Hello, Touch ID, Face ID, or your phone). At sign-in, after your password, click **Use your security key or passkey** and touch the key or unlock your device: no code to type.

A key only works on this site: a fake page imitating it cannot use it, so keys also protect you from phishing. **Passkeys** let you sign in without your password: on the sign-in page, click **Sign in with a passkey** and unlock your device.

To add one, on **Account security** give it a name (for example *YubiKey on my keyring*), enter a code from your authenticator app and click **Add a key**, then follow your browser. Adding two keys - one you carry and one kept safely - means losing one is not a problem. **Remove** takes a key off your account: enter an app code first.

Your authenticator app and recovery codes stay as a fallback. Turning two-factor authentication off, or a reset by an administrator, removes your keys too.

## Password

Local accounts change their password here: enter the current one and the new one twice. Passwords have at least 12 characters; a sentence of several words is easy to remember and hard to guess. Changing it signs you out everywhere else, for example on a computer where you forgot to sign out.

Active Directory accounts change their password in Windows.
