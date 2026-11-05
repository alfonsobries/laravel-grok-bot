## Grok Bot notifications (alfonsobries/laravel-grok-bot)

- Send to a Grok Bot routine with a notification: return `'grok-bot'` from `via()` and a `NotificationChannels\GrokBot\GrokBotMessage` (or a string or an array) from `toGrokBot($notifiable)`.
- Routines live in `config/grok-bot.php` under `routines`, by name, with the webhook `url` and `key` read from `.env`. Never hardcode a key or put it in a message.
- Pick the routine with `->routine('name')` on the message, `routeNotificationForGrokBot()` on the notifiable, or `Notification::route('grok-bot', 'name')` on demand. Without one, the `default` routine is used.
- A sent notification only means the routine started a run. What the Bot does with the JSON is defined by the routine's own instruction, not by the app.
- Check a routine with `php artisan grok-bot:ping {routine}`. In tests, use `Http::fake()` and assert the request body.
- Activate the `grok-bot-notifications` skill to wire a new notification to a Bot end to end.
