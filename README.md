# SMSTunnel Texts for Flarum

Text alerts for your forum's staff, and opt-in reply and mention texts for members. Texts go out as ordinary SMS from
your own number, through your Android phone and the [SMSTunnel](https://smstunnel.io) app
([on Google Play](https://play.google.com/store/apps/details?id=io.smstunnel.app)), under your mobile plan.

For Flarum 2.x (tested on 2.0.2) and PHP 8.3 or newer.

> Written with AI assistance (Claude). Tested on a real Flarum 2.0.2 forum against a local copy of the SMSTunnel API, and
> its sending code delivered a real text to a real phone through the live API. Please report anything that misbehaves.

## What it does

**Staff alerts.** A text to the numbers you saved when a post is flagged or waits for approval. At most one text per
type every 10 minutes, with a count and a link to the review page. It never contains words from a post.

**Member texts.** A **Text** column in each member's notification settings, for two events:

- someone replies to one of my posts
- someone mentions me

Every member starts off. A member adds their own number, confirms it with a code and ticks the boxes they want.
Unticking a box or removing the number stops the texts.

Group mentions and new posts in followed discussions have no box, because one post could then text many people. The
extension has no way to text all members.

## Install

```sh
composer require smstunnel/flarum-sms
php flarum cache:clear
```

Enable **SMSTunnel Texts** in the admin panel. Until you turn something on, the forum behaves as before.

## Set up

1. Install the SMSTunnel app on an Android phone and create an API key in your
   [dashboard](https://smstunnel.io), under Apps.
2. Paste the key in the extension's settings. It is kept in its own table on your server and is never sent back to a
   browser.
3. Send yourself a test text.
4. Turn on what you want:
   - for staff alerts, add each staff number and type back the code it receives;
   - for member texts, turn on the switch. Members then see the Text column in their settings.

Staff alerts held back by the 10-minute limit go out with the next alert, or sooner if
[Flarum's scheduler](https://docs.flarum.org/2.x/scheduler/) runs.

## Limits

| What | Limit |
| --- | --- |
| People one post can text | 3 |
| Texts to one member | 1 per discussion and 3 per hour; 5 per day (you can set 1 to 20) |
| Texts caused by one author | 10 per hour |
| Member texts for the whole forum | 100 per day (you can set 1 to 1000) |
| Alerts to one staff number | 1 per type every 10 minutes, 40 per day |
| Staff numbers | 10 |
| Member confirmation codes | 3 per member and 3 per number a day, 3 per IP an hour, 10 per IP a day, 20 per forum a day |
| Staff confirmation codes | 3 per number a day, 15 per IP an hour, 30 per forum a day |
| Test texts | 5 per hour |

One phone sends about 28 SMS parts every 65 seconds (Android's limit).

## Privacy and safety

- A member's text is only sent if they can still read the post, checked when it is queued and again just before it
  goes out. If that can't be established, nothing is sent.
- The only words a member can put in a text are a display name, stripped of links, domains, digits, and invisible or
  look-alike characters. If nothing safe is left, the text says "A member".
- Numbers must be in international format (`+` and country code). Anything else is refused before any request.
- Numbers, codes, texts and the API key are never written to logs. Codes are stored hashed.
- A text is never sent twice on its own. After an error or a timeout the extension stops, and each send carries an
  `Idempotency-Key` that stays the same for that event.
- With `flarum/gdpr` enabled, a member's number is part of their data export and is removed when their data is erased.

"Accepted" means SMSTunnel took the text for your phone. It doesn't confirm that the text arrived.

## Remove it

Disable the extension, then remove its tables and the package:

```sh
php flarum migrate:reset --extension smstunnel-sms
composer remove smstunnel/flarum-sms
```

## Development

```sh
cd js && npm install && npm run build
php tests/run.php
```

## License

MIT. Copyright (c) 2026 NARBOWEB SRL. Support: support@smstunnel.io
