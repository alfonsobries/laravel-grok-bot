# Project

`alfonsobries/laravel-grok-bot`: a Laravel notification channel that sends notifications to a
Grok Bot routine through its webhook trigger. Open source, MIT. `src/` is the package,
`resources/boost/` ships a Laravel Boost guideline and skill to the apps that install it,
and `website/` is the static docs site deployed to Vercel.

## When to read what

- Before changing what the webhook sends or how it answers, read the Webhook section of
  `README.md`: it is the contract the channel, the tests and the site describe.
- Before editing `website/`, keep its code samples identical to `README.md`.
